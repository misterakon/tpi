<?php

namespace App\Support\Redaction;

use InvalidArgumentException;

/**
 * Accords de genre et de nombre propres aux décisions : Monsieur ou Madame, demandeur ou demanderesse, né ou née,
 * pluriel pour plusieurs parties. Pour un groupe, le masculin l'emporte dès qu'un homme en fait partie.
 * Genres : « m » ou « f ». Les termes de justice irréguliers sont dans le lexique ci-dessous.
 */
class Accords
{
    /** Termes dont le féminin est irrégulier ou à contrôler : masculin singulier => féminin singulier. */
    private const LEXIQUE = [
        'demandeur' => 'demanderesse', 'défendeur' => 'défenderesse', 'comparant' => 'comparante', 'appelant' => 'appelante', 'intimé' => 'intimée',
        'prévenu' => 'prévenue', 'requérant' => 'requérante', 'défunt' => 'défunte', 'héritier' => 'héritière', 'veuf' => 'veuve', 'époux' => 'épouse',
        'assisté' => 'assistée', 'représenté' => 'représentée', 'né' => 'née', 'domicilié' => 'domiciliée', 'ivoirien' => 'ivoirienne', 'étranger' => 'étrangère',
        'présent' => 'présente', 'absent' => 'absente', 'greffier' => 'greffière', 'président' => 'présidente', 'célibataire' => 'célibataire',
        'divorcé' => 'divorcée', 'marié' => 'mariée', 'mineur' => 'mineure', 'majeur' => 'majeure', 'condamné' => 'condamnée', 'détenu' => 'détenue',
        'cultivateur' => 'cultivatrice', 'commerçant' => 'commerçante', 'employeur' => 'employeuse', 'travailleur' => 'travailleuse', 'enseignant' => 'enseignante',
        'propriétaire' => 'propriétaire', 'juge' => 'juge', 'assesseur' => 'assesseur', 'témoin' => 'témoin', 'victime' => 'victime', 'feu' => 'feue', 'décédé' => 'décédée', 'le' => 'la', 'son' => 'sa', 'il' => 'elle',
    ];

    /** Termes invariables en genre ET en nombre : participes présents et locutions. */
    private const INVARIABLES = ['comparaissant', 'partie jointe', 'ministère public', 'statuant', 'ayant', 'sans profession'];

    /** Civilité d'une personne ou d'un groupe : Monsieur, Madame, Messieurs, Mesdames. */
    public static function civilite(array|string $genres, bool $abrege = false): string
    {
        [$genre, $pluriel] = self::resoudre($genres);

        // Un couple s'écrit « Monsieur et Madame » ; seul un groupe de même genre prend le pluriel (Messieurs, Mesdames).
        if (is_array($genres) && count(array_unique($genres)) > 1) {
            return $abrege ? 'M. et Mme' : 'Monsieur et Madame';
        }

        return match (true) {
            $abrege && $genre === 'm' => $pluriel ? 'MM.' : 'M.',
            $abrege => $pluriel ? 'Mmes' : 'Mme',
            $genre === 'm' => $pluriel ? 'Messieurs' : 'Monsieur',
            default => $pluriel ? 'Mesdames' : 'Madame',
        };
    }

    /** Accorde un terme selon une personne (« f ») ou un groupe ([« m », « f »] : pluriel et masculin). */
    public static function accorder(string $terme, array|string $genres): string
    {
        [$genre, $pluriel] = self::resoudre($genres);
        $cle = mb_strtolower(trim($terme));

        if (in_array($cle, self::INVARIABLES, true)) {
            return $terme;
        }

        // Le terme peut être donné au masculin ou au féminin : on retrouve sa forme de base.
        $masculin = array_search($cle, self::LEXIQUE, true);
        $masculin = $masculin === false ? $cle : $masculin;

        $forme = $genre === 'f' ? self::feminin($masculin) : $masculin;
        $forme = $pluriel ? self::pluriel($forme) : $forme;

        return self::casse($terme, $forme);
    }

    /** « né(e) » écrit pour de bon : né, née, nés, nées. */
    public static function ne(array|string $genres): string
    {
        return self::accorder('né', $genres);
    }

    /** Genre et nombre d'un groupe : [genre, pluriel]. Le masculin l'emporte ; plusieurs personnes donnent le pluriel. */
    public static function resoudre(array|string $genres): array
    {
        $liste = is_array($genres) ? array_values($genres) : [$genres];
        if ($liste === []) {
            throw new InvalidArgumentException('Aucune personne à accorder.');
        }
        foreach ($liste as $g) {
            if (! in_array($g, ['m', 'f'], true)) {
                throw new InvalidArgumentException("Genre inconnu : « $g » (attendu « m » ou « f »).");
            }
        }

        return [in_array('m', $liste, true) ? 'm' : 'f', count($liste) > 1];
    }

    /** « A, B et C » */
    public static function enumerer(array $noms): string
    {
        $noms = array_values(array_filter(array_map('trim', $noms), fn ($n) => $n !== ''));
        if (count($noms) <= 1) {
            return $noms[0] ?? '';
        }

        return implode(', ', array_slice($noms, 0, -1)).' et '.end($noms);
    }

    private static function feminin(string $masculin): string
    {
        if (isset(self::LEXIQUE[$masculin])) {
            return self::LEXIQUE[$masculin];
        }

        return match (true) {
            str_ends_with($masculin, 'e') => $masculin,
            str_ends_with($masculin, 'er') => mb_substr($masculin, 0, -2).'ère',
            str_ends_with($masculin, 'eur') => mb_substr($masculin, 0, -3).'euse',
            str_ends_with($masculin, 'ien') => $masculin.'ne',
            str_ends_with($masculin, 'on') => $masculin.'ne',
            default => $masculin.'e',
        };
    }

    private static function pluriel(string $singulier): string
    {
        return match (true) {
            in_array($singulier, ['le', 'la'], true) => 'les',
            $singulier === 'son' || $singulier === 'sa' => 'ses',
            preg_match('/[sxz]$/u', $singulier) === 1 => $singulier,
            str_ends_with($singulier, 'eau') => $singulier.'x',
            str_ends_with($singulier, 'al') => mb_substr($singulier, 0, -2).'aux',
            default => $singulier.'s',
        };
    }

    /** Garde la majuscule initiale du terme donné (« Demandeur » reste « Demanderesse »). */
    private static function casse(string $modele, string $forme): string
    {
        if ($modele !== '' && mb_strtoupper(mb_substr($modele, 0, 1)) === mb_substr($modele, 0, 1) && mb_strtolower($modele) !== $modele) {
            return mb_strtoupper(mb_substr($forme, 0, 1)).mb_substr($forme, 1);
        }

        return $forme;
    }
}
