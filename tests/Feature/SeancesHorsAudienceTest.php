<?php

namespace Tests\Feature;

use App\Models\Chambre;
use App\Models\Profil;
use App\Models\SeanceHorsAudience;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Lot P : séances hors audience publique (AUD-11). */
class SeancesHorsAudienceTest extends TestCase
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

    public function test_planifier_ajouter_un_dossier_tenir_et_annuler(): void
    {
        $u = $this->utilisateur(['seances.voir', 'seances.creer', 'seances.modifier', 'seances.annuler', 'affaires.voir', 'affaires.toutes_chambres']);
        $chambre = Chambre::firstOrFail();

        $this->connecte($u)->post(route('audiences.seances.store'), ['type' => 'conciliation', 'chambre_id' => $chambre->id, 'date_seance' => '2026-11-13', 'heure' => '09:00', 'lieu' => 'Cabinet'])->assertSessionHasNoErrors();
        $s = SeanceHorsAudience::firstOrFail();
        $this->assertSame('prevue', $s->statut);

        $this->connecte($u)->post(route('audiences.seances.dossier', $s->id), ['objet' => 'Litige de voisinage', 'issue' => 'Conciliation réussie'])->assertSessionHasNoErrors();
        $this->assertSame(1, $s->dossiers()->count());
        $this->connecte($u)->post(route('audiences.seances.dossier', $s->id), ['objet' => ''])->assertSessionHasErrors('objet');

        // Une séance à venir ne peut pas être déclarée tenue.
        $this->connecte($u)->post(route('audiences.seances.statut', $s->id), ['statut' => 'tenue'])->assertSessionHasErrors('statut');
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->connecte($u)->post(route('audiences.seances.statut', $s->id), ['statut' => 'tenue'])->assertSessionHasNoErrors();
        $this->assertSame('tenue', $s->refresh()->statut);
        $this->connecte($u)->post(route('audiences.seances.store'), ['id_' => $s->id, 'type' => 'tutelle', 'date_seance' => '2026-11-20'])->assertForbidden();   // plus modifiable
        $this->assertDatabaseHas('journal_audit', ['action' => 'seance_tenue']);

        $this->connecte($u)->get(route('audiences.seances'))->assertOk();
        $this->assertStringContainsString('Litige de voisinage', $this->lignesServeur($u, 'audiences.seances.donnees'));
    }

    public function test_droits(): void
    {
        $lecteur = $this->utilisateur(['seances.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->get(route('audiences.seances'))->assertOk();
        $this->connecte($lecteur)->post(route('audiences.seances.store'), ['type' => 'tutelle', 'date_seance' => '2026-11-20'])->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('audiences.seances'))->assertForbidden();

        $s = SeanceHorsAudience::create(['type' => 'tutelle', 'date_seance' => '2026-11-20', 'statut' => 'prevue']);
        $this->connecte($this->utilisateur(['seances.voir', 'seances.modifier']))->post(route('audiences.seances.statut', $s->id), ['statut' => 'annulee'])->assertForbidden();   // annuler = autre droit
    }
}
