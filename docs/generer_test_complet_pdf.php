<?php

/*
 | Génère docs/TEST_PRATIQUE_COMPLET.pdf à partir de docs/TEST_PRATIQUE_COMPLET.md.
 | Utilisation : php docs/generer_test_complet_pdf.php [TEST_PRATIQUE_COMPLET | DONNEES_TEST_COMPLET]
 */

use Dompdf\Dompdf;
use Dompdf\Options;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;

require __DIR__.'/../vendor/autoload.php';

$nom = $argv[1] ?? 'TEST_PRATIQUE_COMPLET';   // sans extension : docs/<nom>.md devient docs/<nom>.pdf
$source = file_get_contents(__DIR__.'/'.$nom.'.md');
// Les pictogrammes ne s'impriment pas avec les polices du PDF : on les remplace par des étiquettes lisibles.
$source = str_replace(['⛔', '🔎'], ['[REFUS]', '[CACHÉ]'], $source);

$env = new Environment([]);
$env->addExtension(new CommonMarkCoreExtension);
$env->addExtension(new GithubFlavoredMarkdownExtension);
$html = (new MarkdownConverter($env))->convert($source)->getContent();
// Cases à cocher « [ ] » : un carré imprimable.
$html = str_replace('[ ]', '&#9744;', $html);
$html = preg_replace('#\[(REFUS|CACHÉ)\]#u', '<span class="badge badge-$1">$1</span>', $html);

$css = <<<'CSS'
@page { margin: 18mm 14mm 18mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.2pt; color: #1b2333; line-height: 1.45; }
h1 { font-size: 20pt; color: #00794c; border-bottom: 3px solid #fc6b03; padding-bottom: 6px; margin: 0 0 8px; }
h2 { font-size: 14pt; color: #fff; background: #00794c; padding: 6px 10px; margin: 22px 0 8px; page-break-after: avoid; }
h3 { font-size: 11pt; color: #00794c; border-bottom: 1px solid #fc6b03; padding-bottom: 2px; margin: 16px 0 6px; page-break-after: avoid; }
p { margin: 4px 0 6px; }
ul, ol { margin: 3px 0 6px 18px; padding: 0; }
li { margin: 2px 0; }
blockquote { margin: 8px 0; padding: 6px 10px; background: #fff4e8; border-left: 4px solid #fc6b03; }
code { font-family: DejaVu Sans Mono, monospace; font-size: 8.2pt; background: #eef2f0; padding: 0 3px; }
pre { background: #1f2933; color: #e8f0ec; padding: 8px 10px; font-size: 8pt; white-space: pre-wrap; }
pre code { background: transparent; color: inherit; padding: 0; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 8.3pt; }
th { background: #e3f1ea; color: #00593a; text-align: left; border: 1px solid #b9d3c6; padding: 4px 5px; }
td { border: 1px solid #cfd8d3; padding: 4px 5px; vertical-align: top; }
tr { page-break-inside: avoid; }
hr { border: 0; border-top: 1px solid #cfd8d3; margin: 14px 0; }
.badge { font-size: 7.5pt; font-weight: bold; padding: 1px 4px; color: #fff; }
.badge-REFUS { background: #c0392b; }
.badge-CACHÉ { background: #fc6b03; }
CSS;

$document = '<html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>'.$html.'</body></html>';

$options = new Options;
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$pdf = new Dompdf($options);
$pdf->setPaper('A4');
$pdf->loadHtml($document, 'UTF-8');
$pdf->render();

// Pied de page : numéro de page.
$canvas = $pdf->getCanvas();
$police = $pdf->getFontMetrics()->getFont('DejaVu Sans');
$canvas->page_text(500, 820, $nom.' — page {PAGE_NUM} / {PAGE_COUNT}', $police, 7.5, [0.35, 0.4, 0.45]);

file_put_contents(__DIR__.'/'.$nom.'.pdf', $pdf->output());
echo "PDF généré : $nom.pdf, ".round(filesize(__DIR__.'/'.$nom.'.pdf') / 1024)." Ko, ".$pdf->getCanvas()->get_page_count()." pages\n";
