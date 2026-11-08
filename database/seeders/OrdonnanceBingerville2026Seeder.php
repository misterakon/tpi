<?php

namespace Database\Seeders;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Models\Formation;
use App\Models\Salle;
use App\Models\Section;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Charge l'organisation du tribunal d'après l'ordonnance n°600/2026 du 12 octobre 2026 (année judiciaire 2026-2027) :
 | année, sections, salles et lieux, magistrats et greffiers, chambres (avec leurs attributions), formations (composition et
 | calendrier d'audiences), services, comités, délégations, fonctions particulières et bureaux locaux.
 | Les données sont dans database/seeders/data/ordonnance_600_2026.php.
 |
 | Peut être relancé sans danger : chaque élément est retrouvé avant d'être créé, rien n'est dupliqué
 | et les modifications déjà faites dans l'application (nom d'une salle, état d'un agent…) ne sont pas écrasées.
 |   php artisan db:seed --class=OrdonnanceBingerville2026Seeder
 */
class OrdonnanceBingerville2026Seeder extends Seeder
{
    /** @var array<string, array> */
    private array $donnees;

    /** @var array<string, Agent> */
    private array $agents = [];

    /** @var array<string, Salle> */
    private array $salles = [];

    public function run(): void
    {
        $this->donnees = require __DIR__.'/data/ordonnance_600_2026.php';

        DB::transaction(function () {
            $annee = $this->annee();
            $sections = $this->sections();
            $this->salles();
            $this->agents();
            $this->chambres($annee, $sections);
            $this->services($annee);
            $this->enrolement();
        });
    }

    private function annee(): AnneeJudiciaire
    {
        $a = $this->donnees['annee'];

        return AnneeJudiciaire::firstOrCreate(['libelle' => $a['libelle']], [
            'debut' => $a['debut'], 'fin' => $a['fin'], 'ordonnance' => $a['ordonnance'],
            'courante' => ! AnneeJudiciaire::where('courante', true)->exists(),
        ]);
    }

    /** @return array<string, Section> */
    private function sections(): array
    {
        $sections = [];
        foreach ($this->donnees['sections'] as $code => [$libelle, $ordre]) {
            $sections[$code] = Section::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'ordre' => $ordre]);
        }

        return $sections;
    }

    private function salles(): void
    {
        foreach ($this->donnees['salles'] as $cle => [$nom, $type, $emplacement]) {
            $this->salles[$cle] = Salle::firstOrCreate(['nom' => $nom], ['type' => $type, 'emplacement' => $emplacement, 'actif' => true]);
        }
    }

    private function agents(): void
    {
        foreach ($this->donnees['agents'] as $cle => [$nom, $prenoms, $sexe, $fonction]) {
            $agent = Agent::withTrashed()->firstOrCreate(['nom' => $nom, 'prenoms' => $prenoms], ['sexe' => $sexe, 'fonction' => $fonction, 'actif' => true]);
            if ($sexe && $agent->sexe === null) {
                $agent->forceFill(['sexe' => $sexe])->save();   // complète sans jamais écraser une valeur saisie
            }
            $this->agents[$cle] = $agent;
        }
    }

    /** @param array<string, Section> $sections */
    private function chambres(AnneeJudiciaire $annee, array $sections): void
    {
        foreach ($this->donnees['chambres'] as $ordre => $c) {
            $chambre = Chambre::firstOrCreate(
                ['annee_judiciaire_id' => $annee->id, 'libelle' => $c['libelle']],
                [
                    'section_id' => $sections[$c['section']]->id, 'groupe' => $c['groupe'], 'intitule' => $c['intitule'],
                    'attributions' => $c['attributions'], 'remarque' => $c['remarque'], 'ordre' => $ordre + 1, 'actif' => true,
                ],
            );

            foreach ($c['formations'] as $rang => $f) {
                $this->formation($annee, $chambre, $rang + 1, $f);
            }
        }
    }

    private function formation(AnneeJudiciaire $annee, Chambre $chambre, int $ordre, array $f): void
    {
        $formation = Formation::firstOrCreate(
            ['chambre_id' => $chambre->id, 'nom' => $f['nom']],
            ['intitule' => $f['intitule'], 'attributions' => $f['attributions'], 'ordre' => $ordre, 'actif' => true],
        );

        foreach ($f['equipe'] as $role => $cles) {
            $suppleant = str_ends_with($role, '_s');
            $role = $suppleant ? substr($role, 0, -2) : $role;
            foreach ($cles as $cle) {
                Affectation::firstOrCreate(
                    ['formation_id' => $formation->id, 'agent_id' => $this->agents[$cle]->id, 'role' => $role, 'suppleant' => $suppleant],
                    ['chef_section' => ($f['chef'] ?? null) === $cle, 'debut' => $annee->debut],
                );
            }
        }

        foreach ($f['creneaux'] as [$type, $jour, $heure, $frequence, $salle, $precision]) {
            Creneau::firstOrCreate(
                ['formation_id' => $formation->id, 'type' => $type, 'jour' => $jour, 'heure' => $heure],
                ['frequence' => $frequence, 'salle_id' => $salle ? $this->salles[$salle]->id : null, 'precision' => $precision],
            );
        }
    }

    /** Services, comités, délégations, fonctions particulières et bureaux locaux (avec leurs agents). */
    /** Article 7 : dates limites d'enrôlement (jour d'audience => jour limite et heure). Les règles déjà modifiées ne sont pas écrasées. */
    private function enrolement(): void
    {
        $jours = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5];
        foreach ($this->donnees['a_charger_plus_tard']['fin_d_enrolement'] as $audience => [$limite, $heure]) {
            DelaiEnrolement::firstOrCreate(
                ['jour_audience' => $jours[$audience]],
                ['jour_limite' => $jours[explode(' ', $limite)[0]], 'heure' => $heure],
            );
        }
    }

    private function services(AnneeJudiciaire $annee): void
    {
        foreach ($this->donnees['services'] as $ordre => [$categorie, $libelle, $agents, $remarque]) {
            $service = Service::firstOrCreate(
                ['annee_judiciaire_id' => $annee->id, 'categorie' => $categorie, 'libelle' => $libelle],
                ['ordre' => $ordre + 1, 'remarque' => $remarque],
            );

            if ($service->wasRecentlyCreated) {
                foreach ($agents as $i => [$cle, $fonction]) {
                    $service->agents()->create(['agent_id' => $this->agents[$cle]->id, 'fonction' => $fonction, 'ordre' => $i + 1]);
                }
            }
        }
    }
}
