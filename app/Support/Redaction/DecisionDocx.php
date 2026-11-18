<?php

namespace App\Support\Redaction;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use ZipArchive;

/*
 | Fichier Word (.docx, écrit directement) d'une décision, avec la même mise en page que la feuille affichée à l'écran :
 |   - A4, marges et police des réglages « Impression des actes » ;
 |   - le timbre (cour d'appel, tribunal, numéros, parties) dans un cadre à gauche de la première page, texte centré dans sa hauteur ;
 |   - la qualité et le factum dans la même colonne (retrait à droite du timbre) ;
 |   - le numéro de page en bas, centré ; un bandeau « Brouillon » tant que la décision n'est pas validée.
 | Le texte vient des trois parties déjà rendues (entête, qualité, factum : HTML nettoyé par TexteRiche) ; seules les balises
 | que l'éditeur produit sont reprises (paragraphes, gras, italique, souligné, retours à la ligne, listes, alignements, retraits).
 */
class DecisionDocx
{
    private const TWIPS_PAR_MM = 56.6929;

    /** @param  array{entete: string, qualite: string, factum: string}  $parties
     *  @param  array<string, mixed>  $c  la mise en page (voir ModeleDecisionController::miseEnPage) */
    public static function generer(array $parties, array $c, bool $brouillon = false): string
    {
        $mm = fn ($v) => (int) round($v * self::TWIPS_PAR_MM);
        $taille = (int) $c['taille'] * 2;
        $tailleTimbre = (int) $c['tailleTimbre'] * 2;
        $ligneQualite = (int) round(240 * $c['interligneQualite'] / 100);
        $ligneFactum = (int) round(240 * $c['interligneFactum'] / 100);
        $retrait = $mm($c['retrait']);
        $retraitFactum = $mm($c['retraitFactum'] ?? $c['retrait']);

        $corps = '';

        // Le timbre : un tableau flottant d'une seule cellule, posé à l'endroit réglé sur la page, texte du haut vers le bas dans le cadre.
        if (trim((string) $parties['entete']) !== '') {
            $timbre = self::blocs((string) $parties['entete'], ['police' => $c['policeTimbre'], 'sz' => $tailleTimbre, 'ligne' => 276, 'jc' => 'center', 'gras' => true, 'apres' => 160, 'retrait' => 0]);
            $largeur = $mm($c['timbreLargeur']);
            $corps .= '<w:tbl><w:tblPr><w:tblpPr w:leftFromText="0" w:rightFromText="0" w:topFromText="0" w:bottomFromText="0" w:vertAnchor="page" w:horzAnchor="page" w:tblpX="'.$mm($c['timbreGauche']).'" w:tblpY="'.$mm($c['timbreHaut']).'"/>'
                .'<w:tblOverlap w:val="never"/><w:tblW w:w="'.$largeur.'" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="100" w:type="dxa"/><w:left w:w="140" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="140" w:type="dxa"/></w:tblCellMar></w:tblPr>'
                .'<w:tblGrid><w:gridCol w:w="'.$largeur.'"/></w:tblGrid><w:tr><w:trPr><w:cantSplit/><w:trHeight w:val="'.$mm($c['timbreHauteur']).'" w:hRule="exact"/></w:trPr>'
                .'<w:tc><w:tcPr><w:tcW w:w="'.$largeur.'" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>'.($timbre ?: '<w:p/>').'</w:tc></w:tr></w:tbl>';
        }

        $corps .= self::blocs((string) $parties['qualite'], ['police' => $c['police'], 'sz' => $taille, 'ligne' => $ligneQualite, 'jc' => 'both', 'gras' => false, 'apres' => 0, 'retrait' => $retrait]);
        $corps .= self::blocs((string) $parties['factum'], ['police' => $c['police'], 'sz' => $taille, 'ligne' => $ligneFactum, 'jc' => 'both', 'gras' => false, 'apres' => 0, 'retrait' => $retraitFactum]);
        if (trim(strip_tags((string) $parties['qualite'].(string) $parties['factum'])) === '' && trim((string) $parties['entete']) === '') {
            $corps .= '<w:p/>';
        }

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $en = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $police = self::x((string) $c['police']);

        $section = '<w:sectPr>'.($brouillon ? '<w:headerReference w:type="default" r:id="rIdH"/>' : '').'<w:footerReference w:type="default" r:id="rIdF"/>'
            .'<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="'.$mm($c['margeHaut']).'" w:right="'.$mm($c['margeDroite']).'" w:bottom="'.$mm($c['margeBas']).'" w:left="'.$mm($c['margeGauche']).'" w:header="400" w:footer="280" w:gutter="0"/></w:sectPr>';
        $document = $en.'<w:document '.$ns.'><w:body>'.$corps.$section.'</w:body></w:document>';

        $styles = $en.'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="'.$police.'" w:hAnsi="'.$police.'" w:cs="'.$police.'" w:eastAsia="'.$police.'"/><w:sz w:val="'.$taille.'"/><w:szCs w:val="'.$taille.'"/><w:lang w:val="fr-FR"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style></w:styles>';

        $pied = $en.'<w:ftr '.$ns.'><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
            .'<w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:t>1</w:t></w:r><w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
        $entete = $en.'<w:hdr '.$ns.'><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="C00000"/><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">BROUILLON : décision non validée</w:t></w:r></w:p></w:hdr>';

        $types = $en.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            .($brouillon ? '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' : '').'</Types>';
        $rel = fn (string $contenu) => $en.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$contenu.'</Relationships>';
        $base = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $rels = $rel('<Relationship Id="rId1" Type="'.$base.'officeDocument" Target="word/document.xml"/>');
        $relsDoc = $rel('<Relationship Id="rIdS" Type="'.$base.'styles" Target="styles.xml"/><Relationship Id="rIdF" Type="'.$base.'footer" Target="footer1.xml"/>'
            .($brouillon ? '<Relationship Id="rIdH" Type="'.$base.'header" Target="header1.xml"/>' : ''));

        $chemin = tempnam(sys_get_temp_dir(), 'dec');
        $zip = new ZipArchive;
        if ($zip->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Le fichier Word n'a pas pu être créé.");
        }
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->addFromString('word/footer1.xml', $pied);
        if ($brouillon) {
            $zip->addFromString('word/header1.xml', $entete);
        }
        $zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
        $zip->close();
        $octets = (string) file_get_contents($chemin);
        @unlink($chemin);

        return $octets;
    }

