<?php

namespace App\Support\Roles;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use ZipArchive;

/*
 | Sorties du rôle : PDF (sans accès réseau) et Word (.docx écrit directement), tous deux fidèles aux modèles de la greffière en chef :
 | A4 paysage, numéro d'ordre, numéro RG, demandeur, défendeur, objet, observations ; trois rubriques ; « Fait à … » et signataire.
 */
class RoleExport
{
    public static function logo(): string
    {
        return resource_path('assets/role-balance.png');
    }

    public static function armoiries(): string
    {
        return resource_path('assets/role-armoiries.png');
    }

    private static function donnees(string $chemin): string
    {
        return 'data:image/'.(str_ends_with($chemin, '.jpg') ? 'jpeg' : 'png').';base64,'.base64_encode((string) file_get_contents($chemin));
    }

    /** Le filigrane et les armoiries du papier à en-tête, prêts à être posés dans un PDF (sans accès réseau). */
    public static function filigraneDonnees(): string
    {
        return self::donnees(resource_path('assets/role-filigrane.jpg'));
    }

    public static function armoiriesDonnees(): string
    {
        return self::donnees(self::armoiries());
    }

    public static function logoDonnees(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::logo()));
    }

    public static function pdf(array $doc): string
    {
        $doc = RoleDocument::normaliser($doc);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('audiences.roles.pdf', [
            'doc' => $doc, 'logo' => self::donnees(resource_path('assets/role-filigrane.jpg')), 'armoiries' => self::donnees(self::armoiries()),
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    private static function x(string $t): string
    {
        return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Un paragraphe Word. */
    private static function p(string $texte, array $o = []): string
    {
        $ppr = '<w:pPr>'.(! empty($o['keep']) ? '<w:keepNext/>' : '').'<w:spacing w:before="'.($o['avant'] ?? 0).'" w:after="'.($o['apres'] ?? 0).'"/>'
            .(! empty($o['retrait']) ? '<w:ind w:left="'.$o['retrait'].'"/>' : '').'<w:jc w:val="'.($o['jc'] ?? 'left').'"/></w:pPr>';
        $taille = $o['t'] ?? 28;
        $rpr = '<w:rPr>'.(! empty($o['gras']) ? '<w:b/><w:bCs/>' : '').(! empty($o['italique']) ? '<w:i/><w:iCs/>' : '').'<w:sz w:val="'.$taille.'"/><w:szCs w:val="'.$taille.'"/></w:rPr>';

        return '<w:p>'.$ppr.($texte === '' ? '' : '<w:r>'.$rpr.'<w:t xml:space="preserve">'.self::x($texte).'</w:t></w:r>').'</w:p>';
    }

    /** Une cellule : une liste de paragraphes (un par ligne de texte ; un avocat est en italique). */
    private static function cellule(int $largeur, array $lignes): string
    {
        $contenu = '';
        foreach ($lignes ?: [''] as $ligne) {
            [$texte, $italique, $gras] = RoleDocument::ligneDeCellule($ligne);
            $contenu .= self::p($texte, ['italique' => $italique, 'gras' => $gras]);
        }

        return '<w:tc><w:tcPr><w:tcW w:w="'.$largeur.'" w:type="dxa"/></w:tcPr>'.$contenu.'</w:tc>';
    }

    /** Un paragraphe dont le texte contient des retours à la ligne (« \n ») : un saut de ligne Word à chaque retour. */
    private static function paragrapheMultiligne(string $texte, array $o): string
    {
        $taille = $o['t'] ?? 40;
        $rpr = '<w:rPr>'.(! empty($o['gras']) ? '<w:b/><w:bCs/>' : '').'<w:sz w:val="'.$taille.'"/><w:szCs w:val="'.$taille.'"/>'.(! empty($o['souligne']) ? '<w:u w:val="single"/>' : '').'</w:rPr>';
        $runs = '';
        foreach (explode("\n", $texte) as $i => $ligne) {
            $runs .= '<w:r>'.$rpr.($i ? '<w:br/>' : '').'<w:t xml:space="preserve">'.self::x($ligne).'</w:t></w:r>';
        }

        return '<w:p><w:pPr><w:spacing w:before="'.($o['avant'] ?? 0).'" w:after="'.($o['apres'] ?? 0).'"/><w:jc w:val="'.($o['jc'] ?? 'center').'"/></w:pPr>'.$runs.'</w:p>';
    }

    public static function docx(array $doc): string
    {
        $doc = RoleDocument::normaliser($doc);
        $colonnes = $doc['colonnes'];
        $total = array_sum(array_column($colonnes, 'twips'));

        // Papier à en-tête saisi en texte (gauche : ministère, cour, tribunal ; droite : République), la salle au centre, puis le titre : la chambre, et la date seule sur sa ligne.
        // Armoiries en petit (1,5 cm de haut) au-dessus de « République de Côte d'Ivoire ».
        [$la, $ha] = getimagesize(self::armoiries());
        $cy = 540000;
        $cx = (int) round($cy * $la / $ha);
        $armoiries = '<w:p><w:pPr><w:spacing w:before="0" w:after="60"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="7" name="Armoiries"/>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="7" name="armoiries.png"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdA"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
        $bloc = function (array $lignes, int $largeur, string $alignement = 'center', int $taille = 24, string $avant = '') {
            $x = $avant;
            foreach ($lignes as $l) {
                $x .= self::p($l, ['jc' => $alignement, 't' => $taille]);
            }

            return '<w:tc><w:tcPr><w:tcW w:w="'.$largeur.'" w:type="dxa"/></w:tcPr>'.$x.'</w:tc>';
        };
        $salle = '<w:tc><w:tcPr><w:tcW w:w="6200" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
            .($doc['salle'] ? '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:bCs/><w:sz w:val="36"/><w:szCs w:val="36"/><w:u w:val="single"/></w:rPr><w:t xml:space="preserve">'.self::x($doc['salle']).'</w:t></w:r></w:p>' : '<w:p/>').'</w:tc>';
        $corps = '<w:tbl><w:tblPr><w:tblW w:w="14743" w:type="dxa"/><w:tblInd w:w="-998" w:type="dxa"/><w:tblLayout w:type="fixed"/></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="4271"/><w:gridCol w:w="6200"/><w:gridCol w:w="4272"/></w:tblGrid><w:tr>'
            .$bloc($doc['entete_gauche'] ?? RoleDocument::enteteGauche(), 4271).$salle.$bloc($doc['entete_droite'] ?? RoleDocument::enteteDroite(), 4272, 'center', 24, $armoiries).'</w:tr></w:tbl>';
        $corps .= self::paragrapheMultiligne($doc['titre_chambre'] ?? $doc['titre'], ['gras' => true, 't' => 40, 'avant' => 240]);
        if (! empty($doc['titre_quand'])) {
            $corps .= self::paragrapheMultiligne($doc['titre_quand'], ['gras' => true, 't' => 40]);
        }
        // Rôle pénal : l'équipe d'audience (à remplir à la main sur le papier), avec l'heure à droite sur la première ligne.
        foreach ($doc['equipe'] as $i => [$libelle, $valeur]) {
            $tab = $i === 0 && ! empty($doc['heure_droite'])
                ? '<w:r><w:tab/></w:r><w:r><w:rPr><w:b/><w:bCs/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr><w:t xml:space="preserve">'.self::x($doc['heure_droite']).'</w:t></w:r>' : '';
            $corps .= '<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="13700"/></w:tabs><w:spacing w:before="'.($i === 0 ? 240 : 60).'" w:after="0"/></w:pPr><w:r><w:rPr><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr><w:t xml:space="preserve">'.self::x($libelle).'</w:t></w:r>'
                .($valeur ? '<w:r><w:rPr><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr><w:t xml:space="preserve"> '.self::x($valeur).'</w:t></w:r>' : '').$tab.'</w:p>';
        }
        $corps .= self::p('', ['apres' => 120]);

        $lignes = '';
        foreach ($doc['rubriques'] as $r) {
            $lignes .= '<w:tr><w:trPr><w:cantSplit/></w:trPr><w:tc><w:tcPr><w:tcW w:w="'.$total.'" w:type="dxa"/><w:gridSpan w:val="'.count($colonnes).'"/></w:tcPr>'
                .self::p($r['titre'], ['gras' => true, 't' => 32, 'jc' => 'center', 'keep' => true]).'</w:tc></w:tr>';
            $lignes .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            foreach ($colonnes as $i => $colonne) {
                $lignes .= '<w:tc><w:tcPr><w:tcW w:w="'.$colonne['twips'].'" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="F2F2F2"/><w:vAlign w:val="center"/></w:tcPr>'
                    .self::p($colonne['titre'], ['gras' => true, 't' => $i === 0 ? 20 : 24, 'jc' => 'center', 'keep' => true]).'</w:tc>';
            }
            $lignes .= '</w:tr>';
            foreach ($r['lignes'] as $l) {
                $lignes .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
                foreach ($l['cellules'] as $i => $cellule) {
                    $lignes .= self::cellule($colonnes[$i]['twips'], $cellule);
                }
                $lignes .= '</w:tr>';
            }
        }
        $bord = '';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $b) {
            $bord .= '<w:'.$b.' w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
        }
        $grille = '<w:tblGrid>'.implode('', array_map(fn ($w) => '<w:gridCol w:w="'.$w.'"/>', array_column($colonnes, 'twips'))).'</w:tblGrid>';
        $corps .= '<w:tbl><w:tblPr><w:tblW w:w="'.$total.'" w:type="dxa"/><w:tblInd w:w="-998" w:type="dxa"/><w:tblBorders>'.$bord.'</w:tblBorders>'
            .'<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="85" w:type="dxa"/><w:right w:w="85" w:type="dxa"/></w:tblCellMar></w:tblPr>'.$grille.$lignes.'</w:tbl>';

        $corps .= self::p('', ['apres' => 240]);
        $corps .= self::p(($doc['signe_prefixe'] ?? 'Fait à ').$doc['fait_a'].($doc['date_signature'] ? ', le '.$doc['date_signature'] : ''), ['retrait' => 9000, 'keep' => true]);
        $corps .= self::p($doc['signataire'], ['retrait' => 9000, 'gras' => true]);

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
        $en = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $document = $en.'<w:document '.$ns.'><w:body>'.$corps
            .'<w:sectPr><w:headerReference w:type="default" r:id="rIdH"/><w:footerReference w:type="default" r:id="rIdF"/>'
            .'<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="1417" w:right="851" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';

        // Filigrane : la balance de la justice, estompée et centrée derrière le texte (même réglage que le modèle Word : gain et niveau de noir).
        $nsh = $ns.' xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w10="urn:schemas-microsoft-com:office:word"';
        $entete = $en.'<w:hdr '.$nsh.'><w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr><w:r><w:pict>'
            .'<v:shapetype id="_x0000_t75" coordsize="21600,21600" o:spt="75" o:preferrelative="t" path="m@4@5l@4@11@9@11@9@5xe" filled="f" stroked="f"><v:stroke joinstyle="miter"/>'
            .'<v:formulas><v:f eqn="if lineDrawn pixelLineWidth 0"/><v:f eqn="sum @0 1 0"/><v:f eqn="sum 0 0 @1"/><v:f eqn="prod @2 1 2"/><v:f eqn="prod @3 21600 pixelWidth"/><v:f eqn="prod @3 21600 pixelHeight"/><v:f eqn="sum @0 0 1"/><v:f eqn="prod @6 1 2"/><v:f eqn="prod @7 21600 pixelWidth"/><v:f eqn="sum @8 21600 0"/><v:f eqn="prod @7 21600 pixelHeight"/><v:f eqn="sum @10 21600 0"/></v:formulas>'
            .'<v:path o:extrusionok="f" gradientshapeok="t" o:connecttype="rect"/><o:lock v:ext="edit" aspectratio="t"/></v:shapetype>'
            .'<v:shape id="WordPictureWatermark1" o:spid="_x0000_s2051" type="#_x0000_t75" style="position:absolute;margin-left:0;margin-top:0;width:453.35pt;height:279.3pt;z-index:-251654144;mso-position-horizontal:center;mso-position-horizontal-relative:margin;mso-position-vertical:center;mso-position-vertical-relative:margin" o:allowincell="f">'
            .'<v:imagedata r:id="rIdImg" o:title="SYMBOLE" gain="19661f" blacklevel="22938f"/><w10:wrap anchorx="margin" anchory="margin"/></v:shape>'
            .(! empty($doc['brouillon']) ? '<v:shapetype id="_x0000_t136" coordsize="21600,21600" o:spt="136" adj="10800" path="m@7,l@8,m@5,21600l@6,21600e"><v:formulas><v:f eqn="sum #0 0 10800"/><v:f eqn="prod #0 2 1"/><v:f eqn="sum 21600 0 @1"/><v:f eqn="sum 0 0 @2"/><v:f eqn="sum 21600 0 @3"/><v:f eqn="if @0 @3 0"/><v:f eqn="if @0 21600 @1"/><v:f eqn="if @0 0 @2"/><v:f eqn="if @0 @4 21600"/><v:f eqn="mid @5 @6"/><v:f eqn="mid @8 @5"/><v:f eqn="mid @7 @8"/><v:f eqn="mid @6 @7"/><v:f eqn="sum @6 0 @5"/></v:formulas><v:path textpathok="t" o:connecttype="custom" o:connectlocs="@9,0;@10,10800;@11,21600;@12,10800" o:connectangles="270,180,90,0"/><v:textpath on="t" fitshape="t"/><v:handles><v:h position="#0,bottomRight" xrange="6629,14971"/></v:handles><o:lock v:ext="edit" text="t" shapetype="t"/></v:shapetype>'
                .'<v:shape id="PowerPlusWaterMarkObject1" o:spid="_x0000_s2052" type="#_x0000_t136" style="position:absolute;margin-left:0;margin-top:0;width:470pt;height:110pt;rotation:315;z-index:-251653120;mso-position-horizontal:center;mso-position-horizontal-relative:margin;mso-position-vertical:center;mso-position-vertical-relative:margin" o:allowincell="f" fillcolor="#d8d8d8" stroked="f"><v:fill opacity=".5"/><v:textpath style="font-family:&quot;Calibri&quot;;font-size:1pt" string="SPECIMEN"/><w10:wrap anchorx="margin" anchory="margin"/></v:shape>' : '')
            .'</w:pict></w:r></w:p></w:hdr>';
        $r20 = '<w:r><w:rPr><w:sz w:val="20"/></w:rPr>';
        $pied = $en.'<w:ftr '.$ns.'><w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>'.$r20.'<w:t xml:space="preserve">Version '.(int) ($doc['version'] ?? 1).'</w:t></w:r></w:p><w:p><w:pPr><w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="000000"/><w:left w:val="single" w:sz="4" w:space="4" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:space="1" w:color="000000"/><w:right w:val="single" w:sz="4" w:space="4" w:color="000000"/></w:pBdr><w:ind w:left="14600" w:right="-300"/><w:jc w:val="center"/></w:pPr>'
            .$r20.'<w:fldChar w:fldCharType="begin"/></w:r>'.$r20.'<w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'.$r20.'<w:fldChar w:fldCharType="separate"/></w:r>'.$r20.'<w:t>1</w:t></w:r>'.$r20.'<w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
        $styles = $en.'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="Segoe UI" w:hAnsi="Segoe UI" w:cs="Segoe UI" w:eastAsia="Segoe UI"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:lang w:val="fr-FR"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style></w:styles>';
        $types = $en.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>';
        $rel = fn (string $contenu) => $en.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$contenu.'</Relationships>';
        $base = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $rels = $rel('<Relationship Id="rId1" Type="'.$base.'officeDocument" Target="word/document.xml"/>');
        $relsDoc = $rel('<Relationship Id="rIdS" Type="'.$base.'styles" Target="styles.xml"/><Relationship Id="rIdH" Type="'.$base.'header" Target="header1.xml"/><Relationship Id="rIdF" Type="'.$base.'footer" Target="footer1.xml"/><Relationship Id="rIdA" Type="'.$base.'image" Target="media/armoiries.png"/>');
        $relsEntete = $rel('<Relationship Id="rIdImg" Type="'.$base.'image" Target="media/balance.png"/>');

        $chemin = tempnam(sys_get_temp_dir(), 'role');
        $zip = new ZipArchive;
        if ($zip->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Le fichier Word n'a pas pu être créé.");
        }
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->addFromString('word/header1.xml', $entete);
        $zip->addFromString('word/footer1.xml', $pied);
        $zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
        $zip->addFromString('word/_rels/header1.xml.rels', $relsEntete);
        $zip->addFile(self::logo(), 'word/media/balance.png');
        $zip->addFile(self::armoiries(), 'word/media/armoiries.png');
        $zip->close();
        $octets = (string) file_get_contents($chemin);
        @unlink($chemin);

        return $octets;
    }
}
