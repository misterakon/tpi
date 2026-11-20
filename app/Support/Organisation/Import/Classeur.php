<?php

namespace App\Support\Organisation\Import;

use App\Support\Reprise\FichierTableur;
use Illuminate\Support\Str;

/*
 | Lecture du classeur de l'ordonnance (voir docs/FORMAT_EXCEL_ORDONNANCE.md). La lecture elle-même (vérification du fichier, cellules en texte,
 | jamais de formule exécutée) est celle de la reprise des affaires. Ici : reconnaissance des feuilles par leur nom et des colonnes par leur en-tête,
 | sans tenir compte des accents, des majuscules ni de ce qui est entre parenthèses ; les colonnes « information » sont ignorées.
 */
class Classeur
{
    public const TAILLE_MAX = 10 * 1024 * 1024;

    /** Feuilles reconnues (clé = nom normalisé). */
    public const FEUILLES = ['ordonnance', 'sections', 'salles', 'agents', 'chambres', 'formations', 'composition', 'audiences', 'services', 'responsables', 'membres_exterieurs', 'dates_limites', 'articles', 'remarques'];

    /**
     * @return array{feuilles: array<string, list<array<string, mixed>>>, inconnues: list<string>, colonnes: array<string, list<string>>}
     *     feuilles : clé de feuille => lignes (chaque ligne : colonnes par clé, plus « _l » = numéro de ligne Excel, cellule vide = null)
     *
     * @throws \RuntimeException message destiné à l'utilisateur
     */
    public static function lire(string $chemin, ?callable $progres = null): array
    {
        $progres ??= fn () => null;
        $progres('verification', 0, 1, 'Vérification du fichier…');
        FichierTableur::verifier($chemin, self::TAILLE_MAX);
        $progres('verification', 1, 1, 'Fichier vérifié');

        $feuilles = [];
        $inconnues = [];
        $colonnes = [];
        $noms = FichierTableur::feuilles($chemin);
        $total = max(1, count($noms) - 1);
        $faites = 0;
        foreach ($noms as $nom) {
            $cle = self::cle($nom);
            if ($cle !== 'lisez_moi') {
                $progres('lecture', $faites++, $total, 'Lecture de la feuille « '.$nom.' » ('.$faites.'/'.$total.')');
            }
            if ($cle === 'lisez_moi') {
                continue;
            }
            if (! in_array($cle, self::FEUILLES, true)) {
                $inconnues[] = $nom;

                continue;
            }

            $entetes = null;
            $lignes = [];
            FichierTableur::parcourir($chemin, $nom, function (int $n, array $cellules) use (&$entetes, &$lignes, $cle) {
                if ($entetes === null) {
                    $entetes = array_map(fn ($c) => self::cleColonne($c), $cellules);

                    return;
                }
                $ligne = ['_l' => $n];
                $vide = true;
                foreach ($cellules as $i => $c) {
                    $k = $entetes[$i] ?? null;
                    if ($k === null || $k === '') {
                        continue;
                    }
                    $ligne[$k] = $c === '' ? null : $c;
                    $vide = $vide && $c === '';
                }
                if (! $vide) {
                    $lignes[] = $ligne;
                }
            });
            $feuilles[$cle] = $lignes;
            $colonnes[$cle] = array_values(array_filter($entetes ?? []));
        }
        $progres('lecture', $total, $total, 'Toutes les feuilles sont lues');

        return ['feuilles' => $feuilles, 'inconnues' => $inconnues, 'colonnes' => $colonnes];
    }

    /** « Membres extérieurs » => « membres_exterieurs ». */
    public static function cle(string $nom): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(str_replace(["'", '’'], ' ', $nom)))), '_');
    }

    /** « Section (N° ou nom) » => « section » ; « Agent (information) » => null (colonne ignorée) ; « N° » => « n ». */
    public static function cleColonne(string $entete): ?string
    {
        if (preg_match('/\(\s*information\s*\)/iu', $entete)) {
            return null;
        }
        $sans = trim(preg_replace('/\(.*?\)/u', '', $entete));
        $sans = str_replace(['°', 'º'], '', $sans);   // « N° » => « N »

        return self::cle($sans) ?: null;
    }
}
