<?php

namespace Database\Seeders;

use App\Models\CaractereDecision;
use App\Models\DelaiProcedure;
use App\Models\IssueAudience;
use App\Models\Matiere;
use App\Models\ModeComparution;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\NaturePartie;
use App\Models\ObjetLitige;
use App\Models\QualiteProcedurale;
use App\Models\TexteLoi;
use App\Models\TypeAudience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Charge les référentiels judiciaires de départ (types d'audience, caractères, issues, modes, qualités, natures, matières,
 | objets du litige, délais de procédure, textes de loi) d'après le cahier des charges et l'ordonnance n°600/2026.
 | Peut être relancé sans danger : chaque élément est retrouvé avant d'être créé, et ce qui a été modifié dans l'application
 | n'est pas écrasé.
 |   php artisan db:seed --class=ReferentielsJudiciairesSeeder
 */
class ReferentielsJudiciairesSeeder extends Seeder
{
    private array $d;

    public function run(): void
    {
        $this->d = require database_path('seeders/data/referentiels_judiciaires.php');

        DB::transaction(function () {
            $this->simples();
            $this->natures();
            $this->delais();
            $this->objets();
            $this->textes();
        });
    }

    private function simples(): void
    {
        foreach ($this->d['types_audience'] as $libelle) {
            TypeAudience::firstOrCreate(['libelle' => $libelle], ['actif' => true]);
        }
        foreach ($this->d['caracteres_decision'] as $i => [$libelle, $aValider]) {
            CaractereDecision::firstOrCreate(['libelle' => $libelle], ['ordre' => $i + 1, 'actif' => true, 'a_valider' => $aValider]);
        }
        foreach ($this->d['issues_audience'] as $i => [$libelle, $demandeDate]) {
            IssueAudience::firstOrCreate(['libelle' => $libelle], ['ordre' => $i + 1, 'actif' => true, 'demande_date' => $demandeDate]);
        }
        foreach ($this->d['modes_comparution'] as $i => $libelle) {
            ModeComparution::firstOrCreate(['libelle' => $libelle], ['ordre' => $i + 1, 'actif' => true]);
        }
        foreach ($this->d['qualites_procedurales'] as $i => $libelle) {
            QualiteProcedurale::firstOrCreate(['libelle' => $libelle], ['ordre' => $i + 1, 'actif' => true]);
        }
        foreach ($this->d['natures_partie'] as $i => $libelle) {
            NaturePartie::firstOrCreate(['libelle' => $libelle], ['ordre' => $i + 1, 'actif' => true]);
        }
        foreach ($this->d['modes_saisine'] as [$libelle, $registre, $aValider]) {
            ModeSaisine::firstOrCreate(['libelle' => $libelle, 'registre' => $registre], ['actif' => true, 'a_valider' => $aValider]);
        }
        foreach ($this->d['matieres'] as $libelle) {
            Matiere::firstOrCreate(['libelle' => $libelle], ['actif' => true]);
        }
    }

    private function natures(): void
    {
        foreach ($this->d['natures_affaire'] as [$libelle, $registre, $exclue]) {
            NatureAffaire::firstOrCreate(['libelle' => $libelle, 'registre' => $registre], ['actif' => true, 'exclue_enrolement' => $exclue]);
        }
    }

    private function delais(): void
    {
        foreach ($this->d['delais_procedure'] as [$libelle, $registre, $nature, $valeur, $unite, $renvois, $depart]) {
            DelaiProcedure::firstOrCreate(['libelle' => $libelle], [
                'registre' => $registre, 'nature_affaire_id' => $nature ? NatureAffaire::where('libelle', $nature)->value('id') : null,
                'valeur' => $valeur, 'unite' => $unite, 'renvois_max' => $renvois, 'point_depart' => $depart, 'actif' => true, 'a_valider' => true,
            ]);
        }
    }

    private function objets(): void
    {
        foreach (array_merge($this->d['objets_litige'], require database_path('seeders/data/objets_penaux.php')) as [$chambre, $matiere, $nature, $objets]) {
            $matiereId = Matiere::where('libelle', $matiere)->value('id');
            $natureId = $nature ? NatureAffaire::where('libelle', $nature)->value('id') : null;
            foreach ($objets as $libelle) {
                ObjetLitige::firstOrCreate(['libelle' => $libelle, 'chambre_libelle' => $chambre], ['matiere_id' => $matiereId, 'nature_affaire_id' => $natureId, 'actif' => true]);
            }
        }
    }

    private function textes(): void
    {
        foreach ($this->d['textes_loi'] as $i => [$texte, $article, $objet]) {
            TexteLoi::firstOrCreate(['texte' => $texte, 'article' => $article], ['objet' => $objet, 'ordre' => $i + 1, 'actif' => true]);
        }
    }
}
