<?php

namespace App\Support\Redaction;

/**
 * Mise en page des données qui s'étendent sur plusieurs paragraphes dans une décision : texte long, liste à tirets, rubriques
 * (titre et texte), lignes de dispositif et qualités des parties. Une variable écrite seule dans un paragraphe du modèle
 * ({{ demandes|tirets }}) est remplacée par tous ses paragraphes ; écrite au milieu d'une phrase, elle devient un texte à lignes.
 * Une ligne vide sépare les paragraphes, comme dans les jugements du tribunal.
 */
class MiseEnPage
{
    /** Ligne vide entre deux paragraphes. */
    public const VIDE = '<p><br></p>';

    /** Les filtres qui produisent des paragraphes entiers. */
    public const FILTRES = ['paragraphes', 'lignes', 'tirets', 'sections', 'dispositifs', 'parties_qualites'];

    /** @param  string  $argument  « garder » pour que « tirets » ne mette pas la première lettre en minuscule */
    public static function bloc(string $filtre, mixed $valeur, string $argument, bool $html): string
    {
        return match ($filtre) {
            'paragraphes' => self::assembler(self::lignesDe($valeur), $html, true),
            'lignes' => self::assembler(array_map(fn ($l) => rtrim($l, ' ;.,').' ;', self::lignesDe($valeur)), $html, false),
            'tirets' => self::assembler(array_map(fn ($l) => self::tiret($l, $argument !== 'garder'), self::lignesDe($valeur)), $html, false),
            'sections' => self::sections($valeur, $html),
            'dispositifs' => self::dispositifs($valeur, $html),
            'parties_qualites' => self::qualites($valeur, $html),
        };
    }

    /** Les lignes d'un texte long (une par retour à la ligne) ou d'une liste. @return list<string> */
    public static function lignesDe(mixed $valeur): array
    {
        $brut = is_array($valeur) ? $valeur : preg_split('/\r\n|\r|\n/', (string) $valeur);

        return array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/\s+/u', ' ', (string) $l)), $brut), fn ($l) => $l !== ''));
    }

    /** « Déclarer son action recevable. » devient « -déclarer son action recevable ; ». */
    private static function tiret(string $ligne, bool $minuscule): string
    {
        $ligne = trim((string) preg_replace('/^[-–—•]\s*/u', '', $ligne));
        $ligne = rtrim($ligne, " ;.,");
        $mot = (string) strtok($ligne, ' ');
        // Un infinitif (« Déclarer », « Condamner », « Dire ») prend une minuscule ; un nom propre ou une sigle garde sa majuscule.
        if ($minuscule && preg_match('/(er|ir|re|oir)$/u', $mot) && mb_substr($mot, 1) === mb_strtolower(mb_substr($mot, 1))) {
            $ligne = mb_strtolower(mb_substr($ligne, 0, 1)).mb_substr($ligne, 1);
        }

        return '-'.$ligne.' ;';
    }

    /** @param list<string> $lignes */
    private static function assembler(array $lignes, bool $html, bool $espace): string
    {
        if ($html) {
            $paragraphes = array_map(fn ($l) => '<p>'.e($l).'</p>', $lignes);

            return implode($espace ? self::VIDE : '', $paragraphes);
        }

        return implode($espace ? "\n\n" : "\n", $lignes);
    }

    /** Rubriques : chaque titre en gras souligné, puis son texte en paragraphes. @param list<array<string, mixed>> $rubriques */
    private static function sections(mixed $rubriques, bool $html): string
    {
        $blocs = [];
        foreach (is_array($rubriques) ? $rubriques : [] as $r) {
            $titre = trim((string) ($r['titre'] ?? ''));
            $texte = self::lignesDe($r['texte'] ?? '');
            if ($titre === '' && $texte === []) {
                continue;
            }
            if ($html) {
                $morceaux = array_filter([$titre !== '' ? '<p><strong><u>'.e($titre).'</u></strong></p>' : '', self::assembler($texte, true, true)]);
                $blocs[] = implode(self::VIDE, $morceaux);
            } else {
                $blocs[] = trim($titre."\n".self::assembler($texte, false, true));
            }
        }

        return implode($html ? self::VIDE : "\n\n", $blocs);
    }

    /** Les lignes de dispositif saisies dans les rubriques (« Rejette la demande de sursis à statuer ; »). */
    private static function dispositifs(mixed $rubriques, bool $html): string
    {
        $lignes = [];
        foreach (is_array($rubriques) ? $rubriques : [] as $r) {
            $lignes = array_merge($lignes, self::lignesDe($r['dispositif'] ?? ''));
        }

        return self::assembler($lignes, $html, true);
    }

    /** Qualités des parties, une par paragraphe (numérotées quand elles sont plusieurs). */
    private static function qualites(mixed $parties, bool $html): string
    {
        $lignes = Parties::lignes($parties);
        $sortie = [];
        foreach ($lignes as $i => $p) {
            [$debut, $suite] = Parties::qualite($p, count($lignes) > 1 ? $i : null);
            $sortie[] = $html ? '<p><strong>'.e($debut).'</strong>'.e($suite).'</p>' : $debut.$suite;
        }

        return implode($html ? self::VIDE : "\n\n", $sortie);
    }
}
