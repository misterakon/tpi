<?php

namespace Tests\Feature;

use App\Models\Chambre;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Rôle du jour à l'écran (ROL-11). */
class RoleDuJourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function utilisateur(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'P'.uniqid(), 'droits' => $droits])->id]);
    }

    private function connecte(User $u): static
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function role(string $date, string $statut = 'publie', ?Chambre $chambre = null): RoleAudience
    {
        $r = RoleAudience::create(['chambre_id' => ($chambre ?? Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->first())->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'statut' => $statut, 'president' => 'Mme BOKA']);
        RoleLigne::create(['role_id' => $r->id, 'rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '12/26', 'demandeurs' => 'KONE', 'defendeurs' => 'SOCIETE X', 'objet' => 'Loyers', 'issue' => 'juge']);
        RoleLigne::create(['role_id' => $r->id, 'rubrique' => 'nouvelles', 'ordre' => 2, 'numero' => '13/26', 'demandeurs' => 'DIALLO', 'objet' => 'Dette']);
        RoleLigne::create(['role_id' => $r->id, 'rubrique' => 'deliberes', 'ordre' => 1, 'numero' => '5/26', 'demandeurs' => 'TRAORE']);

        return $r;
    }

    public function test_le_role_du_jour_n_affiche_que_les_roles_publies_de_la_journee(): void
    {
        $this->role('2026-11-10');
        $this->role('2026-11-10', 'brouillon', Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->skip(1)->first());   // un brouillon n'est pas affiché
        $this->role('2026-11-11');                     // un autre jour
        $u = $this->utilisateur(['roles.voir', 'affaires.voir', 'affaires.toutes_chambres']);

        $d = $this->connecte($u)->getJson(route('audiences.jour.donnees'))->assertOk()->json();
        $this->assertTrue($d['aujourdhui']);
        $this->assertSame(1, $d['totaux']['roles']);
        $this->assertSame([3, 1], [$d['roles'][0]['total'], $d['roles'][0]['traitees']]);
        $this->assertSame('a_tenir', $d['roles'][0]['etat']);
        $this->assertSame(['Affaires en délibéré', 'Affaires nouvelles'], array_column($d['roles'][0]['rubriques'], 'libelle'));

        $demain = $this->connecte($u)->getJson(route('audiences.jour.donnees', ['jour' => '2026-11-11']))->json();
        $this->assertFalse($demain['aujourdhui']);
        $this->assertSame(1, $demain['totaux']['roles']);
        $this->assertSame('2026-11-10', $demain['precedent']);
        $this->assertSame(0, $this->connecte($u)->getJson(route('audiences.jour.donnees', ['jour' => 'pas une date']))->json('totaux.roles') - 1);   // une date invalide retombe sur aujourd'hui
        $this->connecte($u)->get(route('audiences.jour'))->assertOk()->assertSee('Rôle du jour');
    }

    public function test_droits_et_perimetre(): void
    {
        $r = $this->role('2026-11-10');
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('audiences.jour'))->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->getJson(route('audiences.jour.donnees'))->assertForbidden();
        // Sans affectation ni accès à toutes les chambres : le rôle d'une autre chambre n'est pas visible.
        $limite = $this->utilisateur(['roles.voir']);
        $this->assertSame(0, $this->connecte($limite)->getJson(route('audiences.jour.donnees'))->json('totaux.roles'));
        $this->assertNotNull($r->id);
    }
}
