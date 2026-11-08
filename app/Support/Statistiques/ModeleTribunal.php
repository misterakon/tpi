<?php

namespace App\Support\Statistiques;

use Carbon\CarbonInterface;
use RuntimeException;
use ZipArchive;

/*
 | Remplit un classeur du tribunal (resources/modeles_excel/*.xlsx, préparé par tools/preparer_modeles_excel.py) avec les lignes de l'application.
 | Le classeur d'origine est conservé tel quel : tableau de saisie, colonnes calculées, listes déroulantes, couleurs, onglets « RECAP » protégés.
 | Seules les lignes de saisie sont écrites, en recopiant les styles et les formules de la ligne type (celle qui suit l'en-tête du tableau) ; Excel recalcule
 | tout à l'ouverture (fullCalcOnLoad), si bien que les récapitulatifs du tribunal se remplissent d'eux-mêmes.
 */
class ModeleTribunal
{
    /**
     * @param  list<array<string, string|int|float|CarbonInterface|null>>  $lignes  une ligne par affaire, indexée par lettre de colonne (« A », « B »…)
     * @param  array<string, list<array<string, mixed>>>  $autres  les lignes des autres tableaux du classeur, par nom de feuille (ex. inculpés et victimes de l'instruction)
     * @param  array<string, array<string, int|string>>  $cellules  des cellules fixes à remplir, par nom de feuille puis par adresse (ex. « C3 »), pour les feuilles qui ne sont pas des tableaux
     * @param  string|null  $nomFeuille  la feuille à remplir quand le classeur a un tableau par feuille (ex. le personnel, une feuille par année) ; sinon le tableau principal
     * @return string chemin d'un fichier temporaire (à supprimer après envoi)
     */
    public static function generer(string $code, array $lignes, ?string $nomFeuille = null, array $autres = [], array $cellules = []): string
    {
        $modele = self::modele($code);
        if (! is_file($modele)) {
            throw new RuntimeException("Modèle Excel introuvable : $code");
        }
        $chemin = tempnam(sys_get_temp_dir(), 'reg');
        copy($modele, $chemin);
        $zip = new ZipArchive;
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Modèle Excel illisible.');
        }

        $vide = $lignes === [];
        $principal = $nomFeuille !== null ? self::tableauDeLaFeuille($zip, $nomFeuille) : self::tableauPrincipal($zip);
        self::ecrire($zip, $principal, $lignes);
        foreach ($autres as $feuilleAutre => $lignesAutre) {
            self::ecrire($zip, self::tableauDeLaFeuille($zip, $feuilleAutre), $lignesAutre);
        }
        foreach ($cellules as $feuilleFixe => $valeurs) {
            self::cellulesFixes($zip, $feuilleFixe, $valeurs);
        }
        $zip->close();

        // Valeurs affichées dès l'ouverture (même en mode protégé) pour le récapitulatif annuel ; Excel recalcule ensuite.
        if ($nomFeuille === null && $zip->open($chemin) === true) {
            RecapCalculateur::injecter($zip, $vide ? [] : $lignes, $principal[1]);
            $zip->close();
        }

