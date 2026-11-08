<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Formation;
use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompteAgentTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    public function test_mon_compte_montre_les_fonctions_en_cours_de_l_agent_relie(): void
    {
        $annee = AnneeJudiciaire::create(['libelle' => '2026-2027', 'debut' => '2026-10-12', 'fin' => '2027-10-11', 'courante' => true]);
        $section = Section::create(['code' => 'civile', 'libelle' => 'Civile', 'ordre' => 1]);
        $chambre = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '2e chambre civile', 'ordre' => 1, 'actif' => true]);
        $formation = Formation::create(['chambre_id' => $chambre->id, 'nom' => 'Formation A', 'ordre' => 1, 'actif' => true]);

        $user = User::factory()->create();
        $agent = Agent::create(['nom' => 'KONE', 'prenoms' => 'Awa', 'fonction' => 'juge', 'actif' => true, 'user_id' => $user->id]);
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'president', 'debut' => '2026-10-12']);
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'assesseur', 'debut' => '2025-01-01', 'fin' => '2025-12-31']);   // terminée
        $service = Service::create(['annee_judiciaire_id' => $annee->id, 'categorie' => 'comite', 'libelle' => 'Comité de direction', 'ordre' => 1]);
        $service->agents()->create(['agent_id' => $agent->id, 'fonction' => 'Membre', 'ordre' => 1]);

        $this->assertTrue($user->agent->is($agent));
        $this->assertCount(1, $agent->affectationsEnCours());

        $this->connecte($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Mes fonctions au tribunal')->assertSee('2e chambre civile')->assertSee('Formation A')->assertSee('Comité de direction')
            ->assertDontSee('Assesseur ·', false);
    }

    public function test_un_compte_sans_agent_est_signale(): void
    {
        $user = User::factory()->create();
        $this->connecte($user)->get(route('profile.edit'))->assertOk()->assertSee("pas encore relié");

        $admin = User::factory()->create();
        $this->connecte($admin)->get(route('utilisateur.compte'))->assertOk()->assertSee('Aucun agent relié');

        Agent::create(['nom' => 'DIALLO', 'prenoms' => 'Moussa', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $user->id]);
        $this->connecte($admin)->get(route('utilisateur.compte'))->assertOk()->assertSee('DIALLO Moussa');
    }
}
