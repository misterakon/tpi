<?php

namespace Tests\Feature;

use App\Mail\CodeConnexion;
use App\Mail\CompteCree;
use App\Models\Profil;
use App\Models\User;
use App\Support\Droits;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UtilisateursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function admin(array $attributs = []): User
    {
        return User::factory()->create(array_merge(['email' => 'admin@tribunal.test', 'name' => 'Admin Test'], $attributs));
    }

    /** Utilisateur dont le profil n'a que les droits donnés. */
    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Limité '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id, 'email' => uniqid().'@tribunal.test']);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function donneesCompte(array $plus = []): array
    {
        return array_merge([
            'name' => 'Awa Koné', 'email' => 'awa@tribunal.test', 'password' => '',
            'profil_id' => Profil::admin()->id, 'actif' => 1,
        ], $plus);
    }

    /* ---------- Accès ---------- */

    public function test_l_admin_voit_les_pages_utilisateurs_et_profils(): void
    {
        $this->connecte($this->admin())->get('/utilisateurs/liste')->assertOk()->assertSee('Liste des utilisateurs')->assertSee('Admin Test');
        $this->get('/utilisateurs/profils')->assertOk()->assertSee('Profils et droits');
    }

    public function test_les_anciennes_adresses_equipe_redirigent_vers_utilisateurs(): void
    {
        $this->get('/equipe/profils')->assertStatus(301)->assertRedirect(route('profils.index'));
        $this->get('/equipe/comptes')->assertStatus(301)->assertRedirect(route('utilisateur.compte'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_sans_droit_l_acces_est_refuse(): void
    {
        $user = $this->avecDroits([]);

        $this->connecte($user)->get('/utilisateurs/liste')->assertForbidden();
        $this->get('/utilisateurs/profils')->assertForbidden();
        $this->postJson('/utilisateurs/liste', $this->donneesCompte())->assertForbidden();
    }

    public function test_le_droit_voir_ne_donne_pas_le_droit_de_creer(): void
    {
        $user = $this->avecDroits(['comptes.voir']);

        $this->connecte($user)->get('/utilisateurs/liste')->assertOk();
        $this->post('/utilisateurs/liste', $this->donneesCompte())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'awa@tribunal.test']);
    }

    public function test_le_menu_depend_des_droits(): void
    {
        $this->connecte($this->avecDroits(['comptes.voir']))->get('/accueil')
            ->assertSee('Liste des utilisateurs')->assertDontSee('Profils et droits');
    }

    /* ---------- Profils ---------- */

    public function test_creer_un_profil_avec_ses_droits(): void
    {
        $this->connecte($this->admin())->post('/utilisateurs/profils', [
            'nom' => 'Greffier', 'description' => 'Tient les rôles', 'niveaux' => ['comptes' => 'lecture', 'audit' => 'lecture'],
        ])->assertRedirect('/utilisateurs/profils');

        $profil = Profil::where('nom', 'Greffier')->firstOrFail();
        $this->assertEqualsCanonicalizing(['comptes.voir', 'journaux.audit'], $profil->droits);
        $this->assertFalse($profil->systeme);
        $this->assertDatabaseHas('journal_audit', ['action' => 'profil_cree', 'libelle' => 'Greffier']);
    }

    public function test_on_peut_creer_autant_de_profils_que_l_on_veut(): void
    {
        $this->connecte($this->admin());
        foreach (range(1, 12) as $n) {
            $this->post('/utilisateurs/profils', ['nom' => "Profil $n"])->assertRedirect();
        }

        $this->assertSame(13, Profil::count());   // 12 + Admin
    }

    public function test_un_niveau_inconnu_est_refuse(): void
    {
        $this->connecte($this->admin())->postJson('/utilisateurs/profils', ['nom' => 'Pirate', 'niveaux' => ['comptes' => 'dieu']])
            ->assertStatus(422)->assertJsonValidationErrors('niveaux.comptes');
        $this->assertDatabaseMissing('profils', ['nom' => 'Pirate']);
    }

    public function test_un_niveau_qui_n_existe_pas_pour_ce_sous_menu_est_refuse(): void
    {
        // Le journal de connexion n'a que « Lecture » : pas de « Gestion ».
        $this->connecte($this->admin())->postJson('/utilisateurs/profils', ['nom' => 'Trop', 'niveaux' => ['connexion' => 'gestion']])
            ->assertStatus(422)->assertJsonValidationErrors('niveaux');
    }

    public function test_deux_profils_ne_peuvent_pas_porter_le_meme_nom(): void
    {
        $this->connecte($this->admin())->postJson('/utilisateurs/profils', ['nom' => 'Admin'])->assertStatus(422)->assertJsonValidationErrors('nom');
    }

    public function test_modifier_un_profil(): void
    {
        $profil = Profil::create(['nom' => 'Ancien', 'droits' => ['comptes.voir']]);

        $this->connecte($this->admin())->post('/utilisateurs/profils', ['id_' => $profil->id, 'nom' => 'Nouveau', 'niveaux' => ['profils' => 'lecture']])->assertRedirect();

        $this->assertSame('Nouveau', $profil->fresh()->nom);
        $this->assertSame(['profils.voir'], $profil->fresh()->droits);
    }

    public function test_le_profil_admin_ne_peut_etre_ni_modifie_ni_supprime(): void
    {
        $admin = Profil::admin();
        $this->connecte($this->admin());

        $this->post('/utilisateurs/profils', ['id_' => $admin->id, 'nom' => 'Autre'])->assertForbidden();
        $this->deleteJson('/utilisateurs/profils/'.$admin->id)->assertOk()->assertJson(['status' => false]);
        $this->assertSame('Admin', $admin->fresh()->nom);
    }

    public function test_un_profil_utilise_ne_peut_pas_etre_supprime_puis_oui_quand_il_est_libre(): void
    {
        $profil = Profil::create(['nom' => 'Temporaire', 'droits' => []]);
        $membre = User::factory()->create(['profil_id' => $profil->id]);
        $this->connecte($this->admin());

        $this->deleteJson('/utilisateurs/profils/'.$profil->id)->assertJson(['status' => false]);
        $this->assertDatabaseHas('profils', ['id' => $profil->id]);

        $membre->forceDelete();
        $this->deleteJson('/utilisateurs/profils/'.$profil->id)->assertJson(['status' => true]);
        $this->assertDatabaseMissing('profils', ['id' => $profil->id]);
    }

    public function test_les_droits_du_profil_s_appliquent_vraiment(): void
    {
        $user = $this->avecDroits(['comptes.voir']);

        $this->assertTrue($user->peut('comptes.voir'));
        $this->assertFalse($user->peut('comptes.supprimer'));
        $admin = $this->admin();
        $this->assertTrue($admin->peut('comptes.supprimer'));
        $this->assertTrue($admin->peut('un.droit.futur'));   // Admin : tous les droits, même ceux des modules à venir
    }

    public function test_un_compte_inactif_perd_tous_ses_droits(): void
    {
        $this->assertFalse($this->admin(['actif' => false])->peut('comptes.voir'));
    }

    /* ---------- Catalogue, niveaux, menu ---------- */

    public function test_les_droits_sont_organises_par_menu_sous_menu_et_niveaux(): void
    {
        foreach (Droits::catalogue() as $cle => $menu) {
            $this->assertNotEmpty($menu['pages'], $cle);
            foreach ($menu['pages'] as $page) {
                $this->assertArrayHasKey('lecture', $page['niveaux'], $page['libelle']);
                $precedent = [];
                foreach ($page['niveaux'] as $niveau => $droits) {
                    $this->assertArrayHasKey($niveau, Droits::NIVEAUX);
                    foreach ($droits as $droit) {
                        $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+$/', $droit);
                    }
                    // un niveau contient toujours le niveau précédent
                    $this->assertSame([], array_diff($precedent, $droits), $page['libelle'].' / '.$niveau);
                    $precedent = $droits;
                }
            }
        }
    }

    public function test_conversion_niveaux_vers_droits_et_retour(): void
    {
        $droits = Droits::depuisChoix(['comptes' => 'saisie', 'audit' => 'lecture', 'profils' => 'aucun'], []);
        $this->assertEqualsCanonicalizing(['comptes.voir', 'comptes.creer', 'comptes.modifier', 'journaux.audit'], $droits);

        $choix = Droits::versChoix($droits);
        $this->assertSame('saisie', $choix['niveaux']['comptes']);
        $this->assertSame('lecture', $choix['niveaux']['audit']);
        $this->assertSame('aucun', $choix['niveaux']['profils']);
    }

    public function test_un_droit_special_n_est_accorde_que_si_la_page_est_accessible(): void
    {
        $this->assertSame([], Droits::depuisChoix(['redaction' => 'aucun'], ['redaction.valider']));
        $this->assertContains('redaction.valider', Droits::depuisChoix(['redaction' => 'lecture'], ['redaction.valider']));
    }

    public function test_les_actes_judiciaires_s_annulent_ils_ne_se_suppriment_pas(): void
    {
        $this->assertContains('redaction.annuler', Droits::tous());
        $this->assertNotContains('redaction.supprimer', Droits::tous());
        $this->assertNotContains('delivrance.supprimer', Droits::tous());
    }

    public function test_le_niveau_saisie_ne_donne_pas_le_droit_de_supprimer(): void
    {
        $user = $this->avecDroits(Droits::depuisChoix(['comptes' => 'saisie']));

        $this->assertTrue($user->peut('comptes.creer'));
        $this->assertFalse($user->peut('comptes.supprimer'));
    }

    public function test_chaque_entree_du_menu_n_apparait_qu_avec_son_droit(): void
    {
        $html = $this->connecte($this->avecDroits(['roles.voir', 'stats_civil.voir']))->get('/accueil')->getContent();

        $this->assertStringContainsString('Rôles d&#039;audience', $html);
        $this->assertStringContainsString('Statistiques', $html);
        $this->assertStringNotContainsString('Enrôlement', $html);
        $this->assertStringNotContainsString('Modèles de décision', $html);
        $this->assertStringNotContainsString('Profils et droits', $html);
    }

    public function test_l_admin_voit_toutes_les_entrees_du_menu(): void
    {
        $html = $this->connecte($this->admin())->get('/accueil')->getContent();

        foreach (['Liste des affaires', 'Rôles d&#039;audience', 'Modèles de décision', 'Statistiques', 'Liste des utilisateurs', 'Profils et droits'] as $entree) {
            $this->assertStringContainsString($entree, $html, $entree);
        }
    }

    public function test_la_page_des_profils_liste_les_droits_par_menu(): void
    {
        $html = $this->connecte($this->admin())->get('/utilisateurs/profils')->assertOk()->getContent();

        foreach (['Affaires', 'Audiences', 'Décisions', 'Statistiques', 'Utilisateurs', 'Paramètres', 'Journal de connexion', 'Accès complet'] as $texte) {
            $this->assertStringContainsString($texte, $html, $texte);
        }
        $this->assertSame(count(Droits::pages()), substr_count($html, 'value="aucun"'));
    }

    public function test_un_profil_peut_recevoir_des_droits_de_plusieurs_menus(): void
    {
        $this->connecte($this->admin())->post('/utilisateurs/profils', ['nom' => 'Mixte', 'niveaux' => ['enrolement' => 'saisie', 'connexion' => 'lecture', 'stats_civil' => 'lecture'], 'speciales' => ['stats_civil.exporter']])->assertRedirect();

        $this->assertCount(6, Profil::where('nom', 'Mixte')->firstOrFail()->droits);
    }

    public function test_le_catalogue_est_coherent(): void
    {
        $this->assertNotEmpty(Droits::tous());
        $this->assertSame(Droits::tous(), array_values(array_unique(Droits::tous())));
    }

    /* ---------- Utilisateurs : création ---------- */

    public function test_creation_sans_mot_de_passe_envoie_un_lien_et_jamais_de_mot_de_passe(): void
    {
        $this->connecte($this->admin())->post('/utilisateurs/liste', $this->donneesCompte())->assertRedirect('/utilisateurs/liste');

        $user = User::where('email', 'awa@tribunal.test')->firstOrFail();
        $this->assertNull($user->mot_de_passe_modifie_le);
        Mail::assertSent(CompteCree::class, function (CompteCree $m) {
            return $m->hasTo('awa@tribunal.test') && str_contains((string) $m->lien, '/reset-password/');
        });
        $this->assertDatabaseHas('journal_audit', ['action' => 'compte_cree', 'libelle' => 'awa@tribunal.test']);
    }

    public function test_creation_avec_mot_de_passe_ne_met_pas_le_mot_de_passe_dans_le_courriel(): void
    {
        $this->connecte($this->admin())->post('/utilisateurs/liste', $this->donneesCompte(['password' => 'Secret-Awa-2026']))->assertRedirect();

        Mail::assertSent(CompteCree::class, function (CompteCree $m) {
            $html = $m->render();

            return $m->lien === null && ! str_contains($html, 'Secret-Awa-2026') && str_contains($html, 'communiqué par l');
        });
        $this->assertNotNull(User::where('email', 'awa@tribunal.test')->value('mot_de_passe_modifie_le'));
    }

    public function test_une_adresse_deja_utilisee_est_refusee(): void
    {
        $this->connecte($this->admin());

        $this->postJson('/utilisateurs/liste', $this->donneesCompte(['email' => 'admin@tribunal.test']))->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_un_mot_de_passe_trop_court_est_refuse(): void
    {
        $this->connecte($this->admin())->postJson('/utilisateurs/liste', $this->donneesCompte(['password' => 'court']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_seul_un_admin_peut_attribuer_le_profil_admin(): void
    {
        $gestionnaire = $this->avecDroits(['comptes.voir', 'comptes.creer']);

        $this->connecte($gestionnaire)->postJson('/utilisateurs/liste', $this->donneesCompte(['profil_id' => Profil::admin()->id]))
            ->assertStatus(422)->assertJsonValidationErrors('profil_id');
        $this->assertDatabaseMissing('users', ['email' => 'awa@tribunal.test']);
    }

    public function test_un_profil_inexistant_est_refuse(): void
    {
        $this->connecte($this->admin())->postJson('/utilisateurs/liste', $this->donneesCompte(['profil_id' => 9999]))->assertStatus(422);
    }

    /* ---------- Utilisateurs : protection ---------- */

    public function test_un_compte_protege_ne_peut_etre_ni_modifie_ni_supprime_par_un_autre(): void
    {
        $protege = $this->admin(['protege' => true, 'email' => 'principal@tribunal.test']);
        $autreAdmin = $this->admin(['email' => 'autre@tribunal.test']);

        $this->connecte($autreAdmin);
        $this->get('/utilisateurs/liste/'.$protege->id)->assertForbidden();
        $this->post('/utilisateurs/liste', $this->donneesCompte(['id_' => $protege->id, 'email' => 'principal@tribunal.test', 'name' => 'Piraté']))->assertForbidden();
        $this->deleteJson('/utilisateurs/liste/'.$protege->id)->assertJson(['status' => false]);
        $this->postJson('/utilisateurs/liste/'.$protege->id.'/lien')->assertForbidden();

        $this->assertNull($protege->fresh()->deleted_at);
        $this->assertNotSame('Piraté', $protege->fresh()->name);
    }

    public function test_les_boutons_d_un_compte_protege_sont_masques_pour_les_autres(): void
    {
        $this->admin(['protege' => true, 'email' => 'principal@tribunal.test', 'name' => 'Compte Principal']);
        $autre = $this->admin(['email' => 'autre@tribunal.test', 'name' => 'Autre Admin']);

        $html = $this->connecte($autre)->get('/utilisateurs/liste')->assertOk()->assertSee('Protégé')->getContent();

        $this->assertStringContainsString('Modifier Autre Admin', $html);
        $this->assertStringNotContainsString('Modifier Compte Principal', $html);
        $this->assertStringNotContainsString('Supprimer Compte Principal', $html);
    }

    public function test_on_ne_peut_pas_supprimer_son_propre_compte(): void
    {
        $moi = $this->admin();

        $this->connecte($moi)->deleteJson('/utilisateurs/liste/'.$moi->id)->assertJson(['status' => false]);
        $this->assertNull($moi->fresh()->deleted_at);
    }

    public function test_on_ne_peut_pas_changer_son_propre_profil_ni_se_desactiver(): void
    {
        $moi = $this->admin();
        $autre = Profil::create(['nom' => 'Autre', 'droits' => []]);

        $this->connecte($moi)->postJson('/utilisateurs/liste', $this->donneesCompte(['id_' => $moi->id, 'email' => $moi->email, 'profil_id' => $autre->id]))->assertStatus(422);
        $this->postJson('/utilisateurs/liste', $this->donneesCompte(['id_' => $moi->id, 'email' => $moi->email, 'profil_id' => $moi->profil_id, 'actif' => 0]))->assertStatus(422);

        $this->assertTrue($moi->fresh()->actif);
    }

    public function test_un_non_admin_ne_peut_pas_toucher_a_un_compte_admin(): void
    {
        $cible = $this->admin(['email' => 'cible@tribunal.test']);
        $gestionnaire = $this->avecDroits(['comptes.voir', 'comptes.modifier', 'comptes.supprimer', 'comptes.mot_de_passe']);

        $this->connecte($gestionnaire);
        $this->get('/utilisateurs/liste/'.$cible->id)->assertForbidden();
        $this->deleteJson('/utilisateurs/liste/'.$cible->id)->assertForbidden();
    }

    /* ---------- Utilisateurs : suppression, modification ---------- */

    public function test_supprimer_un_compte_libere_l_adresse_et_ferme_ses_sessions(): void
    {
        $cible = $this->admin(['email' => 'cible@tribunal.test']);
        DB::table('sessions')->insert(['id' => 'sess1', 'user_id' => $cible->id, 'payload' => '', 'last_activity' => time()]);
        $moi = User::factory()->create(['email' => 'moi@tribunal.test']);

        $this->connecte($moi)->deleteJson('/utilisateurs/liste/'.$cible->id)->assertJson(['status' => true]);

        $this->assertSoftDeleted('users', ['id' => $cible->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess1']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'compte_supprime', 'libelle' => 'cible@tribunal.test']);

        // l'adresse peut servir à un nouvel utilisateur
        $this->post('/utilisateurs/liste', $this->donneesCompte(['email' => 'cible@tribunal.test']))->assertRedirect('/utilisateurs/liste');
        $this->assertDatabaseHas('users', ['email' => 'cible@tribunal.test', 'deleted_at' => null]);
    }

    public function test_modifier_un_compte_avec_nouveau_mot_de_passe_ferme_ses_sessions(): void
    {
        $cible = $this->admin(['email' => 'cible@tribunal.test']);
        DB::table('sessions')->insert(['id' => 'sess2', 'user_id' => $cible->id, 'payload' => '', 'last_activity' => time()]);
        $moi = User::factory()->create(['email' => 'moi@tribunal.test']);

        $this->connecte($moi)->post('/utilisateurs/liste', $this->donneesCompte([
            'id_' => $cible->id, 'email' => 'cible@tribunal.test', 'name' => 'Nom changé', 'password' => 'Nouveau-Passe-2026',
        ]))->assertRedirect('/utilisateurs/liste');

        $this->assertSame('Nom changé', $cible->fresh()->name);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess2']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'compte_modifie_mot_de_passe_change']);
        $contenu = json_encode(DB::table('journal_audit')->get());
        $this->assertStringNotContainsString('Nouveau-Passe-2026', $contenu);
    }

    public function test_envoyer_un_lien_de_mot_de_passe(): void
    {
        Notification::fake();
        $cible = $this->admin(['email' => 'cible@tribunal.test']);
        $moi = User::factory()->create(['email' => 'moi@tribunal.test']);

        $this->connecte($moi)->postJson('/utilisateurs/liste/'.$cible->id.'/lien')->assertJson(['status' => true]);

        Notification::assertSentTo($cible, ResetPassword::class);
    }

    /* ---------- Courriels ---------- */

    public function test_les_courriels_ont_le_design_du_tribunal(): void
    {
        $code = (new CodeConnexion('Awa Koné', '123456', 10))->render();
        $this->assertStringContainsString('123456', $code);
        $this->assertStringContainsString('TPI', $code);
        $this->assertStringContainsString('#00794C', $code);
        $this->assertStringContainsString('#E8710A', $code);

        $compte = (new CompteCree('Awa Koné', 'awa@tribunal.test', 'Greffier', 'http://x/reset-password/abc', 60))->render();
        $this->assertStringContainsString('Choisir mon mot de passe', $compte);
        $this->assertStringContainsString('Greffier', $compte);
        $this->assertStringContainsString('Bienvenue', $compte);
    }
}
