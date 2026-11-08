<?php

namespace Tests\Feature\Auth;

use App\Models\JournalAudit;
use App\Models\User;
use App\Services\CodesSecours;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Lot G : alertes de sécurité pour les administrateurs (échecs répétés, adresse jamais vue, nuit, code de secours). */
class AnomaliesConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const MDP = 'Tribunal#Bingerville2026';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = User::factory()->create(['email' => 'admin@tribunal.test']);   // profil Admin du système
        Carbon::setTestNow('2026-11-10 10:00:00');   // en journée
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agent(string $email = 'greffier@tribunal.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => self::MDP, 'actif' => true]);
    }

    private function alertes(): array
    {
        return $this->admin->notifications()->where('data->type', 'securite')->get()->pluck('data.titre')->all();
    }

    public function test_cinq_echecs_en_quelques_minutes_previennent_les_administrateurs_une_seule_fois(): void
    {
        $this->agent();
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/login', ['email' => 'greffier@tribunal.test', 'password' => 'faux'.$i])->assertStatus(422);
        }
        $this->assertSame([], $this->alertes(), 'Quatre échecs : pas encore d\'alerte.');

        $this->postJson('/login', ['email' => 'greffier@tribunal.test', 'password' => 'faux5'])->assertStatus(422);
        $this->assertCount(1, $this->alertes());
        $this->assertStringContainsString('5 échecs de connexion', $this->alertes()[0]);

        $this->postJson('/login', ['email' => 'greffier@tribunal.test', 'password' => 'faux6']);
        $this->assertCount(1, $this->alertes(), 'Une seule alerte par heure pour la même situation.');
        $this->assertSame('attention', $this->admin->notifications()->first()->data['niveau'], 'Cinq échecs : attention ; dix ou plus : critique.');
    }

    public function test_des_tentatives_repetees_sur_des_comptes_inconnus_sont_signalees(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/login', ['email' => "inconnu$i@exemple.ci", 'password' => 'x'.$i]);
        }

        $this->assertCount(1, $this->alertes());
        $this->assertStringContainsString('comptes inconnus', $this->alertes()[0]);
    }

    public function test_une_adresse_jamais_vue_est_signalee_mais_pas_la_premiere_connexion(): void
    {
        $u = $this->agent();

        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);   // toute première connexion : rien à signaler
        $this->assertSame([], $this->alertes());

        $this->app->instance('request', \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']));
        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);   // une autre adresse
        $this->assertCount(1, $this->alertes());
        $this->assertStringContainsString('adresse jamais vue', $this->alertes()[0]);

        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);   // la même adresse, déjà connue
        $this->assertCount(1, $this->alertes());
    }

    public function test_une_connexion_de_nuit_est_signalee_une_fois_par_jour(): void
    {
        $u = $this->agent();
        Carbon::setTestNow('2026-11-10 23:15:00');   // Abidjan est à l'heure UTC

        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);
        $this->assertCount(1, $this->alertes());
        $this->assertStringContainsString('de nuit', $this->alertes()[0]);

        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);
        $this->assertCount(1, $this->alertes(), 'Une alerte de nuit par jour et par personne.');

        Carbon::setTestNow('2026-11-11 03:00:00');   // le lendemain, avant 6 h
        Journal::connexion(Journal::CONNEXION_REUSSIE, $u, true, null, $u->email);
        $this->assertCount(2, $this->alertes());
    }

    public function test_l_usage_d_un_code_de_secours_est_signale(): void
    {
        $u = $this->agent();
        $codes = app(CodesSecours::class)->generer($u);

        $this->postJson('/login', ['email' => $u->email, 'password' => self::MDP])->assertOk();
        $this->postJson(route('two-factor.secours'), ['code_secours' => $codes[0]])->assertOk();

        $this->assertTrue(collect($this->alertes())->contains(fn ($t) => str_contains($t, 'code de secours')));
        $this->assertTrue(collect($this->alertes())->contains(fn ($t) => str_contains($t, 'Il lui reste 7 code')));
    }

    public function test_les_alertes_arrivent_dans_la_cloche_et_la_page_des_notifications(): void
    {
        $this->agent();
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/login', ['email' => 'greffier@tribunal.test', 'password' => 'faux'.$i]);
        }

        $this->actingAs($this->admin)->withSession(['derniere_activite' => now()->timestamp]);
        $this->get(route('notifications.index'))->assertOk()->assertSee('échecs de connexion');
        $this->getJson(route('notifications.resume'))->assertOk()->assertJsonPath('non_lues', 1);
        $this->get(route('notifications.ouvrir', $this->admin->notifications()->first()->id))->assertRedirect(url('/journaux/connexions'));
    }

    public function test_on_peut_couper_les_alertes_de_connexion(): void
    {
        config(['securite.anomalies.actif' => false]);
        $this->agent();
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/login', ['email' => 'greffier@tribunal.test', 'password' => 'faux'.$i]);
        }

        $this->assertSame([], $this->alertes());
    }

    public function test_ouvrir_une_affaire_est_inscrit_au_journal_une_fois_par_quart_d_heure(): void
    {
        $this->seed(\Database\Seeders\OrdonnanceBingerville2026Seeder::class);
        $this->seed(\Database\Seeders\ReferentielsJudiciairesSeeder::class);
        $affaire = \App\Models\Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 901, 'numero_complet' => 'RG-901/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => 'Essai',
            'mode_saisine_id' => \App\Models\ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => \App\Models\NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => \App\Models\Matiere::value('id'), 'chambre_id' => \App\Models\Chambre::value('id'), 'statut' => 'enrolee', 'date_premiere_audience' => '2026-11-05',
        ]);

        $this->actingAs($this->admin)->withSession(['derniere_activite' => now()->timestamp]);
        $this->get(route('affaires.liste.fiche', $affaire->id))->assertOk();
        $this->get(route('affaires.liste.fiche', $affaire->id))->assertOk();   // rafraîchissement : pas une deuxième ligne
        $this->assertSame(1, JournalAudit::where('action', 'affaire_consultee')->count());

        $ligne = JournalAudit::where('action', 'affaire_consultee')->first();
        $this->assertSame($this->admin->id, $ligne->user_id);
        $this->assertStringContainsString('901', (string) $ligne->libelle);

        Carbon::setTestNow('2026-11-10 10:20:00');   // vingt minutes plus tard : nouvelle consultation
        Cache::flush();
        $this->get(route('affaires.liste.fiche', $affaire->id))->assertOk();
        $this->assertSame(2, JournalAudit::where('action', 'affaire_consultee')->count());
    }
}
