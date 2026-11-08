<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\ImportReprise;
use App\Models\PieceDossier;
use App\Models\Profil;
use App\Models\User;
use App\Support\Reprise\FichierTableur;
use App\Support\Reprise\LigneReprise;
use App\Support\Reprise\ModeleReprise;
use App\Support\Reprise\PartiesReprise;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

/** Reprise de l'existant : import sécurisé et strict d'un registre Excel, à partir du modèle. */
class RepriseExistantTest extends TestCase
{
    use RefreshDatabase;

    private array $aSupprimer = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->aSupprimer as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    /** Un vrai classeur .xlsx. @param list<list<mixed>> $lignes */
    private function xlsx(array $lignes, string $feuille = 'Registre', string $nom = 'registre.xlsx'): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'tst').'.xlsx';
        $w = new Writer;
        $w->openToFile($chemin);
        $w->getCurrentSheet()->setName($feuille);
        foreach ($lignes as $l) {
            $w->addRow(Row::fromValues($l));
        }
        $w->close();
        $this->aSupprimer[] = $chemin;

        return new UploadedFile($chemin, $nom, null, null, true);
    }

    /** Un classeur conforme au modèle : titres exacts puis les lignes. @param list<list<mixed>> $donnees */
    private function classeur(array $donnees, string $serie = 'RG'): UploadedFile
    {
        return $this->xlsx(array_merge([ModeleReprise::titres($serie)], $donnees));
    }

    private function registre(): UploadedFile
    {
        return $this->classeur([
            ['1391/25', '12/03/2025', 'Paiement de loyers impayés', 'Mme KOFFI Adjoua', 'Société BATIMAT SARL', '1ère chambre civile', '', '', 'A-77'],
            ['RG 1392/25', '2025-03-14', 'Expulsion', 'DIABY Karamoko ; TRAORE Salif', 'GNAGNE Brice', '2e chambre civile', '', '', ''],
            ['1393/25', '31/02/2025', 'Date impossible', 'KONE Awa', 'ABI', '1ère chambre civile', '', '', ''],
            ['1391/25', '12/03/2025', 'Même numéro que la première ligne', 'KOUASSI Yao', 'Société X', '1ère chambre civile', '', '', ''],
            ['1394/25', '20/03/2025', 'Chambre inconnue', 'BAMBA Fatoumata', 'Société Y', 'Chambre imaginaire', '', '', ''],
            ['1000/25', '05/01/2025', "Déjà dans l'application", 'YAO Marc', 'Z', '1ère chambre civile', '', '', ''],
        ]);
    }

    private function propre(): UploadedFile
    {
        return $this->classeur([
            ['1391/25', '12/03/2025', 'Paiement de loyers impayés', 'Mme KOFFI Adjoua', 'Société BATIMAT SARL', '1ère chambre civile', '', '20/05/2026', 'A-77'],
            ['1392/25', '14/03/2025', 'Expulsion', 'DIABY Karamoko ; TRAORE Salif', 'GNAGNE Brice', '2e chambre civile', '', '', ''],
        ]);
    }

    private function televerser(User $u, ?UploadedFile $f = null, string $serie = 'RG')
    {
        return $this->connecte($u)->postJson(route('affaires.reprise.televerser'), ['fichier' => $f ?? $this->registre(), 'serie' => $serie]);
    }

    private function analyse(User $u, ?UploadedFile $f = null, string $serie = 'RG'): string
    {
        $ref = $this->televerser($u, $f, $serie)->assertOk()->json('reference');
        $this->connecte($u)->postJson(route('affaires.reprise.analyser', $ref))->assertOk()->assertJson(['status' => true]);

        return $ref;
    }

    private function controle(User $u, string $ref): array
    {
        do {
            $j = $this->connecte($u)->postJson(route('affaires.reprise.controler', $ref))->assertOk()->json();
        } while (! $j['termine']);

        return $j;
    }

    // ---------------- le modèle ----------------

    public function test_le_modele_est_telechargeable_et_complet(): void
    {
        $admin = User::factory()->create();
        foreach (['RG', 'RP', 'RI', 'RE'] as $serie) {
            $r = $this->connecte($admin)->get(route('affaires.reprise.modele', $serie))->assertOk();
            $this->assertStringContainsString('modele-reprise-'.$serie.'.xlsx', $r->headers->get('Content-Disposition'));
            $chemin = $r->baseResponse->getFile()->getPathname();
            FichierTableur::verifier($chemin, 5 * 1048576);
            $this->assertSame(["Mode d'emploi", 'Registre', 'Exemple', 'Listes'], FichierTableur::feuilles($chemin));
            $entete = [];
            FichierTableur::parcourir($chemin, 'Registre', function ($n, $c) use (&$entete) {
                $entete = $c;

                return false;
            });
            $this->assertSame(ModeleReprise::titres($serie), $entete);
            $this->assertNull(ModeleReprise::verifierEntete($entete, $serie));
        }
        // Les listes reprennent les chambres de l'application ; l'onglet « Registre » ne contient que les titres.
        $chemin = $this->connecte($admin)->get(route('affaires.reprise.modele', 'RG'))->baseResponse->getFile()->getPathname();
        $listes = [];
        FichierTableur::parcourir($chemin, 'Listes', function ($n, $c) use (&$listes) {
            $listes[] = $c[0];
        });
        $this->assertContains('1re chambre civile', $listes);
        $this->assertNotContains('1re chambre correctionnelle', $listes);
        $lignes = 0;
        FichierTableur::parcourir($chemin, 'Registre', function () use (&$lignes) {
            $lignes++;
        });
        $this->assertSame(1, $lignes);

        $this->connecte($admin)->get('/affaires/reprise/modele/XX')->assertNotFound();
        $this->connecte($this->avecDroits(['affaires.voir']))->get(route('affaires.reprise.modele', 'RG'))->assertForbidden();
    }

    public function test_un_fichier_excel_qui_ne_respecte_pas_le_modele_est_refuse(): void
    {
        $admin = User::factory()->create();
        $titres = ModeleReprise::titres('RG');

        // N'importe quel classeur Excel : pas d'onglet « Registre ».
        $r = $this->televerser($admin, $this->xlsx([['Nom', 'Prénom'], ['A', 'B']], 'Feuil1'))->assertStatus(422)->assertJsonFragment(['status' => false]);
        $this->assertStringContainsString('onglet « Registre »', $r->json('message'));
        // Le bon onglet mais d'autres titres.
        $this->assertStringContainsString('colonne A', $this->televerser($admin, $this->xlsx([['Nom', 'Prénom'], ['A', 'B']]))->json('message'));
        // Un titre modifié au milieu.
        $abime = $titres;
        $abime[3] = 'Les gens';
        $r = $this->televerser($admin, $this->xlsx([$abime, ['1/25']]))->assertStatus(422);
        $this->assertStringContainsString('colonne D', $r->json('message'));
        $this->assertStringContainsString($titres[3], $r->json('message'));
        // Une colonne en trop.
        $this->assertStringContainsString('colonnes en plus', $this->televerser($admin, $this->xlsx([array_merge($titres, ['Intrus']), ['1']]))->json('message'));
        // Le modèle d'un autre registre.
        $this->televerser($admin, $this->classeur([], 'RP'), 'RG')->assertStatus(422);
        $this->televerser($admin, $this->classeur([], 'RG'), 'RP')->assertStatus(422);
        // Titres corrects mais sans aucune ligne : l'analyse le refuse.
        $ref = $this->televerser($admin, $this->classeur([]))->assertOk()->json('reference');
        $this->connecte($admin)->postJson(route('affaires.reprise.analyser', $ref))->assertStatus(422);

        $this->assertSame(1, ImportReprise::count());
        $this->assertCount(1, Storage::disk('local')->allFiles());                 // seuls les fichiers acceptés sont conservés
        $this->assertDatabaseHas('journal_audit', ['action' => 'reprise_refusee']);
        // La casse et l'étoile ne comptent pas : un titre légèrement retapé reste accepté.
        $legers = array_map(fn ($t) => mb_strtoupper(str_replace(' *', '', $t)), $titres);
        $this->televerser($admin, $this->xlsx([$legers, ['1391/25']]))->assertOk();
    }

    // ---------------- le parcours ----------------

    public function test_controle_ligne_par_ligne_et_tout_ou_rien(): void
    {
        $admin = User::factory()->create();
        Affaire::create(['serie' => 'RG', 'annee' => 2025, 'numero' => 1000, 'numero_complet' => 'RG-1000/25', 'date_rg' => '2025-01-05', 'objet' => 'Existante', 'chambre_id' => Chambre::value('id'), 'statut' => 'enrolee']);

        $ref = $this->analyse($admin);
        $c = $this->controle($admin, $ref);
        $this->assertSame(100, $c['pourcentage']);
        $this->assertSame([2, 2, 2], [$c['valides'], $c['erreurs'], $c['doublons']]);         // 2 valides, date impossible + chambre inconnue, doublon dans le fichier + déjà enregistrée

        $etat = $this->connecte($admin)->getJson(route('affaires.reprise.etat', $ref))->assertOk()->json();
        $this->assertFalse($etat['importable']);
        $this->assertCount(6, $etat['lignes']);
        $par = collect($etat['lignes'])->keyBy('ligne');
        $this->assertSame(['nouvelle', 'nouvelle', 'erreur', 'doublon', 'erreur', 'doublon'], array_values($par->pluck('etat')->all()));
        $this->assertStringContainsString('Date illisible', $par[4]['messages'][0]['texte']);
        $this->assertStringContainsString('Chambre', $par[6]['messages'][0]['texte']);
        $this->assertStringContainsString('ligne 2', $par[5]['messages'][0]['texte']);

        // Tout ou rien : tant qu'une ligne est en erreur, rien n'est importé.
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertStatus(422);
        $this->assertSame(1, Affaire::count());
        $this->connecte($admin)->get(route('affaires.reprise.rapport', $ref))->assertOk();
    }

    public function test_parcours_complet_et_import(): void
    {
        $admin = User::factory()->create();
        $ref = $this->analyse($admin, $this->propre());
        $this->assertSame(26, strlen($ref));
        $c = $this->controle($admin, $ref);
        $this->assertSame([2, 0, 0], [$c['valides'], $c['erreurs'], $c['doublons']]);
        $etat = $this->connecte($admin)->getJson(route('affaires.reprise.etat', $ref))->assertOk()->json();
        $this->assertTrue($etat['importable']);
        $this->assertSame(0, Affaire::count());                                              // rien n'est écrit avant l'importation

        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk()->assertJson(['termine' => true, 'importees' => 2, 'pourcentage' => 100]);

        $a1 = Affaire::where('numero', 1391)->firstOrFail();
        $this->assertTrue($a1->numero_saisi);
        $this->assertSame('RG-1391/25', $a1->numero_complet);
        $this->assertSame('A-77', $a1->ancien_numero);
        $this->assertSame('2026-05-20', $a1->date_premiere_audience->toDateString());
        $this->assertSame('1re chambre civile', $a1->chambre->libelle);
        $this->assertSame(ImportReprise::firstOrFail()->id, $a1->reprise_import_id);
        $this->assertEqualsCanonicalizing(['demandeur' => 'femme', 'defendeur' => 'morale'], $a1->parties->pluck('nature', 'role')->all());
        $this->assertSame(['DIABY', 'TRAORE'], Affaire::where('numero', 1392)->firstOrFail()->parties->where('role', 'demandeur')->pluck('nom')->sort()->values()->all());

        $import = ImportReprise::firstOrFail();
        $this->assertSame('importe', $import->statut);
        $this->assertNull($import->chemin);
        $this->assertSame([], Storage::disk('local')->allFiles());                            // le fichier source est supprimé
        $this->assertDatabaseHas('journal_audit', ['action' => 'reprise_importee']);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a1->id))->assertOk()->assertSee("Affaire reprise d'un registre Excel", false);
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertStatus(409);             // une étape passée ne se rejoue pas
        $this->connecte($admin)->postJson(route('affaires.reprise.controler', $ref))->assertStatus(409);
    }

    public function test_reprise_a_plusieurs_paquets_avec_progression(): void
    {
        $admin = User::factory()->create();
        $donnees = [];
        for ($i = 1; $i <= 330; $i++) {
            $donnees[] = [(string) $i.'/24', '10/10/2024', 'Loyers impayés '.$i, 'KOUASSI Yao '.$i, 'Société ABC SARL', '2e chambre civile', '', '', ''];
        }
        $ref = $this->analyse($admin, $this->classeur($donnees));
        $premier = $this->connecte($admin)->postJson(route('affaires.reprise.controler', $ref))->assertOk()->json();
        $this->assertFalse($premier['termine']);
        $this->assertGreaterThan(0, $premier['pourcentage']);
        $this->assertLessThan(100, $premier['pourcentage']);
        $this->controle($admin, $ref);

        $paquets = 0;
        do {
            $j = $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk()->json();
            $paquets++;
        } while (! $j['termine']);
        $this->assertGreaterThanOrEqual(4, $paquets);
        $this->assertSame(330, Affaire::count());
        $this->assertSame('Société ABC SARL', Affaire::where('numero', 7)->firstOrFail()->parties->firstWhere('role', 'defendeur')->denomination);
    }

    public function test_pour_le_penal_le_modele_et_les_roles_sont_ceux_du_penal(): void
    {
        $admin = User::factory()->create();
        $ref = $this->analyse($admin, $this->classeur([['1334/26', '12/10/2026', 'Destruction volontaire de biens', 'JOHNSON Wedha', 'Société EXEMPLE SARL', '1re chambre correctionnelle', '', '', '']], 'RP'), 'RP');
        $c = $this->controle($admin, $ref);
        $this->assertSame([1, 0], [$c['valides'], $c['erreurs']]);
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk();
        $a = Affaire::firstOrFail();
        $this->assertSame(['RP', 'RP 1334/26'], [$a->serie, $a->numeroAffichage()]);
        $this->assertEqualsCanonicalizing(['prevenu', 'victime'], $a->parties->pluck('role')->all());
        // Une chambre civile n'est pas reconnue au pénal.
        $ref2 = $this->analyse($admin, $this->classeur([['1335/26', '12/10/2026', 'Vol', 'DOSSO Ibrahim', '', '1ère chambre civile', '', '', '']], 'RP'), 'RP');
        $this->assertSame(1, $this->controle($admin, $ref2)['erreurs']);
    }

    // ---------------- sécurité ----------------

    public function test_fichiers_dangereux_refuses(): void
    {
        $admin = User::factory()->create();
        $this->televerser($admin, UploadedFile::fake()->createWithContent('registre.xlsx', 'Ceci est du texte, pas un classeur'))->assertStatus(422)->assertJson(['status' => false]);
        $this->televerser($admin, UploadedFile::fake()->createWithContent('registre.xls', "PK\x03\x04"))->assertStatus(422);
        $this->televerser($admin, UploadedFile::fake()->createWithContent('registre.csv', "a;b\n1;2"))->assertStatus(422);
        $this->televerser($admin, UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'))->assertStatus(422);

        $avecMacros = $this->classeur([['1/25']]);
        $zip = new ZipArchive;
        $zip->open($avecMacros->getPathname());
        $zip->addFromString('xl/vbaProject.bin', 'macro');
        $zip->close();
        $this->assertStringContainsString('macros', $this->televerser($admin, $avecMacros)->assertStatus(422)->json('message'));

        $zipQuelconque = tempnam(sys_get_temp_dir(), 'zip').'.xlsx';
        $this->aSupprimer[] = $zipQuelconque;
        $z = new ZipArchive;
        $z->open($zipQuelconque, ZipArchive::CREATE);
        $z->addFromString('lisez-moi.txt', 'bonjour');
        $z->close();
        $this->televerser($admin, new UploadedFile($zipQuelconque, 'x.xlsx', null, null, true))->assertStatus(422);

        $this->assertSame(0, ImportReprise::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_les_formules_ne_sont_jamais_executees(): void
    {
        $admin = User::factory()->create();
        $ref = $this->analyse($admin, $this->classeur([['1/25', '10/01/2025', '=HYPERLINK("http://exemple.test","clic")', '=cmd|calc!A1', '', '1ère chambre civile', '', '', '']]));
        $this->controle($admin, $ref);
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk();
        $a = Affaire::firstOrFail();
        $this->assertStringContainsString('HYPERLINK', $a->objet);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertSee('=HYPERLINK(&quot;http://exemple.test&quot;', false);
    }

    public function test_droits_et_acces(): void
    {
        $aucun = $this->avecDroits(['affaires.voir']);
        $lecteur = $this->avecDroits(['reprise.voir']);
        $preparateur = $this->avecDroits(['reprise.voir', 'reprise.creer', 'reprise.modifier']);
        $admin = User::factory()->create();

        $this->connecte($aucun)->get(route('affaires.reprise'))->assertForbidden();
        $this->connecte($lecteur)->get(route('affaires.reprise'))->assertOk()->assertDontSee('id="imp_stepper"', false);
        $this->televerser($lecteur)->assertForbidden();

        $ref = $this->analyse($preparateur, $this->propre());
        $this->controle($preparateur, $ref);
        $this->connecte($preparateur)->postJson(route('affaires.reprise.importer', $ref))->assertForbidden();   // le droit spécial est à part
        $this->connecte($lecteur)->postJson(route('affaires.reprise.controler', $ref))->assertForbidden();
        $this->connecte($aucun)->getJson(route('affaires.reprise.etat', $ref))->assertForbidden();
        $this->connecte($aucun)->get(route('affaires.reprise.rapport', $ref))->assertForbidden();
        $this->connecte($preparateur)->postJson(route('affaires.reprise.annuler', $ref))->assertForbidden();
        $this->assertSame(0, Affaire::count());

        $this->connecte($admin)->getJson('/affaires/reprise/1/etat')->assertNotFound();                          // jamais d'identifiant interne
        $this->connecte($admin)->getJson(route('affaires.reprise.etat', str_repeat('A', 26)))->assertNotFound();
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk();
        $this->assertGreaterThan(0, Affaire::count());
    }

    public function test_un_seul_paquet_a_la_fois_et_etats_respectes(): void
    {
        $admin = User::factory()->create();
        $ref = $this->televerser($admin, $this->propre())->json('reference');
        $this->connecte($admin)->postJson(route('affaires.reprise.controler', $ref))->assertStatus(409);     // pas de contrôle avant l'analyse
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertStatus(409);
        $this->connecte($admin)->postJson(route('affaires.reprise.analyser', $ref))->assertOk();

        $import = ImportReprise::firstOrFail();
        $import->forceFill(['verrou_jusqu_a' => now()->addMinute()])->save();
        $this->connecte($admin)->postJson(route('affaires.reprise.controler', $ref))->assertStatus(409)->assertJsonFragment(['status' => false]);
        $import->forceFill(['verrou_jusqu_a' => null])->save();
        $this->controle($admin, $ref);
    }

    public function test_annuler_un_import_retire_ses_affaires_sauf_si_elles_ont_ete_travaillees(): void
    {
        $admin = User::factory()->create();
        $ref = $this->analyse($admin, $this->propre());
        $this->controle($admin, $ref);
        $this->connecte($admin)->postJson(route('affaires.reprise.importer', $ref))->assertOk();
        $this->assertSame(2, Affaire::count());

        $travaillee = Affaire::where('numero', 1391)->firstOrFail();
        PieceDossier::create(['affaire_id' => $travaillee->id, 'type' => 'autre', 'titre' => 'Pièce', 'chemin' => 'x.pdf', 'nom_original' => 'x.pdf', 'mime' => 'application/pdf', 'taille' => 1, 'empreinte' => str_repeat('a', 64)]);
        $this->connecte($admin)->postJson(route('affaires.reprise.annuler', $ref))->assertStatus(422);
        $this->assertSame(2, Affaire::count());

        PieceDossier::query()->delete();
        $this->connecte($admin)->postJson(route('affaires.reprise.annuler', $ref))->assertOk()->assertJson(['status' => true]);
        $this->assertSame(0, Affaire::count());
        $this->assertSame('annule', ImportReprise::firstOrFail()->statut);
        $this->assertDatabaseHas('journal_audit', ['action' => 'reprise_annulee']);
        $this->connecte($admin)->postJson(route('affaires.reprise.annuler', $ref))->assertStatus(422);
    }

    public function test_abandonner_un_import_supprime_le_fichier(): void
    {
        $admin = User::factory()->create();
        $ref = $this->televerser($admin, $this->propre())->json('reference');
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->connecte($admin)->postJson(route('affaires.reprise.annuler', $ref))->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---------------- lecture des valeurs ----------------

    public function test_lecture_des_numeros_et_des_dates(): void
    {
        $this->assertSame(['numero' => 1391, 'annee' => 2025], LigneReprise::numero('1391/25', 'RG', null));
        $this->assertSame(['numero' => 1391, 'annee' => 2025], LigneReprise::numero(' RG 1391 / 2025 ', 'RG', null));
        $this->assertSame(['numero' => 12, 'annee' => 2024], LigneReprise::numero('N° 0012-24', 'RG', null));
        $this->assertSame(['numero' => 77, 'annee' => 2023], LigneReprise::numero('77', 'RG', 2023));
        $this->assertSame(['numero' => 1334, 'annee' => 2026], LigneReprise::numero('RP 1334/26', 'RP', null));
        foreach ([['RP 1334/26', 'RG'], ['abc', 'RG'], ['0/25', 'RG'], ['', 'RG'], ['77', 'RG']] as [$brut, $serie]) {
            $this->assertArrayHasKey('erreur', LigneReprise::numero($brut, $serie, null), $brut);
        }
        $this->assertSame('2025-03-12', LigneReprise::date('12/03/2025')->toDateString());
        $this->assertSame('2025-03-12', LigneReprise::date('12-03-25')->toDateString());
        $this->assertSame('2025-03-12', LigneReprise::date('2025-03-12 00:00')->toDateString());
        $this->assertSame('2025-03-12', LigneReprise::date('45728')->toDateString());
        $this->assertNull(LigneReprise::date('31/02/2025'));
        $this->assertNull(LigneReprise::date('hier'));
        $this->assertSame(LigneReprise::cleLibelle('1ère chambre civile'), LigneReprise::cleLibelle('1ere Chambre  Civile'));
        $this->assertSame('=HYPERLINK', FichierTableur::texte('=HYPERLINK'));
        $this->assertSame('ab', FichierTableur::texte("a\x00b\x07"));
        $this->assertSame(2000, mb_strlen(FichierTableur::texte(str_repeat('é', 5000))));
        $this->assertSame('1391', FichierTableur::texte(1391.0));
    }

    public function test_devinette_des_parties(): void
    {
        $naturesCivil = ['homme', 'femme', 'couple', 'morale', 'etat', 'mp'];
        $p = PartiesReprise::depuis('Mme KOFFI Adjoua ; M. YAO Marc Antoine ; Société BATIMAT SARL ; Mairie de Bingerville ; MP', 'demandeur', $naturesCivil);
        $this->assertSame(['femme', 'homme', 'morale', 'etat', 'mp'], array_column($p, 'nature'));
        $this->assertSame(['KOFFI', 'YAO'], [$p[0]['nom'], $p[1]['nom']]);
        $this->assertSame('Marc Antoine', $p[1]['prenoms']);
        $this->assertSame('morale', PartiesReprise::depuis('Mairie de Bingerville', 'prevenu', ['homme', 'femme', 'morale'])[0]['nature']);
        $this->assertSame(['KOUASSI Yao', 'Société ABC SARL'], PartiesReprise::separer('KOUASSI Yao c/ Société ABC SARL'));
        $this->assertCount(50, PartiesReprise::depuis(implode(';', array_map(fn ($i) => "NOM$i", range(1, 80))), 'demandeur', $naturesCivil));
    }
}
