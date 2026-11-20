<?php

namespace Tests\Feature;

use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\ImportOrdonnance;
use App\Models\Profil;
use App\Models\User;
use App\Support\Organisation\ExportOrdonnance;
use App\Support\Organisation\Import\Applicateur;
use App\Support\Organisation\Import\Classeur;
use App\Support\Organisation\Import\Comparateur;
use App\Support\Organisation\Import\Controle;
use App\Support\Organisation\Import\Noms;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Chargement de l'ordonnance par fichier Excel : aller-retour sans changement, contrôle, noms douteux, application et annulation, droit. */
class ImportOrdonnanceTest extends TestCase
{
    use RefreshDatabase;

    private AnneeJudiciaire $annee;

    private string $chemin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->annee = AnneeJudiciaire::firstOrFail();
        $this->chemin = ExportOrdonnance::generer($this->annee);
    }

    protected function tearDown(): void
    {
        @unlink($this->chemin);
        parent::tearDown();
    }

    private function controle(array $classeur, string $mode = 'maj', array $rapp = []): array
    {
        return Controle::executer($classeur, $mode, $rapp);
    }

    private function empreinte(): array
    {
        $r = [];
        foreach (['chambres', 'formations', 'agents', 'affectations', 'creneaux', 'services', 'service_agents', 'membres_exterieurs', 'delais_enrolement', 'articles_ordonnance', 'sections', 'salles', 'annees_judiciaires'] as $t) {
            $r[$t] = md5(json_encode(DB::table($t)->orderBy('id')->get()->map(fn ($l) => collect((array) $l)->except(['updated_at'])->all())->all()));
        }

        return $r;
    }

    public function test_le_fichier_exporte_se_recharge_sans_erreur_ni_changement(): void
    {
        $c = Classeur::lire($this->chemin);
        foreach (['maj', 'complet'] as $mode) {
            $r = $this->controle($c, $mode);
            $this->assertSame([], $r['controle']['erreurs'], 'Mode '.$mode);
            $this->assertSame([], $r['controle']['noms']);
            $this->assertTrue($r['controle']['peut_continuer']);
            $this->assertSame([], Comparateur::calculer($r['donnees'], $mode), 'Aucun changement attendu en mode '.$mode);
        }
    }

    public function test_les_erreurs_sont_signalees_ligne_par_ligne_sans_rien_ecrire(): void
    {
        $avant = $this->empreinte();
        $c = Classeur::lire($this->chemin);
        $c['feuilles']['composition'][0]['role'] = 'chef';
        $c['feuilles']['composition'][1]['du'] = '31/02/2026';
        $c['feuilles']['composition'][2]['formation'] = '99999';
        $c['feuilles']['audiences'][0]['heure'] = '25h99';

        $r = $this->controle($c);
        $messages = collect($r['controle']['erreurs'])->pluck('message')->implode(' | ');
        $this->assertCount(4, $r['controle']['erreurs']);
        $this->assertStringContainsString('Rôle inconnu', $messages);
        $this->assertStringContainsString('illisible', $messages);
        $this->assertStringContainsString("n'existe pas", $messages);
        $this->assertFalse($r['controle']['peut_continuer']);
        $this->assertSame($avant, $this->empreinte(), 'Le contrôle n\'écrit rien.');
    }

    public function test_un_nom_douteux_n_est_jamais_devine_mais_un_titre_ou_un_ordre_different_est_reconnu(): void
    {
        $c = Classeur::lire($this->chemin);
        $this->assertNotSame([], Noms::mots("Mme N'GUESSAN Appa Brigitte épouse LEPRY"));

        $c['feuilles']['composition'][0]['agent'] = "Mme N'GUESSAN Appa Brigitte";   // titre, sans « épouse » : reconnu
        $c['feuilles']['composition'][1]['agent'] = 'Marie-Laurette Kouadio';        // ordre inversé : reconnu
        $c['feuilles']['composition'][2]['agent'] = 'KOUADJO Marie Laurette';        // faute : à confirmer
        $c['feuilles']['composition'][3]['agent'] = 'ZZZ Inconnu Paul';              // inconnu : à confirmer

        $r = $this->controle($c);
        $this->assertSame([], $r['controle']['erreurs']);
        $textes = collect($r['controle']['noms'])->pluck('texte')->all();
        $this->assertContains('KOUADJO Marie Laurette', $textes);
        $this->assertContains('ZZZ Inconnu Paul', $textes);
        $this->assertCount(2, $textes);
        $this->assertFalse($r['controle']['peut_continuer']);

        // Le choix de l'utilisateur (ignorer, ou rattacher à un agent) résout le nom.
        $cle = collect($r['controle']['noms'])->firstWhere('texte', 'ZZZ Inconnu Paul')['cle'];
        $kouadio = \App\Models\Agent::where('nom', 'KOUADIO')->where('prenoms', 'like', 'Marie%')->firstOrFail();
        $r2 = $this->controle($c, 'maj', [$cle => ['action' => 'ignorer'], Noms::cle('KOUADJO Marie Laurette') => ['action' => 'agent', 'id' => $kouadio->id]]);
        $this->assertSame([], $r2['controle']['noms']);
        $this->assertTrue($r2['controle']['peut_continuer']);
    }

    public function test_application_en_une_operation_puis_annulation_exacte(): void
    {
        $avant = $this->empreinte();
        $c = Classeur::lire($this->chemin);
        $c['feuilles']['chambres'][0]['intitule'] = 'Intitulé corrigé';
        $c['feuilles']['composition'][6]['agent'] = $c['feuilles']['composition'][20]['agent'];   // remplacement d'une personne
        $c['feuilles']['audiences'][0]['heure'] = '10:30';
        $c['feuilles']['chambres'][] = ['_l' => 90, 'n' => null, 'section' => (string) \App\Models\Section::orderBy('id')->skip(1)->value('id'), 'groupe' => null, 'libelle' => "Chambre d'essai", 'intitule' => 'Essai', 'attributions' => 'x', 'remarque' => null, 'ordre' => 99];
        $c['feuilles']['formations'][] = ['_l' => 91, 'n' => null, 'chambre' => "Chambre d'essai", 'nom' => null, 'intitule' => null, 'attributions_propres' => null, 'ordre' => 1];
        $c['feuilles']['agents'][] = ['_l' => 92, 'n' => null, 'nom' => 'ESSAI', 'prenoms' => 'Marc', 'sexe' => 'M', 'fonction' => 'juge', 'categorie' => null];
        $c['feuilles']['composition'][] = ['_l' => 93, 'n' => null, 'formation' => "Chambre d'essai", 'role' => 'president', 'suppleant' => 'non', 'agent' => 'ESSAI Marc', 'chef_de_section' => 'oui', 'du' => '19/11/2026', 'au' => null, 'remarque' => null];

        $r = $this->controle($c);
        $this->assertTrue($r['controle']['peut_continuer'], json_encode($r['controle']['erreurs']));

        $import = ImportOrdonnance::create(['nom_fichier' => 'test.xlsx', 'empreinte' => sha1('t'), 'mode' => 'maj', 'statut' => 'brouillon', 'donnees' => $r['donnees'], 'controle' => $r['controle']]);
        $changements = Comparateur::calculer($r['donnees'], 'maj');
        $this->assertGreaterThanOrEqual(7, count($changements));

        $res = Applicateur::appliquer($import, array_column($changements, 'sig'), null);
        $this->assertSame(count($changements), $res['appliques']);
        $this->assertSame([], $res['ecartes']);
        $this->assertSame('Intitulé corrigé', Chambre::orderBy('id')->first()->intitule);
        $this->assertDatabaseHas('chambres', ['libelle' => "Chambre d'essai"]);
        $this->assertDatabaseHas('agents', ['nom' => 'ESSAI', 'prenoms' => 'Marc']);
        $nouvelle = Chambre::where('libelle', "Chambre d'essai")->firstOrFail();
        $this->assertDatabaseHas('affectations', ['role' => 'president', 'chef_section' => 1]);
        $this->assertTrue(Applicateur::annulable($import->fresh()));

        // Réappliquer le même fichier : plus rien ne change (idempotent).
        $this->assertSame([], Comparateur::calculer($this->controle($c)['donnees'], 'maj'));

        $undo = Applicateur::annuler($import->fresh(), null);
        $this->assertSame([], $undo['echecs']);
        $this->assertSame('annule', $import->fresh()->statut);
        $this->assertSame($avant, $this->empreinte(), 'L\'annulation rend l\'organisation exactement comme avant.');
        $this->assertDatabaseMissing('chambres', ['id' => $nouvelle->id]);
    }

    public function test_seul_le_dernier_import_applique_est_annulable_et_une_selection_partielle_est_respectee(): void
    {
        $c = Classeur::lire($this->chemin);
        $c['feuilles']['chambres'][0]['intitule'] = 'Premier';
        $r = $this->controle($c);
        $un = ImportOrdonnance::create(['nom_fichier' => 'un.xlsx', 'empreinte' => sha1('1'), 'mode' => 'maj', 'statut' => 'brouillon', 'donnees' => $r['donnees'], 'controle' => $r['controle']]);
        $ch = Comparateur::calculer($r['donnees'], 'maj');
        Applicateur::appliquer($un, [], null);   // rien de coché : rien n'est écrit
        $this->assertNotSame('Premier', Chambre::orderBy('id')->first()->intitule);
        $un->update(['statut' => 'brouillon']);
        Applicateur::appliquer($un->fresh(), array_column($ch, 'sig'), null);

        $c['feuilles']['chambres'][0]['intitule'] = 'Second';
        $r2 = $this->controle($c);
        $deux = ImportOrdonnance::create(['nom_fichier' => 'deux.xlsx', 'empreinte' => sha1('2'), 'mode' => 'maj', 'statut' => 'brouillon', 'donnees' => $r2['donnees'], 'controle' => $r2['controle']]);
        Applicateur::appliquer($deux, array_column(Comparateur::calculer($r2['donnees'], 'maj'), 'sig'), null);

        $this->assertFalse(Applicateur::annulable($un->fresh()), 'Un import plus récent existe.');
        $this->assertTrue(Applicateur::annulable($deux->fresh()));
        Applicateur::annuler($deux->fresh(), null);
        $this->assertSame('Premier', Chambre::orderBy('id')->first()->intitule);
        $this->assertTrue(Applicateur::annulable($un->fresh()));
    }

    public function test_le_mode_complet_retire_sans_supprimer(): void
    {
        $c = Classeur::lire($this->chemin);
        $retiree = $c['feuilles']['composition'][5];
        array_splice($c['feuilles']['composition'], 5, 1);
        $r = $this->controle($c, 'complet');
        $ch = collect(Comparateur::calculer($r['donnees'], 'complet'));
        $this->assertSame(1, $ch->where('bloc', 'composition')->where('action', 'retirer')->count());
        $this->assertSame(0, $ch->where('action', 'retirer')->where('mode', 'supprime')->count());

        $import = ImportOrdonnance::create(['nom_fichier' => 'c.xlsx', 'empreinte' => sha1('c'), 'mode' => 'complet', 'statut' => 'brouillon', 'donnees' => $r['donnees'], 'controle' => $r['controle']]);
        $avantNb = DB::table('affectations')->count();
        Applicateur::appliquer($import, $ch->pluck('sig')->all(), null);
        $this->assertSame($avantNb, DB::table('affectations')->count(), 'Aucune affectation supprimée : elle reçoit une date de fin.');
        $this->assertNotNull(DB::table('affectations')->where('id', $retiree['n'])->value('fin'));
    }

    public function test_un_fichier_d_une_autre_base_est_rapproche_par_les_noms(): void
    {
        $c = Classeur::lire($this->chemin);
        foreach ($c['feuilles']['agents'] as $i => $a) {
            $c['feuilles']['agents'][$i]['nom'] = 'AUTRE'.$i;
            $c['feuilles']['agents'][$i]['prenoms'] = 'Inconnu'.$i;
        }
        $r = $this->controle($c);
        $this->assertTrue($r['controle']['autre_base']);
        $this->assertSame([], $r['controle']['erreurs']);
        $this->assertStringContainsString('autre base', collect($r['controle']['avertissements'])->pluck('message')->implode(' '));

        // Chambres, formations, services et salles sont retrouvés par leur nom : seules les personnes (absentes de cette base) seraient créées.
        $ch = collect(Comparateur::calculer($r['donnees'], 'maj'));
        $this->assertSame(\App\Models\Agent::count(), $ch->where('bloc', 'agents')->where('action', 'creer')->count());
        $this->assertSame(0, $ch->where('bloc', 'chambres')->count());
        $this->assertSame(0, $ch->where('bloc', 'formations')->count());
        $this->assertSame(0, $ch->where('bloc', 'services')->where('action', 'creer')->count());
    }

    public function test_le_droit_d_importer_est_reserve(): void
    {
        $sans = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Sans', 'droits' => ['ordonnances.voir', 'ordonnances.creer']])->id]);
        $avec = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Avec', 'droits' => ['ordonnances.voir', 'ordonnances.importer']])->id]);
        $session = ['derniere_activite' => now()->timestamp];

        $this->actingAs($sans)->withSession($session)->get(route('organisation.ordonnances.import'))->assertForbidden();
        $this->actingAs($sans)->withSession($session)->post(route('organisation.ordonnances.import.store'))->assertForbidden();
        $this->actingAs($avec)->withSession($session)->get(route('organisation.ordonnances.import'))->assertOk()->assertSee('Charger');
        $this->actingAs($sans)->withSession($session)->get(route('organisation.ordonnances.excel'))->assertOk();
    }
}
