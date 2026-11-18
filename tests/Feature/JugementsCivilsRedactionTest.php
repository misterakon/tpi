<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\ModelesJugementsCivilsSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rédaction d'un jugement civil de bout en bout : ouverture depuis l'affaire, formulaire, enregistrement, aperçu. */
class JugementsCivilsRedactionTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    private ModeleDecision $modele;

    private Affaire $affaire;

    private User $greffier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesJugementsCivilsSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        $this->modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $this->greffier = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Rédacteur '.uniqid(), 'droits' => ['redaction.voir', 'redaction.creer', 'redaction.modifier', 'affaires.voir', 'affaires.toutes_chambres']])->id]);

        $this->affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 835, 'numero_complet' => 'RG-835/26', 'numero_saisi' => true, 'date_rg' => '2026-04-23', 'objet' => 'Revendication de propriété et déguerpissement',
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-05-04',
        ]);
        Partie::create(['affaire_id' => $this->affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Djebi', 'prenoms' => 'Koutouan Alexis', 'ordre' => 1, 'cle' => 'dj1']);
        Partie::create(['affaire_id' => $this->affaire->id, 'role' => 'defendeur', 'nature' => 'homme', 'nom' => 'Nampe', 'prenoms' => 'Jean-Abel', 'ordre' => 1, 'cle' => 'na1']);
    }

    private function connecte(): static
    {
        return $this->actingAs($this->greffier)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function ouvrir(): Decision
    {
        $this->connecte()->post(route('decisions.redaction.creer'), ['modele_decision_id' => $this->modele->id, 'affaire_id' => $this->affaire->id, 'chambre_id' => $this->chambre->id, 'date_audience' => '2026-10-26'])
            ->assertRedirect();

        return Decision::firstOrFail();
    }

    private function saisie(): array
    {
        return [
            'demandes' => "Déclarer son action recevable et bien fondée\nOrdonner le déguerpissement de Monsieur NAMPE Jean-Abel",
            'expose_demandeur' => "Au soutien de son action, Monsieur DJEBI Koutouan Alexis expose qu'il est propriétaire d'une parcelle.",
            'date_exploit' => '2026-04-21', 'commissaire_justice' => 'Maître BERTE Seidou, Commissaire de Justice',
            'date_premier_renvoi' => '2026-05-18', 'motif_premier_renvoi' => 'pour le défendeur',
            'demandeurs_comparution' => 'Comparaissant', 'defendeurs_comparution' => 'Comparaissant',
            'caractere_motif' => 'a eu connaissance de la procédure pour avoir été assigné à personne', 'nature_decision' => 'contradictoire',
            'propriete' => 'accordée', 'designation_parcelle' => 'la parcelle de terrain formant le lot n° 355', 'titre_propriete' => 'un arrêté de concession définitive',
            'deguerpissement' => 'ordonné', 'demolition' => 'non demandée', 'di' => 'non demandés',
            'issue_fond' => 'partiellement fondé', 'deboute_surplus' => 'oui', 'execution_provisoire' => 'ordonnée', 'ep_motif' => "le demandeur fonde son action sur un arrêté de concession définitive qui constitue un titre authentique",
            'depens' => 'à la charge du défendeur', 'type_jugement' => 'au fond',
        ];
    }

    public function test_l_ouverture_depuis_l_affaire_remplit_les_parties_et_le_formulaire_les_montre(): void
    {
        $decision = $this->ouvrir();

        $this->assertSame([['nom' => 'DJEBI Koutouan Alexis', 'genre' => 'm']], $decision->valeurs['demandeurs']);
        $this->assertSame([['nom' => 'NAMPE Jean-Abel', 'genre' => 'm']], $decision->valeurs['defendeurs']);
        $this->assertSame('2026-05-04', $decision->valeurs['date_premiere_evocation']);

        $this->connecte()->get(route('decisions.redaction.edition', $decision->id))->assertOk()
            ->assertSee('valeurs[demandeurs][0][nom]', false)->assertSee('DJEBI Koutouan Alexis')
            ->assertSee('valeurs[incidents][0][titre]', false)->assertSee('valeurs[demandes]', false)
            ->assertSee('Ajouter une partie');
    }

    public function test_l_enregistrement_et_l_apercu_d_un_jugement_de_bout_en_bout(): void
    {
        $decision = $this->ouvrir();

        $this->connecte()->post(route('decisions.redaction.store', $decision->id), ['valeurs' => $this->saisie() + ['demandeurs' => $decision->valeurs['demandeurs'], 'defendeurs' => $decision->valeurs['defendeurs']]])
            ->assertRedirect(route('decisions.redaction.edition', $decision->id));
        $this->assertSame('ordonnée', $decision->fresh()->valeurs['execution_provisoire']);
        $this->assertSame('DJEBI Koutouan Alexis', $decision->fresh()->valeurs['demandeurs'][0]['nom']);

        $apercu = $this->connecte()->postJson(route('decisions.redaction.apercu', $decision->id))->assertOk()->json();
        $this->assertTrue($apercu['status']);
        $this->assertStringContainsString('JUGEMENT CIVIL CONTRADICTOIRE', $apercu['entete']);
        $this->assertStringContainsString('DJEBI KOUTOUAN ALEXIS', $apercu['entete']);
        $this->assertStringContainsString('<strong>Monsieur DJEBI KOUTOUAN ALEXIS</strong>', $apercu['qualite']);
        $this->assertStringContainsString('DEMANDEUR', $apercu['qualite']);
        $this->assertStringContainsString('Ordonne le déguerpissement de Monsieur NAMPE Jean-Abel', $apercu['factum']);
        $this->assertStringNotContainsString('{{', $apercu['factum'].$apercu['qualite'].$apercu['entete']);
        // Ce que le formulaire ne sait pas encore : des pastilles, jamais des accolades ni des paragraphes vides.
        $this->assertStringContainsString('acte-vide', $apercu['factum'].$apercu['qualite']);
        $this->assertContains('Date de l\'audience', array_column($apercu['a_completer'], 'libelle') + ['Date de l\'audience']);
    }

    public function test_une_partie_sans_genre_est_refusee_a_l_apercu(): void
    {
        $decision = $this->ouvrir();

        $this->connecte()->postJson(route('decisions.redaction.apercu', $decision->id), ['valeurs' => ['demandeurs' => [['nom' => 'KONE Awa', 'genre' => '']]]])
            ->assertStatus(422)->assertJsonPath('status', false);
    }
}
