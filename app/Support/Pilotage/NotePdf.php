<?php

namespace App\Support\Pilotage;

use App\Models\SynthesePilotage;
use App\Support\Roles\RoleDocument;
use App\Support\Roles\RoleExport;
use Dompdf\Dompdf;
use Dompdf\Options;

/*
 | La note d'analyse et de pilotage en PDF : papier à en-tête du tribunal, indicateurs, indice de santé, analyse et recommandations
 | (automatique ou rédigée par l'IA), graphiques dessinés côté serveur, tableau par chambre. Rien n'est calculé par le navigateur :
 | le document est le même quel que soit l'écran qui le demande.
 */
class NotePdf
{
    public const COULEURS_SUITES = ['renvoi' => '#F59A3B', 'delibere' => '#6B7BE8', 'juge' => '#00A36C', 'radie' => '#94A3B8', 'joint' => '#2AA7B2', 'desiste' => '#A66BC9'];

    /** @param  array<string, mixed>  $donnees  @param  array<string, mixed>  $analyse  */
    public static function generer(array $donnees, array $analyse, ?SynthesePilotage $ia, string $editeur): string
    {
        $note = $ia ? self::lireTexte($ia->contenu) : ['situation' => $analyse['situation'], 'points_forts' => $analyse['points_forts'], 'points_attention' => $analyse['points_attention'], 'recommandations' => $analyse['recommandations']];

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('audiences.pilotage.note_pdf', [
            'd' => $donnees, 'a' => $analyse, 'note' => $note, 'ia' => $ia, 'editeur' => $editeur, 'date' => now(),
            'courbe' => self::courbeMensuelle($donnees['mensuel']), 'jauge' => self::jauge($analyse['sante']['score'], $analyse['sante']['niveau']),
            'logo' => RoleExport::filigraneDonnees(), 'armoiries' => RoleExport::armoiriesDonnees(),
            'gauche' => RoleDocument::enteteGauche(), 'droite' => RoleDocument::enteteDroite(),
        ])->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    /** Le texte de l'IA (titres « ## », puces « - ») en parties. @return array{situation: string, points_forts: list<string>, points_attention: list<string>, recommandations: list<string>} */
    public static function lireTexte(string $texte): array
    {
        $parts = ['situation' => '', 'points_forts' => [], 'points_attention' => [], 'recommandations' => []];
        $cle = null;
        foreach (preg_split('/\R/u', $texte) ?: [] as $ligne) {
            $t = trim($ligne);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^##\s*Situation/iu', $t)) { $cle = 'situation'; } elseif (preg_match('/^##\s*Points forts/iu', $t)) { $cle = 'points_forts'; } elseif (preg_match('/^##\s*Points d/iu', $t)) { $cle = 'points_attention'; } elseif (preg_match('/^##\s*Recommandations/iu', $t)) { $cle = 'recommandations'; } elseif ($cle === 'situation') {
                $parts['situation'] .= ($parts['situation'] ? ' ' : '').$t;
            } elseif ($cle) {
                $parts[$cle][] = preg_replace('/^-\s*/u', '', $t);
            }
        }

        return $parts;
    }

    /** Les douze derniers mois : affaires enrôlées (vert) et terminées (orange), en colonnes. Dessin SVG, sans aucune bibliothèque de navigateur. */
    private static function courbeMensuelle(array $m): string
    {
        $l = 720; $h = 180; $g = 34; $b = 38; $hautUtile = $h - $g - $b; $n = count($m['mois']);
        $max = max(1, max(array_merge($m['entrees'], $m['sorties'])));
        $pas = (int) ceil($max / 5);
        $max = $pas * 5;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$l.'" height="'.$h.'" viewBox="0 0 '.$l.' '.$h.'">';
        for ($i = 0; $i <= 5; $i++) {
            $y = $g + $hautUtile - $hautUtile * $i / 5;
            $svg .= '<line x1="36" y1="'.$y.'" x2="'.($l - 6).'" y2="'.$y.'" stroke="#E3E9F0" stroke-width="1"/><text x="30" y="'.($y + 3).'" font-size="9" fill="#4A5670" text-anchor="end" font-family="DejaVu Sans">'.($pas * $i).'</text>';
        }
        $zone = ($l - 44) / $n;
        for ($i = 0; $i < $n; $i++) {
            $x = 40 + $zone * $i;
            foreach ([[$m['entrees'][$i], '#00794C', 0], [$m['sorties'][$i], '#E8710A', 1]] as [$v, $c, $k]) {
                $hv = $hautUtile * $v / $max;
                $svg .= '<rect x="'.($x + $zone * 0.14 + $k * $zone * 0.36).'" y="'.($g + $hautUtile - $hv).'" width="'.($zone * 0.34).'" height="'.max($hv, 0.01).'" fill="'.$c.'"/>';
            }
            $svg .= '<text x="'.($x + $zone / 2).'" y="'.($h - 20).'" font-size="8" fill="#4A5670" text-anchor="middle" font-family="DejaVu Sans">'.htmlspecialchars(mb_substr($m['mois'][$i], 0, 4)).'</text>'
                .'<text x="'.($x + $zone / 2).'" y="'.($h - 9).'" font-size="8" fill="#4A5670" text-anchor="middle" font-family="DejaVu Sans">'.htmlspecialchars(mb_substr($m['mois'][$i], -4)).'</text>';
        }
        $svg .= '<rect x="40" y="6" width="10" height="10" fill="#00794C"/><text x="55" y="15" font-size="10" fill="#1F2A44" font-family="DejaVu Sans">Affaires enrôlées</text>'
            .'<rect x="160" y="6" width="10" height="10" fill="#E8710A"/><text x="175" y="15" font-size="10" fill="#1F2A44" font-family="DejaVu Sans">Affaires terminées</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** L'indice de santé : un arc de cercle gradué et la note au centre. */
    private static function jauge(int $score, string $niveau): string
    {
        $couleur = ['bon' => '#00794C', 'vigilance' => '#E8710A', 'alerte' => '#B42318'][$niveau] ?? '#00794C';
        $cx = 80; $cy = 80; $r = 62;
        $point = fn (float $deg) => [$cx + $r * cos(deg2rad($deg)), $cy + $r * sin(deg2rad($deg))];
        $debut = 145; $etendue = 250;                         // du bas gauche au bas droit, par le haut
        $fin = $debut + $etendue * max(0, min(100, $score)) / 100;
        [$x1, $y1] = $point($debut); [$x2, $y2] = $point($debut + $etendue); [$x3, $y3] = $point($fin);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="150" viewBox="0 0 160 150">'
            .'<path d="M'.$x1.' '.$y1.' A'.$r.' '.$r.' 0 1 1 '.$x2.' '.$y2.'" fill="none" stroke="#E5EDE8" stroke-width="13" stroke-linecap="round"/>';
        if ($score > 0) {
            $svg .= '<path d="M'.$x1.' '.$y1.' A'.$r.' '.$r.' 0 '.($fin - $debut > 180 ? 1 : 0).' 1 '.$x3.' '.$y3.'" fill="none" stroke="'.$couleur.'" stroke-width="13" stroke-linecap="round"/>';
        }
        $svg .= '<text x="80" y="88" font-size="38" font-weight="bold" fill="'.$couleur.'" text-anchor="middle" font-family="DejaVu Sans">'.$score.'</text>'
            .'<text x="80" y="106" font-size="11" fill="#4A5670" text-anchor="middle" font-family="DejaVu Sans">sur 100</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
