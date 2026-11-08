<?php

namespace Tests\Feature\Auth;

use App\Mail\CodeConnexion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerrouillageTest extends TestCase
{
    use RefreshDatabase;

    private const MDP = 'Secret-Verrou-2026';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create(['email' => 'greffier@tribunal.test', 'name' => 'Awa Koné', 'password' => self::MDP]);
    }

    private function connecte(): static
    {
        return $this->actingAs($this->user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    /** Connecté, puis inactif pendant 21 minutes : la première requête déclenche le verrouillage. */
    private function verrouille(string $page = '/utilisateurs/liste'): static
    {
        $this->connecte()->get('/accueil')->assertOk();
        $this->travel(21)->minutes();
        $this->get($page)->assertRedirect(route('verrouillage.show'));

        return $this;
    }

    /* ---------- Verrouillage ---------- */

    public function test_l_inactivite_verrouille_la_session_au_lieu_de_la_fermer(): void
    {
        $this->verrouille('/accueil');

        $this->assertAuthenticatedAs($this->user);   // toujours identifié : il suffira du mot de passe
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'session_verrouillee', 'user_id' => $this->user->id]);
        $this->assertDatabaseMissing('journal_connexions', ['evenement' => 'deconnexion_inactivite']);
    }

    public function test_une_session_verrouillee_bloque_toutes_les_pages_et_actions(): void
    {
        $this->verrouille('/accueil');

        $this->get('/utilisateurs/liste')->assertRedirect(route('verrouillage.show'));
        $this->get('/mon-compte')->assertRedirect(route('verrouillage.show'));
        $this->post('/utilisateurs/liste', ['name' => 'X', 'email' => 'x@tribunal.test', 'profil_id' => 1, 'actif' => 1])->assertRedirect(route('verrouillage.show'));
        $this->assertDatabaseMissing('users', ['email' => 'x@tribunal.test']);
    }

    public function test_un_appel_ajax_pendant_le_verrouillage_recoit_423(): void
    {
        $this->verrouille('/accueil');

        $this->getJson('/utilisateurs/liste')->assertStatus(423)->assertJson(['verrouille' => true, 'redirect' => route('verrouillage.show')]);
    }

    public function test_verrouillage_manuel_depuis_le_menu(): void
    {
        $this->connecte()->postJson('/verrouiller')->assertOk()->assertJson(['redirect' => route('verrouillage.show')]);

        $this->get('/accueil')->assertRedirect(route('verrouillage.show'));
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'session_verrouillee', 'motif' => "demandé par l'utilisateur"]);
    }

    public function test_le_battement_de_coeur_du_navigateur_garde_la_session_active(): void
    {
        $this->connecte();

        $this->travel(15)->minutes();
        $this->post('/session/actif')->assertNoContent();
        $this->travel(15)->minutes();   // 30 minutes au total, mais 15 depuis le dernier signe d'activité
        $this->get('/accueil')->assertOk();
    }

    public function test_le_battement_de_coeur_ne_deverrouille_pas(): void
    {
        $this->verrouille('/accueil');

        $this->postJson('/session/actif')->assertStatus(423);
    }

    public function test_les_pages_connectees_contiennent_le_minuteur_et_le_bouton_verrouiller(): void
    {
        $this->connecte()->get('/accueil')->assertOk()
            ->assertSee('tpi_derniere_activite')
            ->assertSee(route('verrouillage.lock'), false)
            ->assertSee('Verrouiller');
    }

    /* ---------- Écran de verrouillage ---------- */

    public function test_l_ecran_affiche_l_email_en_lecture_seule_et_demande_seulement_le_mot_de_passe(): void
    {
        $this->verrouille('/accueil');

        $html = $this->get('/verrouille')->assertOk()
            ->assertSee('Session verrouillée')->assertSee('greffier@tribunal.test')->assertSee('Awa Koné')
            ->assertSee('ea-verrou__email', false)->getContent();

        $this->assertStringNotContainsString('type="email"', $html);          // aucun champ e-mail à modifier
        $this->assertStringContainsString('type="password"', $html);
        $this->assertSame(1, substr_count($html, 'name="password"'));
        $this->assertStringNotContainsString('name="email"', $html);
    }

    public function test_l_ecran_de_verrouillage_n_a_pas_de_pastille_d_initiales_mais_l_en_tete_en_garde_une(): void
    {
        $this->verrouille('/accueil');
        $this->get('/verrouille')->assertOk()->assertDontSee('ea-verrou__avatar', false);

        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk();
        $this->get('/accueil')->assertOk()->assertSee('data:image/svg+xml', false);   // pastille des initiales dans l'en-tête
    }

    public function test_un_utilisateur_non_verrouille_est_renvoye_vers_l_accueil(): void
    {
        $this->connecte()->get('/verrouille')->assertRedirect(route('dashboard'));
        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk()->assertJson(['redirect' => route('dashboard')]);
    }

    /* ---------- Déverrouillage ---------- */

    public function test_le_bon_mot_de_passe_deverrouille_sans_code_par_email_et_ramene_a_la_page(): void
    {
        $this->verrouille('/utilisateurs/liste');

        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk()->assertJson(['redirect' => url('/utilisateurs/liste')]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('codes_double_auth', 0);
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'session_deverrouillee', 'user_id' => $this->user->id]);
        $this->get('/accueil')->assertOk();
    }

    public function test_aucun_code_double_auth_n_est_envoye_pendant_tout_le_parcours(): void
    {
        $this->verrouille('/accueil');
        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk();

        Mail::assertNotSent(CodeConnexion::class);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse_avec_le_nombre_d_essais_restants(): void
    {
        $this->verrouille('/accueil');

        $this->postJson('/verrouille', ['password' => 'faux'])->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Mot de passe incorrect. 4 essai(s) restant(s).');

        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deverrouillage_echec', 'user_id' => $this->user->id]);
        $this->get('/accueil')->assertRedirect(route('verrouillage.show'));   // toujours verrouillé
    }

    public function test_apres_cinq_essais_rates_la_session_est_fermee(): void
    {
        $this->verrouille('/accueil');

        foreach (range(1, 4) as $essai) {
            $this->postJson('/verrouille', ['password' => 'faux'])->assertStatus(422);
        }
        $this->postJson('/verrouille', ['password' => 'faux'])->assertOk()->assertJson(['redirect' => route('login')]);

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deconnexion', 'motif' => "trop d'essais de déverrouillage"]);
        // le bon mot de passe ne sert plus à rien : il faut une connexion complète
        $this->postJson('/verrouille', ['password' => self::MDP])->assertUnauthorized();
    }

    public function test_un_verrouillage_trop_long_exige_une_connexion_complete(): void
    {
        $this->verrouille('/accueil');

        $this->travel(121)->minutes();

        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk()->assertJson(['redirect' => route('login')]);
        $this->assertGuest();
    }

    public function test_un_verrouillage_trop_long_ferme_aussi_la_session_a_la_page_suivante(): void
    {
        $this->verrouille('/accueil');

        $this->travel(121)->minutes();
        $this->get('/accueil')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deconnexion_inactivite']);
    }

    public function test_un_compte_desactive_pendant_le_verrouillage_ne_peut_pas_se_deverrouiller(): void
    {
        $this->verrouille('/accueil');
        $this->user->forceFill(['actif' => false])->save();

        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk()->assertJson(['redirect' => route('login')]);

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deconnexion', 'motif' => 'compte indisponible']);
    }

    public function test_le_mot_de_passe_est_obligatoire(): void
    {
        $this->verrouille('/accueil');

        $this->postJson('/verrouille', ['password' => ''])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_se_deconnecter_reste_possible_pendant_le_verrouillage(): void
    {
        $this->verrouille('/accueil');

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deconnexion', 'user_id' => $this->user->id]);
    }

    /* ---------- Adresse de retour ---------- */

    public function test_une_adresse_de_retour_externe_est_ignoree(): void
    {
        $this->connecte()->postJson('/verrouiller', ['retour' => 'https://pirate.example/vol'])->assertOk();

        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk()->assertJson(['redirect' => route('dashboard')]);
    }

    public function test_une_adresse_de_retour_interne_est_acceptee_mais_pas_les_pages_d_authentification(): void
    {
        $this->connecte()->postJson('/verrouiller', ['retour' => url('/utilisateurs/profils')])->assertOk();
        $this->postJson('/verrouille', ['password' => self::MDP])->assertJson(['redirect' => url('/utilisateurs/profils')]);

        $this->postJson('/verrouiller', ['retour' => url('/login')])->assertOk();
        $this->postJson('/verrouille', ['password' => self::MDP])->assertJson(['redirect' => route('dashboard')]);
    }

    public function test_le_mot_de_passe_n_est_jamais_ecrit_dans_les_journaux(): void
    {
        $this->verrouille('/accueil');
        $this->postJson('/verrouille', ['password' => 'Essai-Secret-Faux-1'])->assertStatus(422);
        $this->postJson('/verrouille', ['password' => self::MDP])->assertOk();

        $contenu = json_encode(\DB::table('journal_connexions')->get());
        $this->assertStringNotContainsString('Essai-Secret-Faux-1', $contenu);
        $this->assertStringNotContainsString(self::MDP, $contenu);
    }
}
