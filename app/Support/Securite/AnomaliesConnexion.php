<?php

namespace App\Support\Securite;

use App\Models\JournalConnexion;
use App\Models\Profil;
use App\Models\User;
use App\Notifications\AnomalieConnexionNotification;
use App\Services\Journal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/*
 | Détecte les connexions qui sortent de l'ordinaire et prévient les administrateurs (cloche et « Mes notifications »). Appelée à chaque écriture du journal de connexion :
 |  - échecs répétés sur un compte (5 en 15 minutes par défaut) ou tentatives répétées depuis une même adresse sur des comptes inconnus ;
 |  - connexion depuis une adresse jamais vue pour ce compte (la toute première connexion n'est pas une anomalie) ;
 |  - connexion de nuit (de 20 h à 6 h, heure d'Abidjan par défaut) ;
 |  - connexion avec un code de secours.
 | Chaque alerte n'est envoyée qu'une fois par période (une heure, ou un jour pour la nuit) pour ne pas noyer les administrateurs. Ne casse jamais la connexion.
 */
class AnomaliesConnexion
{
    private const ECHECS = [Journal::CONNEXION_ECHEC, Journal::CODE_ECHEC, Journal::CODE_SECOURS_ECHEC, Journal::DEVERROUILLAGE_ECHEC];

    public static function examiner(JournalConnexion $e): void
    {
        if (! config('securite.anomalies.actif', true)) {
            return;
        }
        try {
            if (in_array($e->evenement, self::ECHECS, true)) {
                self::echecs($e);
            } elseif ($e->evenement === Journal::CODE_SECOURS_UTILISE) {
                self::prevenir('secours:'.$e->id, 'secours', ($e->user?->name ?? 'Un utilisateur')." s'est connecté avec un code de secours (adresse ".$e->ip.'). Il lui reste '.(int) ($e->details['restants'] ?? 0).' code(s).', 'attention', 1);
            } elseif ($e->evenement === Journal::CONNEXION_REUSSIE && $e->user) {
                self::nouvelleAdresse($e);
                self::nuit($e);
            }
        } catch (Throwable $ex) {
            Log::warning('Analyse des anomalies de connexion impossible', ['erreur' => $ex->getMessage()]);
        }
    }

    private static function echecs(JournalConnexion $e): void
    {
        $max = (int) config('securite.anomalies.echecs', 5);
        $depuis = now()->subMinutes((int) config('securite.anomalies.fenetre_minutes', 15));

        if ($e->user_id) {
            $n = JournalConnexion::where('user_id', $e->user_id)->whereIn('evenement', self::ECHECS)->where('created_at', '>=', $depuis)->count();
            if ($n >= $max) {
                self::prevenir('echecs:u'.$e->user_id, 'echecs', $n.' échecs de connexion en '.(int) config('securite.anomalies.fenetre_minutes', 15).' minutes sur le compte de '.($e->user?->name ?? $e->email_saisi).' (dernière adresse '.$e->ip.').', $n >= 2 * $max ? 'critique' : 'attention', 60);
            }

            return;
        }

        // Comptes inconnus : on regroupe par adresse (quelqu'un essaie des identifiants au hasard).
        $n = JournalConnexion::whereNull('user_id')->where('ip', $e->ip)->whereIn('evenement', self::ECHECS)->where('created_at', '>=', $depuis)->count();
        if ($n >= $max) {
            self::prevenir('echecs:ip'.$e->ip, 'echecs_inconnus', $n.' tentatives de connexion sur des comptes inconnus depuis l\'adresse '.$e->ip.' en '.(int) config('securite.anomalies.fenetre_minutes', 15).' minutes.', 'critique', 60);
        }
    }

    private static function nouvelleAdresse(JournalConnexion $e): void
    {
        $avant = JournalConnexion::where('user_id', $e->user_id)->where('evenement', Journal::CONNEXION_REUSSIE)->where('id', '!=', $e->id);
        if (! (clone $avant)->exists()) {
            return;   // la toute première connexion d'un compte n'est pas une anomalie
        }
        if (! (clone $avant)->where('ip', $e->ip)->exists()) {
            self::prevenir('adresse:'.$e->user_id.':'.$e->ip, 'adresse_nouvelle', 'Connexion de '.$e->user->name.' depuis une adresse jamais vue pour ce compte ('.$e->ip.').', 'attention', 60 * 24 * 30);
        }
    }

    private static function nuit(JournalConnexion $e): void
    {
        $debut = (int) config('securite.anomalies.nuit_debut', 20);
        $fin = (int) config('securite.anomalies.nuit_fin', 6);
        $heure = now(config('securite.anomalies.fuseau', 'Africa/Abidjan'));
        $h = (int) $heure->format('G');
        $nuit = $debut > $fin ? ($h >= $debut || $h < $fin) : ($h >= $debut && $h < $fin);
        if ($nuit) {
            self::prevenir('nuit:'.$e->user_id.':'.$heure->toDateString(), 'nuit', 'Connexion de nuit de '.$e->user->name.' à '.$heure->format('H:i').' ('.$e->ip.').', 'attention', 60 * 24);
        }
    }

    /** Prévient les administrateurs, une seule fois par période pour une même situation. */
    private static function prevenir(string $cle, string $genre, string $titre, string $niveau, int $minutes): void
    {
        if (! Cache::add('anomalie_connexion:'.$cle, true, now()->addMinutes($minutes))) {
            return;
        }
        $admins = User::where('actif', true)->whereIn('profil_id', Profil::where('systeme', true)->select('id'))->get();
        foreach ($admins as $admin) {
            $admin->notify(new AnomalieConnexionNotification($genre, $titre, $niveau));
        }
    }
}
