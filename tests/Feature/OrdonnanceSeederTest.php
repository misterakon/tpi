<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Formation;
use App\Models\Salle;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdonnanceSeederTest extends TestCase
{
    use RefreshDatabase;

    private function compteurs(): array
    {
        return [AnneeJudiciaire::count(), Section::count(), Chambre::count(), Salle::count(), Agent::count(), Formation::count(), Affectation::count(), Creneau::count()];
    }

    public function test_le_seeder_charge_l_organisation_de_l_ordonnance(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);

        $this->assertSame([1, 6, 17, 5, 56, 24, 252, 27], $this->compteurs());
        $annee = AnneeJudiciaire::courante();
        $this->assertSame('2026-2027', $annee->libelle);
        $this->assertStringContainsString('600/2026', $annee->ordonnance);
        $this->assertSame(17, Chambre::where('annee_judiciaire_id', $annee->id)->count());
    }

    public function test_le_seeder_peut_etre_relance_sans_doublon(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $avant = $this->compteurs();

        $this->seed(OrdonnanceBingerville2026Seeder::class);

        $this->assertSame($avant, $this->compteurs());
    }

    public function test_les_modifications_faites_dans_l_application_ne_sont_pas_ecrasees(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $salle = Salle::where('nom', 'like', '%KONE Mamadou%')->firstOrFail();
        $salle->update(['actif' => false, 'emplacement' => 'Rez-de-chaussée']);
        Agent::where('nom', 'KIPRE')->update(['actif' => false]);

        $this->seed(OrdonnanceBingerville2026Seeder::class);

        $this->assertFalse($salle->fresh()->actif);
        $this->assertSame('Rez-de-chaussée', $salle->fresh()->emplacement);
        $this->assertFalse(Agent::where('nom', 'KIPRE')->first()->actif);
    }

    public function test_la_composition_de_la_premiere_chambre_civile(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $chambre = Chambre::where('libelle', '1re chambre civile')->firstOrFail();
        $this->assertSame('Présidentielle et administrative', $chambre->intitule);
        $this->assertStringContainsString('100 000 000', $chambre->attributions);   // attributions portées par la chambre
        $this->assertCount(1, $chambre->formations);
        $formation = $chambre->formations->first();
        $this->assertNull($formation->nom);   // formation unique : composition de la chambre

        $president = $formation->affectations()->where('role', 'president')->with('agent')->first();
        $this->assertSame("N'GUESSAN", $president->agent->nom);
        $this->assertSame('presidente', $president->agent->fonction);
        $this->assertSame(2, $formation->affectations()->where('role', 'assesseur')->where('suppleant', false)->count());
        $this->assertSame(4, $formation->affectations()->where('role', 'assesseur')->where('suppleant', true)->count());
        $this->assertTrue($formation->affectations()->where('role', 'greffier')->where('chef_section', true)->exists());

        $audience = $formation->creneaux()->first();
        $this->assertSame(4, $audience->jour);   // jeudi
        $this->assertSame('09:00', substr($audience->heure, 0, 5));
        $this->assertStringContainsString('KONE Mamadou', $audience->salle->nom);
    }

    public function test_la_hierarchie_section_chambre_formation(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);

        $parSection = Chambre::query()->join('sections', 'sections.id', '=', 'chambres.section_id')
            ->selectRaw('sections.code as code, count(*) as n')->groupBy('sections.code')->pluck('n', 'code')->all();
        ksort($parSection);
        $this->assertSame(['civile' => 5, 'instruction' => 3, 'penale' => 8, 'sociale' => 1], $parSection);
        $this->assertSame(0, Section::where('code', 'urgence')->first()->chambres()->count());   // section citée à l'art. 1, sans chambre propre

        // La 2e chambre civile : attributions communes, deux formations (A et B).
        $deuxieme = Chambre::where('libelle', '2e chambre civile')->firstOrFail();
        $this->assertStringContainsString('expropriation', strtolower($deuxieme->attributions));
        $this->assertSame(['Formation A', 'Formation B'], $deuxieme->formations->pluck('nom')->all());
        $this->assertNull($deuxieme->formations[0]->attributions);

        // La 3e chambre civile : chaque formation a ses propres attributions.
        $troisieme = Chambre::where('libelle', '3e chambre civile')->firstOrFail();
        $this->assertNull($troisieme->attributions);
        $this->assertCount(2, $troisieme->formations);
        $this->assertStringContainsString('Hérédité', $troisieme->formations[1]->attributions);

        $this->assertSame(3, Chambre::where('libelle', '5e chambre civile')->firstOrFail()->formations()->count());
        $this->assertSame('Les juridictions correctionnelles', Chambre::where('libelle', '2e chambre correctionnelle')->first()->groupe);
    }

    public function test_aucune_donnee_inventee_le_sexe_n_est_renseigne_que_s_il_est_dans_l_ordonnance(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);

        $this->assertSame('F', Agent::where('nom', 'ZEREGBA')->first()->sexe);
        $this->assertSame('M', Agent::where('nom', 'KIPRE')->first()->sexe);
        $this->assertNull(Agent::where('nom', 'TAPE')->first()->sexe);   // « Me » : la source ne précise pas
    }

    public function test_les_pages_affichent_les_donnees_chargees(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->actingAs(User::factory()->create())->withSession(['derniere_activite' => now()->timestamp]);

        $this->get('/organisation/personnel')->assertOk()->assertSee('CAUPHY')->assertSee('Greffier');
        $this->get('/organisation/salles')->assertOk()->assertSee('Jean Sansan Kambile')->assertSee('Cabinet du Président');
        $this->get('/organisation/annees')->assertOk()->assertSee('2026-2027')->assertSee('Ordonnance n°600/2026');
    }
}
