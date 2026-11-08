<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\DecisionPenale;
use App\Models\MiseEnEtat;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\ResultatAffaire;
use App\Models\User;
use App\Support\Statistiques\DonneesCivil;
use App\Support\Statistiques\ModeleTribunal;
use App\Support\Statistiques\RegistresTribunal;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/** Registres au modèle du tribunal : les classeurs d'origine remplis avec les affaires de l'application. */
class RegistresTribunalTest extends TestCase
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

    private function civile(string $nature, string $objet, string $premiere, ?int $chambre = null): Affaire
    {
        static $n = 0;
        $n++;
        $a = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 5000 + $n, 'numero_complet' => 'RG-'.(5000 + $n).'/26', 'numero_saisi' => true, 'date_rg' => $premiere, 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('libelle', $nature)->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre ?? Chambre::where('libelle', 'like', '%chambre civile%')->value('id'), 'statut' => 'enrolee', 'date_premiere_audience' => $premiere,
        ]);
        Partie::create(['affaire_id' => $a->id, 'role' => 'demandeur', 'nature' => 'femme', 'nom' => 'KONE', 'prenoms' => 'Awa', 'cle' => 'd'.$n]);
        Partie::create(['affaire_id' => $a->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE', 'cle' => 'f'.$n]);

        return $a;
    }

    /** Ouvre un classeur produit : feuille principale, référence du tableau, présence des formules et des calculs à l'ouverture. */
    private string $dernierFichier = '';

    private function inspecter(string $chemin): array
    {
        $this->dernierFichier = $chemin;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($chemin) === true);
        [$feuille, $table] = ModeleTribunal::tableauPrincipal($zip);
        $info = ['feuille' => $zip->getFromName($feuille), 'table' => $zip->getFromName($table), 'classeur' => $zip->getFromName('xl/workbook.xml'), 'calcchain' => $zip->getFromName('xl/calcChain.xml')];
        $zip->close();

        return $info;
    }

    public function test_chaque_modele_se_remplit_et_garde_ses_formules(): void
    {
        foreach (array_keys(RegistresTribunal::REGISTRES) as $code) {
            if ($code === 'activites_jap') {
                continue;   // classeur sans tableau : cellules fixes, voir AnnexesTest
            }
            $fichier = ModeleTribunal::generer($code, []);   // registre vide : le classeur reste valide, avec sa ligne type
            $i = $this->inspecter($fichier);
            $this->assertStringContainsString('fullCalcOnLoad="1"', $i['classeur'], $code);
            $this->assertFalse($i['calcchain'], $code);
            $this->assertDoesNotMatchRegularExpression('/<row r="5"[^>]*hidden/', $i['feuille'], "$code : aucune ligne masquée par un ancien filtre");
            $this->assertStringNotContainsString('<filterColumn', $i['table'], $code);
            $this->assertStringNotContainsString('"aaaa"', $i['feuille'], "$code : formules sans format de date propre à la langue d'Excel");
            if (! in_array($code, ['personnel_magistrat', 'assistance_judiciaire'], true)) {   // le tableau du personnel n'a pas de colonne calculée dans ses lignes
                $this->assertMatchesRegularExpression('/<c r="[A-Z]+5"[^>]*><f>/', $i['feuille'], "$code : les colonnes calculées du modèle sont conservées");
            }
            unlink($fichier);
        }
    }

    public function test_ecriture_des_lignes_references_du_tableau_et_echappement(): void
    {
        $lignes = [
            ['A' => '1/26', 'B' => Carbon::parse('2026-03-02'), 'C' => 'KONE & <FILS> "Awa"', 'D' => 1],
            ['A' => '2/26', 'B' => Carbon::parse('2026-03-03'), 'C' => "N'GUESSAN", 'E' => 2],
            ['A' => '3/26', 'B' => Carbon::parse('2026-03-04')],
        ];
        $fichier = ModeleTribunal::generer('matrimoniale', $lignes);
        $i = $this->inspecter($fichier);
        $this->assertMatchesRegularExpression('/ref="A4:AI7"/', $i['table']);                    // 3 affaires : lignes 5 à 7, colonnes du tableau inchangées
        $this->assertStringContainsString('<row r="7"', $i['feuille']);
        $this->assertStringNotContainsString('<row r="8"', $i['feuille']);
        $this->assertStringContainsString('KONE &amp; &lt;FILS&gt; &quot;Awa&quot;', $i['feuille']);   // texte échappé, jamais interprété
        $this->assertStringContainsString('<v>46083</v>', $i['feuille']);                         // 02/03/2026 en numéro de série Excel
        $this->assertSame(3, preg_match_all('/<c r="AB\d+"[^>]*><f>/', $i['feuille']));          // la formule « durée » est recopiée sur chaque ligne
        unlink($fichier);
    }

    public function test_le_recapitulatif_annuel_affiche_les_bons_chiffres_des_l_ouverture(): void
    {
        $admin = User::factory()->create();
        $a = $this->civile('Matrimoniale', 'Divorce pour faute', '2026-02-01');
        $this->civile('Matrimoniale', 'Divorce pour faute', '2026-03-01');
        $this->civile('Matrimoniale', 'Divorce pour faute', '2025-03-01');
        ResultatAffaire::create(['affaire_id' => $a->id, 'type_sortie' => 'decision_au_fond', 'date_sortie' => '2026-06-01', 'resultat' => 'accord']);

        $i = $this->inspecter(ModeleTribunal::generer('matrimoniale', RegistresTribunal::lignes('matrimoniale', $admin)));
        $zip = new ZipArchive;
        $zip->open($this->dernierFichier);
        $recap = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();
        $this->assertMatchesRegularExpression('/Affaire matrimoniale"[^<]*"2026"\)<\/f><v>2<\/v>/u', $recap);     // 2 affaires nouvelles en 2026
        $this->assertMatchesRegularExpression('/Affaire matrimoniale"[^<]*"2025"\)<\/f><v>1<\/v>/u', $recap);
        $this->assertStringNotContainsString('"Affaire matrimoniale"', '');   // garde-fou lisible
        unset($i);
    }

    public function test_repartition_des_affaires_dans_les_registres(): void
    {
        $presidentielle = Chambre::where('libelle', 'Juridiction présidentielle')->value('id');
        $this->civile('Matrimoniale', 'Divorce pour faute', '2026-02-01');
        $this->civile('État des personnes', 'Jugement supplétif de naissance', '2026-02-02');
        $this->civile('État des personnes', "Établissement de la qualité d'héritiers", '2026-02-03');
        $this->civile('Civile ordinaire', 'Placement sous tutelle', '2026-02-04');
        $this->civile('Référé-expulsion', 'Expulsion', '2026-02-05');
        $this->civile('Référé ordinaire', 'Référé en paiement', '2026-02-06');
        $this->civile("Juge de l'exécution (voies d'exécution)", 'Saisie', '2026-02-07');
        $this->civile('Commerciale', 'Factures', '2026-02-08');
        $this->civile('Civile ordinaire', 'Affaire de la présidente', '2026-02-09', $presidentielle);

        $n = RegistresTribunal::compter(User::factory()->create());
        $this->assertSame(1, $n['matrimoniale']);
        $this->assertSame(1, $n['etat_des_personnes']);
        $this->assertSame(1, $n['heredite']);
        $this->assertSame(1, $n['tutelle']);
        $this->assertSame(1, $n['refere_expulsion']);
        $this->assertSame(1, $n['refere_ordinaire']);
        $this->assertSame(1, $n['refere_voies_execution']);
        $this->assertSame(1, $n['civile_diverse']);
        $this->assertSame(1, $n['civil_presidentielle']);
        unset($n['personnel_magistrat']);
        $this->assertSame(9, array_sum($n));      // chaque affaire dans un seul registre
    }

    public function test_colonnes_civiles_comme_dans_le_classeur(): void
    {
        $admin = User::factory()->create();
        $a = $this->civile('Matrimoniale', 'Divorce pour faute', '2026-02-01');
        ResultatAffaire::create(['affaire_id' => $a->id, 'type_sortie' => 'decision_au_fond', 'date_sortie' => '2026-04-01', 'resultat' => 'accord', 'caractere' => 'contradictoire', 'adoption_filles' => 1, 'adoption_garcons' => 2, 'adoptant_ivoirien' => 'couple', 'observations' => 'RAS']);

        $l = RegistresTribunal::lignes('matrimoniale', $admin)[0];
        $this->assertSame(['KONE Awa', 1, null, 'SOCIETE EXEMPLE', null, 1], [$l['C'], $l['E'], $l['D'], $l['H'], $l['I'], $l['L']]);   // demandeur femme, défendeur personne morale
        $this->assertSame(['Affaire matrimoniale', 'Couple', 'Décision au fond', 'Contradictoire', 'Accord', 1, 2, 'RAS', 'Divorce pour faute'], [$l['N'], $l['O'], $l['R'], $l['U'], $l['V'], $l['W'], $l['X'], $l['Y'], $l['AA']]);
        $this->assertSame('2026-04-01', $l['T']->toDateString());
        $this->assertNull($l['Z']);       // « Traitement état des personnes » reste vide hors état des personnes

        // Les classeurs spécialisés rangent l'adoption dans « Etat des personnes », avec leurs propres listes.
        $this->civile('Matrimoniale', 'Adoption plénière', '2026-03-01');
        $this->civile('État des personnes', 'Modification de prénom', '2026-03-02');
        $lignes = collect(RegistresTribunal::lignes('etat_des_personnes', $admin));
        $this->assertSame(2, $lignes->count());
        $adoption = $lignes->firstWhere('M', 'Adoption plénière');
        $this->assertSame(['Etat des personnes', 'Adoption plénière', null], [$adoption['N'], $adoption['Z'], $adoption['AA']]);
        $this->assertSame('Modification nom et/ou prénom', $lignes->firstWhere('M', 'Modification de prénom')['Z']);
    }

    public function test_registre_penal_et_mise_en_etat(): void
    {
        $admin = User::factory()->create();
        $chambre = Chambre::where('libelle', 'like', '%correctionnelle%')->value('id');
        $a = Affaire::create([
            'serie' => 'RP', 'annee' => 2026, 'numero' => 77, 'numero_complet' => 'RP-77/26', 'numero_saisi' => true, 'date_rg' => '2026-01-05', 'objet' => 'Vol', 'mode_saisine_id' => ModeSaisine::where('registre', 'penal')->value('id'),
            'nature_affaire_id' => NatureAffaire::where('libelle', 'Délits économiques')->value('id'), 'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-02-01',
        ]);
        $p = Partie::create(['affaire_id' => $a->id, 'role' => 'prevenu', 'nature' => 'homme', 'nom' => 'DIABATE', 'cle' => 'p', 'nationalite' => 'malienne', 'profession' => 'chauffeur']);
        DecisionPenale::create(['affaire_id' => $a->id, 'partie_id' => $p->id, 'date_decision' => '2026-03-01', 'contradiction' => 'defaut', 'issue' => 'condamne', 'emprisonnement_mois' => 12, 'sursis_mois' => 12, 'amende' => 100000,
            'victimes_hommes' => 1, 'victimes_filles' => 1, 'date_transmission_minute' => '2026-03-20']);

        $n = RegistresTribunal::compter($admin);
        $this->assertSame(1, $n['penal_delits_economiques']);
        $this->assertSame(0, $n['penal_delits_divers']);
        $l = RegistresTribunal::lignes('penal_delits_economiques', $admin)[0];
        $this->assertSame(['77/26', 1, 'Défaut', "Condamnation à l'emprisonnement avec sursis", 'NON', 'Délit', 1, 1, 0, '01 malienne', '01 chauffeur', 2], [$l['A'], $l['C'], $l['G'], $l['H'], $l['L'], $l['M'], $l['P'], $l['Q'], $l['R'], $l['S'], $l['T'], $l['U']]);
        $this->assertSame('2026-03-20', $l['AC']->toDateString());

        // Mise en état.
        $c = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 90, 'numero_complet' => 'RG-90/26', 'numero_saisi' => true, 'date_rg' => '2026-01-05', 'objet' => 'Dette', 'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'),
            'nature_affaire_id' => NatureAffaire::where('libelle', 'Civile ordinaire')->value('id'), 'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::where('libelle', 'like', '%chambre civile%')->value('id'), 'statut' => 'enrolee',
        ]);
        MiseEnEtat::create(['affaire_id' => $c->id, 'date_reception' => '2026-01-10', 'date_premiere_audience' => '2026-01-20', 'statut' => 'cloturee', 'numero_ordonnance_cloture' => '12/26', 'date_cloture' => '2026-05-01']);
        $m = RegistresTribunal::lignes('mise_en_etat', $admin)[0];
        $this->assertSame(['90/26', 'Dette', 'Civile ordinaire', '12/26'], [$m['A'], $m['E'], $m['F'], $m['H']]);
        $this->assertSame('2026-05-01', $m['I']->toDateString());
        $i = $this->inspecter(ModeleTribunal::generer('mise_en_etat', [$m]));
        $this->assertMatchesRegularExpression('/ref="A4:R5"/', $i['table']);
    }

    public function test_personnel_magistrat_dans_la_feuille_de_l_annee(): void
    {
        $admin = User::factory()->create();
        \App\Models\Agent::create(['nom' => 'KONE', 'prenoms' => 'Awa', 'sexe' => 'F', 'fonction' => 'juge', 'matricule' => 'M123', 'actif' => true]);
        \App\Models\Agent::create(['nom' => 'TRAORE', 'prenoms' => 'Ali', 'sexe' => 'M', 'fonction' => 'vice_president', 'actif' => true]);
        \App\Models\Agent::create(['nom' => 'DIALLO', 'prenoms' => 'Fatou', 'sexe' => 'F', 'fonction' => 'greffier', 'actif' => true]);   // un greffier n'est pas magistrat

        $lignes = RegistresTribunal::lignes('personnel_magistrat', $admin);
        $this->assertNotContains('DIALLO Fatou', array_column($lignes, 'B'));                      // les greffiers ne figurent pas au personnel magistrat
        $kone = collect($lignes)->firstWhere('B', 'KONE Awa');
        $this->assertSame(['M123', 'F', 'Siège'], [$kone['A'], $kone['F'], $kone['J']]);
        $lignes = [$kone, $kone];
        $zip = new ZipArchive;
        $zip->open(ModeleTribunal::generer('personnel_magistrat', $lignes, 'PERSONNEL MAGISTRAT 2026'));
        [$feuille, $table] = ModeleTribunal::tableauDeLaFeuille($zip, 'PERSONNEL MAGISTRAT 2026');
        $this->assertMatchesRegularExpression('/ref="A3:K5"/', $zip->getFromName($table));      // en-tête ligne 3, deux magistrats : lignes 4 et 5
        $this->assertStringContainsString('KONE Awa', $zip->getFromName($feuille));
        $zip->close();
    }

    public function test_droits_et_telechargement(): void
    {
        $this->civile('Matrimoniale', 'Divorce pour faute', '2026-02-01');
        $voir = $this->utilisateur(['stats_registres.voir', 'affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($voir)->get(route('statistiques.registres'))->assertOk()->assertSee('Registres du tribunal');
        $this->connecte($voir)->getJson(route('statistiques.registres.effectifs'))->assertOk()->assertJsonPath('matrimoniale', 1);
        $this->connecte($voir)->get(route('statistiques.registres.export', 'matrimoniale'))->assertForbidden();   // les registres contiennent les noms : droit à part
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('statistiques.registres'))->assertForbidden();

        $export = $this->utilisateur(['stats_registres.voir', 'stats_registres.exporter', 'affaires.voir', 'affaires.toutes_chambres']);
        $rep = $this->connecte($export)->get(route('statistiques.registres.export', 'matrimoniale'));
        $rep->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($rep->baseResponse->getFile()->getPathname()) === true);
        $this->assertStringContainsString('KONE Awa', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));      // l'onglet RECAP du tribunal est conservé
        $zip->close();
        $this->connecte($export)->get(route('statistiques.registres.export', 'inconnu'))->assertNotFound();
        $this->assertDatabaseHas('journal_audit', ['action' => 'registre_exporte']);
    }

    public function test_perimetre_de_chambre(): void
    {
        $this->civile('Matrimoniale', 'Divorce pour faute', '2026-02-01');
        $limite = $this->utilisateur(['stats_registres.voir', 'stats_registres.exporter', 'affaires.voir']);   // sans affectation : aucune chambre
        $n = RegistresTribunal::compter($limite);
        unset($n['personnel_magistrat']);      // le personnel ne dépend pas des chambres
        $this->assertSame(0, array_sum($n));
        $this->assertSame([], RegistresTribunal::lignes('matrimoniale', $limite));
        $this->assertSame(0, DonneesCivil::affaires($limite)->count());
    }
}
