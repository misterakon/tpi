<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\MiseEnEtat;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Matiere;
use App\Models\Requete;
use App\Models\User;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** La commande tpi:formation prépare le jeu du test pratique complet, l'efface sans toucher au reste, et refuse d'agir s'il existe une donnée réelle. */
class FormationDonneesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);
        User::factory()->create();
    }

    public function test_prepare_le_jeu_complet_puis_l_efface(): void
    {
        $this->artisan('tpi:formation', ['--force' => true, '--sans-stats' => true])->assertExitCode(0);

        // Décisions : un exemplaire de chaque stade du circuit et du suivi des recours.
        $this->assertSame(10, Decision::count());
        $this->assertEqualsCanonicalizing(['brouillon', 'redigee', 'validee', 'signee', 'delivree'], Decision::distinct()->pluck('statut')->all());
        $this->assertSame(8, Decision::whereNotNull('numero')->count());
        $this->assertSame(1, DB::table('certificats_recours')->count());
        $this->assertSame(1, DB::table('recours')->count());
        $this->assertSame(1, DB::table('delivrances')->count());
        $this->assertSame(4, DB::table('suivis_recours')->count());

        $this->assertSame(8, Requete::count());
        $this->assertSame(4, MiseEnEtat::count());
        $this->assertSame(4, DB::table('seances_hors_audience')->count());
        $this->assertSame(6, DB::table('assistance_judiciaire')->count());
        $this->assertSame(10, Avocat::where('email', 'like', '%@formation.test')->count());
        $this->assertGreaterThanOrEqual(2, DB::table('parties')->whereNotNull('mandat_depot_date')->whereNull('libere_le')->count());
        $this->assertSame(2, DB::table('dossiers_instruction')->count());

        // Relancer repart de zéro : mêmes quantités, numéros de décision et de requête repartis de 1.
        $this->artisan('tpi:formation', ['--force' => true, '--sans-stats' => true])->assertExitCode(0);
        $this->assertSame(10, Decision::count());
        $this->assertSame(8, Requete::count());
        $this->assertSame(['D-1/'.date('Y')], [Decision::where('statut', 'validee')->first()->numeroAffichage()]);
        $this->assertSame('RQ-0001/'.date('y'), Requete::orderBy('id')->first()->numero_complet);
        $this->assertEqualsCanonicalizing(['recue', 'repartie', 'traitee', 'classee'], Requete::distinct()->pluck('statut')->all());

        // Ce qui n'est pas d'essai reste : organisation, modèles, comptes.
        $chambres = Chambre::count();
        $this->artisan('tpi:formation', ['--force' => true, '--retirer' => true])->assertExitCode(0);
        $this->assertSame(0, Affaire::count() + Decision::count() + Requete::count() + MiseEnEtat::count() + Avocat::count());
        $this->assertSame(0, DB::table('assistance_judiciaire')->count() + DB::table('seances_hors_audience')->count() + DB::table('alertes')->count());
        $this->assertSame($chambres, Chambre::count());
        $this->assertSame(1, User::count());
    }

    public function test_refuse_d_agir_s_il_existe_une_affaire_reelle(): void
    {
        $chambre = Chambre::firstOrFail();
        $reelle = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 77, 'numero_complet' => 'RG-77/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => 'Affaire réelle',
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee',
        ]);

        $this->artisan('tpi:formation', ['--force' => true, '--sans-stats' => true])->assertExitCode(1);
        $this->assertNotNull(Affaire::find($reelle->id));
        $this->assertSame(0, Decision::count());
    }
}
