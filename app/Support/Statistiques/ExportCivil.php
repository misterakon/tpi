<?php

namespace App\Support\Statistiques;

use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Exports des statistiques civiles au modèle des classeurs du TPI : mêmes onglets (« RECAP ENROL. CIV PAR AN », « PAR MOIS »), mêmes blocs,
 | mêmes libellés de catégories, même ordre. Les blocs sont construits une fois ici, puis écrits en Excel ou en PDF.
 | Un bloc : titre, ligne d'en-tête, lignes de chiffres, dernière ligne en gras (total).
 */
class ExportCivil
{
    /**
     * @return array<string, list<array{titre: string, entete: list<string>, lignes: list<list<int|string>>}>> onglet => blocs
     */
    public static function onglets(array $recap, array $etatCivil, array $matrimonial, int $anneeMois): array
    {
        $annees = $recap['annees'];
        $cats = $recap['categories'];
        $enteteAn = fn (bool $stock) => array_merge([''], $stock ? ['Stock en début '.$annees[0]] : [], array_map('strval', $annees));

        $blocAn = function (string $titre, string $cle, bool $stock) use ($recap, $cats, $annees, $enteteAn) {
            $lignes = [];
            foreach ($cats as $k => $lib) {
                $b = $recap['par_categorie'][$k];
                $lignes[] = array_merge([$lib], $stock ? [$b['stock_debut']] : [], array_map(fn ($y) => $b[$cle][$y], $annees));
            }
            $t = $recap['total'];
            $lignes[] = array_merge(['Total'], $stock ? [$t['stock_debut']] : [], array_map(fn ($y) => $t[$cle][$y], $annees));

            return ['titre' => $titre, 'entete' => $enteteAn($stock), 'lignes' => $lignes];
        };

        $sous = function (string $titre, array $etat, string $cle, bool $stock) {
            $annees = $etat['annees'];
            $lignes = [];
            $tot = array_fill(0, count($annees) + ($stock ? 1 : 0), 0);
            foreach ($etat['lignes'] as $l) {
                $v = array_merge($stock ? [$l['stock_debut']] : [], array_map(fn ($y) => $l[$cle][$y], $annees));
                foreach ($v as $i => $n) {
                    $tot[$i] += $n;
                }
                $lignes[] = array_merge([$l['libelle']], $v);
            }
            $lignes[] = array_merge(['Total'], $tot);

            return ['titre' => $titre, 'entete' => array_merge([''], $stock ? ['Stock en début '.$annees[0]] : [], array_map('strval', $annees)), 'lignes' => $lignes];
        };

        $parAn = [
            $blocAn("Etat des stocks en fin d'année", 'stock_fin', true),
            $blocAn("Etat des affaires à juger selon l'année", 'a_juger', false),
            $blocAn("Etat des affaires nouvelles selon l'année", 'nouvelles', false),
            $blocAn('Etat des affaires vidées par an', 'videes', false),
            $sous("Etat des stocks d'affaires d'état civil en fin d'année", $etatCivil, 'stock_fin', true),
            $sous("Etat des affaires d'état civil nouvelles reçues par an", $etatCivil, 'nouvelles', false),
            $sous("Etat des affaires d'état civil vidées par an", $etatCivil, 'videes', false),
            $sous("Etat des demandes accordées en matière d'état civil selon l'année de la décision", $etatCivil, 'accordees', false),
            $sous("Etat des demandes rejetées en matière d'état civil selon l'année de la décision", $etatCivil, 'rejetees', false),
            $sous('Etat des stocks des affaires matrimoniales en fin d\'année', $matrimonial, 'stock_fin', true),
            $sous('Etat des nouvelles affaires matrimoniales reçues par an', $matrimonial, 'nouvelles', false),
            $sous('Etat des affaires matrimoniales vidées par an', $matrimonial, 'videes', false),
            $sous('Etat des demandes accordées en matière matrimoniale selon l\'année de la décision', $matrimonial, 'accordees', false),
            $sous('Etat des demandes rejetées en matière matrimoniale selon l\'année de la décision', $matrimonial, 'rejetees', false),
            $sous('Adoption : filles adoptées (demandes accordées)', $matrimonial, 'filles', false),
            $sous('Adoption : garçons adoptés (demandes accordées)', $matrimonial, 'garcons', false),
        ];

        $mois = [];
        $enteteMois = array_merge([''], array_values(DonneesCivil::MOIS), ['Total']);
        foreach ([['Etat des stocks en fin de mois', 'stock_fin'], ['Etat des affaires nouvelles par mois', 'nouvelles'], ['Etat des affaires vidées par mois', 'videes']] as [$titre, $cle]) {
            $lignes = [];
            foreach (['total' => 'Total'] + $cats as $k => $lib) {
                $m = $recap['mois'][$k];
                $v = array_map(fn ($i) => $m[$cle][$i], array_keys(DonneesCivil::MOIS));
                $lignes[] = array_merge([$k === 'total' ? 'Total' : $lib], $v, [$cle === 'stock_fin' ? end($v) : array_sum($v)]);
            }
            // Le total est en dernière ligne, comme dans les classeurs.
            $lignes[] = array_shift($lignes);
            $mois[] = ['titre' => $titre.' '.$anneeMois, 'entete' => $enteteMois, 'lignes' => $lignes];
        }

        return ['RECAP ENROL. CIV PAR AN' => $parAn, 'RECAP ENROL. CIV PAR MOIS' => $mois];
    }

