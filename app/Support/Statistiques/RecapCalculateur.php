<?php

namespace App\Support\Statistiques;

use Carbon\CarbonInterface;
use ZipArchive;

/*
 | Écrit les valeurs « en cache » de l'onglet RECAP … PAR AN d'un registre rempli, en évaluant ses formules comme Excel (COUNTIFS, SUMIF, SUM, + et −, formules partagées).
 | Sans cela, Excel en mode protégé (fichier téléchargé) affiche les anciennes valeurs, des zéros, tant qu'on n'a pas cliqué sur « Activer la modification ».
 | Excel recalcule de toute façon à l'ouverture : ces valeurs ne servent qu'à ce que le récapitulatif soit juste dès l'affichage.
 */
class RecapCalculateur
{
    /** @param list<array<string, mixed>> $lignes les lignes du tableau, par lettre de colonne */
    public static function injecter(ZipArchive $zip, array $lignes, string $table): void
    {
        $noms = self::nomsDeColonnes((string) $zip->getFromName($table));
        $records = self::records($lignes, $noms);
        foreach (self::feuillesRecapParAn($zip) as $chemin) {
            $xml = (string) $zip->getFromName($chemin);
            $zip->addFromString($chemin, self::calculer($xml, $records, $zip));
        }
    }

    /** @return list<string> */
    private static function nomsDeColonnes(string $table): array
    {
        preg_match_all('/<tableColumn [^>]*?name="([^"]+)"/', $table, $m);

        return array_map(fn ($n) => html_entity_decode($n, ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
    }

    /** Les lignes du tableau avec les colonnes calculées « Année … », « Reçu avant … », « Vidé avant … ». @return list<array<string, mixed>> */
    private static function records(array $lignes, array $noms): array
    {
        $records = [];
        foreach ($lignes as $l) {
            $r = [];
            foreach ($noms as $i => $nom) {
                $lettre = self::lettre($i + 1);
                if (array_key_exists($lettre, $l) && $l[$lettre] !== null && $l[$lettre] !== '') {
                    $r[$nom] = $l[$lettre];
                }
            }
            $date1 = self::premier($r, 'Date 1');
            $dateD = self::premier($r, 'Date de la d');
            $r['Année 1ère audience'] = $date1 ? (string) $date1->year : '';
            $r['Année décision'] = $dateD ? (string) $dateD->year : '';
            foreach ($noms as $nom) {
                if (preg_match('/^(Reçu|Vidé|Traité) avant (\d{4})/u', $nom, $m)) {
                    $d = str_starts_with($nom, 'Reçu') ? $date1 : $dateD;
                    $r[$nom] = $d ? ($d->year < (int) $m[2] ? 1 : 0) : '';
                }
            }
            $records[] = $r;
        }

        return $records;
    }

    private static function premier(array $r, string $prefixe): ?CarbonInterface
    {
        foreach ($r as $k => $v) {
            if (str_starts_with($k, $prefixe) && $v instanceof CarbonInterface) {
                return $v;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function feuillesRecapParAn(ZipArchive $zip): array
    {
        $wb = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        preg_match_all('/<Relationship [^>]*\/>/', $rels, $rm);
        $cibles = [];
        foreach ($rm[0] as $rel) {
            if (preg_match('/Id="([^"]+)"/', $rel, $a) && preg_match('/Target="([^"]+)"/', $rel, $b)) {
                $cibles[$a[1]] = $b[1];
            }
        }
        $sortie = [];
        preg_match_all('/<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"/', $wb, $sm, PREG_SET_ORDER);
        foreach ($sm as $s) {
            $nom = mb_strtoupper(html_entity_decode($s[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if (str_starts_with($nom, 'RECAP') && str_ends_with($nom, 'PAR AN') && isset($cibles[$s[2]])) {
                $sortie[] = 'xl/'.str_replace('xl/', '', ltrim($cibles[$s[2]], '/'));
            }
        }

        return $sortie;
    }

    private static function calculer(string $xml, array $records, ZipArchive $zip): string
    {
        $formules = [];     // ref => [attrs, texte]
        $valeurs = [];      // ref => texte ou nombre
        $chaines = self::chainesPartagees($zip);
        preg_match_all('/<c r="([A-Z]+\d+)"([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $xml, $cm, PREG_SET_ORDER);
        $maitres = [];
        foreach ($cm as $c) {
            $corps = $c[3] ?? '';
            if (preg_match('/<f([^>]*?)(?:\/>|>(.*?)<\/f>)/s', $corps, $f)) {
                $formules[$c[1]] = [$f[1], html_entity_decode($f[2] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8')];
                if (str_contains($f[1], 't="shared"') && ($f[2] ?? '') !== '' && preg_match('/si="(\d+)"/', $f[1], $si)) {
                    $maitres[$si[1]] = [$c[1], $formules[$c[1]][1]];
                }
            } elseif (preg_match('/<v>(.*?)<\/v>/', $corps, $v)) {
                $valeurs[$c[1]] = str_contains($c[2], 't="s"') ? ($chaines[(int) $v[1]] ?? '') : (is_numeric($v[1]) ? $v[1] + 0 : $v[1]);
            }
        }

        $memo = [];
        $valeur = null;
        $valeur = function (string $ref) use (&$valeur, &$memo, $formules, $valeurs, $maitres, $records) {
            if (array_key_exists($ref, $memo)) {
                return $memo[$ref];
            }
            if (isset($formules[$ref])) {
                $memo[$ref] = 0;
                [$attrs, $texte] = $formules[$ref];
                if (str_contains($attrs, 't="shared"') && $texte === '' && preg_match('/si="(\d+)"/', $attrs, $si) && isset($maitres[$si[1]])) {
                    [$mref, $mtexte] = $maitres[$si[1]];
                    $texte = self::decaler($mtexte, self::colonne($ref) - self::colonne($mref), self::ligne($ref) - self::ligne($mref));
                }

                return $memo[$ref] = self::evaluer($texte, $valeur, $records);
            }
            $v = $valeurs[$ref] ?? 0;

            return $memo[$ref] = is_numeric($v) ? $v + 0 : 0;
        };

        return preg_replace_callback('/<c r="([A-Z]+\d+)"([^>]*?)>(<f[^>]*?(?:\/>|>.*?<\/f>))(?:<v>[^<]*<\/v>)?<\/c>/s', function ($m) use ($valeur) {
            $attrs = preg_replace('/\st="[^"]*"/', '', $m[2]);

            return '<c r="'.$m[1].'"'.$attrs.'>'.$m[3].'<v>'.$valeur($m[1]).'</v></c>';
        }, $xml) ?? $xml;
    }

    private static function evaluer(string $f, callable $valeur, array $records): float|int
    {
        $f = str_replace("''", "'", trim($f));
        $f = preg_replace_callback('/COUNTIFS\((.*?)\)(?=$|[+\-)*])/s', function ($m) use ($records) {
            preg_match_all('/\w+\[([^\]]+)\],"([^"]*)"/u', $m[1], $p, PREG_SET_ORDER);

            return (string) count(array_filter($records, fn ($r) => self::tout($r, $p)));
        }, $f);
        $f = preg_replace_callback('/SUMIF\(\w+\[([^\]]+)\],"([^"]*)",\w+\[([^\]]+)\]\)/u', function ($m) use ($records) {
            $somme = 0;
            foreach ($records as $r) {
                if (self::egal($r[$m[1]] ?? '', $m[2])) {
                    $somme += is_numeric($r[$m[3]] ?? null) ? $r[$m[3]] + 0 : 0;
                }
            }

            return (string) $somme;
        }, $f);
        $f = preg_replace_callback('/SUM\(([A-Z]+\d+):([A-Z]+\d+)\)/', function ($m) use ($valeur) {
            $t = 0;
            for ($c = self::colonne($m[1]); $c <= self::colonne($m[2]); $c++) {
                for ($l = self::ligne($m[1]); $l <= self::ligne($m[2]); $l++) {
                    $t += $valeur(self::lettre($c).$l);
                }
            }

            return (string) $t;
        }, $f);
        $f = preg_replace_callback('/\$?([A-Z]{1,2})\$?(\d+)/', fn ($m) => '('.$valeur($m[1].$m[2]).')', $f);

        // Il ne reste que des nombres, + − et parenthèses.
        if (! preg_match('/^[\d\s+\-().]*$/', $f)) {
            return 0;
        }
        try {
            return (float) eval('return '.($f === '' ? '0' : $f).';') + 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param list<array{0: string, 1: string, 2: string}> $criteres */
    private static function tout(array $r, array $criteres): bool
    {
        foreach ($criteres as $c) {
            if (! self::egal($r[$c[1]] ?? '', $c[2])) {
                return false;
            }
        }

        return true;
    }

    private static function egal(mixed $valeur, string $critere): bool
    {
        return mb_strtolower(trim((string) $valeur)) === mb_strtolower(trim($critere));
    }

    /** Décale les références relatives d'une formule partagée. */
    private static function decaler(string $f, int $dc, int $dl): string
    {
        return preg_replace_callback('/(\$?)([A-Z]{1,2})(\$?)(\d+)(?![\d(])/', fn ($m) => ($m[1] !== '' ? '$'.$m[2] : self::lettre(self::colonne($m[2]) + $dc)).($m[3] !== '' ? '$'.$m[4] : (string) ((int) $m[4] + $dl)), $f) ?? $f;
    }

    private static function colonne(string $ref): int
    {
        preg_match('/[A-Z]+/', $ref, $m);
        $n = 0;
        foreach (str_split($m[0]) as $ch) {
            $n = $n * 26 + ord($ch) - 64;
        }

        return $n;
    }

    private static function ligne(string $ref): int
    {
        return (int) preg_replace('/\D/', '', $ref);
    }

    private static function lettre(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $s = chr(65 + ($n - 1) % 26).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    /** @return list<string> */
    private static function chainesPartagees(ZipArchive $zip): array
    {
        $x = (string) $zip->getFromName('xl/sharedStrings.xml');
        preg_match_all('/<si>(.*?)<\/si>/s', $x, $m);

        return array_map(fn ($s) => html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
    }
}
