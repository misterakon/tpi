<?php

namespace App\Support\Reprise;

use App\Models\Affaire;
use App\Support\RegistreAffaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 | Contrôle d'une ligne du registre repris : numéro, date, objet, chambre, parties. Rien n'est écrit en base ici : le résultat
 | (normalisé, avec ses erreurs et avertissements) est conservé sur la ligne ; l'importation ne reprend que les lignes valides.
 */
class LigneReprise
{
    /** Champs reconnaissables dans un fichier, avec leur libellé (la correspondance colonne/champ est choisie par l'utilisateur). */
    public const CHAMPS = [
        'numero' => ['Numéro de l\'affaire', true], 'date' => ['Date d\'enrôlement', true], 'objet' => ['Objet ou prévention', true],
        'demandeur' => ['Demandeur / prévenu / inculpé', false], 'defendeur' => ['Défendeur / victime', false], 'parties' => ['Parties (« A c/ B » dans une seule colonne)', false],
        'chambre' => ['Chambre ou cabinet', true], 'nature' => ['Nature de l\'affaire', false], 'audience' => ['Date de première audience', false], 'ancien_numero' => ['Ancien numéro', false],
    ];

    /**
     * @param  list<string>  $cellules
     * @param  array<string, int>  $corr  champ => index de colonne
     * @param  array{chambres: array<string, int>, natures: array<string, int>, serie: string}  $ctx
     * @return array{statut: string, messages: list<array{niveau: string, texte: string}>, resultat: array<string, mixed>, cle: ?string}
     */
    public static function analyser(array $cellules, array $corr, array $ctx): array
    {
        $erreurs = [];
        $avert = [];
        $val = fn (string $champ): string => isset($corr[$champ]) ? trim((string) ($cellules[$corr[$champ]] ?? '')) : '';
        $serie = $ctx['serie'];
        $def = RegistreAffaire::depuisSerie($serie);
        $res = ['serie' => $serie];

        // Date d'enrôlement
        $date = self::date($val('date'));
        if ($val('date') === '') {
            $erreurs[] = "La date d'enrôlement est vide.";
        } elseif ($date === null) {
            $erreurs[] = 'Date illisible : « '.Str::limit($val('date'), 30).' » (attendu : jj/mm/aaaa).';
        } elseif ($date->isFuture()) {
            $erreurs[] = "La date d'enrôlement est dans le futur.";
        } elseif ($date->year < 1990) {
            $erreurs[] = "La date d'enrôlement est antérieure à 1990.";
        }
        $res['date_rg'] = $date?->toDateString();

        // Numéro
        $n = self::numero($val('numero'), $serie, $date?->year);
        $cle = null;
        if (isset($n['erreur'])) {
            $erreurs[] = $n['erreur'];
        } else {
            $res['numero'] = $n['numero'];
            $res['annee'] = $n['annee'];
            $cle = $serie.'|'.$n['annee'].'|'.$n['numero'];
            if ($date && $date->year !== $n['annee']) {
                $avert[] = "L'année du numéro ({$n['annee']}) diffère de celle de la date ({$date->year}).";
            }
        }

        // Objet
        $objet = trim(strip_tags($val('objet')));
        if ($objet === '') {
            $erreurs[] = "L'objet est vide.";
        }
        $res['objet'] = mb_substr($objet, 0, 1000);

        // Chambre (obligatoire : celle de l'onglet « Listes » du modèle)
        $res['chambre_id'] = null;
        if ($val('chambre') === '') {
            $erreurs[] = 'La chambre est vide.';
        } else {
            $res['chambre_id'] = self::chambre($val('chambre'), $ctx['chambres']);
            if ($res['chambre_id'] === null) {
                $erreurs[] = 'Chambre « '.Str::limit($val('chambre'), 40)." » inconnue : recopiez-la depuis l'onglet « Listes ».";
            }
        }

        // Nature (facultative)
        $res['nature_affaire_id'] = null;
        if ($val('nature') !== '') {
            $res['nature_affaire_id'] = self::chambre($val('nature'), $ctx['natures']);
            if ($res['nature_affaire_id'] === null) {
                $avert[] = 'Nature « '.Str::limit($val('nature'), 40).' » inconnue : laissée vide.';
            }
        }

        // Audience (facultative)
        $res['date_premiere_audience'] = null;
        if ($val('audience') !== '') {
            $audience = self::date($val('audience'));
            $audience ? $res['date_premiere_audience'] = $audience->toDateString() : $avert[] = "Date d'audience illisible : ignorée.";
        }
        $res['ancien_numero'] = $val('ancien_numero') !== '' ? mb_substr($val('ancien_numero'), 0, 40) : null;

        // Parties
        [$roleA, $roleB] = $def['roles'];
        $principal = $val('demandeur');
        $autre = $val('defendeur');
        if ($val('parties') !== '') {
            [$a, $b] = PartiesReprise::separer($val('parties'));
            $principal = $principal !== '' ? $principal : $a;
            $autre = $autre !== '' ? $autre : $b;
        }
        $res['parties'] = array_merge(
            PartiesReprise::depuis($principal, $roleA, $def['natures_partie']),
            PartiesReprise::depuis($autre, $roleB, $def['natures_partie']),
        );
        if ($principal === '' || ! collect($res['parties'])->contains('role', $roleA)) {
            $erreurs[] = 'Aucune partie principale ('.mb_strtolower(\App\Models\Partie::ROLES[$roleA]).').';
        }

        $messages = array_merge(array_map(fn ($t) => ['niveau' => 'erreur', 'texte' => $t], $erreurs), array_map(fn ($t) => ['niveau' => 'avert', 'texte' => $t], $avert));

        return ['statut' => $erreurs === [] ? 'valide' : 'erreur', 'messages' => $messages, 'resultat' => $res, 'cle' => $cle];
    }

