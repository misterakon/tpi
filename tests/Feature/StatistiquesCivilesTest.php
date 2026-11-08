<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Profil;
use App\Models\ResultatAffaire;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Models\User;
use App\Support\Statistiques\CategoriesStats;
use App\Support\Statistiques\DonneesCivil;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Statistiques civiles : règles de calcul des classeurs du TPI, droits, exports. */
class StatistiquesCivilesTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
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
        return $this->utilisateur(array_merge(['stats_civil.voir', 'affaires.voir', 'affaires.toutes_chambres'], $en_plus));
    }

    private function affaire(string $nature, string $objet, string $premiere, array $plus = []): Affaire
    {
        static $n = 0;
        $n++;

        return Affaire::create($plus + [
            'serie' => 'RG', 'annee' => 2026, 'numero' => 3000 + $n, 'numero_complet' => 'RG-'.(3000 + $n).'/26', 'numero_saisi' => true, 'date_rg' => $premiere, 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('libelle', $nature)->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $premiere,
        ]);
    }

    private function juger(Affaire $a, string $date, string $issue = 'juge'): void
    {
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'statut' => 'publie']);
        RoleLigne::create(['role_id' => $role->id, 'affaire_id' => $a->id, 'rubrique' => 'nouvelles', 'ordre' => 1, 'issue' => $issue, 'objet' => 'x']);
    }

    public function test_categories_et_sous_categories_deduites(): void
    {
        $a = fn (string $nature, string $objet) => $this->affaire($nature, $objet, '2026-01-10');
        $this->assertSame('civile_ordinaire', CategoriesStats::categorie($a('Civile ordinaire', 'Paiement de loyers')));
        $this->assertSame('refere', CategoriesStats::categorie($a('Référé-expulsion', 'Expulsion')));
        $this->assertSame('heredite', CategoriesStats::categorie($a('État des personnes', "Établissement de la qualité d'héritiers")));
        $this->assertSame('matrimoniale', CategoriesStats::categorie($a('Civile ordinaire', 'Adoption plénière de X')));
        $this->assertSame('jex', CategoriesStats::categorie($a("Juge de l'exécution (voies d'exécution)", 'Saisie')));
        $e = $a('État des personnes', 'Jugement supplétif de naissance');
        $this->assertSame('jsn', CategoriesStats::sousCategorie($e, 'etat_civil'));
        $this->assertSame('jsd', CategoriesStats::sousCategorie($a('État des personnes', 'Jugement supplétif de décès'), 'etat_civil'));
        $this->assertSame('prenom', CategoriesStats::sousCategorie($a('État des personnes', 'Modification de prénom'), 'etat_civil'));
        $this->assertSame('dcm', CategoriesStats::sousCategorie($a('Matrimoniale', 'Divorce par consentement mutuel'), 'matrimoniale'));
        $this->assertSame('divorce_faute', CategoriesStats::sousCategorie($a('Matrimoniale', 'Divorce pour faute'), 'matrimoniale'));
        $this->assertSame('adoption_pleniere', CategoriesStats::sousCategorie($a('Matrimoniale', 'Adoption plénière'), 'matrimoniale'));
        $this->assertSame('nom', CategoriesStats::sousCategorie($e, 'etat_civil', 'nom'));   // la correction à la main l'emporte
    }

    public function test_regles_du_classeur_stock_nouvelles_videes(): void
    {
        $admin = User::factory()->create();
        // 2 affaires reçues en 2025, dont une jugée en 2025 et l'autre en 2026 ; 2 reçues en 2026 dont une radiée en 2026 ; 1 sans issue.
        $a1 = $this->affaire('Civile ordinaire', 'Loyers', '2025-03-10');
        $a2 = $this->affaire('Civile ordinaire', 'Dette', '2025-06-10');
        $a3 = $this->affaire('Commerciale', 'Vente', '2026-02-10');
        $a4 = $this->affaire('Commerciale', 'Livraison', '2026-04-10');
        $this->affaire('Civile ordinaire', 'En cours', '2026-05-10');
        $this->juger($a1, '2025-11-20');
        $this->juger($a2, '2026-03-12');
        $this->juger($a4, '2026-09-10', 'radie');
        $this->juger($a3, '2026-10-01', 'renvoi');   // un renvoi ne vide pas l'affaire

        $r = DonneesCivil::recapitulatif(DonneesCivil::affaires($admin), 2025, 2026, 2026);
        $t = $r['total'];
        $this->assertSame([2, 3], [$t['nouvelles'][2025], $t['nouvelles'][2026]]);
        $this->assertSame([1, 2], [$t['videes'][2025], $t['videes'][2026]]);
        $this->assertSame([1, 2], [$t['stock_fin'][2025], $t['stock_fin'][2026]]);       // 0+2-1 ; 1+3-2
        $this->assertSame([2, 4], [$t['a_juger'][2025], $t['a_juger'][2026]]);           // (2) ; (2-1)+3
        $this->assertSame([2, 1], [$r['par_categorie']['commerciale']['nouvelles'][2026], $r['par_categorie']['civile_ordinaire']['nouvelles'][2026]]);
        // Stock fin = stock début + nouvelles − vidées, au total comme par mois.
        $m = $r['mois']['total'];
        $this->assertSame(1, $m['stock_debut']);
        $this->assertSame($m['stock_debut'] + 3 - 2, $m['stock_fin'][12]);
        $this->assertSame(1, $m['videes'][3]);
        $this->assertSame(1, $m['videes'][9]);
    }

    public function test_detail_accord_rejet_et_qualification(): void
    {
        $greffe = $this->stats(['stats_civil.detail', 'stats_civil.qualifier', 'stats_civil.exporter']);
        $e = $this->affaire('État des personnes', 'Jugement supplétif de naissance', '2026-02-02');
        $this->juger($e, '2026-04-02');
        $d = fn () => $this->connecte($greffe)->getJson(route('statistiques.civil.donnees', ['depuis' => 2026, 'jusqua' => 2026]))->assertOk()->json();

        $this->assertSame(1, $d()['a_qualifier']['total']);
        $this->connecte($greffe)->post(route('statistiques.civil.qualifier'), ['affaire_id' => $e->id, 'resultat' => 'accord'])->assertSessionHasNoErrors();
        $json = $d();
        $this->assertSame(0, $json['a_qualifier']['total']);
        $this->assertSame(1, $json['etat_civil']['lignes']['jsn']['accordees'][2026]);
        $this->assertSame(0, $json['etat_civil']['lignes']['jsn']['rejetees'][2026]);

        // Sortie saisie à la main (PV de conciliation totale) : compte comme une affaire vidée à cette date.
        $c = $this->affaire('Matrimoniale', 'Divorce pour faute', '2026-01-05');
        $this->connecte($greffe)->post(route('statistiques.civil.qualifier'), ['affaire_id' => $c->id, 'type_sortie' => 'pv_conciliation', 'date_sortie' => '2026-06-01'])->assertSessionHasNoErrors();
        $this->connecte($greffe)->post(route('statistiques.civil.qualifier'), ['affaire_id' => $c->id, 'type_sortie' => 'pv_conciliation'])->assertSessionHasErrors('date_sortie');
        $this->assertSame(2, $d()['recap']['total']['videes'][2026]);

        $detail = $this->connecte($greffe)->getJson(route('statistiques.civil.detail', ['indicateur' => 'videes', 'annee' => 2026, 'categorie' => 'etat_civil']))->assertOk()->json();
        $this->assertSame(1, $detail['total']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'statistique_affaire_qualifiee']);
    }

    public function test_droits_et_chiffres_agreges(): void
    {
        $this->affaire('Civile ordinaire', 'Loyers', '2026-01-10');
        $lecteur = $this->stats();
        $this->connecte($lecteur)->get(route('statistiques.civil'))->assertOk();
        $this->connecte($lecteur)->getJson(route('statistiques.civil.donnees'))->assertOk();
        // Sans le droit « détail » : aucun nom ni numéro de dossier ne sort.
        $this->connecte($lecteur)->getJson(route('statistiques.civil.detail', ['indicateur' => 'nouvelles', 'annee' => 2026]))->assertForbidden();
        $this->connecte($lecteur)->get(route('statistiques.civil.export', ['format' => 'xlsx']))->assertForbidden();
        $this->connecte($lecteur)->post(route('statistiques.civil.qualifier'), ['affaire_id' => 1])->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('statistiques.civil'))->assertForbidden();
        $this->assertStringNotContainsString('RG-', $this->connecte($lecteur)->getJson(route('statistiques.civil.donnees'))->getContent());
    }

    public function test_perimetre_de_chambre(): void
    {
        $autre = Chambre::where('id', '!=', $this->chambre->id)->firstOrFail();
        $this->affaire('Civile ordinaire', 'Ici', '2026-01-10');
        $this->affaire('Civile ordinaire', 'Ailleurs', '2026-01-11', ['chambre_id' => $autre->id]);
        $this->assertSame(2, DonneesCivil::affaires(User::factory()->create())->count());
        $limite = $this->utilisateur(['stats_civil.voir', 'affaires.voir']);
        $this->assertSame(0, DonneesCivil::affaires($limite)->count());      // aucune affectation : rien n'est visible
    }

    public function test_exports_excel_et_pdf_au_modele(): void
    {
        $greffe = $this->stats(['stats_civil.exporter']);
        $this->affaire('Civile ordinaire', 'Loyers', '2026-01-10');
        $xlsx = $this->connecte($greffe)->get(route('statistiques.civil.export', ['format' => 'xlsx', 'depuis' => 2025, 'jusqua' => 2026]));
        $xlsx->assertOk()->assertHeader('Content-Disposition');
        $fichier = $xlsx->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($fichier) === true);
        $classeur = $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('RECAP ENROL. CIV PAR AN', $classeur);
        $this->assertStringContainsString('RECAP ENROL. CIV PAR MOIS', $classeur);
        $feuille = $zip->getFromName('xl/worksheets/sheet1.xml').$zip->getFromName('xl/sharedStrings.xml');
        $this->assertStringContainsString('Civile ordinaire', $feuille);
        $this->assertStringContainsString("Etat des stocks en fin d", $feuille);
        $zip->close();

        $pdf = $this->connecte($greffe)->get(route('statistiques.civil.export', ['format' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
}
