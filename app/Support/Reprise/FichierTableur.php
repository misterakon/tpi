<?php

namespace App\Support\Reprise;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use ZipArchive;

/*
 | Lecture sécurisée d'un classeur Excel (.xlsx). Avant toute lecture, le fichier est vérifié : vraie archive Office (pas un fichier renommé),
 | sans macros, sans entrées dangereuses, sans « bombe de décompression ». Les cellules sont lues comme du texte : une formule n'est jamais
 | exécutée, une valeur trop longue est coupée, les caractères de contrôle sont retirés.
 */
class FichierTableur
{
    public const MAX_LIGNES = 20000;

    public const MAX_COLONNES = 40;

    public const MAX_CELLULE = 2000;

    private const MAX_ENTREES = 1000;

    private const MAX_DECOMPRESSE = 300 * 1024 * 1024;

    private const RATIO_MAX = 200;

    /** @throws RuntimeException avec un message destiné à l'utilisateur */
    public static function verifier(string $chemin, int $tailleMax): void
    {
        if (! is_file($chemin) || ! is_readable($chemin)) {
            throw new RuntimeException('Le fichier est introuvable.');
        }
        if (filesize($chemin) === 0) {
            throw new RuntimeException('Le fichier est vide.');
        }
        if (filesize($chemin) > $tailleMax) {
            throw new RuntimeException('Le fichier dépasse la taille permise ('.round($tailleMax / 1048576).' Mo).');
        }
        $debut = (string) file_get_contents($chemin, false, null, 0, 4);
        if ($debut !== "PK\x03\x04") {
            throw new RuntimeException("Ce fichier n'est pas un classeur Excel (.xlsx) : son contenu ne correspond pas à son extension.");
        }

        $zip = new ZipArchive;
        if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Le classeur est illisible ou endommagé.');
        }
        try {
            if ($zip->numFiles > self::MAX_ENTREES) {
                throw new RuntimeException('Le classeur contient trop d\'éléments internes : il est refusé par précaution.');
            }
            $total = 0;
            $noms = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $e = $zip->statIndex($i);
                $nom = (string) ($e['name'] ?? '');
                $noms[] = strtolower($nom);
                if (str_contains($nom, '..') || str_starts_with($nom, '/') || str_contains($nom, '\\')) {
                    throw new RuntimeException('Le classeur contient un chemin interne dangereux : il est refusé.');
                }
                $total += (int) $e['size'];
                if ((int) $e['comp_size'] > 0 && (int) $e['size'] / (int) $e['comp_size'] > self::RATIO_MAX && (int) $e['size'] > 1048576) {
                    throw new RuntimeException('Le classeur est trop compressé pour être sûr (risque de bombe de décompression) : il est refusé.');
                }
            }
            if ($total > self::MAX_DECOMPRESSE) {
                throw new RuntimeException('Le contenu du classeur est trop volumineux une fois décompressé : il est refusé.');
            }
            if (! in_array('[content_types].xml', $noms, true) || ! in_array('xl/workbook.xml', $noms, true)) {
                throw new RuntimeException("Ce fichier n'est pas un classeur Excel valide.");
            }
            foreach ($noms as $nom) {
                if (str_contains($nom, 'vbaproject') || str_ends_with($nom, '.bin') && str_contains($nom, 'macro')) {
                    throw new RuntimeException('Le classeur contient des macros : elles ne sont jamais acceptées. Enregistrez-le en .xlsx simple.');
                }
                if (str_starts_with($nom, 'xl/embeddings/') || str_starts_with($nom, 'xl/activex/')) {
                    throw new RuntimeException('Le classeur contient des objets intégrés : il est refusé par précaution.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** Noms des feuilles, avec leur ordre. @return list<string> */
    public static function feuilles(string $chemin): array
    {
        $lecteur = self::ouvrir($chemin);
        try {
            $noms = self::nommer($lecteur);
        } finally {
            $lecteur->close();
            gc_collect_cycles();
        }

        return $noms;
    }

    /** @return list<string> */
    private static function nommer(Reader $lecteur): array
    {
        $noms = [];
        foreach ($lecteur->getSheetIterator() as $feuille) {
            $noms[] = $feuille->getName();
        }

        return $noms;
    }

    /**
     * Parcourt une feuille ligne par ligne (numéro de ligne Excel, cellules en texte). Arrête au-delà de MAX_LIGNES.
     *
     * @param  callable(int, list<string>): (bool|void)  $surLigne  renvoyer false arrête la lecture
     */
    public static function parcourir(string $chemin, string $feuille, callable $surLigne): void
    {
        $lecteur = self::ouvrir($chemin);
        $trouvee = false;
        try {
            $trouvee = self::balayer($lecteur, $feuille, $surLigne);
        } finally {
            // La lecture est faite dans une fonction à part : ses itérateurs sont libérés avant la fermeture, ce qui rend le fichier supprimable (Windows).
            $lecteur->close();
            gc_collect_cycles();
        }
        if (! $trouvee) {
            throw new RuntimeException('La feuille « '.$feuille.' » est introuvable.');
        }
    }

    private static function balayer(Reader $lecteur, string $feuille, callable $surLigne): bool
    {
        foreach ($lecteur->getSheetIterator() as $f) {
            if ($f->getName() !== $feuille) {
                continue;
            }
            $n = 0;
            $arret = false;
            // On parcourt toujours la feuille jusqu'au bout, même si le rappel a assez lu (contrôle des titres) : arrêter un itérateur
            // à mi-chemin laisserait le fichier ouvert, donc impossible à supprimer sous Windows. Le coût est faible (20 000 lignes au plus).
            foreach ($f->getRowIterator() as $ligne) {
                $n++;
                if ($n > self::MAX_LIGNES + 50) {
                    throw new RuntimeException('Le fichier dépasse '.number_format(self::MAX_LIGNES, 0, ',', ' ').' lignes : découpez-le en plusieurs fichiers.');
                }
                if (! $arret && $surLigne($n, array_slice(array_map([self::class, 'texte'], $ligne->toArray()), 0, self::MAX_COLONNES)) === false) {
                    $arret = true;   // le rappel a assez lu
                }
            }

            return true;
        }

        return false;
    }

    private static function ouvrir(string $chemin): Reader
    {
        $options = new Options(SHOULD_FORMAT_DATES: false, SHOULD_PRESERVE_EMPTY_ROWS: true);
        $lecteur = new Reader($options);
        $lecteur->open($chemin);

        return $lecteur;
    }

    /** Une cellule devient un texte propre : dates en AAAA-MM-JJ, nombres sans « .0 », sans caractère de contrôle, longueur bornée. */
    public static function texte(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof DateTimeInterface) {
            return $v->format('H:i:s') === '00:00:00' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i');
        }
        if (is_bool($v)) {
            return $v ? 'oui' : 'non';
        }
        if (is_float($v)) {
            $v = rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
        }
        $texte = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v);

        return mb_substr(trim($texte), 0, self::MAX_CELLULE);
    }
}
