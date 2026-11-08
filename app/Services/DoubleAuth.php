<?php

namespace App\Services;

use App\Mail\CodeConnexion;
use App\Models\CodeDoubleAuth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 | Double authentification par code à 6 chiffres envoyé par e-mail.
 |  - code tiré avec un générateur cryptographique (random_int) ;
 |  - seule son empreinte (HMAC) est stockée ; comparaison en temps constant ;
 |  - usage unique, durée de vie courte, nombre d'essais limité ;
 |  - un seul code actif par utilisateur : un nouvel envoi invalide le précédent.
 */
class DoubleAuth
{
    public const OK = 'ok';

    public const INVALIDE = 'invalide';

    public const EXPIRE = 'expire';

    public const TROP_D_ESSAIS = 'trop_d_essais';

    public const ABSENT = 'absent';

    public const RENVOI_TROP_RAPIDE = 'trop_rapide';

    public const RENVOI_LIMITE = 'limite';

    public const RENVOI_OK = 'ok';

    /** Crée et envoie un nouveau code. Lève une exception si l'e-mail ne part pas. */
    public function envoyer(User $user, bool $renvoi = false): void
    {
        $longueur = (int) config('securite.double_auth.longueur');
        $code = str_pad((string) random_int(0, (10 ** $longueur) - 1), $longueur, '0', STR_PAD_LEFT);
        $validite = (int) config('securite.double_auth.validite_minutes');

        $ancien = CodeDoubleAuth::where('user_id', $user->id)->first();

        CodeDoubleAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'empreinte' => $this->empreinte($user, $code),
                'tentatives' => 0,
                'renvois' => $renvoi ? ($ancien?->renvois ?? 0) + 1 : 0,
                'envoye_le' => now(),
                'expire_le' => now()->addMinutes($validite),
            ],
        );

        Mail::to($user->email)->send(new CodeConnexion($user->name, $code, $validite));
    }

    /** Secondes à attendre avant de pouvoir demander un nouvel envoi. */
    public function attente(User $user): int
    {
        $code = CodeDoubleAuth::where('user_id', $user->id)->first();
        if (! $code) {
            return 0;
        }
        $reste = $code->envoye_le->copy()->addSeconds((int) config('securite.double_auth.renvoi_secondes'))->diffInSeconds(now(), false);

        return max(0, (int) ceil(-$reste));
    }

    public function renvoiPossible(User $user): string
    {
        $code = CodeDoubleAuth::where('user_id', $user->id)->first();
        if ($code && $code->renvois >= (int) config('securite.double_auth.renvois_max')) {
            return self::RENVOI_LIMITE;
        }

        return $this->attente($user) > 0 ? self::RENVOI_TROP_RAPIDE : self::RENVOI_OK;
    }

    /** Vérifie le code saisi. Chaque essai est compté, qu'il soit bon ou non. */
    public function verifier(User $user, string $saisi): string
    {
        return DB::transaction(function () use ($user, $saisi) {
            $code = CodeDoubleAuth::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $code) {
                return self::ABSENT;
            }
            if ($code->expire_le->isPast()) {
                $code->delete();

                return self::EXPIRE;
            }
            if ($code->tentatives >= (int) config('securite.double_auth.tentatives_max')) {
                $code->delete();

                return self::TROP_D_ESSAIS;
            }

            if (hash_equals($code->empreinte, $this->empreinte($user, $saisi))) {
                $code->delete();

                return self::OK;
            }

            $code->increment('tentatives');
            if ($code->tentatives >= (int) config('securite.double_auth.tentatives_max')) {
                $code->delete();

                return self::TROP_D_ESSAIS;
            }

            return self::INVALIDE;
        });
    }

    public function annuler(User $user): void
    {
        CodeDoubleAuth::where('user_id', $user->id)->delete();
    }

    private function empreinte(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, config('app.key'));
    }
}