    /** « 1391/25 », « RG 1391/25 », « N° 1391 / 2025 » ou « 1391 » (année prise à la date). @return array{numero?: int, annee?: int, erreur?: string} */
    public static function numero(string $brut, string $serie, ?int $anneeDate): array
    {
        $brut = trim($brut);
        if ($brut === '') {
            return ['erreur' => 'Le numéro est vide.'];
        }
        $brut = (string) preg_replace('/\.0+$/', '', $brut);
        if (preg_match('/^(RG|RP|RI|RE)\b/iu', $brut, $p) && strtoupper($p[1]) !== $serie) {
            return ['erreur' => 'Ce numéro est du registre '.strtoupper($p[1]).', pas du registre '.$serie.'.'];
        }
        $brut = (string) preg_replace('/^(RG|RP|RI|RE)\s*[-:]?\s*/iu', '', $brut);
        $brut = (string) preg_replace('/^(n°|no|num[ée]ro)\s*/iu', '', $brut);
        if (preg_match('/^0*(\d{1,6})\s*[\/\-.]\s*(\d{2}|\d{4})$/', $brut, $m)) {
            $annee = (int) $m[2];
            if ($annee < 100) {
                $annee += $annee <= ((int) date('y') + 1) ? 2000 : 1900;
            }
        } elseif (preg_match('/^0*(\d{1,6})$/', $brut, $m) && $anneeDate) {
            $annee = $anneeDate;
        } else {
            return ['erreur' => 'Numéro illisible : « '.Str::limit($brut, 30).' » (attendu : 1391/25).'];
        }
        if ((int) $m[1] < 1) {
            return ['erreur' => 'Le numéro doit être supérieur à zéro.'];
        }
        if ($annee < 1990 || $annee > (int) date('Y') + 1) {
            return ['erreur' => "L'année du numéro ($annee) est invraisemblable."];
        }

        return ['numero' => (int) $m[1], 'annee' => $annee];
    }

    /** jj/mm/aaaa, jj-mm-aaaa, jj.mm.aa, aaaa-mm-jj, ou numéro de série Excel. */
    public static function date(string $brut): ?Carbon
    {
        $brut = trim($brut);
        if ($brut === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $brut)) {
                return Carbon::createFromFormat('Y-m-d', substr($brut, 0, 10))->startOfDay();
            }
            if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $brut, $m)) {
                $a = (int) $m[3];
                $a = $a < 100 ? $a + ($a <= (int) date('y') ? 2000 : 1900) : $a;

                return checkdate((int) $m[2], (int) $m[1], $a) ? Carbon::create($a, (int) $m[2], (int) $m[1])->startOfDay() : null;
            }
            if (preg_match('/^\d{5}(\.\d+)?$/', $brut) && (float) $brut > 20000 && (float) $brut < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $brut)->startOfDay();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** Clé d'un libellé de chambre : « 1ère chambre civile » et « 1ere Chambre Civile » donnent la même. */
    public static function cleLibelle(string $libelle): string
    {
        $t = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($libelle))));

        return (string) preg_replace('/^(\d+)\s*(?:ere|re|er|eme|e)(?=\s)/', '$1', $t);
    }

    /** @param  array<string, int>  $index  clé de libellé => identifiant */
    public static function chambre(string $libelle, array $index): ?int
    {
        $cle = self::cleLibelle($libelle);
        if ($cle === '') {
            return null;
        }
        if (isset($index[$cle])) {
            return $index[$cle];
        }
        foreach ($index as $k => $id) {
            if (str_starts_with($cle, $k.' ') || str_starts_with($k, $cle.' ')) {
                return $id;
            }
        }

        return null;
    }

    /** Une affaire du même registre porte-t-elle déjà ce numéro ? */
    public static function dejaEnregistree(string $serie, int $annee, int $numero): bool
    {
        return Affaire::where('serie', $serie)->where('annee', $annee)->where('numero', $numero)->exists();
    }
}
