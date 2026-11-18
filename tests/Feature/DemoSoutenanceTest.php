<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\User;
use App\Support\Redaction\ChoixAffaire;
use Database\Seeders\ModelesJugementsCivilsSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les trois affaires de démonstration : chacune à sa place dans le parcours, relançable sans doublon. */
class DemoSoutenanceTest extends TestCase
{
    use RefreshDatabase;

    private function preparer(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesJugementsCivilsSeeder::class);
        User::factory()->create();
    }

    public function test_les_trois_affaires_sont_a_trois_etapes_differentes(): void
    {
        $this->preparer();

        $this->artisan('demo:soutenance')->assertSuccessful();

        $affaires = Affaire::where('ancien_numero', 'DEMO')->whereIn('numero', [2101, 2102, 2103])->get()->keyBy('numero');
        $this->assertCount(3, $affaires);

        // 2101 : au rôle d'aujourd'hui, pas encore jugée ; 2102 : jugée, à rédiger ; 2103 : décision signée et délivrée.
        $foncier = ChoixAffaire::pour($affaires[2101]);
        $paiement = ChoixAffaire::pour($affaires[2102]);
        $bail = ChoixAffaire::pour($affaires[2103]);
        $this->assertFalse($foncier['a_rediger']);
        $this->assertTrue($paiement['a_rediger']);
        $this->assertSame('Jugée', $paiement['suite']);
        $this->assertFalse($bail['a_rediger']);

        // Chaque affaire propose le modèle qui lui convient d'après son objet.
        $this->assertSame('Jugement civil : foncier', $foncier['modeles'][0]['nom']);
        $this->assertSame('Jugement civil : paiement et contrats', $paiement['modeles'][0]['nom']);
        $this->assertSame("Jugement civil : bail d'habitation", $bail['modeles'][0]['nom']);

        $decision = Decision::where('affaire_id', $affaires[2103]->id)->firstOrFail();
        $this->assertSame('transmise', $decision->statut);
        $this->assertNotNull($decision->numero);
    }

    public function test_relancer_la_commande_ne_duplique_rien(): void
    {
        $this->preparer();

        $this->artisan('demo:soutenance')->assertSuccessful();
        $this->artisan('demo:soutenance')->assertSuccessful();

        $this->assertSame(3, Affaire::where('ancien_numero', 'DEMO')->whereIn('numero', [2101, 2102, 2103])->count());
        $this->assertSame(1, Decision::count());
    }

    public function test_retirer_efface_les_trois_affaires_et_leurs_decisions(): void
    {
        $this->preparer();
        $this->artisan('demo:soutenance')->assertSuccessful();

        $this->artisan('demo:soutenance', ['--retirer' => true])->assertSuccessful();

        $this->assertSame(0, Affaire::where('ancien_numero', 'DEMO')->count());
        $this->assertSame(0, Decision::count());
    }
}
