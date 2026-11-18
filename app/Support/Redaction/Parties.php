<?php

namespace App\Support\Redaction;

use Illuminate\Support\Carbon;

/**
 * Les parties d'un jugement civil (demandeurs, défendeurs) : une ligne par partie, avec ce qu'il faut pour écrire ses qualités
 * (nom, genre, naissance, nationalité, profession, domicile, autres mentions). Le genre vaut « m », « f » ou « s » (société ou
 * institution : accordée au féminin, sans civilité). Les accords (demandeur ou demanderesse, né ou née, pluriel) suivent les parties.
 */
class Parties
{
    /** Genres d'une ligne de partie. */
    public const GENRES = ['m' => 'Monsieur', 'f' => 'Madame', 's' => 'Société ou institution'];

    /** Les lignes qui ont un nom (les lignes vides du formulaire sont écartées). @return list<array<string, mixed>> */
    public static function lignes(mixed $valeur): array
    {
        return array_values(array_filter(is_array($valeur) ? $valeur : [], fn ($p) => is_array($p) && trim((string) ($p['nom'] ?? '')) !== ''));
    }

    /** Les genres pour l'accord, d'une liste de parties ou de personnes (une société s'accorde au féminin). @return list<string> */
    public static function genres(mixed $valeur): array
    {
        if (! is_array($valeur)) {
            return [$valeur === 's' ? 'f' : (string) $valeur];
        }
        $genres = [];
        foreach ($valeur as $p) {
            $g = is_array($p) ? (string) ($p['genre'] ?? '') : (string) $p;
            $genres[] = $g === 's' ? 'f' : $g;
        }

        return $genres;
    }

    /** [genre, pluriel] d'un groupe : le masculin l'emporte, plusieurs personnes donnent le pluriel. */
    public static function resoudre(mixed $valeur): array
    {
        return Accords::resoudre(self::genres($valeur));
    }

    /** « il », « elle », « ils », « elles ». */
    public static function pronom(mixed $valeur): string
    {
        [$genre, $pluriel] = self::resoudre($valeur);

        return $genre === 'm' ? ($pluriel ? 'ils' : 'il') : ($pluriel ? 'elles' : 'elle');
    }

    public static function civilite(array $p): string
    {
        return match ($p['genre'] ?? '') {
            'm' => 'Monsieur', 'f' => 'Madame', default => '',
        };
    }

    /** « Monsieur CHEHADE Kassem » ; une société garde son nom seul. */
    public static function nom(array $p): string
    {
        return trim(self::civilite($p).' '.trim((string) ($p['nom'] ?? '')));
    }

    /** « Monsieur A et Madame B » */
    public static function noms(array $parties): string
    {
        return Accords::enumerer(array_map(fn ($p) => self::nom($p), $parties));
    }

    /** Les noms en majuscules pour le timbre : « A » seul, ou « 1-A », « 2-B » quand elles sont plusieurs (une par ligne). */
    public static function timbre(array $parties): string
    {
        $noms = array_map(fn ($p) => mb_strtoupper(trim((string) $p['nom'])), $parties);
        if (count($noms) <= 1) {
            return $noms[0] ?? '';
        }

        return implode("\n", array_map(fn ($n, $i) => ($i + 1).'-'.$n, $noms, array_keys($noms)));
    }

    /** « le 12 décembre 1980 » ou « vers 1980 » à partir de la saisie de la naissance. */
    public static function naissance(mixed $n): string
    {
        if (! is_array($n)) {
            return trim((string) $n);
        }
        if (($n['mode'] ?? '') === 'annee') {
            return 'vers '.($n['annee'] ?? '');
        }
        if (empty($n['date'])) {
            return '';
        }

        return 'le '.Carbon::parse($n['date'])->locale('fr')->translatedFormat('d F Y');
    }

    /**
     * Les qualités d'une partie, comme dans un jugement : « Monsieur CHEHADE KASSEM, né le 12 décembre 1980 à Abengourou, de nationalité
     * ivoirienne, gérant de société, domicilié à Abidjan ; ». Le nom (avec sa civilité) est le premier élément ; $rang numérote (« 1- »).
     *
     * @return array{0: string, 1: string} [le début en gras, la suite]
     */
    public static function qualite(array $p, ?int $rang = null): array
    {
        $genre = ($p['genre'] ?? '') === 's' ? 'f' : (string) ($p['genre'] ?? '');
        $nom = mb_strtoupper(trim((string) $p['nom']));
        $debut = ($rang !== null ? ($rang + 1).'-' : '').trim(self::civilite($p).' '.$nom);

        $propre = fn ($cle) => trim((string) ($p[$cle] ?? ''), " \t\n\r\0\x0B;.,");
        $parts = [];
        if (($p['genre'] ?? '') === 's') {
            foreach ([$propre('profession'), $propre('domicile') !== '' ? 'ayant son siège social à '.$propre('domicile') : '', $propre('details')] as $x) {
                if ($x !== '') {
                    $parts[] = $x;
                }
            }
        } else {
            $naissance = self::naissance($p['naissance'] ?? null);
            $lieu = $propre('lieu_naissance');
            if ($naissance !== '' || $lieu !== '') {
                $parts[] = Accords::ne($genre).($naissance !== '' ? ' '.$naissance : '').($lieu !== '' ? ' à '.$lieu : '');
            }
            if ($propre('nationalite') !== '') {
                $parts[] = 'de nationalité '.$propre('nationalite');
            }
            if ($propre('profession') !== '') {
                $parts[] = $propre('profession');
            }
            if ($propre('domicile') !== '') {
                $parts[] = Accords::accorder('domicilié', $genre).' à '.$propre('domicile');
            }
            if ($propre('details') !== '') {
                $parts[] = $propre('details');
            }
        }

        return [$debut, ($parts ? ', '.implode(', ', $parts) : '').' ;'];
    }
}
