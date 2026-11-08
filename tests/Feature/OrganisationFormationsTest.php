<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Formation;
use App\Models\Profil;
use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationFormationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function annee(): AnneeJudiciaire
    {
        return AnneeJudiciaire::create(['libelle' => '2026-2027', 'debut' => '2026-10-12', 'fin' => '2027-10-11', 'courante' => true]);
    }

    private function agent(string $nom = 'KONE', string $prenoms = 'Awa'): Agent
    {
        return Agent::create(['nom' => $nom, 'prenoms' => $prenoms, 'fonction' => 'juge', 'actif' => true]);
    }

    private function chambre(?AnneeJudiciaire $annee = null, string $libelle = '1re chambre civile'): Chambre
    {
        $section = Section::firstOrCreate(['code' => 'civile'], ['libelle' => 'Section civile', 'ordre' => 1]);

        return Chambre::create(['annee_judiciaire_id' => ($annee ?? AnneeJudiciaire::courante() ?? $this->annee())->id, 'section_id' => $section->id, 'libelle' => $libelle, 'ordre' => 1, 'actif' => true]);
    }

    /** Une formation (unique, sans nom) dans une chambre créée pour l'occasion. */
    private function formation(?AnneeJudiciaire $annee = null, string $libelle = '1re chambre civile', ?string $nom = null): Formation
    {
        return Formation::create(['chambre_id' => $this->chambre($annee, $libelle)->id, 'nom' => $nom, 'ordre' => 1, 'actif' => true]);
    }

    /* ---------- Accès ---------- */

    public function test_chaque_page_exige_son_droit(): void
    {
        $formation = $this->formation();
        $chambreId = $formation->chambre_id;
        $this->connecte($this->avecDroits([]));
        foreach (['/organisation/sections', '/organisation/chambres', "/organisation/chambres/$chambreId/composition", '/organisation/services'] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->connecte($this->avecDroits(['sections.voir']))->get('/organisation/sections')->assertOk();
        $this->get('/organisation/chambres')->assertForbidden();
        $this->connecte($this->avecDroits(['affectations.voir']))->get("/organisation/chambres/$chambreId/composition")->assertOk();
    }

    public function test_les_ecritures_sont_refusees_sans_droit(): void
    {
        $formation = $this->formation();
        $chambre = $formation->chambre;
        $agent = $this->agent();
        $this->connecte($this->avecDroits(['sections.voir', 'chambres.voir', 'formations.voir', 'affectations.voir', 'services.voir']));

        $this->post('/organisation/sections', ['libelle' => 'X'])->assertForbidden();
        $this->post('/organisation/chambres', ['annee_judiciaire_id' => $chambre->annee_judiciaire_id, 'section_id' => $chambre->section_id, 'libelle' => 'X'])->assertForbidden();
        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'nom' => 'X'])->assertForbidden();
        $this->post('/organisation/affectations', ['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'juge'])->assertForbidden();
        $this->post('/organisation/services', ['annee_judiciaire_id' => $chambre->annee_judiciaire_id, 'categorie' => 'service', 'libelle' => 'X'])->assertForbidden();
        $this->assertSame(0, Affectation::count());
        $this->assertSame(1, Chambre::count());
    }

    public function test_les_boutons_suivent_les_droits(): void
    {
        $formation = $this->formation();
        $this->connecte($this->avecDroits(['chambres.voir', 'affectations.voir']))->get('/organisation/chambres')->assertOk()
            ->assertDontSee('Ajouter')->assertDontSee('onclick="edit_chambre')->assertSee('Composition');

        $this->get("/organisation/chambres/{$formation->chambre_id}/composition")->assertOk()
            ->assertDontSee('onclick="add_form(', false)->assertDontSee('onclick="add_formation()"', false)->assertDontSee('onclick="edit_affectation', false);
    }

    public function test_le_menu_montre_les_nouvelles_pages_selon_les_droits(): void
    {
        $this->connecte($this->avecDroits(['sections.voir', 'services.voir']))->get('/accueil')->assertSee('Sections')->assertSee('Services et responsabilités')->assertDontSee('>Chambres<', false);
        $this->connecte($this->avecDroits(['chambres.voir']))->get('/accueil')->assertSee('>Chambres<', false);
    }

    /* ---------- Sections ---------- */

    public function test_creation_modification_suppression_d_une_section(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/sections', ['libelle' => 'Section pénale', 'ordre' => 3])->assertRedirect('/organisation/sections');
        $section = Section::where('libelle', 'Section pénale')->firstOrFail();
        $this->assertSame('section-penale', $section->code);

        $this->post('/organisation/sections', ['id_' => $section->id, 'libelle' => 'Section pénale', 'ordre' => 5])->assertSessionHasNoErrors();
        $this->assertSame(5, $section->fresh()->ordre);
        $this->post('/organisation/sections', ['libelle' => 'Section pénale'])->assertSessionHasErrors('libelle');

        $this->deleteJson("/organisation/sections/{$section->id}")->assertJson(['status' => true]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'section_supprime']);
    }

    public function test_une_section_avec_des_chambres_ne_peut_pas_etre_supprimee(): void
    {
        $chambre = $this->chambre();
        $this->connecte($this->admin())->deleteJson("/organisation/sections/{$chambre->section_id}")->assertJson(['status' => false]);
        $this->assertDatabaseCount('sections', 1);
    }

    /* ---------- Chambres ---------- */

    public function test_creation_d_une_chambre_cree_sa_formation_unique(): void
    {
        $annee = $this->annee();
        $section = Section::create(['code' => 'penale', 'libelle' => 'Section pénale', 'ordre' => 1]);
        $autre = AnneeJudiciaire::create(['libelle' => '2027-2028', 'debut' => '2027-10-12', 'fin' => '2028-10-11', 'courante' => false]);
        $this->connecte($this->admin());

        $this->post('/organisation/chambres', [
            'annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '2e chambre correctionnelle', 'intitule' => 'Flagrants délits',
            'groupe' => 'Les juridictions correctionnelles', 'attributions' => 'Infractions poursuivies en flagrant délit.', 'actif' => '1', 'ordre' => 2,
        ])->assertRedirect(route('organisation.chambres', ['annee' => $annee->id]));

        $chambre = Chambre::where('libelle', '2e chambre correctionnelle')->firstOrFail();
        $this->assertSame('Flagrants délits', $chambre->intitule);
        $this->assertCount(1, $chambre->formations);
        $this->assertNull($chambre->formations[0]->nom);
        $this->assertDatabaseHas('journal_audit', ['action' => 'chambre_cree']);

        $this->get('/organisation/chambres')->assertSee('2e chambre correctionnelle')->assertSee('Flagrants délits')->assertSee('Les juridictions correctionnelles');
        $this->get('/organisation/chambres?annee='.$autre->id)->assertDontSee('Flagrants délits');
    }

    public function test_une_chambre_en_double_est_refusee_pour_la_meme_annee(): void
    {
        $chambre = $this->chambre(null, '3e chambre civile');
        $this->connecte($this->admin());
        $donnees = ['annee_judiciaire_id' => $chambre->annee_judiciaire_id, 'section_id' => $chambre->section_id, 'libelle' => '3e chambre civile', 'actif' => '1'];

        $this->post('/organisation/chambres', $donnees)->assertSessionHasErrors('libelle');
        $this->post('/organisation/chambres', ['id_' => $chambre->id] + $donnees)->assertSessionHasNoErrors();   // se modifier soi-même reste possible
        $this->post('/organisation/chambres', ['libelle' => '4e chambre civile'] + $donnees)->assertSessionHasNoErrors();
        $this->assertSame(2, Chambre::count());
    }

    public function test_la_validation_des_chambres(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/chambres', ['annee_judiciaire_id' => 999, 'section_id' => 999, 'libelle' => ''])->assertSessionHasErrors(['annee_judiciaire_id', 'section_id', 'libelle']);
    }

    public function test_une_chambre_avec_composition_ne_peut_pas_etre_supprimee(): void
    {
        $formation = $this->formation();
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $this->agent()->id, 'role' => 'president']);
        $vide = $this->formation($formation->chambre->annee, 'Chambre vide');

        $this->connecte($this->admin());
        $this->deleteJson("/organisation/chambres/{$formation->chambre_id}")->assertJson(['status' => false]);
        $this->deleteJson("/organisation/chambres/{$vide->chambre_id}")->assertJson(['status' => true]);
        $this->assertDatabaseHas('chambres', ['id' => $formation->chambre_id]);
        $this->assertDatabaseMissing('formations', ['id' => $vide->id]);   // la formation unique disparaît avec sa chambre
    }

    /* ---------- Formations (facultatives, dans une chambre) ---------- */

    public function test_ajout_de_formations_nommees_dans_une_chambre(): void
    {
        $chambre = $this->chambre();
        $this->connecte($this->admin());

        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'nom' => 'Formation A', 'intitule' => 'Tribunal de simple police', 'attributions' => 'Contraventions', 'actif' => '1', 'ordre' => 1])
            ->assertRedirect("/organisation/chambres/{$chambre->id}/composition");
        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'nom' => 'Formation B', 'actif' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(['Formation A', 'Formation B'], $chambre->formations()->pluck('nom')->all());
        $this->assertDatabaseHas('journal_audit', ['action' => 'formation_cree']);
        $this->get("/organisation/chambres/{$chambre->id}/composition")->assertSee('Formation A – Tribunal de simple police')->assertSee('Contraventions');
    }

    public function test_une_formation_en_double_est_refusee_dans_la_meme_chambre(): void
    {
        $chambre = $this->chambre();
        $this->connecte($this->admin());

        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'nom' => 'Formation A', 'actif' => '1'])->assertSessionHasNoErrors();
        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'nom' => 'Formation A', 'actif' => '1'])->assertSessionHasErrors('nom');
        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'actif' => '1'])->assertSessionHasNoErrors();   // composition de la chambre (sans nom)
        $this->post('/organisation/formations', ['chambre_id' => $chambre->id, 'actif' => '1'])->assertSessionHasErrors('nom');   // une seule fois
        $this->assertSame(2, Formation::where('chambre_id', $chambre->id)->count());
    }

    public function test_une_formation_ne_change_pas_de_chambre_et_la_chambre_doit_exister(): void
    {
        $formation = $this->formation();
        $autre = $this->chambre($formation->chambre->annee, 'Autre chambre');
        $this->connecte($this->admin());

        $this->postJson('/organisation/formations', ['id_' => $formation->id, 'chambre_id' => $autre->id])->assertStatus(422);
        $this->post('/organisation/formations', ['chambre_id' => 99999, 'nom' => 'X'])->assertSessionHasErrors('chambre_id');
    }

    public function test_une_formation_avec_composition_ne_peut_pas_etre_supprimee(): void
    {
        $formation = $this->formation(null, 'Chambre A', 'Formation A');
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $this->agent()->id, 'role' => 'president']);
        $vide = Formation::create(['chambre_id' => $formation->chambre_id, 'nom' => 'Formation B', 'ordre' => 2, 'actif' => true]);

        $this->connecte($this->admin());
        $this->deleteJson("/organisation/formations/{$formation->id}")->assertJson(['status' => false]);
        $this->deleteJson("/organisation/formations/{$vide->id}")->assertJson(['status' => true]);
        $this->assertDatabaseHas('formations', ['id' => $formation->id]);
    }

    /* ---------- Composition (affectations) ---------- */

    public function test_ajout_et_modification_d_une_affectation(): void
    {
        $formation = $this->formation();
        $agent = $this->agent('N\'GUESSAN', 'Appa Brigitte');
        $this->connecte($this->admin());

        $this->post('/organisation/affectations', [
            'formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'president', 'debut' => '2026-10-12', 'chef_section' => '1',
        ])->assertRedirect("/organisation/chambres/{$formation->chambre_id}/composition");

        $a = Affectation::firstOrFail();
        $this->assertTrue($a->chef_section);
        $this->assertFalse($a->suppleant);

        $this->post('/organisation/affectations', ['id_' => $a->id, 'formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'president', 'suppleant' => '1', 'fin' => '2027-06-30', 'debut' => '2026-10-12'])->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->suppleant);
        $this->assertSame('2027-06-30', $a->fresh()->fin->format('Y-m-d'));
        $this->get("/organisation/chambres/{$formation->chambre_id}/composition")->assertSee('N&#039;GUESSAN', false)->assertSee('Suppléant');
    }

    public function test_une_affectation_ne_peut_pas_se_chevaucher_ni_avoir_une_fin_avant_son_debut(): void
    {
        $formation = $this->formation();
        $agent = $this->agent();
        $base = ['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'assesseur'];
        $this->connecte($this->admin());

        $this->post('/organisation/affectations', $base + ['debut' => '2026-10-12', 'fin' => '2026-12-31'])->assertSessionHasNoErrors();
        $this->post('/organisation/affectations', $base + ['debut' => '2026-12-01'])->assertSessionHasErrors('agent_id');          // recouvre
        $this->post('/organisation/affectations', $base + ['debut' => '2027-01-01'])->assertSessionHasNoErrors();                    // après la fin : possible
        $this->post('/organisation/affectations', ['role' => 'juge', 'debut' => '2026-05-01', 'fin' => '2026-04-01'] + $base)->assertSessionHasErrors('fin');
        $this->assertSame(2, Affectation::count());
    }

    public function test_le_role_et_la_personne_sont_controles(): void
    {
        $formation = $this->formation();
        $agent = $this->agent();
        $agent->delete();   // agent supprimé (masqué)
        $this->connecte($this->admin());

        $this->post('/organisation/affectations', ['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => 'juge'])->assertSessionHasErrors('agent_id');
        $this->post('/organisation/affectations', ['formation_id' => $formation->id, 'agent_id' => $this->agent('A', 'B')->id, 'role' => 'pirate'])->assertSessionHasErrors('role');
    }

    public function test_le_remplacement_termine_l_ancienne_affectation_et_cree_la_nouvelle(): void
    {
        $formation = $this->formation();
        $ancien = $this->agent('KIPRE', 'Aubin');
        $nouveau = $this->agent('BOKA', 'Carine');
        $a = Affectation::create(['formation_id' => $formation->id, 'agent_id' => $ancien->id, 'role' => 'assesseur', 'suppleant' => true, 'debut' => '2026-10-12']);
        $this->connecte($this->admin());

        $this->post("/organisation/affectations/{$a->id}/remplacer", ['nouvel_agent_id' => $nouveau->id, 'date_effet' => '2027-02-01'])->assertSessionHasNoErrors();

        $this->assertSame('2027-01-31', $a->fresh()->fin->format('Y-m-d'));
        $b = Affectation::where('agent_id', $nouveau->id)->firstOrFail();
        $this->assertSame('assesseur', $b->role);
        $this->assertTrue($b->suppleant);
        $this->assertSame('2027-02-01', $b->debut->format('Y-m-d'));
        $this->assertNull($b->fin);
        $this->assertStringContainsString('KIPRE', $b->remarque);
        $this->assertDatabaseHas('journal_audit', ['action' => 'affectation_remplacee']);
    }

    public function test_le_remplacement_refuse_les_cas_incoherents(): void
    {
        $formation = $this->formation();
        $ancien = $this->agent('KIPRE', 'Aubin');
        $a = Affectation::create(['formation_id' => $formation->id, 'agent_id' => $ancien->id, 'role' => 'juge', 'debut' => '2026-10-12', 'fin' => '2026-12-31']);
        $autre = $this->agent('BOKA', 'Carine');
        $this->connecte($this->admin());

        $this->post("/organisation/affectations/{$a->id}/remplacer", ['nouvel_agent_id' => $ancien->id, 'date_effet' => '2026-11-01'])->assertSessionHasErrors('nouvel_agent_id');
        $this->post("/organisation/affectations/{$a->id}/remplacer", ['nouvel_agent_id' => $autre->id, 'date_effet' => '2026-10-12'])->assertSessionHasErrors('date_effet');   // pas après le début
        $this->post("/organisation/affectations/{$a->id}/remplacer", ['nouvel_agent_id' => $autre->id, 'date_effet' => '2027-03-01'])->assertSessionHasErrors('date_effet');   // déjà terminée
        $this->assertSame(1, Affectation::count());
    }

    public function test_suppression_d_une_affectation(): void
    {
        $formation = $this->formation();
        $a = Affectation::create(['formation_id' => $formation->id, 'agent_id' => $this->agent()->id, 'role' => 'greffier']);

        $this->connecte($this->admin())->deleteJson("/organisation/affectations/{$a->id}")->assertJson(['status' => true]);
        $this->assertSame(0, Affectation::count());
        $this->assertDatabaseHas('journal_audit', ['action' => 'affectation_supprime']);
    }

    /* ---------- Services ---------- */

    public function test_creation_d_un_service_avec_ses_agents(): void
    {
        $annee = $this->annee();
        $a1 = $this->agent('COULIBALY', 'Dramane');
        $a2 = $this->agent('KOUTOU', 'Moillé');
        $this->connecte($this->admin());

        $this->post('/organisation/services', [
            'annee_judiciaire_id' => $annee->id, 'categorie' => 'service', 'libelle' => 'Service Statistiques', 'ordre' => 1, 'remarque' => 'Rattaché au greffe',
            'agents' => [['agent_id' => $a1->id, 'fonction' => '2e greffier en chef adjoint'], ['agent_id' => $a2->id, 'fonction' => '']],
        ])->assertRedirect();

        $service = Service::with('agents')->firstOrFail();
        $this->assertCount(2, $service->agents);
        $this->assertSame('2e greffier en chef adjoint', $service->agents[0]->fonction);
        $this->assertNull($service->agents[1]->fonction);
        $this->get('/organisation/services')->assertSee('Service Statistiques')->assertSee('COULIBALY Dramane')->assertSee('2e greffier en chef adjoint');
        $this->assertDatabaseHas('journal_audit', ['action' => 'service_cree', 'libelle' => 'Service Statistiques']);
    }

    public function test_la_modification_d_un_service_remplace_la_liste_des_agents(): void
    {
        $annee = $this->annee();
        $a1 = $this->agent('A', 'A');
        $a2 = $this->agent('B', 'B');
        $this->connecte($this->admin());
        $this->post('/organisation/services', ['annee_judiciaire_id' => $annee->id, 'categorie' => 'comite', 'libelle' => 'Comité', 'agents' => [['agent_id' => $a1->id]]]);
        $service = Service::firstOrFail();

        $this->post('/organisation/services', ['id_' => $service->id, 'annee_judiciaire_id' => $annee->id, 'categorie' => 'comite', 'libelle' => 'Comité de direction', 'agents' => [['agent_id' => $a2->id, 'fonction' => 'Secrétaire']]])->assertSessionHasNoErrors();

        $this->assertSame([$a2->id], $service->fresh()->agents->pluck('agent_id')->all());
        $this->assertSame('Comité de direction', $service->fresh()->libelle);
        $this->getJson("/organisation/services/{$service->id}")->assertOk()->assertJsonPath('agents.0.fonction', 'Secrétaire');
    }

    public function test_validation_des_services(): void
    {
        $annee = $this->annee();
        $this->connecte($this->admin());

        $this->post('/organisation/services', ['annee_judiciaire_id' => $annee->id, 'categorie' => 'inconnue', 'libelle' => ''])->assertSessionHasErrors(['categorie', 'libelle']);
        $this->post('/organisation/services', ['annee_judiciaire_id' => $annee->id, 'categorie' => 'service', 'libelle' => 'X', 'agents' => [['agent_id' => 99999]]])->assertSessionHasErrors('agents.0.agent_id');
        $this->assertSame(0, Service::count());
    }

    public function test_suppression_d_un_service(): void
    {
        $annee = $this->annee();
        $service = Service::create(['annee_judiciaire_id' => $annee->id, 'categorie' => 'service', 'libelle' => 'Service du casier judiciaire']);
        $service->agents()->create(['agent_id' => $this->agent()->id]);

        $this->connecte($this->admin())->deleteJson("/organisation/services/{$service->id}")->assertJson(['status' => true]);
        $this->assertSame(0, Service::count());
        $this->assertDatabaseCount('service_agents', 0);
    }

    /* ---------- Avec les données de l'ordonnance ---------- */

    public function test_les_pages_affichent_l_ordonnance_chargee(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->connecte($this->admin());

        $this->get('/organisation/sections')->assertSee('Section pénale')->assertSee('Section sociale');
        $this->get('/organisation/chambres')->assertSee('Tribunal criminel pour mineurs')->assertSee('Flagrants délits')->assertSee('Les juridictions correctionnelles');
        $this->get('/organisation/services')->assertSee('Service des recours civils')->assertSee('Juge taxateur');

        $chambre = Chambre::where('libelle', '1re chambre civile')->firstOrFail();
        $this->get("/organisation/chambres/{$chambre->id}/composition")->assertOk()->assertSee('KOUADIO Guedji Emile')->assertSee('Chef de section')
            ->assertSee('Salle d&#039;audience KONE Mamadou', false)->assertSee('Juges chargés de la mise en état');

        $deuxieme = Chambre::where('libelle', '2e chambre civile')->firstOrFail();
        $this->get("/organisation/chambres/{$deuxieme->id}/composition")->assertOk()->assertSee('Formation A')->assertSee('Formation B');
    }
}
