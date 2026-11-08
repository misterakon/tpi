<?php

namespace Tests\Feature\Auth;

use App\Mail\CodeConnexion;
use App\Models\JournalConnexion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const MDP = 'Tribunal#Bingerville2026';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function utilisateur(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'greffier@tribunal.test',
            'password' => self::MDP,
            'actif' => true,
        ], $attributs));
    }

    private function seConnecter(string $email = 'greffier@tribunal.test', string $mdp = self::MDP)
    {
        return $this->postJson('/login', ['email' => $email, 'password' => $mdp]);
    }

    private function codeEnvoye(): string
    {
        $code = null;
        Mail::assertSent(CodeConnexion::class, function (CodeConnexion $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_les_pages_protegees_exigent_une_connexion(): void
    {
        $this->get('/accueil')->assertRedirect('/login');
        $this->get('/mon-compte')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_un_mauvais_mot_de_passe_est_refuse_sous_le_champ_mot_de_passe(): void
    {
        $this->utilisateur();

        $this->seConnecter(mdp: 'faux')->assertStatus(422)
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', 'Adresse e-mail ou mot de passe incorrect.');

        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'connexion_echec', 'succes' => false, 'motif' => 'mot de passe incorrect']);
        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_une_adresse_inconnue_ne_se_distingue_pas_d_un_mauvais_mot_de_passe(): void
    {
        $this->utilisateur();

        // Même message, même champ : on ne peut pas deviner quelles adresses ont un compte.
        $this->seConnecter('personne@tribunal.test')->assertStatus(422)
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', 'Adresse e-mail ou mot de passe incorrect.');

        $this->assertDatabaseHas('journal_connexions', ['motif' => 'compte inconnu', 'email_saisi' => 'personne@tribunal.test']);
        Mail::assertNothingSent();
    }

    public function test_un_compte_existant_au_mauvais_mot_de_passe_recoit_le_meme_message(): void
    {
        $u = $this->utilisateur();

        $inconnu = $this->seConnecter('personne@tribunal.test', 'faux')->assertStatus(422)->json('errors');
        $connu = $this->seConnecter($u->email, 'faux')->assertStatus(422)->json('errors');
        $this->assertSame($inconnu, $connu);
    }

    public function test_le_bon_mot_de_passe_envoie_un_code_sans_ouvrir_de_session(): void
    {
        $this->utilisateur();

        $this->seConnecter()->assertOk()->assertJson(['redirect' => route('two-factor.show')]);

        $this->assertGuest();
        Mail::assertSent(CodeConnexion::class, fn ($m) => $m->hasTo('greffier@tribunal.test') && strlen($m->code) === 6);
        $this->get('/accueil')->assertRedirect('/login');
    }

    public function test_le_code_n_est_jamais_stocke_en_clair(): void
    {
        $this->utilisateur();
        $this->seConnecter();
        $code = $this->codeEnvoye();

        $this->assertDatabaseMissing('codes_double_auth', ['empreinte' => $code]);
    }

    public function test_la_page_du_code_est_inaccessible_sans_etape_mot_de_passe(): void
    {
        $this->get('/two-factor')->assertRedirect('/login');
        $this->postJson('/two-factor', ['code' => '123456'])->assertOk()->assertJson(['redirect' => route('login')]);
        $this->assertGuest();
    }

    public function test_le_bon_code_ouvre_la_session_et_journalise(): void
    {
        $user = $this->utilisateur();
        $this->seConnecter();
        $code = $this->codeEnvoye();

        $this->postJson('/two-factor', ['code' => $code])->assertOk()->assertJson(['redirect' => route('dashboard')]);

        $this->assertAuthenticatedAs($user);
        $this->get('/accueil')->assertOk();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'code_valide', 'user_id' => $user->id]);
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'connexion_reussie', 'user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_un_code_ne_sert_qu_une_fois(): void
    {
        $this->utilisateur();
        $this->seConnecter();
        $code = $this->codeEnvoye();

        $this->postJson('/two-factor', ['code' => $code])->assertOk();
        $this->post('/logout');
        $this->postJson('/two-factor', ['code' => $code])->assertOk()->assertJson(['redirect' => route('login')]);
        $this->assertGuest();
    }

    public function test_un_mauvais_code_est_refuse_puis_le_compte_de_tentatives_s_epuise(): void
    {
        $this->utilisateur();
        $this->seConnecter();
        $bon = $this->codeEnvoye();
        $faux = $bon === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/two-factor', ['code' => $faux])->assertStatus(422)->assertJsonValidationErrors('code');
        }
        // 5e essai faux : le code est détruit, même le bon code ne passe plus.
        $this->postJson('/two-factor', ['code' => $faux])->assertStatus(422);
        $this->postJson('/two-factor', ['code' => $bon])->assertOk()->assertJson(['redirect' => route('login')]);

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'code_echec', 'motif' => 'trop_d_essais']);
    }

    public function test_un_code_expire_est_refuse(): void
    {
        $this->utilisateur();
        $this->seConnecter();
        $code = $this->codeEnvoye();

        $this->travel(11)->minutes();

        $this->postJson('/two-factor', ['code' => $code])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertGuest();
    }

    public function test_le_renvoi_du_code_est_limite_dans_le_temps(): void
    {
        $this->utilisateur();
        $this->seConnecter();

        $this->postJson('/two-factor/resend')->assertStatus(429);

        $this->travel(61)->seconds();
        $this->postJson('/two-factor/resend')->assertOk()->assertJsonStructure(['message', 'attente']);
        Mail::assertSent(CodeConnexion::class, 2);
    }

    public function test_le_compte_se_verrouille_apres_trop_d_echecs(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->utilisateur();

        for ($i = 0; $i < 5; $i++) {
            $this->seConnecter(mdp: 'faux');
        }

        $this->assertTrue($user->fresh()->estVerrouille());
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'compte_verrouille', 'user_id' => $user->id]);

        // Même avec le bon mot de passe, le compte reste verrouillé.
        $this->seConnecter()->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_les_essais_trop_rapproches_sont_freines(): void
    {
        $this->utilisateur();

        for ($i = 0; $i < 5; $i++) {
            $this->seConnecter(mdp: 'faux')->assertStatus(422);
        }

        $this->seConnecter(mdp: 'faux')->assertStatus(429)->assertJsonStructure(['message']);
    }

    public function test_un_compte_inactif_ne_peut_pas_se_connecter(): void
    {
        $this->utilisateur(['actif' => false]);

        $this->seConnecter()->assertStatus(422)->assertJsonPath('errors.email.0', "Ce compte est désactivé. Contactez l'administrateur.");
        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_la_deconnexion_est_journalisee(): void
    {
        $user = $this->utilisateur();
        $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'deconnexion', 'user_id' => $user->id]);
    }

    public function test_le_journal_ne_peut_etre_ni_modifie_ni_supprime(): void
    {
        $this->utilisateur();
        $this->seConnecter(mdp: 'faux');
        $ligne = JournalConnexion::firstOrFail();

        $this->expectException(LogicException::class);
        $ligne->update(['motif' => 'falsifié']);
    }

    public function test_le_journal_ne_peut_pas_etre_supprime(): void
    {
        $this->utilisateur();
        $this->seConnecter(mdp: 'faux');

        $this->expectException(LogicException::class);
        JournalConnexion::firstOrFail()->delete();
    }

    public function test_le_menu_affiche_les_entrees_de_test_et_la_marque_du_tribunal(): void
    {
        $this->actingAs($this->utilisateur())->withSession(['derniere_activite' => now()->timestamp]);

        $this->get('/accueil')->assertOk()
            ->assertSee('TPI')->assertSee('BINGERVILLE')
            ->assertSee('sidebar-link sidebar-title link-nav active', false)
            ->assertSee('Affaires')->assertSee('Rôles d&#039;audience', false)->assertSee('Modèles de décision');
    }

    public function test_les_entetes_de_securite_sont_presents(): void
    {
        $r = $this->get('/login');

        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_le_profil_et_la_protection_ne_peuvent_pas_etre_modifies_par_assignation_de_masse(): void
    {
        $user = $this->utilisateur();
        $user->fill(['profil_id' => 999, 'protege' => true, 'actif' => false, 'echecs_connexion' => 99])->save();

        $this->assertNotSame(999, $user->fresh()->profil_id);
        $this->assertFalse($user->fresh()->protege);
        $this->assertTrue($user->fresh()->actif);
        $this->assertSame(0, $user->fresh()->echecs_connexion);
    }
}
