<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\DecisionPenale;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use App\Support\Statistiques\DonneesPenal;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Statistiques pénales d'après le répertoire pénal. */
class StatistiquesPenalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
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

    private function stats(array $en_plus = []): User
    {
        return $this->utilisateur(array_merge(['stats_penal.voir', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction'], $en_plus));
    }

    private function decision(string $sexe, string $nationalite, string $profession, string $issue, string $date, array $plus = []): DecisionPenale
    {
        static $n = 0;
        $n++;
        $a = Affaire::create([
            'serie' => 'RP', 'annee' => 2026, 'numero' => 4000 + $n, 'numero_complet' => 'RP-'.(4000 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-01-05', 'objet' => 'Vol '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'penal')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RP')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::value('id'), 'statut' => 'enrolee', 'date_premiere_audience' => '2026-02-01',
        ]);
        $p = Partie::create(['affaire_id' => $a->id, 'role' => 'prevenu', 'nature' => $sexe, 'nom' => 'X'.$n, 'cle' => 'x'.$n, 'nationalite' => $nationalite, 'profession' => $profession]);

        return DecisionPenale::create($plus + ['affaire_id' => $a->id, 'partie_id' => $p->id, 'date_decision' => $date, 'contradiction' => 'contradictoire', 'issue' => $issue]);
    }

    public function test_recapitulatif_condamnes_infractions_victimes_durees(): void
    {
        $admin = User::factory()->create();
        $this->decision('homme', 'burkinabè', 'maçon', 'condamne', '2026-03-01', ['emprisonnement_mois' => 12, 'sursis_mois' => 6, 'amende' => 100000, 'infraction_retenue' => 'Vol simple', 'victimes_hommes' => 1, 'victimes_filles' => 2, 'date_transmission_minute' => '2026-03-11']);
        $this->decision('femme', 'ivoirienne', 'commerçante', 'condamne', '2026-03-20', ['amende' => 50000, 'infraction_retenue' => 'Escroquerie', 'traite' => true]);
        $this->decision('homme', 'ivoirienne', 'élève', 'relaxe', '2026-04-02', ['infraction_retenue' => 'Vol simple']);
        $this->decision('homme', 'malienne', 'chauffeur', 'condamne', '2025-06-02', ['emprisonnement_mois' => 6, 'infraction_retenue' => 'Vol simple']);

        $r = DonneesPenal::recapitulatif(DonneesPenal::decisions($admin, 2025, 2026), 2025, 2026);
        $this->assertSame([1, 3], [$r['par_an'][2025]['decisions'], $r['par_an'][2026]['decisions']]);
        $this->assertSame([2, 1], [$r['par_an'][2026]['condamnes'], $r['par_an'][2026]['relaxes']]);
        $this->assertSame([1, 1], [$r['par_an'][2026]['hommes'], $r['par_an'][2026]['femmes']]);
        $this->assertSame('Vol simple', $r['infractions'][0]['libelle']);
        $this->assertSame(3, $r['infractions'][0]['n']);
        $this->assertSame(1, $r['par_an'][2026]['traite']);
        $this->assertSame(['hommes' => 1, 'femmes' => 0, 'garcons' => 0, 'filles' => 2], $r['victimes']);
        $this->assertSame(9.0, $r['peines']['moyenne_mois']);                                  // (12 + 6) / 2
        $this->assertSame(['burkinabè', 'ivoirienne', 'malienne'], collect($r['nationalites'])->pluck('libelle')->sort()->values()->all());
        $this->assertSame(45, $r['duree_audience']['moyenne']);                                 // 28, 47 et 60 jours après la 1re audience du 01/02
        $this->assertSame(10, $r['duree_minute']['moyenne']);                                   // 01/03 → 11/03
        $this->assertSame(3, $r['duree_minute']['manquantes']);                                 // trois décisions sans minute
        $this->assertSame([3, 1], [$r['duree_audience']['mesurees'], $r['duree_audience']['manquantes']]);   // la décision de 2025 précède la date de 1re audience : non mesurée, signalée
    }

    public function test_droits_exports_et_detail(): void
    {
        $this->decision('homme', 'ivoirienne', 'maçon', 'condamne', '2026-03-01', ['amende' => 1000]);
        $lecteur = $this->stats();
        $this->connecte($lecteur)->get(route('statistiques.penal'))->assertOk();
        $this->connecte($lecteur)->getJson(route('statistiques.penal.donnees'))->assertOk()->assertJsonPath('total', 1);
        $this->connecte($lecteur)->get(route('statistiques.penal.export', ['format' => 'xlsx']))->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('statistiques.penal'))->assertForbidden();
        $this->assertStringNotContainsString('RP-', $this->connecte($lecteur)->getJson(route('statistiques.penal.donnees'))->getContent());

        $classeur = fn (User $u) => (function () use ($u) {
            $rep = $this->connecte($u)->get(route('statistiques.penal.export', ['format' => 'xlsx', 'depuis' => 2026, 'jusqua' => 2026]))->assertOk();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($rep->baseResponse->getFile()->getPathname()) === true);
            $x = $zip->getFromName('xl/workbook.xml');
            $zip->close();

            return $x;
        })();
        $sansDetail = $classeur($this->stats(['stats_penal.exporter']));
        $avecDetail = $classeur($this->stats(['stats_penal.exporter', 'stats_penal.detail']));
        $this->assertStringContainsString('RECAP PENAL', $sansDetail);
        $this->assertStringNotContainsString('AUDIENCE PENALE', $sansDetail);     // pas de liste de décisions sans le droit du détail
        $this->assertStringContainsString('AUDIENCE PENALE', $avecDetail);

        $pdf = $this->connecte($this->stats(['stats_penal.exporter']))->get(route('statistiques.penal.export', ['format' => 'pdf']));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_les_nouveaux_champs_du_repertoire_sont_enregistres(): void
    {
        $greffe = $this->utilisateur(['repertoire_penal.voir', 'repertoire_penal.creer', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction']);
        $d = $this->decision('homme', 'ivoirienne', 'maçon', 'relaxe', '2026-03-01');
        $d->delete();
        $a = Affaire::where('serie', 'RP')->firstOrFail();
        $this->connecte($greffe)->post(route('decisions.repertoire_penal.store'), [
            'affaire_id' => $a->id, 'date_decision' => '2026-11-03', 'contradiction' => 'contradictoire', 'issue' => 'condamne', 'amende' => 20000, 'infraction_retenue' => 'Recel', 'nature_infraction' => 'delit',
            'traite' => 1, 'victimes_hommes' => 2, 'victimes_filles' => 1,
        ])->assertSessionHasNoErrors();
        $s = DecisionPenale::firstOrFail();
        $this->assertSame(['Recel', 'delit', true, 2, 1], [$s->infraction_retenue, $s->nature_infraction, (bool) $s->traite, $s->victimes_hommes, $s->victimes_filles]);
        $this->connecte($greffe)->post(route('decisions.repertoire_penal.store'), ['affaire_id' => $a->id, 'date_decision' => '2026-11-03', 'contradiction' => 'contradictoire', 'issue' => 'condamne', 'amende' => 1, 'nature_infraction' => 'bizarre'])->assertSessionHasErrors('nature_infraction');
    }
}
