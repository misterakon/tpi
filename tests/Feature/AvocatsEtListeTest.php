<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use App\Support\ExportAffaires;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Référentiel des avocats, et filtres + exports de la liste des affaires. */
class AvocatsEtListeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'automatique')]);
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function formulaire(string $avocat = '', string $nom = 'KOUASSI'): array
    {
        return [
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->firstOrFail()->id,
            'objet' => 'Demande en paiement de loyers impayés',
            'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->firstOrFail()->id,
            'matiere_id' => Matiere::firstOrFail()->id,
            'chambre_id' => Chambre::firstOrFail()->id,
            'parties' => [
                0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => $nom, 'prenoms' => 'Yao Marc', 'avocat' => $avocat],
                1 => ['role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'Société ABC SARL'],
            ],
        ];
    }

    private function affaire(array $attributs = []): Affaire
    {
        static $n = 0;
        $n++;

        return Affaire::create($attributs + [
            'serie' => 'RG', 'annee' => 2026, 'numero' => 5000 + $n, 'numero_complet' => 'RG-'.(5000 + $n).'/26', 'date_rg' => '2026-10-20', 'objet' => 'Loyers impayés',
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::firstOrFail()->id, 'statut' => 'enrolee',
        ]);
    }

    // ---------------- avocats ----------------

    public function test_une_meme_personne_a_une_seule_cle(): void
    {
        $cle = Avocat::cle('YAO Aminata');
        foreach (['Maître YAO Aminata', 'Me Aminata Yao', 'maitre  yao   AMINATA', 'YAO, Aminata'] as $variante) {
            $this->assertSame($cle, Avocat::cle($variante), $variante);
        }
        $this->assertSame(Avocat::cle('ABI & Associés'), Avocat::cle('Cabinet ABI et associés') === '' ? '' : Avocat::cle('Cabinet ABI & Associés'));
        $this->assertNotSame($cle, Avocat::cle('YAO Marie'));
        $this->assertSame('', Avocat::cle('Maître'));
    }

    public function test_decoupage_du_nom_saisi(): void
    {
        $this->assertSame(['type' => 'avocat', 'nom' => 'YAO', 'prenoms' => 'Aminata'], Avocat::decouper('Maître YAO Aminata'));
        $this->assertSame(['type' => 'avocat', 'nom' => 'N\'GUESSAN', 'prenoms' => 'Paul'], Avocat::decouper('Me N\'GUESSAN Paul'));
        $this->assertSame(['type' => 'cabinet', 'nom' => 'ABI & Associés', 'prenoms' => null], Avocat::decouper('Cabinet ABI & Associés'));
        $this->assertSame('avocat', Avocat::decouper('Koffi')['type']);
    }

    public function test_enrolement_cree_la_fiche_a_completer_puis_la_reutilise(): void
    {
        $greffier = $this->avecDroits(['enrolement.voir', 'enrolement.creer', 'affaires.voir', 'affaires.toutes_chambres', 'avocats.creer']);

        $this->connecte($greffier)->post(route('affaires.enrolement.store'), $this->formulaire('Maître YAO Aminata'))->assertSessionHasNoErrors();
        $fiche = Avocat::firstOrFail();
        $this->assertTrue($fiche->a_completer);
        $this->assertSame('Maître YAO Aminata', $fiche->libelle());
        $this->assertSame($fiche->id, Partie::where('nom', 'KOUASSI')->value('avocat_id'));

        // Une autre écriture du même nom retombe sur la même fiche, avec l'écriture canonique.
        $this->connecte($greffier)->post(route('affaires.enrolement.store'), $this->formulaire('Me Aminata Yao', 'TRAORE'))->assertSessionHasNoErrors();
        $this->assertSame(1, Avocat::count());
        $this->assertSame(2, $fiche->parties()->count());
        $this->assertSame(['Maître YAO Aminata'], Partie::whereIn('nom', ['KOUASSI', 'TRAORE'])->pluck('avocat')->unique()->values()->all());
    }

    public function test_sans_le_droit_de_creer_le_nom_reste_libre(): void
    {
        $greffier = $this->avecDroits(['enrolement.voir', 'enrolement.creer', 'affaires.voir', 'affaires.toutes_chambres']);

        $this->connecte($greffier)->post(route('affaires.enrolement.store'), $this->formulaire('Maître DIALLO Fanta'))->assertSessionHasNoErrors();
        $this->assertSame(0, Avocat::count());
        $partie = Partie::where('nom', 'KOUASSI')->firstOrFail();
        $this->assertNull($partie->avocat_id);
        $this->assertSame('Maître DIALLO Fanta', $partie->avocat);

        // Une fois la fiche créée, le même nom s'y rattache même pour ce greffier.
        Avocat::create(Avocat::decouper('Maître DIALLO Fanta') + ['cle' => Avocat::cle('DIALLO Fanta'), 'actif' => true]);
        $this->connecte($greffier)->post(route('affaires.enrolement.store'), $this->formulaire('diallo fanta', 'TRAORE'))->assertSessionHasNoErrors();
        $this->assertSame(Avocat::value('id'), Partie::where('nom', 'TRAORE')->value('avocat_id'));
    }

    public function test_page_des_avocats_et_droits(): void
    {
        $admin = User::factory()->create();
        $lecteur = $this->avecDroits(['avocats.voir']);
        $aucun = $this->avecDroits(['affaires.voir']);

        $this->connecte($aucun)->get(route('affaires.avocats'))->assertForbidden();
        $this->connecte($lecteur)->get(route('affaires.avocats'))->assertOk()->assertSee('Avocats et cabinets');
        $this->connecte($lecteur)->post(route('affaires.avocats.store'), ['type' => 'avocat', 'nom' => 'TOURE'])->assertForbidden();

        $this->connecte($admin)->post(route('affaires.avocats.store'), ['type' => 'avocat', 'nom' => 'TOURÉ', 'prenoms' => 'Salimata', 'telephone' => '07 00 00 00 00', 'email' => 'salimata@example.test', 'actif' => 1])->assertSessionHasNoErrors();
        $a = Avocat::firstOrFail();
        $this->assertSame('Maître TOURÉ Salimata', $a->libelle());
        $this->assertFalse($a->a_completer);
        $lignes = $this->lignesServeur($lecteur, 'affaires.avocats.donnees');
        $this->assertStringContainsString('Maître TOURÉ Salimata', $lignes);
        $this->assertStringContainsString('salimata@example.test', $lignes);
        $this->assertDatabaseHas('journal_audit', ['action' => 'avocat_cree']);

        // Doublon, e-mail invalide, nom vide.
        $this->connecte($admin)->post(route('affaires.avocats.store'), ['type' => 'avocat', 'nom' => 'Touré', 'prenoms' => 'SALIMATA'])->assertSessionHasErrors('nom');
        $this->connecte($admin)->post(route('affaires.avocats.store'), ['type' => 'avocat', 'nom' => 'KONE', 'email' => 'pas-un-email'])->assertSessionHasErrors('email');
        $this->connecte($admin)->post(route('affaires.avocats.store'), ['type' => 'avocat', 'nom' => ''])->assertSessionHasErrors('nom');
        $this->assertSame(1, Avocat::count());
    }

    public function test_modifier_met_a_jour_les_affaires_et_supprimer_est_protege(): void
    {
        $admin = User::factory()->create();
        $a = Avocat::create(['type' => 'avocat', 'nom' => 'YAO', 'prenoms' => 'Aminata', 'cle' => Avocat::cle('YAO Aminata'), 'actif' => true, 'a_completer' => true]);
        $affaire = $this->affaire();
        $affaire->parties()->create(['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'KOUASSI', 'cle' => 'kouassi', 'avocat' => $a->libelle(), 'avocat_id' => $a->id]);

        $this->connecte($admin)->post(route('affaires.avocats.store'), ['id_' => $a->id, 'type' => 'avocat', 'nom' => 'YAO', 'prenoms' => 'Aminata Rose', 'actif' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($a->refresh()->a_completer);
        $this->assertSame('Maître YAO Aminata Rose', $affaire->parties()->value('avocat'));

        $this->connecte($admin)->deleteJson(route('affaires.avocats.delete', $a->id))->assertOk()->assertJson(['status' => false]);
        $this->assertSame(1, Avocat::count());

        $libre = Avocat::create(['type' => 'cabinet', 'nom' => 'ABI & Associés', 'cle' => Avocat::cle('ABI & Associés'), 'actif' => true]);
        $this->connecte($admin)->deleteJson(route('affaires.avocats.delete', $libre->id))->assertOk()->assertJson(['status' => true]);
        $this->assertSame(1, Avocat::count());
    }

    public function test_le_formulaire_propose_les_avocats_actifs(): void
    {
        $admin = User::factory()->create();
        Avocat::create(['type' => 'avocat', 'nom' => 'YAO', 'prenoms' => 'Aminata', 'cle' => Avocat::cle('YAO Aminata'), 'actif' => true]);
        Avocat::create(['type' => 'avocat', 'nom' => 'ANCIEN', 'prenoms' => 'Jean', 'cle' => Avocat::cle('ANCIEN Jean'), 'actif' => false]);

        $page = $this->connecte($admin)->get(route('affaires.liste'))->assertOk();
        $page->assertSee('data-libre', false)->assertSee('Maître YAO Aminata')->assertDontSee('Maître ANCIEN Jean');
    }

    // ---------------- filtres et exports de la liste ----------------

    public function test_filtres_de_la_liste(): void
    {
        $admin = User::factory()->create();
        $chambres = Chambre::orderBy('id')->take(2)->get();
        $ancienne = $this->affaire(['date_rg' => '2026-01-10', 'chambre_id' => $chambres[0]->id]);
        $recente = $this->affaire(['date_rg' => '2026-10-20', 'chambre_id' => $chambres[1]->id]);
        $annulee = $this->affaire(['date_rg' => '2026-10-21', 'chambre_id' => $chambres[1]->id, 'statut' => 'annulee']);

        $voit = fn (array $q) => collect([$ancienne, $recente, $annulee])->filter(fn ($a) => str_contains($this->lignesServeur($admin, 'affaires.liste.donnees', $q), route('affaires.liste.fiche', $a->id).'"'))->pluck('id')->all();

        $this->assertSame([$ancienne->id, $recente->id, $annulee->id], $voit([]));
        $this->assertSame([$ancienne->id], $voit(['chambre' => $chambres[0]->id]));
        $this->assertSame([$recente->id, $annulee->id], $voit(['du' => '2026-06-01']));
        $this->assertSame([$ancienne->id], $voit(['au' => '2026-06-01']));
        $this->assertSame([$annulee->id], $voit(['statut' => 'annulee']));
        $this->assertSame([$recente->id], $voit(['chambre' => $chambres[1]->id, 'statut' => 'enrolee']));
        // Des valeurs invalides sont ignorées, jamais exécutées.
        $this->assertSame([$ancienne->id, $recente->id, $annulee->id], $voit(['du' => 'pas-une-date', 'statut' => "x' or 1=1", 'chambre' => 'abc']));
    }

    public function test_export_excel_et_pdf(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire(['objet' => '=HYPERLINK("http://exemple.test")']);
        $a->parties()->create(['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'KOUASSI', 'prenoms' => 'Yao', 'cle' => 'kouassi yao']);
        $this->affaire(['date_rg' => '2025-01-05']);

        $r = $this->connecte($admin)->get(route('affaires.liste.export', ['format' => 'xlsx', 'du' => '2026-01-01']))->assertOk();
        $this->assertStringContainsString('.xlsx', $r->headers->get('Content-Disposition'));
        $chemin = $r->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($chemin) === true);
        $feuille = $zip->getFromName('xl/worksheets/sheet1.xml').$zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        $this->assertStringContainsString('KOUASSI', $feuille);
        $this->assertStringContainsString('=HYPERLINK', $feuille);
        $this->assertStringNotContainsString('<f>', $feuille);              // du texte, jamais une formule
        $this->assertSame(0, substr_count($feuille, '05/01/2025'));

        $p = $this->connecte($admin)->get(route('affaires.liste.export', ['format' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $p->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $p->getContent());
        $this->assertDatabaseHas('journal_audit', ['action' => 'liste_exportee']);
    }

    public function test_l_export_est_un_droit_a_part_et_respecte_les_chambres(): void
    {
        $sans = $this->avecDroits(['affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($sans)->get(route('affaires.liste.export', ['format' => 'pdf']))->assertForbidden();
        $this->connecte($sans)->get(route('affaires.liste'))->assertOk()->assertDontSee('au format PDF', false);

        $avec = $this->avecDroits(['affaires.voir', 'affaires.exporter']);   // pas de droit « toutes chambres » ni d'agent : rien à exporter
        $this->affaire();
        $this->connecte($avec)->get(route('affaires.liste'))->assertSee('au format PDF', false);
        $lignes = ExportAffaires::lignes(ExportAffaires::appliquer(Affaire::visiblesPour($avec), ExportAffaires::filtres([]))->get());
        $this->assertSame([], $lignes);
        $this->connecte($avec)->get(route('affaires.liste.export', ['format' => 'xlsx']))->assertOk();
    }
}
