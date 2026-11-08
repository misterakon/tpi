<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\DecisionPenale;
use App\Models\Infraction;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use App\Support\Alertes\CollecteEcheances;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Lot O : numéro du parquet, infractions, répertoire pénal, minute. */
class MatierePenaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'manuel')]);
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
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

    private function greffe(array $en_plus = []): User
    {
        return $this->utilisateur(array_merge(['repertoire_penal.voir', 'repertoire_penal.creer', 'repertoire_penal.modifier', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction'], $en_plus));
    }

    private function affairePenale(?string $mandat = null): Affaire
    {
        static $n = 0;
        $n++;
        $a = Affaire::create([
            'serie' => 'RP', 'annee' => 2026, 'numero' => 700 + $n, 'numero_complet' => 'RP-'.(700 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => 'Vol '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'penal')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RP')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::value('id'), 'statut' => 'enrolee',
        ]);
        Partie::create(['affaire_id' => $a->id, 'role' => 'prevenu', 'nature' => 'homme', 'nom' => 'KONE'.$n, 'prenoms' => 'Adama', 'cle' => 'k'.$n, 'mandat_depot_date' => $mandat]);

        return $a;
    }

    private function enrolement(User $u, array $plus = [])
    {
        return $this->connecte($u)->post(route('affaires.enrolement.store'), array_replace_recursive([
            'registre' => 'penal', 'numero_rg' => '1334/26', 'date_rg' => '2026-10-20', 'numero_parquet' => 'PQ-2026-884',
            'mode_saisine_id' => ModeSaisine::where('registre', 'penal')->firstOrFail()->id, 'nature_affaire_id' => NatureAffaire::where('registre', 'RP')->firstOrFail()->id,
            'matiere_id' => Matiere::firstOrFail()->id, 'chambre_id' => Chambre::firstOrFail()->id, 'objet' => 'Vol', 'articles' => 'Art. 1 du CP',
            'parties' => [0 => ['role' => 'prevenu', 'nature' => 'homme', 'nom' => 'DIABATE', 'prenoms' => 'Sekou']],
        ], $plus));
    }

    public function test_numero_du_parquet_a_l_enrolement(): void
    {
        $admin = User::factory()->create();
        $this->enrolement($admin)->assertSessionHasNoErrors();
        $this->assertSame('PQ-2026-884', Affaire::firstOrFail()->numero_parquet);

        // Le même numéro du parquet sur une autre affaire : refusé, sauf jonction.
        $this->enrolement($admin, ['numero_rg' => '1335/26', 'parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasErrors('numero_parquet');
        $this->assertSame(1, Affaire::count());
        $this->enrolement($admin, ['numero_rg' => '1334/26', 'jonction' => 1, 'parties' => [0 => ['nom' => 'JOINT']]])->assertSessionHasNoErrors();
        $this->assertSame(2, Affaire::count());
    }

    public function test_infractions_page_et_droits(): void
    {
        $this->assertGreaterThan(5, Infraction::count());
        $gestion = $this->utilisateur(['infractions.voir', 'infractions.creer', 'infractions.modifier', 'infractions.supprimer']);
        $this->connecte($gestion)->get(route('referentiels.infractions'))->assertOk();
        $this->assertStringContainsString('Abus de confiance', $this->lignesServeur($gestion, 'referentiels.infractions.donnees'));
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('referentiels.infractions'))->assertForbidden();

        $this->connecte($gestion)->post(route('referentiels.infractions.store'), ['libelle' => 'Trafic de faux billets', 'articles' => 'Art. 9 du CP', 'actif' => 1])->assertSessionHasNoErrors();
        $this->connecte($gestion)->post(route('referentiels.infractions.store'), ['libelle' => 'Trafic de faux billets'])->assertSessionHasErrors('libelle');
        $this->assertSame('Art. 9 du CP', Infraction::where('libelle', 'Trafic de faux billets')->value('articles'));
        // La liste est proposée dans le formulaire d'enrôlement pénal.
        $this->connecte(User::factory()->create())->get(route('affaires.liste'))->assertOk()->assertSee('Trafic de faux billets');
    }

    public function test_decision_penale_regles_de_peine(): void
    {
        $u = $this->greffe();
        $a = $this->affairePenale();
        $p = $a->parties()->first();
        $poster = fn (array $d) => $this->connecte($u)->post(route('decisions.repertoire_penal.store'), $d + ['affaire_id' => $a->id, 'partie_id' => $p->id, 'date_decision' => '2026-11-05', 'contradiction' => 'contradictoire']);

        $poster(['issue' => 'condamne'])->assertSessionHasErrors('issue');                                          // condamné sans peine
        $poster(['issue' => 'relaxe', 'amende' => 5000])->assertSessionHasErrors('issue');                          // relaxé avec peine
        $poster(['issue' => 'condamne', 'emprisonnement_mois' => 12, 'sursis_mois' => 18])->assertSessionHasErrors('sursis_mois');
        $poster(['issue' => 'condamne', 'amende' => 100000, 'sursis_mois' => 3])->assertSessionHasErrors('sursis_mois');   // sursis sans emprisonnement
        $poster(['issue' => 'condamne', 'date_decision' => '2026-10-01', 'amende' => 1000])->assertSessionHasErrors('date_decision');   // avant l'enregistrement
        $poster(['issue' => 'condamne', 'emprisonnement_mois' => 24, 'sursis_mois' => 6, 'amende' => 100000])->assertSessionHasNoErrors();

        $d = DecisionPenale::firstOrFail();
        $this->assertSame('Emprisonnement : 2 ans dont 6 mois avec sursis ; Amende : 100 000 F CFA', $d->peineEnClair());
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_penale_enregistree']);
        $this->connecte($u)->get(route('decisions.repertoire_penal'))->assertOk();
        $this->assertStringContainsString('KONE', $this->lignesServeur($u, 'decisions.repertoire_penal.donnees'));
    }

    public function test_un_prevenu_relaxe_est_libere_du_suivi_des_detenus(): void
    {
        $u = $this->greffe();
        $a = $this->affairePenale('2026-10-21');
        $p = $a->parties()->first();
        $this->assertTrue($p->estDetenu());

        $this->connecte($u)->post(route('decisions.repertoire_penal.store'), ['affaire_id' => $a->id, 'partie_id' => $p->id, 'date_decision' => '2026-11-06', 'contradiction' => 'contradictoire', 'issue' => 'relaxe'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertFalse($p->estDetenu());
        $this->assertSame('2026-11-06', $p->libere_le->toDateString());
    }

    public function test_minute_transmission_alerte_et_perimetre(): void
    {
        $u = $this->greffe(['repertoire_penal.supprimer']);
        $a = $this->affairePenale();
        $d = DecisionPenale::create(['affaire_id' => $a->id, 'partie_id' => $a->parties()->first()->id, 'date_decision' => '2026-09-20', 'contradiction' => 'defaut', 'issue' => 'condamne', 'amende' => 50000]);

        $this->assertTrue($d->minuteEnRetard());                       // 51 jours > 30
        $this->assertNotNull(CollecteEcheances::toutes()->firstWhere('type', 'minute_a_transmettre'));

        $this->connecte($u)->post(route('decisions.repertoire_penal.minute', $d->id), ['date_transmission_minute' => '2026-09-01'])->assertSessionHasErrors('date_transmission_minute');   // avant la décision
        $this->connecte($u)->post(route('decisions.repertoire_penal.minute', $d->id), ['date_transmission_minute' => '2026-11-09', 'minute_transmise_a' => 'Parquet'])->assertSessionHasNoErrors();
        $this->assertFalse($d->refresh()->minuteATransmettre());
        $this->assertNull(CollecteEcheances::toutes()->firstWhere('type', 'minute_a_transmettre'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'minute_transmise']);

        // Droits : lecture seule ne modifie ni ne supprime.
        $lecteur = $this->utilisateur(['repertoire_penal.voir', 'affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->post(route('decisions.repertoire_penal.minute', $d->id), ['date_transmission_minute' => '2026-11-09'])->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('decisions.repertoire_penal.delete', $d->id))->assertForbidden();
        $this->connecte($u)->deleteJson(route('decisions.repertoire_penal.delete', $d->id))->assertOk()->assertJson(['status' => true]);

        // Hors périmètre de chambres : la décision n'est pas visible.
        $d2 = DecisionPenale::create(['affaire_id' => $a->id, 'date_decision' => '2026-11-01', 'contradiction' => 'contradictoire', 'issue' => 'relaxe']);
        $sans = $this->utilisateur(['repertoire_penal.voir', 'repertoire_penal.modifier']);
        $this->connecte($sans)->getJson(route('decisions.repertoire_penal.get', $d2->id))->assertNotFound();
    }
}
