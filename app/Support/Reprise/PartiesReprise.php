<?php

namespace App\Support\Reprise;

use App\Models\Partie;
use Illuminate\Support\Str;

/*
 | Les parties d'une cellule de registre : « KOUASSI Yao Marc ; Société BATIMAT SARL ». Le registre papier ne distingue pas les natures :
 | on les devine (société, État, femme…) et la fiche indique que les parties reprises sont à vérifier.
 */
class PartiesReprise
{
    private const MAX_PARTIES = 50;

    private const MORALES = ['sarl', 'sa', 'sas', 'sasu', 'sci', 'scpi', 'gie', 'ong', 'association', 'societe', 'ets', 'etablissement', 'etablissements', 'banque', 'compagnie',
        'cabinet', 'groupe', 'entreprise', 'cooperative', 'mutuelle', 'fondation', 'universite', 'hotel', 'clinique', 'pharmacie', 'agence', 'syndicat', 'eglise', 'ecole', 'lycee', 'college', 'cie', 'holding'];

    private const ETAT = ['etat', 'ministere', 'mairie', 'commune', 'district', 'prefecture', 'direction', 'republique', 'gouvernement', 'departement', 'region', 'conseil', 'agent judiciaire'];

    /**
     * @param  list<string>  $naturesAutorisees  natures permises par le registre (le pénal n'a pas d'« État »)
     * @return list<array<string, mixed>>
     */
    public static function depuis(string $texte, string $role, array $naturesAutorisees): array
    {
        $morceaux = preg_split('/\s*(?:;|\R|\s\/\s|\s\+\s)\s*/u', trim($texte)) ?: [];
        $parties = [];
        foreach ($morceaux as $m) {
            $m = trim(strip_tags($m));
            if ($m === '' || count($parties) >= self::MAX_PARTIES) {
                continue;
            }
            $parties[] = self::une(mb_substr($m, 0, 200), $role, $naturesAutorisees);
        }

        return $parties;
    }

    /** Sépare « A c/ B » (demandeur contre défendeur) en deux textes ; sinon tout est la partie principale. @return array{0: string, 1: string} */
    public static function separer(string $texte): array
    {
        $morceaux = preg_split('/\s+(?:c\/|c\.|contre|vs?\.?)\s+/iu', trim($texte), 2) ?: [$texte];

        return [$morceaux[0] ?? '', $morceaux[1] ?? ''];
    }

    private static function une(string $nom, string $role, array $autorisees): array
    {
        $cle = Str::ascii(mb_strtolower($nom));
        $mots = preg_split('/[^a-z]+/', $cle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = ['role' => $role, 'naissance_mode' => null, 'naissance_date' => null, 'naissance_annee' => null, 'lieu_naissance' => null, 'nationalite' => null, 'profession' => null,
            'domicile' => null, 'avocat' => null, 'autres' => 0, 'mandat_depot_date' => null];

        if (preg_match('/^(mp|ministere public|parquet|procureur)\b/', $cle) && in_array('mp', $autorisees, true)) {
            return $base + ['nature' => 'mp', 'nom' => null, 'prenoms' => null, 'denomination' => 'MP', 'cle' => Partie::cle('MP')];
        }
        $etat = array_intersect($mots, self::ETAT) !== [];
        $morale = array_intersect($mots, self::MORALES) !== [] || preg_match('/\b[A-Z]{2,}\s*(&|ET)\s*[A-Z]{2,}\b/', $nom);
        if ($etat || $morale) {
            $nature = $etat && in_array('etat', $autorisees, true) ? 'etat' : 'morale';
            $nature = in_array($nature, $autorisees, true) ? $nature : 'morale';

            return $base + ['nature' => $nature, 'nom' => null, 'prenoms' => null, 'denomination' => mb_substr($nom, 0, 250), 'cle' => Partie::cle($nom)];
        }

        // Personne physique : civilité (qui donne le sexe), puis nom en majuscules suivi des prénoms.
        $nature = 'homme';
        if (preg_match('/^(mme|madame|mlle|mademoiselle|dame|veuve|vve)\.?\s+/iu', $nom, $c)) {
            $nature = 'femme';
            $nom = trim(mb_substr($nom, mb_strlen($c[0])));
        } elseif (preg_match('/^(m|mr|monsieur|sieur)\.?\s+/iu', $nom, $c)) {
            $nom = trim(mb_substr($nom, mb_strlen($c[0])));
        }
        $mots = preg_split('/\s+/u', $nom, -1, PREG_SPLIT_NO_EMPTY) ?: [$nom];
        $noms = [];
        $prenoms = [];
        foreach ($mots as $mot) {
            if (mb_strtoupper($mot) === $mot && preg_match('/\p{L}/u', $mot) && $prenoms === []) {
                $noms[] = $mot;
            } else {
                $prenoms[] = $mot;
            }
        }
        if ($noms === []) {
            $noms = [mb_strtoupper(array_shift($prenoms) ?? $nom)];
        }
        $nomComplet = mb_substr(implode(' ', $noms), 0, 160);
        $prenomsTexte = $prenoms ? mb_substr(implode(' ', $prenoms), 0, 200) : null;

        return $base + ['nature' => $nature, 'nom' => $nomComplet, 'prenoms' => $prenomsTexte, 'denomination' => null, 'cle' => Partie::cle($nomComplet.' '.$prenomsTexte)];
    }
}
