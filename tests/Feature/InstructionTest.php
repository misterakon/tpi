<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\CommissionRogatoire;
use App\Models\DossierInstruction;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\SuiviInculpe;
use App\Models\User;
use App\Support\Statistiques\RegistresTribunal;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/** Lot R : suivi des dossiers d'instruction, inculpés, victimes, commissions rogatoires, registres JI et JE. */
class InstructionTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $cabinet;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->cabinet = Chambre::where('libelle', 'like', '%1er cabinet%')->firstOrFail();
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

    private function juge(array $en_plus = []): User
    {
        return $this->utilisateur(array_merge(['instruction.voir', 'instruction.modifier', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.instruction'], $en_plus));
    }

    private function dossier(): array
    {
        $a = Affaire::create([
            'serie' => 'RI', 'annee' => 2026, 'numero' => 12, 'numero_complet' => 'RI-12/26', 'numero_saisi' => true, 'date_rg' => '2026-03-02', 'objet' => 'Meurtre', 'articles' => 'Art. 1 du CP',
            'mode_saisine_id' => ModeSaisine::where('registre', 'instruction')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RI')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->cabinet->id, 'statut' => 'enrolee',
        ]);
        $inc = Partie::create(['affaire_id' => $a->id, 'role' => 'inculpe', 'nature' => 'homme', 'nom' => 'TRAORE', 'prenoms' => 'Moussa', 'cle' => 'i', 'nationalite' => 'malienne', 'profession' => 'chauffeur', 'naissance_annee' => 2000, 'mandat_depot_date' => '2026-03-05']);
        $vic = Partie::create(['affaire_id' => $a->id, 'role' => 'victime', 'nature' => 'femme', 'nom' => 'KONE', 'prenoms' => 'Awa', 'cle' => 'v']);

        return [$a, $inc, $vic];
    }

    public function test_page_droits_et_protection_de_la_famille(): void
    {
        [$a] = $this->dossier();
        $this->connecte($this->juge())->get(route('instruction'))->assertOk();
        $this->assertStringContainsString('RI 12/26', $this->lignesServeur($this->juge(), 'instruction.donnees'));
        $this->connecte($this->juge())->getJson(route('instruction.show', $a->id))->assertOk()->assertJsonPath('numero', 'RI 12/26');
        // Sans le droit spécial de la famille « instruction », le dossier n'existe pas pour l'utilisateur.
        $sans = $this->utilisateur(['instruction.voir', 'instruction.modifier', 'affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($sans)->getJson(route('instruction.show', $a->id))->assertNotFound();
        $this->connecte($sans)->post(route('instruction.dossier', $a->id), ['observations' => 'x'])->assertNotFound();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('instruction'))->assertForbidden();
        $lecteur = $this->utilisateur(['instruction.voir', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.instruction']);
        $this->connecte($lecteur)->post(route('instruction.dossier', $a->id), ['observations' => 'x'])->assertForbidden();
    }

    public function test_dossier_inculpe_et_victime_avec_coherence_des_dates(): void
    {
        [$a, $inc, $vic] = $this->dossier();
        $u = $this->juge();
        $post = fn (string $route, int $id, array $d) => $this->connecte($u)->postJson(route($route, $id), $d);

        $post('instruction.dossier', $a->id, ['date_requisitoire' => '2026-03-03', 'date_cloture' => '2026-02-01'])->assertStatus(422);      // clôture avant l'enregistrement
        $post('instruction.dossier', $a->id, ['date_requisitoire' => '2026-03-03', 'date_faits' => '2026-04-01'])->assertStatus(422);        // faits après le réquisitoire
        $post('instruction.dossier', $a->id, ['date_requisitoire' => '2026-03-03', 'date_faits' => '2026-02-20', 'nature_infraction' => 'crime', 'textes_vises' => 'Art. 1', 'date_cloture' => '2026-09-01', 'nature_cloture' => 'Renvoi'])->assertOk();
        $this->assertSame('crime', DossierInstruction::firstOrFail()->nature_infraction);
        $post('instruction.dossier', $a->id, ['nature_infraction' => 'bizarre'])->assertStatus(422);

        $post('instruction.inculpe', $inc->id, ['statut' => 'detenu', 'date_titre_detention' => '2026-03-05', 'date_renouvellement' => '2026-02-01'])->assertStatus(422);   // renouvellement avant le titre
        $post('instruction.inculpe', $inc->id, ['statut' => 'detenu', 'date_titre_detention' => '2026-03-05', 'duree_detention_mois' => 6, 'date_renouvellement' => '2026-09-05', 'duree_renouvellement_mois' => 6, 'traite' => 1])->assertOk();
        $s = SuiviInculpe::firstOrFail();
        $this->assertSame(['detenu', 6, true], [$s->statut, $s->duree_detention_mois, $s->traite]);
        $post('instruction.inculpe', $vic->id, ['statut' => 'libre'])->assertNotFound();      // une victime n'est pas un inculpé

        $post('instruction.victime', $vic->id, ['audition_2' => '2026-04-01'])->assertStatus(422);                                        // 2e audition sans la 1re
        $post('instruction.victime', $vic->id, ['audition_1' => '2026-03-20', 'audition_2' => '2026-04-01', 'infraction_subie' => 'Meurtre'])->assertOk();
        $this->assertDatabaseHas('journal_audit', ['action' => 'instruction_inculpe_suivi']);

        $detail = $this->connecte($u)->getJson(route('instruction.show', $a->id))->json();
        $this->assertSame('detenu', $detail['inculpes'][0]['suivi']['statut']);
        $this->assertSame('2026-03-20', $detail['victimes'][0]['suivi']['audition_1']);
    }

    public function test_commissions_rogatoires(): void
    {
        $u = $this->juge();
        $mois = array_fill(1, 12, ['stock_initial' => 0, 'nouvelles' => 0, 'executees' => 0]);
        $mois[1] = ['stock_initial' => 2, 'nouvelles' => 3, 'executees' => 4];
        $base = ['chambre_id' => $this->cabinet->id, 'annee' => 2026, 'sens' => 'recu', 'portee' => 'nationale'];
        $this->connecte($u)->postJson(route('instruction.commissions.store'), $base + ['mois' => $mois])->assertOk();
        $c = CommissionRogatoire::where('mois', 1)->firstOrFail();
        $this->assertSame([2, 3, 4, 1], [$c->stock_initial, $c->nouvelles, $c->executees, $c->stockRestant()]);

        $mois[2] = ['stock_initial' => 0, 'nouvelles' => 1, 'executees' => 5];     // plus d'exécutées que de commissions à exécuter
        $this->connecte($u)->postJson(route('instruction.commissions.store'), $base + ['mois' => $mois])->assertStatus(422);
        $this->connecte($u)->getJson(route('instruction.commissions', ['chambre_id' => $this->cabinet->id, 'annee' => 2026]))->assertOk()->assertJsonPath('lignes.recu|nationale.0.nouvelles', 3);
        $this->connecte($u)->getJson(route('instruction.commissions', ['chambre_id' => Chambre::where('libelle', 'like', '%civile%')->value('id'), 'annee' => 2026]))->assertNotFound();   // pas un cabinet
    }

    public function test_registre_instruction_au_modele_du_tribunal(): void
    {
        [$a, $inc] = $this->dossier();
        DossierInstruction::create(['affaire_id' => $a->id, 'date_requisitoire' => '2026-03-03', 'nature_infraction' => 'crime', 'date_cloture' => '2026-09-01']);
        SuiviInculpe::create(['partie_id' => $inc->id, 'statut' => 'detenu', 'duree_detention_mois' => 6]);
        CommissionRogatoire::create(['chambre_id' => $this->cabinet->id, 'annee' => 2026, 'mois' => 2, 'sens' => 'emis', 'portee' => 'internationale', 'stock_initial' => 1, 'nouvelles' => 2, 'executees' => 1]);
        $admin = User::factory()->create();

        $this->assertSame(1, RegistresTribunal::compter($admin)['instruction_ji1']);
        $this->assertSame(0, RegistresTribunal::compter($admin)['instruction_ji2']);
        [$fichier, $n] = RegistresTribunal::fichier('instruction_ji1', $admin);
        $this->assertSame(1, $n);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($fichier) === true);
        $feuilles = [];
        for ($i = 1; $i <= 12; $i++) {
            $x = $zip->getFromName("xl/worksheets/sheet$i.xml");
            if ($x !== false) {
                $feuilles[$i] = $x;
            }
        }
        $tout = implode('', $feuilles);
        $this->assertStringContainsString('RI 12/26 du 02/03/2026', $tout);      // tableau des dossiers
        $this->assertStringContainsString('Moussa TRAORE', $tout);                // tableau des inculpés
        $this->assertStringContainsString('Masculin', $tout);
        $this->assertStringContainsString('Détenu', $tout);
        $this->assertStringContainsString('Crime', $tout);
        // Commissions rogatoires émises internationales de février : ligne 3 + 3 × 1 + 1 = 7, colonnes H à L.
        $this->assertMatchesRegularExpression('/<c r="I7"[^>]*><v>2<\/v>/', $tout);
        $this->assertMatchesRegularExpression('/<c r="L7"[^>]*><v>2<\/v>/', $tout);      // stock restant : 1 + 2 − 1
        $zip->close();
    }

    public function test_statistiques_de_l_instruction(): void
    {
        [$a, $inc] = $this->dossier();
        DossierInstruction::create(['affaire_id' => $a->id, 'date_cloture' => '2026-09-01']);
        SuiviInculpe::create(['partie_id' => $inc->id, 'statut' => 'detenu', 'traite' => true]);
        $u = $this->utilisateur(['stats_instruction.voir', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.instruction']);
        $this->connecte($u)->get(route('statistiques.instruction'))->assertOk();
        $d = $this->connecte($u)->getJson(route('statistiques.instruction.donnees', ['depuis' => 2025, 'jusqua' => 2026]))->assertOk()->json();
        $this->assertSame([1, 1, 0], [$d['par_an']['2026']['ouverts'], $d['par_an']['2026']['clos'], $d['par_an']['2026']['stock_fin']]);
        $this->assertSame([1, 1, 1, 1], [$d['inculpes']['total'], $d['inculpes']['hommes'], $d['inculpes']['detenus'], $d['inculpes']['traite']]);
        $this->assertSame(183, $d['durees']['moyenne']);        // du 02/03 au 01/09
        // Sans le droit de la famille « instruction », aucun dossier n'est compté ; sans le droit de la page, accès refusé.
        $sans = $this->utilisateur(['stats_instruction.voir', 'affaires.voir', 'affaires.toutes_chambres']);
        $this->assertSame(0, $this->connecte($sans)->getJson(route('statistiques.instruction.donnees'))->json('total_dossiers'));
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('statistiques.instruction'))->assertForbidden();
    }
}
