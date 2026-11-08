<?php

namespace App\Support\Redaction;

use InvalidArgumentException;

/**
 * Nombres en toutes lettres, selon l'orthographe traditionnelle des actes de justice :
 * « vingt et un », « quatre-vingts », « deux cents », « mil » pour une année (« deux mil vingt-cinq »).
 * Sert à écrire « trois (03) enfants » ou « un million deux cent mille (1 200 000) francs CFA ».
 */
class NombreEnLettres
{
    private const UNITES = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    private const MAX = 999_999_999_999;

    /** « 3 » donne « trois » ; féminin : « une ». */
    public static function convertir(int $n, bool $feminin = false): string
    {
        self::verifier($n);
        if ($n === 0) {
            return 'zéro';
        }

        $groupes = [(int) intdiv($n, 1_000_000_000), (int) intdiv($n, 1_000_000) % 1000, (int) intdiv($n, 1000) % 1000, $n % 1000];
        $texte = [];

        if ($groupes[0] > 0) {
            $texte[] = self::sousMille($groupes[0], false, true).' milliard'.($groupes[0] > 1 ? 's' : '');
        }
        if ($groupes[1] > 0) {
            $texte[] = self::sousMille($groupes[1], false, true).' million'.($groupes[1] > 1 ? 's' : '');
        }
        if ($groupes[2] > 0) {
            // « mille » est invariable et ne prend pas de « un » : « mille », « deux mille » ; « cent » et « quatre-vingt » restent au singulier devant lui.
            $texte[] = $groupes[2] === 1 ? 'mille' : self::sousMille($groupes[2], $feminin, false).' mille';
        }
        if ($groupes[3] > 0) {
            $texte[] = self::sousMille($groupes[3], $feminin, true);
        }

        return implode(' ', $texte);
    }

    /** Année : « mil » à la place de « mille » (« deux mil vingt-cinq », « mil neuf cent quatre-vingt-dix-neuf »). */
    public static function annee(int $annee): string
    {
        if ($annee < 1000 || $annee > 9999) {
            return self::convertir($annee);
        }

        $milliers = intdiv($annee, 1000);
        $reste = $annee % 1000;
        $texte = $milliers === 1 ? 'mil' : self::convertir($milliers).' mil';

        return $reste > 0 ? $texte.' '.self::sousMille($reste, false, true) : $texte;
    }

    /** « trois (03) », « mille deux cent (1 200) » : en lettres puis en chiffres entre parenthèses ; un nom peut suivre et prend le pluriel dès 2. */
    public static function chiffresEtLettres(int $n, ?string $singulier = null, ?string $pluriel = null, bool $feminin = false): string
    {
        $chiffres = $n >= 0 && $n < 10 ? '0'.$n : number_format($n, 0, ',', ' ');
        $texte = self::convertir($n, $feminin).' ('.$chiffres.')';

        if ($singulier !== null) {
            $texte .= ' '.($n >= 2 ? ($pluriel ?? $singulier.'s') : $singulier);
        }

        return $texte;
    }

    /** « un million deux cent mille (1 200 000) francs CFA » ; « un million de francs CFA » quand le nombre est un compte rond de millions. */
    public static function montant(int $n, string $singulier = 'franc CFA', string $pluriel = 'francs CFA'): string
    {
        self::verifier($n);
        $lettres = self::convertir($n);
        $de = $n > 0 && $n % 1_000_000 === 0 ? ' de' : '';

        return $lettres.' ('.number_format($n, 0, ',', ' ').')'.$de.' '.($n >= 2 ? $pluriel : $singulier);
    }

    /** « neuf heures », « quatorze heures trente minutes », « une heure ». */
    public static function heure(int $heures, int $minutes = 0): string
    {
        if ($heures < 0 || $heures > 23 || $minutes < 0 || $minutes > 59) {
            throw new InvalidArgumentException('Heure invalide.');
        }

        $texte = self::convertir($heures, true).' heure'.($heures >= 2 ? 's' : '');

        return $minutes > 0 ? $texte.' '.self::convertir($minutes, true).' minute'.($minutes >= 2 ? 's' : '') : $texte;
    }

    /** Un nombre de 1 à 999. $final : « cents » et « quatre-vingts » prennent un s s'ils terminent le groupe (pas devant « mille »). */
    private static function sousMille(int $n, bool $feminin, bool $final): string
    {
        $centaines = intdiv($n, 100);
        $reste = $n % 100;
        $parties = [];

        if ($centaines > 0) {
            $cent = $centaines === 1 ? 'cent' : self::UNITES[$centaines].' cent';
            $parties[] = $cent.($centaines > 1 && $reste === 0 && $final ? 's' : '');
        }
        if ($reste > 0) {
            $parties[] = self::sousCent($reste, $feminin, $final);
        }

        return implode(' ', $parties);
    }

    private static function sousCent(int $n, bool $feminin, bool $final): string
    {
        $un = $feminin ? 'une' : 'un';

        if ($n <= 16) {
            return $n === 1 ? $un : self::UNITES[$n];
        }
        if ($n <= 19) {
            return 'dix-'.self::UNITES[$n - 10];
        }
        if ($n < 70) {
            $dizaine = self::DIZAINES[intdiv($n, 10)];
            $unite = $n % 10;

            return match (true) {
                $unite === 0 => $dizaine,
                $unite === 1 => $dizaine.' et '.$un,
                default => $dizaine.'-'.self::UNITES[$unite],
            };
        }
        if ($n < 80) {   // 70 à 79 : soixante + dix… dix-neuf
            return match (true) {
                $n === 70 => 'soixante-dix',
                $n === 71 => 'soixante et onze',
                default => 'soixante-'.self::sousCent($n - 60, $feminin, $final),
            };
        }
        if ($n === 80) {
            return 'quatre-vingt'.($final ? 's' : '');
        }

        return 'quatre-vingt-'.self::sousCent($n - 80, $feminin, $final);   // 81 à 99, jamais « et »
    }

    private static function verifier(int $n): void
    {
        if ($n < 0 || $n > self::MAX) {
            throw new InvalidArgumentException('Nombre hors limites (0 à 999 999 999 999).');
        }
    }
}
