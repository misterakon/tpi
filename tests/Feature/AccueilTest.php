<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Le tableau de bord : chacun ne voit que les blocs de ses droits et les affaires de son périmètre. */
class AccueilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function utilisateur(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function connecte(User $u): static
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function affaire(Chambre $chambre, string $objet): Affaire
    {
        static $n = 0;
        $n++;

        return Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 700 + $n, 'numero_complet' => 'RG-'.(700 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-11-05',
        ]);
    }

    public function test_la_page_exige_une_connexion(): void
    {
        $this->get(route('dashboard'))->assertRedirect();
        $this->connecte($this->utilisateur([]))->get(route('dashboard'))->assertOk()->assertSee('Tableau de bord');
    }

    public function test_un_profil_sans_droit_ne_voit_aucun_indicateur(): void
    {
        $d = $this->connecte($this->utilisateur([]))->getJson(route('dashboard.donnees'))->assertOk();

        $d->assertJsonPath('kpis', [])->assertJsonPath('courbe', null)->assertJsonPath('audiences', null)->assertJsonPath('recentes', null)->assertJsonPath('retards', null);
        $this->assertContains($d->json('accueil.moment'), ['Bonjour', 'Bonsoir']);
    }

    public function test_chaque_bloc_depend_d_un_droit(): void
    {
        $this->affaire(Chambre::where('libelle', 'like', '%3e chambre civile%')->firstOrFail(), 'Paiement de loyers');

        // Affaires seulement : indicateurs et courbe, pas de rôles ni d'alertes.
        $a = $this->connecte($this->utilisateur(['affaires.voir', 'affaires.toutes_chambres']))->getJson(route('dashboard.donnees'))->assertOk();
        $this->assertSame(['en_cours'], array_column($a->json('kpis'), 'code'));
        $this->assertNotNull($a->json('courbe'));
        $this->assertCount(12, $a->json('courbe.mois'));
        $this->assertIsArray($a->json('chambres'));   // graphique en barres par chambre
        $this->assertSame('3e chambre civile', $a->json('chambres.0.libelle'));
        $this->assertIsArray($a->json('suites'));      // anneau des suites notées
        $this->assertCount(1, $a->json('recentes'));
        $this->assertNull($a->json('audiences'));
        $this->assertNull($a->json('retards'));

        // Rôles + alertes + rédaction : les trois autres indicateurs, pas de liste d'affaires.
        $b = $this->connecte($this->utilisateur(['roles.voir', 'alertes.voir', 'redaction.voir', 'affaires.toutes_chambres']))->getJson(route('dashboard.donnees'))->assertOk();
        $this->assertEqualsCanonicalizing(['audiences', 'retards', 'a_rediger'], array_column($b->json('kpis'), 'code'));
        $this->assertNull($b->json('recentes'));
        $this->assertNull($b->json('courbe'));
        $this->assertNull($b->json('chambres'));
        $this->assertIsArray($b->json('audiences'));
    }

    public function test_la_courbe_se_limite_a_six_mois_a_la_demande(): void
    {
        $u = $this->utilisateur(['affaires.voir', 'affaires.toutes_chambres']);

        $this->assertCount(6, $this->connecte($u)->getJson(route('dashboard.donnees', ['mois' => 6]))->json('courbe.mois'));
        $this->assertCount(12, $this->connecte($u)->getJson(route('dashboard.donnees', ['mois' => 99]))->json('courbe.mois'), 'Une valeur inconnue retombe sur douze mois.');
    }

    public function test_un_greffier_ne_voit_que_les_affaires_de_sa_chambre(): void
    {
        $sienne = Chambre::where('libelle', 'like', '%3e chambre civile%')->firstOrFail();
        $autre = Chambre::where('libelle', 'like', '%2e chambre civile%')->firstOrFail();
        $this->affaire($sienne, 'Affaire de ma chambre');
        $this->affaire($autre, 'Affaire de la voisine');

        $greffier = $this->utilisateur(['affaires.voir']);
        $agent = Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $greffier->id]);
        Affectation::create(['formation_id' => $sienne->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);

        $d = $this->connecte($greffier)->getJson(route('dashboard.donnees'))->assertOk();
        $this->assertSame(['Affaire de ma chambre'], array_column($d->json('recentes'), 'objet'));
        $this->assertStringContainsString('3e chambre civile', $d->json('accueil.perimetre'));
        $this->assertStringNotContainsString('2e chambre civile', $d->json('accueil.perimetre'));

        // L'administrateur du système voit les deux.
        $admin = $this->connecte(User::factory()->create())->getJson(route('dashboard.donnees'))->assertOk();
        $this->assertCount(2, $admin->json('recentes'));
        $this->assertSame('Toutes les chambres', $admin->json('accueil.perimetre'));
    }
}
