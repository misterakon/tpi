<?php

namespace Tests\Feature\Auth;

use App\Models\CodeSecours;
use App\Models\JournalAudit;
use App\Models\JournalConnexion;
use App\Models\Profil;
use App\Models\User;
use App\Services\CodesSecours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Lot G : codes de secours de la connexion (huit codes à usage unique, empreintes seulement). */
class CodesSecoursTest extends TestCase
{
    use RefreshDatabase;

    private const MDP = 'Tribunal#Bingerville2026';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function utilisateur(array $attributs = []): User
    {
        return User::factory()->create(array_merge(['email' => 'greffier@tribunal.test', 'password' => self::MDP, 'actif' => true], $attributs));
    }

    public function test_huit_codes_distincts_dont_seule_l_empreinte_est_conservee(): void
    {
        $u = $this->utilisateur();
        $codes = app(CodesSecours::class)->generer($u);

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^\d{8}$/', $c, 'Huit chiffres, sans tiret.');
        }
        $stockes = CodeSecours::where('user_id', $u->id)->pluck('empreinte')->all();
        $this->assertCount(8, $stockes);
        foreach ($codes as $c) {
            $this->assertNotContains($c, $stockes, 'Le code en clair n\'est jamais conservé.');
        }
        $this->assertSame(['restants' => 8, 'total' => 8], array_intersect_key(app(CodesSecours::class)->etat($u), ['restants' => 1, 'total' => 1]));
    }

    public function test_un_code_ne_sert_qu_une_fois_et_seulement_a_son_proprietaire(): void
    {
        $service = app(CodesSecours::class);
        $u = $this->utilisateur();
        $autre = $this->utilisateur(['email' => 'autre@tribunal.test']);
        $codes = $service->generer($u);
        $service->generer($autre);

        $this->assertFalse($service->utiliser($autre, $codes[0]), 'Le code d\'un autre compte est refusé.');
        $this->assertFalse($service->utiliser($u, '00000000'));
        $this->assertTrue($service->utiliser($u, substr($codes[0], 0, 3).' '.substr($codes[0], 3)), 'Les espaces n\'ont pas d\'importance.');
        $this->assertFalse($service->utiliser($u, $codes[0]), 'Un code utilisé est brûlé.');
        $this->assertSame(7, $service->etat($u)['restants']);
    }

    public function test_generer_de_nouveaux_codes_annule_les_anciens_et_revoquer_les_annule_tous(): void
    {
        $service = app(CodesSecours::class);
        $u = $this->utilisateur();
        $anciens = $service->generer($u);
        $nouveaux = $service->generer($u);

        $this->assertFalse($service->utiliser($u, $anciens[1]));
        $this->assertTrue($service->utiliser($u, $nouveaux[1]));

        $this->assertSame(8, $service->revoquer($u), 'Tous les codes, même celui déjà utilisé, sont effacés.');
        $this->assertFalse($service->utiliser($u, $nouveaux[2]));
        $this->assertSame(0, $service->etat($u)['total']);
    }

    public function test_on_se_connecte_avec_un_code_de_secours_quand_le_code_par_email_n_arrive_pas(): void
    {
        $u = $this->utilisateur();
        $codes = app(CodesSecours::class)->generer($u);

        $this->postJson('/login', ['email' => $u->email, 'password' => self::MDP])->assertOk();
        $this->assertGuest();

        $this->postJson(route('two-factor.secours'), ['code_secours' => 'NOPE-NOPE'])->assertStatus(422)->assertJsonValidationErrors('code_secours');
        $this->assertGuest();
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'code_secours_echec', 'succes' => false]);

        $this->postJson(route('two-factor.secours'), ['code_secours' => $codes[0]])->assertOk()->assertJsonPath('redirect', route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'code_secours_utilise', 'succes' => true, 'user_id' => $u->id]);
        $this->assertSame(7, app(CodesSecours::class)->etat($u)['restants']);
        $this->assertSame(7, JournalConnexion::where('evenement', 'code_secours_utilise')->first()->details['restants']);

        // Le même code ne rouvre pas une session.
        $this->post('/logout');
        $this->postJson('/login', ['email' => $u->email, 'password' => self::MDP])->assertOk();
        $this->postJson(route('two-factor.secours'), ['code_secours' => $codes[0]])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_le_code_de_secours_ne_remplace_pas_le_mot_de_passe(): void
    {
        $u = $this->utilisateur();
        $codes = app(CodesSecours::class)->generer($u);

        // Sans avoir saisi le mot de passe, on n'atteint pas l'étape du code.
        $this->postJson(route('two-factor.secours'), ['code_secours' => $codes[0]])->assertOk()->assertJsonPath('redirect', route('login'));
        $this->assertGuest();
        $this->assertSame(8, app(CodesSecours::class)->etat($u)['restants']);
    }

    public function test_trop_d_essais_de_code_de_secours_ferment_la_tentative_de_connexion(): void
    {
        $u = $this->utilisateur();
        $codes = app(CodesSecours::class)->generer($u);
        $this->postJson('/login', ['email' => $u->email, 'password' => self::MDP])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('two-factor.secours'), ['code_secours' => 'FAUX-'.($i + 1000)])->assertStatus(422);
        }
        $this->postJson(route('two-factor.secours'), ['code_secours' => $codes[0]])->assertStatus(422);   // même un bon code, après les essais
        $this->assertGuest();
        $this->assertSame(8, app(CodesSecours::class)->etat($u)['restants']);
    }

    public function test_generer_ses_codes_demande_le_mot_de_passe(): void
    {
        $u = $this->utilisateur();
        $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);

        $this->postJson(route('profile.codes_secours'), ['current_password' => 'faux'])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->assertSame(0, app(CodesSecours::class)->etat($u)['total']);

        $r = $this->postJson(route('profile.codes_secours'), ['current_password' => self::MDP])->assertOk();
        $this->assertCount(8, $r->json('codes'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'codes_secours_generes']);
        $this->get(route('profile.edit'))->assertOk()->assertSee('8 codes');
    }

    public function test_l_administrateur_revoque_les_codes_sans_jamais_les_voir(): void
    {
        $admin = User::factory()->create();   // profil Admin du système
        $agent = $this->utilisateur();
        app(CodesSecours::class)->generer($agent);

        $sans = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Sans droit', 'droits' => []])->id]);
        $this->actingAs($sans)->withSession(['derniere_activite' => now()->timestamp])->postJson(route('utilisateur.compte.codes_secours.revoquer', $agent->id))->assertForbidden();
        $this->assertSame(8, app(CodesSecours::class)->etat($agent)['restants']);

        $r = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->postJson(route('utilisateur.compte.codes_secours.revoquer', $agent->id))->assertOk();
        $this->assertTrue($r->json('status'));
        $this->assertArrayNotHasKey('codes', $r->json());
        $this->assertSame(0, app(CodesSecours::class)->etat($agent)['total']);
        $this->assertTrue(JournalAudit::where('action', 'codes_secours_revoques')->exists());
    }

    public function test_les_anciens_codes_de_huit_caracteres_restent_acceptes_jusqu_a_leur_remplacement(): void
    {
        $u = $this->utilisateur();
        CodeSecours::create(['user_id' => $u->id, 'empreinte' => hash_hmac('sha256', $u->id.'|K7M2-9PQX', (string) config('app.key'))]);

        $this->assertTrue(app(CodesSecours::class)->utiliser($u, 'k7m2 9pqx'));
        $this->assertFalse(app(CodesSecours::class)->utiliser($u, 'K7M2-9PQX'), 'Un code utilisé est brûlé, quel que soit son format.');
    }

    public function test_la_page_de_verification_propose_le_code_de_secours(): void
    {
        $u = $this->utilisateur();
        $this->postJson('/login', ['email' => $u->email, 'password' => self::MDP])->assertOk();

        $this->get('/two-factor')->assertOk()->assertSee('code de secours', false)->assertSee(route('two-factor.secours'), false);
    }
}