    /** Écrit le classeur et renvoie son chemin (fichier temporaire, à supprimer après envoi). */
    public static function excel(array $onglets, string $titre, string $periode): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'stc');
        $w = new Writer;
        $w->getOptions()->setColumnWidth(48, 1);
        $w->getOptions()->setColumnWidthForRange(16, 2, 24);
        $w->openToFile($chemin);

        $titreStyle = (new Style)->withFontBold(true)->withFontSize(14)->withFontColor('00794C');
        $blocStyle = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C');
        $enteteStyle = (new Style)->withFontBold(true)->withBackgroundColor('FCE4CC')->withCellAlignment(\OpenSpout\Common\Entity\Style\CellAlignment::CENTER);
        $totalStyle = (new Style)->withFontBold(true)->withBackgroundColor('E7F3EC');
        $normal = new Style;

        $premier = true;
        foreach ($onglets as $nom => $blocs) {
            if ($premier) {
                $w->getCurrentSheet()->setName($nom);
                $premier = false;
            } else {
                $w->addNewSheetAndMakeItCurrent()->setName($nom);
            }
            $w->addRow(self::ligne([mb_strtoupper($titre)], $titreStyle));
            $w->addRow(Row::fromValues([$periode.' · édité le '.now()->format('d/m/Y à H:i')]));
            $w->addRow(Row::fromValues(['']));
            foreach ($blocs as $b) {
                $n = count($b['entete']);
                $w->addRow(self::ligne(array_merge([$b['titre']], array_fill(0, $n - 1, '')), $blocStyle));
                $w->addRow(self::ligne($b['entete'], $enteteStyle));
                $dernier = ($b['total'] ?? true) ? count($b['lignes']) - 1 : -1;
                foreach ($b['lignes'] as $i => $l) {
                    $w->addRow(self::ligne($l, $i === $dernier ? $totalStyle : $normal));
                }
                $w->addRow(Row::fromValues(['']));
            }
        }
        $w->close();

        return $chemin;
    }

    /** Une ligne dont toutes les cellules portent le même style. */
    private static function ligne(array $valeurs, Style $style): Row
    {
        return Row::fromValuesWithStyles($valeurs, array_fill(0, count($valeurs), $style));
    }

    public static function pdf(array $onglets, string $titre, string $periode): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('statistiques.export_pdf', [
            'onglets' => $onglets, 'titre' => $titre, 'periode' => $periode, 'date' => now(),
            'logo' => \App\Support\Roles\RoleExport::filigraneDonnees(), 'armoiries' => \App\Support\Roles\RoleExport::armoiriesDonnees(),
            'gauche' => \App\Support\Roles\RoleDocument::enteteGauche(), 'droite' => \App\Support\Roles\RoleDocument::enteteDroite(),
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
