<?php

namespace App\Support\Redaction;

use App\Models\BlocTexte;
use App\Models\ModeleDecisionVersion;

/** Le texte d'une décision : les trois parties d'une version de modèle remplies avec les valeurs saisies (HTML protégé). */
class RenduDecision
{
    public const PARTIES = ['entete' => 'En-tête (timbre)', 'qualite' => 'Qualité', 'factum' => 'Factum'];

    /**
     * @param  array<string, mixed>  $valeurs  code => valeur, à plat
     * @param  array<string, string>  $retouches  partie => texte retouché à la main (il remplace le texte généré)
     * @return array{entete: string, qualite: string, factum: string, manquantes: list<string>, generes: array<string, string>, retouchees: list<string>}
     */
    public static function rendre(ModeleDecisionVersion $version, array $valeurs, array $libelles = [], array $retouches = []): array
    {
        $ensemble = ChampsDecision::enEnsembles($valeurs);
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();

        $sortie = ['entete' => '', 'qualite' => '', 'factum' => '', 'manquantes' => [], 'generes' => [], 'retouchees' => []];
        foreach (array_keys(self::PARTIES) as $partie) {
            $r = Gabarit::rendre((string) $version->$partie, $ensemble, $blocs, true, $libelles);
            $sortie['generes'][$partie] = $r['texte'];

            if (trim((string) ($retouches[$partie] ?? '')) !== '') {
                // Une partie retouchée à la main ne suit plus le formulaire : elle ne compte pas dans ce qui reste à compléter.
                $sortie[$partie] = $retouches[$partie];
                $sortie['retouchees'][] = $partie;

                continue;
            }
            $sortie[$partie] = $r['texte'];
            $sortie['manquantes'] = array_merge($sortie['manquantes'], $r['manquantes']);
        }
        $sortie['manquantes'] = array_values(array_unique($sortie['manquantes']));

        return $sortie;
    }
}