        return $chemin;
    }

    /** Écrit les lignes dans UN tableau du classeur (feuille et tableau donnés). @param array{0: string, 1: string} $emplacement @param list<array<string, mixed>> $lignes */
    private static function ecrire(ZipArchive $zip, array $emplacement, array $lignes): void
    {
        [$feuille, $table] = $emplacement;
        $t = (string) $zip->getFromName($table);
        preg_match('/ref="A(\d+):([A-Z]+)\d+"/', $t, $mt);
        [$entete, $dernierTableau] = [(int) $mt[1], $mt[2]];        // l'en-tête est en ligne 4 pour les registres, en ligne 3 pour le personnel
        $ligneType = $entete + 1;

        $xml = (string) $zip->getFromName($feuille);
        $debut = strpos($xml, '<row r="'.$ligneType.'"');
        $finDonnees = strpos($xml, '</sheetData>');
        if ($debut === false || $finDonnees === false) {
            throw new RuntimeException('Ligne type absente du modèle.');
        }
        $finLigne = strpos($xml, '</row>', $debut) + 6;
        $ligneXml = substr($xml, $debut, $finLigne - $debut);
        preg_match('/<row\b([^>]*)>/', $ligneXml, $m);
        // jamais de ligne masquée : le classeur d'origine pouvait avoir un filtre actif qui cachait la ligne type
        $attributsLigne = preg_replace(['/\sr="\d+"/', '/\s(?:hidden|collapsed|outlineLevel)="[^"]*"/'], '', $m[1]);
        preg_match_all('/<c r="([A-Z]+)\d+"((?:\s+[\w:]+="[^"]*")*)\s*(?:\/>|>(.*?)<\/c>)/s', $ligneXml, $cellules, PREG_SET_ORDER);
        $proto = [];
        $derniereColonne = 'A';
        foreach ($cellules as $c) {
            $style = preg_match('/\ss="(\d+)"/', $c[2], $s) ? $s[1] : null;
            $formule = isset($c[3]) && preg_match('/<f\b[^>]*>(.*?)<\/f>/s', $c[3], $f) ? self::sansLangue($f[1]) : null;
            $proto[] = ['col' => $c[1], 'style' => $style, 'formule' => $formule];
            $derniereColonne = $c[1];
        }

        $lignes = $lignes === [] ? [[]] : $lignes;
        $lignesXml = '';
        foreach ($lignes as $i => $valeurs) {
            $r = $ligneType + $i;
            $lignesXml .= '<row r="'.$r.'"'.$attributsLigne.'>';
            foreach ($proto as $p) {
                $lignesXml .= self::cellule($p['col'].$r, $p['style'], $p['formule'], $valeurs[$p['col']] ?? null);
            }
            $lignesXml .= '</row>';
        }
        $derniere = $ligneType + count($lignes) - 1;
        $xml = substr($xml, 0, $debut).$lignesXml.substr($xml, $finDonnees);
        $xml = preg_replace('/<dimension ref="[^"]*"\/>/', '<dimension ref="A1:'.$derniereColonne.$derniere.'"/>', $xml, 1);
        $zip->addFromString($feuille, $xml);

        // La dernière colonne du TABLEAU (la ligne type peut déborder avec des cellules de mise en forme) ; aucun filtre actif : toutes les lignes sont visibles.
        $t = preg_replace('/ref="A'.$entete.':[A-Z]+\d+"/', 'ref="A'.$entete.':'.$dernierTableau.$derniere.'"', $t);
        $t = preg_replace('/<autoFilter ref="([^"]*)">.*?<\/autoFilter>/s', '<autoFilter ref="$1"/>', $t);
        $t = preg_replace_callback('/<calculatedColumnFormula>(.*?)<\/calculatedColumnFormula>/s', fn ($m) => '<calculatedColumnFormula>'.self::sansLangue($m[1]).'</calculatedColumnFormula>', $t) ?? $t;
        $zip->addFromString($table, $t);
    }

    /** Le classeur modèle d'un registre : .xlsx, ou .xlsm (avec macros) pour les activités du JAP. */
    public static function modele(string $code): string
    {
        $xlsm = resource_path('modeles_excel/'.$code.'.xlsm');

        return is_file($xlsm) ? $xlsm : resource_path('modeles_excel/'.$code.'.xlsx');
    }

    /** Remplit des cellules fixes dans un classeur sans tableau (activités du JAP). @param array<string, array<string, int|string>> $cellules feuille => adresse => valeur */
    public static function genererCellules(string $code, array $cellules): string
    {
        $modele = self::modele($code);
        if (! is_file($modele)) {
            throw new RuntimeException("Modèle Excel introuvable : $code");
        }
        $chemin = tempnam(sys_get_temp_dir(), 'reg');
        copy($modele, $chemin);
        $zip = new ZipArchive;
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Modèle Excel illisible.');
        }
        foreach ($cellules as $feuille => $valeurs) {
            self::cellulesFixes($zip, $feuille, $valeurs);
        }
        $zip->close();

        return $chemin;
    }

    /** Remplit des cellules fixes d'une feuille (sans tableau), en gardant leur style. Une adresse absente de la feuille est ignorée. @param array<string, int|string> $valeurs */
    private static function cellulesFixes(ZipArchive $zip, string $nomFeuille, array $valeurs): void
    {
        $wb = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        preg_match_all('/<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"/', $wb, $feuilles, PREG_SET_ORDER);
        foreach ($feuilles as $f) {
            if (html_entity_decode($f[1], ENT_QUOTES | ENT_XML1, 'UTF-8') !== $nomFeuille) {
                continue;
            }
            preg_match('/<Relationship [^>]*Id="'.preg_quote($f[2], '/').'"[^>]*Target="([^"]+)"|<Relationship [^>]*Target="([^"]+)"[^>]*Id="'.preg_quote($f[2], '/').'"/', $rels, $r);
            $chemin = 'xl/worksheets/'.basename($r[1] !== '' ? $r[1] : $r[2]);
            $xml = (string) $zip->getFromName($chemin);
            foreach ($valeurs as $ref => $v) {
                $xml = preg_replace_callback('/<c r="'.preg_quote($ref, '/').'"((?:\s+[\w:]+="[^"]*")*)\s*(?:\/>|>.*?<\/c>)/s', function ($m) use ($ref, $v) {
                    $style = preg_match('/\ss="(\d+)"/', $m[1], $s) ? $s[1] : null;

                    return self::cellule($ref, $style, null, $v);
                }, $xml, 1) ?? $xml;
            }
            $zip->addFromString($chemin, $xml);

            return;
        }
    }

    /** Le tableau principal du classeur : le premier (table1), avec sa feuille. @return array{0: string, 1: string} */
    public static function tableauPrincipal(ZipArchive $zip): array
    {
        $table = 'xl/tables/table1.xml';
        $nom = basename($table);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $f = $zip->getNameIndex($i);
            if (! preg_match('#^xl/worksheets/_rels/(sheet\d+)\.xml\.rels$#', $f, $m)) {
                continue;
            }
            if (str_contains((string) $zip->getFromName($f), '../tables/'.$nom)) {
                return ['xl/worksheets/'.$m[1].'.xml', $table];
            }
        }
        throw new RuntimeException('Tableau principal introuvable dans le modèle.');
    }

    /** Le tableau d'une feuille donnée (par son nom). @return array{0: string, 1: string} */
    public static function tableauDeLaFeuille(ZipArchive $zip, string $nomFeuille): array
    {
        $wb = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        preg_match_all('/<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"/', $wb, $feuilles, PREG_SET_ORDER);
        foreach ($feuilles as $s) {
            if (html_entity_decode($s[1], ENT_QUOTES | ENT_XML1, 'UTF-8') !== $nomFeuille) {
                continue;
            }
            preg_match('/<Relationship [^>]*Id="'.preg_quote($s[2], '/').'"[^>]*Target="([^"]+)"|<Relationship [^>]*Target="([^"]+)"[^>]*Id="'.preg_quote($s[2], '/').'"/', $rels, $r);
            $cible = basename($r[1] !== '' ? $r[1] : $r[2]);
            $relsFeuille = (string) $zip->getFromName('xl/worksheets/_rels/'.$cible.'.rels');
            if (preg_match('#\.\./tables/(table\d+\.xml)#', $relsFeuille, $t)) {
                return ['xl/worksheets/'.$cible, 'xl/tables/'.$t[1]];
            }
        }
        throw new RuntimeException("Feuille ou tableau introuvable : $nomFeuille");
    }

    /**
     * Rend les formules des colonnes « Année … » indépendantes de la langue d'Excel : TEXT(date;"aaaa") (« aaaa » n'existe qu'en français) devient YEAR(date)&"".
     * Le résultat est le même texte « 2026 », que les récapitulatifs du tribunal comparent à "2026".
     */
    private static function sansLangue(string $formule): string
    {
        return preg_replace('/TEXT\((.+?),(?:"|&quot;)aaaa(?:"|&quot;)\)/u', 'YEAR($1)&amp;""', $formule) ?? $formule;
    }

    private static function cellule(string $ref, ?string $style, ?string $formule, mixed $valeur): string
    {
        $s = $style !== null ? ' s="'.$style.'"' : '';
        if ($formule !== null) {
            return '<c r="'.$ref.'"'.$s.'><f>'.$formule.'</f></c>';   // la formule est déjà en XML dans le modèle
        }
        if ($valeur === null || $valeur === '') {
            return '<c r="'.$ref.'"'.$s.'/>';
        }
        if ($valeur instanceof CarbonInterface) {
            return '<c r="'.$ref.'"'.$s.'><v>'.(int) $valeur->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::create(1899, 12, 30), true).'</v></c>';
        }
        if (is_int($valeur) || is_float($valeur)) {
            return '<c r="'.$ref.'"'.$s.'><v>'.$valeur.'</v></c>';
        }
        $texte = htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $valeur) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.$texte.'</t></is></c>';
    }
}