    private static function x(string $t): string
    {
        return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Les paragraphes Word d'un morceau de HTML. @param  array<string, mixed>  $o  police, sz (demi-points), ligne, jc, gras, apres, retrait (twips) */
    private static function blocs(string $html, array $o): string
    {
        if (trim($html) === '') {
            return '';
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="racine">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $racine = $dom->getElementById('racine');
        if (! $racine) {
            return '';
        }

        $xml = '';
        self::parcourir($racine, $o, $xml);

        return $xml;
    }

    private static function parcourir(DOMNode $parent, array $o, string &$xml): void
    {
        $enAttente = [];   // du texte ou des balises « en ligne » qui n'ont pas de paragraphe autour
        $vider = function () use (&$enAttente, &$xml, $o) {
            if ($enAttente !== []) {
                $texte = '';
                foreach ($enAttente as $n) {
                    $texte .= $n->textContent;
                }
                if (trim($texte) !== '') {
                    $xml .= self::paragraphe($enAttente, $o);
                }
                $enAttente = [];
            }
        };

        foreach ($parent->childNodes as $n) {
            if ($n instanceof DOMElement && in_array($n->nodeName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                $vider();
                $oo = $o;
                if (str_starts_with($n->nodeName, 'h')) {
                    $oo['gras'] = true;
                }
                $xml .= self::paragraphe([$n], $oo, true);
            } elseif ($n instanceof DOMElement && in_array($n->nodeName, ['div', 'blockquote'], true)) {
                $vider();
                $oo = $o;
                if ($n->nodeName === 'blockquote') {
                    $oo['retrait'] += 680;
                }
                self::parcourir($n, $oo, $xml);
            } elseif ($n instanceof DOMElement && in_array($n->nodeName, ['ul', 'ol'], true)) {
                $vider();
                $i = 0;
                foreach ($n->childNodes as $li) {
                    if ($li instanceof DOMElement && $li->nodeName === 'li') {
                        $i++;
                        $marque = $n->nodeName === 'ol' ? $i.'. ' : '- ';
                        $oo = $o;
                        $oo['retrait'] += 567;
                        $oo['pendu'] = 283;
                        $oo['prefixe'] = $marque;
                        $xml .= self::paragraphe([$li], $oo, true);
                    }
                }
            } elseif ($n instanceof DOMElement && $n->nodeName === 'br' && $enAttente === []) {
                $xml .= self::paragraphe([], $o);   // une ligne vide
            } else {
                $enAttente[] = $n;
            }
        }
        $vider();
    }

    /** Un paragraphe Word. @param  list<DOMNode>  $noeuds  les nœuds dont le contenu fait le paragraphe ; $contenu : vrai si ce sont des éléments dont on prend l'intérieur */
    private static function paragraphe(array $noeuds, array $o, bool $contenu = false): string
    {
        $jc = $o['jc'];
        $retrait = (int) $o['retrait'];
        $trait = null;
        if ($contenu && isset($noeuds[0]) && $noeuds[0] instanceof DOMElement) {
            $el = $noeuds[0];
            $classe = ' '.$el->getAttribute('class').' ';
            $style = strtolower($el->getAttribute('style'));
            if (str_contains($classe, ' ql-align-center ') || str_contains($style, 'text-align: center') || str_contains($style, 'text-align:center')) {
                $jc = 'center';
            } elseif (str_contains($classe, ' ql-align-right ') || str_contains($style, 'text-align: right') || str_contains($style, 'text-align:right')) {
                $jc = 'right';
            } elseif (str_contains($classe, ' ql-align-left ') || str_contains($style, 'text-align: left') || str_contains($style, 'text-align:left')) {
                $jc = 'left';
            } elseif (str_contains($classe, ' ql-align-justify ')) {
                $jc = 'both';
            }
            if (preg_match('/ql-indent-(\d+)/', $classe, $m)) {
                $retrait += (int) $m[1] * 851;
            }
            // Les traits du timbre (pointillé sous la cour d'appel, trait plein sous le numéro du RG) : une bordure sous le paragraphe.
            foreach ((new \DOMXPath($el->ownerDocument))->query('.//*[contains(@class, "acte-trait")]', $el) as $t) {
                $trait = str_contains((string) $t->getAttribute('class'), 'pointille') ? 'dashed' : 'single';
            }
        }

        // Une ligne vide (« <p><br></p> ») est une seule ligne : on ne met donc aucun retour à la ligne dans le paragraphe Word.
        $vide = $contenu && isset($noeuds[0]) && $noeuds[0] instanceof DOMElement && trim($noeuds[0]->textContent) === '' && $noeuds[0]->getElementsByTagName('br')->length <= 1 && ! $trait;
        $ppr = '<w:pPr>'.($trait ? '<w:pBdr><w:bottom w:val="'.$trait.'" w:sz="6" w:space="1" w:color="000000"/></w:pBdr>' : '')
            .'<w:spacing w:before="0" w:after="'.($trait ? 0 : (int) $o['apres']).'" w:line="'.(int) $o['ligne'].'" w:lineRule="auto"/>'
            .($retrait || ! empty($o['pendu']) ? '<w:ind w:left="'.$retrait.'"'.(! empty($o['pendu']) ? ' w:hanging="'.(int) $o['pendu'].'"' : '').'/>' : '')
            .'<w:jc w:val="'.$jc.'"/><w:rPr><w:sz w:val="'.(int) $o['sz'].'"/><w:szCs w:val="'.(int) $o['sz'].'"/></w:rPr></w:pPr>';

        $etat = ['gras' => (bool) $o['gras'], 'italique' => false, 'souligne' => false, 'barre' => false];
        $runs = ! empty($o['prefixe']) ? self::run($o['prefixe'], $etat, $o) : '';
        if ($vide) {
            // rien : paragraphe vide
        } elseif ($contenu && isset($noeuds[0])) {
            $runs .= self::runs($noeuds[0], $etat, $o);
        } else {
            foreach ($noeuds as $n) {
                $runs .= self::runsNoeud($n, $etat, $o);
            }
        }

        return '<w:p>'.$ppr.$runs.'</w:p>';
    }

    /** Les morceaux de texte d'un élément (son intérieur). */
    private static function runs(DOMNode $element, array $etat, array $o): string
    {
        $sortie = '';
        foreach ($element->childNodes as $n) {
            $sortie .= self::runsNoeud($n, $etat, $o);
        }

        return $sortie;
    }

    private static function runsNoeud(DOMNode $n, array $etat, array $o): string
    {
        if ($n->nodeType === XML_TEXT_NODE) {
            $texte = (string) preg_replace('/[\r\n\t]+/', ' ', $n->nodeValue);

            return $texte === '' ? '' : self::run($texte, $etat, $o);
        }
        if (! $n instanceof DOMElement) {
            return '';
        }
        $nom = $n->nodeName;
        if ($nom === 'br') {
            return '<w:r><w:br/></w:r>';
        }
        if (in_array($nom, ['strong', 'b'], true)) {
            $etat['gras'] = true;
        } elseif (in_array($nom, ['em', 'i'], true)) {
            $etat['italique'] = true;
        } elseif ($nom === 'u') {
            $etat['souligne'] = true;
        } elseif (in_array($nom, ['s', 'strike', 'del'], true)) {
            $etat['barre'] = true;
        }
        // Un trait du timbre n'a pas de texte : il devient la bordure du paragraphe (voir paragraphe()).
        if (str_contains((string) $n->getAttribute('class'), 'acte-trait')) {
            return '';
        }

        return self::runs($n, $etat, $o);
    }

    private static function run(string $texte, array $etat, array $o): string
    {
        $rpr = '<w:rPr><w:rFonts w:ascii="'.self::x((string) $o['police']).'" w:hAnsi="'.self::x((string) $o['police']).'" w:cs="'.self::x((string) $o['police']).'"/>'
            .($etat['gras'] ? '<w:b/><w:bCs/>' : '').($etat['italique'] ? '<w:i/><w:iCs/>' : '').($etat['barre'] ? '<w:strike/>' : '')
            .'<w:sz w:val="'.(int) $o['sz'].'"/><w:szCs w:val="'.(int) $o['sz'].'"/>'.($etat['souligne'] ? '<w:u w:val="single"/>' : '').'</w:rPr>';   // l'ordre des éléments est celui que Word exige

        return '<w:r>'.$rpr.'<w:t xml:space="preserve">'.self::x($texte).'</w:t></w:r>';
    }
}
