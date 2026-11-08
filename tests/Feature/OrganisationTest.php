<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Profil;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationTest extends TestCase
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

    private function annee(array $a = []): array
    {
        return $a + ['libelle' => '2026-2027', 'debut' => '2026-09-01', 'fin' => '2027-08-31', 'ordonnance' => 'Ordonnance n°600/2026', 'courante' => '1'];
    }

    /* ---------- Accès ---------- */

    public function test_les_pages_exigent_le_droit_voir(): void
    {
        $this->connecte($this->avecDroits([]));
        foreach (['annees', 'salles', 'personnel'] as $cle) {
            $this->get("/organisation/$cle")->assertForbidden();
            $this->postJson("/organisation/$cle", [])->assertForbidden();
        }

        $this->connecte($this->avecDroits(['salles.voir']))->get('/organisation/salles')->assertOk();
        $this->get('/organisation/annees')->assertForbidden();
    }

    public function test_sans_droit_creer_ou_modifier_les_boutons_sont_masques_et_les_actions_refusees(): void
    {
        $salle = Salle::create(['nom' => 'Salle n°3', 'type' => 'audience', 'actif' => true]);

        $this->connecte($this->avecDroits(['salles.voir']))->get('/organisation/salles')->assertOk()->assertDontSee('Ajouter')->assertDontSee('onclick="edit_salle');
        $this->post('/organisation/salles', ['nom' => 'X', 'type' => 'cabinet'])->assertForbidden();
        $this->post('/organisation/salles', ['id_' => $salle->id, 'nom' => 'Y', 'type' => 'cabinet'])->assertForbidden();
        $this->deleteJson("/organisation/salles/{$salle->id}")->assertForbidden();
        $this->assertSame('Salle n°3', $salle->fresh()->nom);
    }

    public function test_chaque_page_a_sa_propre_fenetre_avec_interrupteurs(): void
    {
        $this->connecte($this->admin());
        $this->get('/organisation/annees')->assertSee('id="modal-annee"', false)->assertSee('el-switch', false)->assertDontSee('modal-dialog-scrollable', false);
        $this->get('/organisation/salles')->assertSee('id="modal-salle"', false)->assertSee('name="actif"', false);
        $this->get('/organisation/personnel')->assertSee('id="modal-agent"', false);
    }

    public function test_les_listes_deroulantes_ont_la_recherche_sur_toutes_les_pages(): void
    {
        $this->connecte($this->admin());
        foreach (['/organisation/personnel', '/utilisateurs/liste', '/journaux/connexions', '/parametres/reglages'] as $url) {
            $this->get($url)->assertOk()->assertSee('assets/js/select2-init.js', false)->assertSee('assets/js/select2/select2.full.min.js', false)->assertSee('assets/css/select2-theme.css', false);
        }
        $this->assertStringContainsString('select.form-select', file_get_contents(public_path('assets/js/select2-init.js')));
    }

    public function test_le_menu_organisation_suit_les_droits(): void
    {
        $this->connecte($this->avecDroits(['personnel.voir']))->get('/accueil')->assertSee('Magistrats et greffiers')->assertDontSee('Salles et lieux');
    }

    /* ---------- Année judiciaire ---------- */

    public function test_creation_d_une_annee_et_journal(): void
    {
        $this->connecte($this->admin())->post('/organisation/annees', $this->annee())->assertRedirect('/organisation/annees');

        $this->assertDatabaseHas('annees_judiciaires', ['libelle' => '2026-2027', 'courante' => true]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'annee_cree', 'libelle' => '2026-2027']);
        $this->get('/organisation/annees')->assertSee('2026-2027')->assertSee('Ordonnance n°600/2026')->assertSee('En cours');
    }

    public function test_une_seule_annee_est_courante(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/annees', $this->annee());
        $this->post('/organisation/annees', $this->annee(['libelle' => '2027-2028', 'debut' => '2027-09-01', 'fin' => '2028-08-31']));

        $this->assertSame(1, AnneeJudiciaire::where('courante', true)->count());
        $this->assertSame('2027-2028', AnneeJudiciaire::courante()->libelle);
    }

    public function test_les_dates_incoherentes_ou_qui_se_chevauchent_sont_refusees(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/annees', $this->annee());

        $this->post('/organisation/annees', $this->annee(['libelle' => 'X', 'debut' => '2027-01-01', 'fin' => '2026-01-01']))->assertSessionHasErrors('fin');
        $this->post('/organisation/annees', $this->annee(['libelle' => '2026-2028', 'debut' => '2027-06-01', 'fin' => '2028-05-31']))->assertSessionHasErrors('debut');
        $this->post('/organisation/annees', $this->annee(['libelle' => '2026-2027']))->assertSessionHasErrors('libelle');
        $this->assertSame(1, AnneeJudiciaire::count());
    }

    public function test_modifier_une_annee_ne_la_confond_pas_avec_elle_meme(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/annees', $this->annee());
        $id = AnneeJudiciaire::first()->id;

        $this->post('/organisation/annees', $this->annee(['id_' => $id, 'ordonnance' => 'Ordonnance n°601/2026']))->assertSessionHasNoErrors();
        $this->assertSame('Ordonnance n°601/2026', AnneeJudiciaire::first()->ordonnance);
        $this->assertDatabaseHas('journal_audit', ['action' => 'annee_modifie']);
    }

    public function test_l_annee_courante_ne_peut_pas_etre_supprimee(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/annees', $this->annee());
        $id = AnneeJudiciaire::first()->id;

        $this->deleteJson("/organisation/annees/$id")->assertOk()->assertJson(['status' => false]);
        $this->assertDatabaseCount('annees_judiciaires', 1);

        $this->post('/organisation/annees', $this->annee(['libelle' => '2027-2028', 'debut' => '2027-09-01', 'fin' => '2028-08-31']));
        $this->deleteJson("/organisation/annees/$id")->assertJson(['status' => true]);
    }

    /* ---------- Salles ---------- */

    public function test_creation_modification_suppression_d_une_salle(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/salles', ['nom' => 'Salle n°4', 'type' => 'audience', 'emplacement' => 'Rez-de-chaussée', 'actif' => '1'])->assertRedirect();
        $salle = Salle::first();
        $this->assertTrue($salle->actif);

        $this->post('/organisation/salles', ['id_' => $salle->id, 'nom' => 'Salle n°4', 'type' => 'audience'])->assertSessionHasNoErrors();
        $this->assertFalse($salle->fresh()->actif);   // case décochée = false

        $this->getJson("/organisation/salles/{$salle->id}")->assertOk()->assertJson(['nom' => 'Salle n°4', 'type' => 'audience']);
        $this->deleteJson("/organisation/salles/{$salle->id}")->assertJson(['status' => true]);
        $this->assertDatabaseCount('salles', 0);
        $this->assertDatabaseHas('journal_audit', ['action' => 'salle_supprime']);
    }

    public function test_salle_doublon_et_type_invalide_refuses(): void
    {
        $this->connecte($this->admin());
        Salle::create(['nom' => 'Salle n°3', 'type' => 'audience', 'actif' => true]);

        $this->post('/organisation/salles', ['nom' => 'Salle n°3', 'type' => 'audience'])->assertSessionHasErrors('nom');
        $this->post('/organisation/salles', ['nom' => 'Autre', 'type' => 'piscine'])->assertSessionHasErrors('type');
    }

    /* ---------- Personnel ---------- */

    public function test_creation_d_un_agent_avec_compte(): void
    {
        $compte = User::factory()->create();
        $this->connecte($this->admin())->post('/organisation/personnel', [
            'nom' => 'Kone', 'prenoms' => 'Awa', 'fonction' => 'greffier', 'matricule' => 'M-001', 'email' => 'awa@tribunal.ci', 'user_id' => $compte->id, 'actif' => '1',
        ])->assertRedirect();

        $agent = Agent::first();
        $this->assertSame('KONE Awa', $agent->nomComplet());
        $this->assertSame($compte->id, $agent->user_id);
        $this->get('/organisation/personnel')->assertSee('KONE Awa')->assertSee('Greffier');
    }

    public function test_un_compte_ne_peut_etre_relie_qu_a_un_seul_agent(): void
    {
        $compte = User::factory()->create();
        $this->connecte($this->admin());
        $base = ['nom' => 'A', 'prenoms' => 'B', 'fonction' => 'juge', 'user_id' => $compte->id];

        $this->post('/organisation/personnel', $base)->assertSessionHasNoErrors();
        $this->post('/organisation/personnel', ['nom' => 'C'] + $base)->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('agents', 1);
    }

    public function test_validation_des_champs_de_l_agent(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/personnel', ['nom' => '', 'prenoms' => '', 'fonction' => 'astronaute'])->assertSessionHasErrors(['nom', 'prenoms', 'fonction']);
        $this->post('/organisation/personnel', ['nom' => 'A', 'prenoms' => 'B', 'fonction' => 'juge', 'email' => 'pas-un-mail', 'telephone' => 'abc'])->assertSessionHasErrors(['email', 'telephone']);
        $this->post('/organisation/personnel', ['nom' => 'A', 'prenoms' => 'B', 'fonction' => 'juge', 'user_id' => 99999])->assertSessionHasErrors('user_id');
    }

    public function test_un_agent_supprime_est_conserve_pour_l_historique(): void
    {
        $this->connecte($this->admin());
        $this->post('/organisation/personnel', ['nom' => 'A', 'prenoms' => 'B', 'fonction' => 'juge']);
        $agent = Agent::first();

        $this->deleteJson("/organisation/personnel/{$agent->id}")->assertJson(['status' => true]);
        $this->assertSoftDeleted('agents', ['id' => $agent->id]);
    }

    public function test_les_pages_ne_montrent_aucune_valeur_non_echappee(): void
    {
        Salle::create(['nom' => '<script>alert(1)</script>', 'type' => 'autre', 'actif' => true]);

        $this->connecte($this->admin())->get('/organisation/salles')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
