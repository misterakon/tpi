<?php

namespace App\Support\Statistiques;

use App\Models\Affaire;

/*
 | Les catégories des classeurs du TPI (listes déroulantes des onglets « RECAP ») et leur déduction d'après la nature de l'affaire et son objet.
 | Sous-catégories d'état civil et d'affaires matrimoniales : déduites de l'objet du litige, corrigeables affaire par affaire (resultats_affaire.sous_categorie).
 */
class CategoriesStats
{
    public const CATEGORIES = [
        'civile_ordinaire' => 'Civile ordinaire', 'etat_civil' => 'État civil', 'matrimoniale' => 'Affaire matrimoniale', 'heredite' => 'Hérédité', 'refere' => 'Référé',
        'jex' => "Procédure devant le juge de l'exécution", 'commerciale' => 'Commerciale', 'administrative' => 'Administrative', 'saisie_remuneration' => 'Saisie rémunération', 'autre' => 'Autre',
    ];

    public const ETAT_CIVIL = [
        'jsn' => 'JSN', 'jsd' => 'JSD', 'nom' => 'Modification nom', 'prenom' => 'Modification prénom', 'identite' => 'Reprise identité',
        'rectification' => 'Rectification judiciaire', 'paternite' => 'Reconnaissance paternité', 'autre' => 'Autre',
    ];

    public const MATRIMONIAL = [
        'divorce_faute' => 'Divorce pour faute', 'dcm' => 'DCM', 'separation' => 'Séparation de corps', 'adoption_simple' => 'Adoption simple', 'adoption_pleniere' => 'Adoption plénière', 'autre' => 'Autre',
    ];

    /** La catégorie d'une affaire civile (nature, puis mots de l'objet pour l'hérédité et l'adoption). */
    public static function categorie(Affaire $a): string
    {
        $objet = self::texte($a->objet);
        if (str_contains($objet, 'adoption')) {
            return 'matrimoniale';
        }
        if (preg_match('/hered|heritier|succession/', $objet)) {
            return 'heredite';
        }
        $nature = self::texte($a->nature?->libelle);

        return match (true) {
            str_starts_with($nature, 'refere') => 'refere',
            str_starts_with($nature, 'juge de l') => 'jex',
            str_starts_with($nature, 'saisie') => 'saisie_remuneration',
            str_starts_with($nature, 'matrimon') => 'matrimoniale',
            str_starts_with($nature, 'etat des') => 'etat_civil',
            str_starts_with($nature, 'commerc') => 'commerciale',
            str_starts_with($nature, 'administr') => 'administrative',
            str_starts_with($nature, 'civile') => 'civile_ordinaire',
            default => 'autre',
        };
    }

    /** La sous-catégorie : celle saisie à la main si elle existe, sinon déduite de l'objet. Null si l'affaire n'est ni d'état civil ni matrimoniale. */
    public static function sousCategorie(Affaire $a, string $categorie, ?string $corrigee = null): ?string
    {
        if (! in_array($categorie, ['etat_civil', 'matrimoniale'], true)) {
            return null;
        }
        if ($corrigee !== null && $corrigee !== '') {
            return $corrigee;
        }
        $o = self::texte($a->objet);
        if ($categorie === 'etat_civil') {
            return match (true) {
                (bool) preg_match('/supplétif.*décès|supplet.*deces|\bjsd\b/', $o) => 'jsd',
                (bool) preg_match('/supplet.*naissance|\bjsn\b/', $o) => 'jsn',
                str_contains($o, 'prenom') => 'prenom',
                (bool) preg_match('/\bnom\b/', $o) => 'nom',
                str_contains($o, 'identite') => 'identite',
                str_contains($o, 'rectification') => 'rectification',
                str_contains($o, 'paternite') => 'paternite',
                default => 'autre',
            };
        }

        return match (true) {
            str_contains($o, 'pleniere') => 'adoption_pleniere',
            str_contains($o, 'adoption') => 'adoption_simple',
            str_contains($o, 'separation de corps') => 'separation',
            (bool) preg_match('/consentement mutuel|\bdcm\b/', $o) => 'dcm',
            str_contains($o, 'divorce') => 'divorce_faute',
            default => 'autre',
        };
    }

    /** Libellés de toutes les sous-catégories d'une catégorie (pour les tableaux). @return array<string, string> */
    public static function sous(string $categorie): array
    {
        return $categorie === 'etat_civil' ? self::ETAT_CIVIL : self::MATRIMONIAL;
    }

    /** Minuscules sans accents : la comparaison ne dépend ni de la casse ni des accents saisis. */
    private static function texte(?string $t): string
    {
        $t = mb_strtolower((string) $t);

        return strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'"])
            ?: $t;
    }
}
