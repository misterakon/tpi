<?php

namespace App\Services;

use App\Models\JournalAudit;
use App\Models\JournalConnexion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/*
 | Point d'entrée unique pour écrire dans les journaux.
 | Les secrets (mots de passe, codes, jetons) ne doivent jamais lui être transmis ;
 | par précaution, les clés sensibles sont retirées des valeurs « avant / après ».
 */
class Journal
{
    public const CONNEXION_REUSSIE = 'connexion_reussie';

    public const CONNEXION_ECHEC = 'connexion_echec';

    public const COMPTE_VERROUILLE = 'compte_verrouille';

    public const CODE_ENVOYE = 'code_envoye';

    public const CODE_RENVOYE = 'code_renvoye';

    public const CODE_VALIDE = 'code_valide';

    public const CODE_ECHEC = 'code_echec';

    public const CODE_SECOURS_UTILISE = 'code_secours_utilise';

    public const CODE_SECOURS_ECHEC = 'code_secours_echec';

    public const DECONNEXION = 'deconnexion';

    public const DECONNEXION_INACTIVITE = 'deconnexion_inactivite';

    public const SESSION_VERROUILLEE = 'session_verrouillee';

    public const SESSION_DEVERROUILLEE = 'session_deverrouillee';

    public const DEVERROUILLAGE_ECHEC = 'deverrouillage_echec';

    public const MDP_LIEN_DEMANDE = 'mdp_lien_demande';

    public const MDP_MODIFIE = 'mdp_modifie';

    private const CLES_SECRETES = ['password', 'remember_token', 'token', 'code', 'empreinte'];

    public static function connexion(
        string $evenement,
        ?User $user = null,
        bool $succes = true,
        ?string $motif = null,
        ?string $emailSaisi = null,
        array $details = [],
    ): void {
        $requete = request();

        $entree = JournalConnexion::create([
            'user_id' => $user?->getKey(),
            'email_saisi' => $emailSaisi !== null ? mb_substr($emailSaisi, 0, 255) : null,
            'evenement' => $evenement,
            'succes' => $succes,
            'motif' => $motif,
            'ip' => $requete->ip(),
            'navigateur' => self::navigateur($requete),
            'session_ref' => self::referenceSession($requete),
            'details' => $details ?: null,
        ]);

        \App\Support\Securite\AnomaliesConnexion::examiner($entree);
    }

    public static function audit(string $action, ?Model $cible = null, ?string $libelle = null, array $avant = [], array $apres = [], ?User $auteur = null): void
    {
        $requete = request();

        JournalAudit::create([
            'user_id' => ($auteur ?? $requete->user())?->getKey(),
            'action' => $action,
            'cible_type' => $cible ? $cible::class : null,
            'cible_id' => $cible?->getKey() !== null ? (string) $cible->getKey() : null,
            'libelle' => $libelle,
            'avant' => self::nettoyer($avant) ?: null,
            'apres' => self::nettoyer($apres) ?: null,
            'ip' => $requete->ip(),
            'navigateur' => self::navigateur($requete),
        ]);
    }

    private static function nettoyer(array $valeurs): array
    {
        return array_diff_key($valeurs, array_flip(self::CLES_SECRETES));
    }

    private static function navigateur(Request $requete): ?string
    {
        $ua = $requete->userAgent();

        return $ua !== null ? mb_substr($ua, 0, 255) : null;
    }

    /** Empreinte de la session (jamais l'identifiant réel) pour relier connexion et déconnexion. */
    private static function referenceSession(Request $requete): ?string
    {
        if (! $requete->hasSession() || ! $requete->session()->isStarted()) {
            return null;
        }

        return substr(hash_hmac('sha256', $requete->session()->getId(), config('app.key')), 0, 32);
    }
}
