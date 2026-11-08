<?php

namespace App\Services;

use App\Models\CodeSecours;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 | Codes de secours de la connexion : quand le code par e-mail n'arrive pas (messagerie en panne, boîte inaccessible), l'utilisateur
 | saisit l'un de ses codes de secours. Chaque code ne sert qu'une fois.
 |  - huit codes de 8 chiffres, sans tiret (« 48291735 ») : on ne les confond pas avec le code à 6 chiffres reçu par e-mail ;
 |  - tirés avec un générateur cryptographique ;
 |  - seule l'empreinte (HMAC) est stockée ; comparaison en temps constant ;
 |  - en générer de nouveaux annule les anciens ; l'administrateur peut tous les révoquer, sans jamais les voir.
 */
class CodesSecours
{
    public const NOMBRE = 8;

    private const CHIFFRES = '0123456789';

    /** Crée huit nouveaux codes (les anciens sont annulés) et les renvoie EN CLAIR, une seule fois. @return list<string> */
    public function generer(User $user): array
    {
        return DB::transaction(function () use ($user) {
            CodeSecours::where('user_id', $user->id)->delete();

            $codes = [];
            while (count($codes) < self::NOMBRE) {
                $code = $this->tirer();
                if (! in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
            foreach ($codes as $code) {
                CodeSecours::create(['user_id' => $user->id, 'empreinte' => $this->empreinte($user, $code)]);
            }

            return $codes;
        });
    }

    /** Vérifie un code saisi (majuscules, tirets et espaces sans importance) et le brûle s'il est bon. */
    public function utiliser(User $user, string $saisi, ?string $ip = null): bool
    {
        $propre = $this->normaliser($saisi);
        if (strlen($propre) !== 8) {
            return false;
        }

        return DB::transaction(function () use ($user, $propre, $ip) {
            // Un code s'écrit « 48291735 » ; les premiers codes distribués s'écrivaient « 4829-1735 » (avec tiret) : les deux formes sont reconnues.
            $candidats = [$this->empreinte($user, $propre), $this->empreinte($user, $this->formater($propre))];
            $trouve = null;
            foreach (CodeSecours::where('user_id', $user->id)->whereNull('utilise_le')->lockForUpdate()->get() as $c) {
                foreach ($candidats as $empreinte) {
                    if (hash_equals($c->empreinte, $empreinte)) {
                        $trouve = $c;
                    }
                }
            }
            if (! $trouve) {
                return false;
            }
            $trouve->forceFill(['utilise_le' => now(), 'utilise_ip' => $ip])->save();

            return true;
        });
    }

    /** Combien de codes restent utilisables, et quand ils ont été générés. @return array{restants: int, total: int, genere_le: ?\Illuminate\Support\Carbon} */
    public function etat(User $user): array
    {
        $codes = CodeSecours::where('user_id', $user->id)->get(['utilise_le', 'created_at']);

        return ['restants' => $codes->whereNull('utilise_le')->count(), 'total' => $codes->count(), 'genere_le' => $codes->min('created_at')];
    }

    /** L'administrateur annule tous les codes d'un compte : l'utilisateur devra en générer de nouveaux. */
    public function revoquer(User $user): int
    {
        return CodeSecours::where('user_id', $user->id)->delete();
    }

    private function tirer(): string
    {
        $c = '';
        for ($i = 0; $i < 8; $i++) {
            $c .= self::CHIFFRES[random_int(0, 9)];
        }

        return $c;
    }

    private function formater(string $brut): string
    {
        return substr($brut, 0, 4).'-'.substr($brut, 4, 4);
    }

    private function normaliser(string $saisi): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $saisi));   // les chiffres seuls comptent ; tiret et espaces sont ignorés
    }

    private function empreinte(User $user, string $codeFormate): string
    {
        return hash_hmac('sha256', $user->id.'|'.$codeFormate, (string) config('app.key'));
    }
}
