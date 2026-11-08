<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\User;
use App\Support\Audiences\SuitesAudience;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Page « Suites d'audience » : consultation filtrée par périmètre et export Excel / PDF. */
class SuitesAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function affaire(int $n, string $audience): Affaire
    {
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 700 + $n, 'numero_complet' => 'RG-'.(700 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-05-20', 'objet' => 'Paiement de loyers '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Kone', 'prenoms' => 'Ibrahima '.$n, 'cle' => 'kone'.$n]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$n, 'cle' => 'ste'.$n]);

        return $affaire;
    }

    /** Un rôle publié de trois affaires : une jugée, une renvoyée, une radiée. */
    private function roleAvecSuites(): RoleAudience
    {
        foreach ([1, 2, 3] as $n) {
            $this->affaire($n, '2026-06-04');
        }
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04', 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => '2026-06-04']);
        GenerateurRole::completer($role);
        $role->forceFill(['statut' => 'publie'])->save();
        $suites = ['juge', 'renvoi', 'radie'];
        foreach ($role->refresh()->lignes()->orderBy('id')->get() as $i => $ligne) {
            $ligne->update(['issue' => $suites[$i], 'renvoi_au' => $suites[$i] === 'renvoi' ? '2026-07-02' : null, 'motif' => $suites[$i] === 'renvoi' ? 'Pour conclusions' : null]);
        }

        return $role;
    }

    private function lecteur(): User
    {
        return $this->avecDroits(['suites.voir', 'affaires.toutes_chambres']);
    }

    public function test_les_pages_exigent_leurs_droits(): void
    {
        $this->roleAvecSuites();
        $sans = $this->avecDroits(['roles.voir']);
        $this->connecte($sans)->get(route('audiences.suites'))->assertForbidden();
        $this->connecte($sans)->getJson(route('audiences.suites.donnees'))->assertForbidden();

        $lecteur = $this->lecteur();
        $this->connecte($lecteur)->get(route('audiences.suites'))->assertOk()->assertSee("Suites d'audience");
        $this->connecte($lecteur)->get(route('audiences.suites.export', ['format' => 'xlsx']))->assertForbidden();
    }

    public function test_la_liste_se_filtre_par_suite_chambre_periode_et_recherche(): void
    {
        $this->roleAvecSuites();
        $u = $this->lecteur();

        $tout = $this->connecte($u)->getJson(route('audiences.suites.donnees'))->assertOk();
        $tout->assertJsonPath('total', 3)->assertJsonPath('compteurs.juge', 1)->assertJsonPath('compteurs.renvoi', 1)->assertJsonPath('compteurs.radie', 1)->assertJsonPath('compteurs.delibere', 0);

        $renvois = $this->connecte($u)->getJson(route('audiences.suites.donnees', ['suite' => 'renvoi']))->assertOk();
        $renvois->assertJsonPath('total', 1)->assertJsonPath('lignes.0.suite', 'renvoi')->assertJsonPath('lignes.0.detail', 'Renvoyée au 02/07/2026 · Pour conclusions');
        // Les compteurs ne dépendent pas du filtre « suite » : on voit toujours le détail par suite.
        $renvois->assertJsonPath('compteurs.juge', 1);
        // Les dates qui situent l'affaire : enrôlement et première audience.
        $renvois->assertJsonPath('lignes.0.enrolee_le', '20/05/2026')->assertJsonPath('lignes.0.premiere_le', '04/06/2026')->assertJsonPath('lignes.0.date_libelle', '04/06/2026');

        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['du' => '2026-06-05']))->assertJsonPath('total', 0);
        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['au' => '2026-06-04', 'chambre' => $this->chambre->id]))->assertJsonPath('total', 3);
        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['q' => 'SOCIETE EXEMPLE 2']))->assertJsonPath('total', 1);
        // Une valeur inconnue est ignorée, jamais interprétée.
        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['suite' => "juge' OR 1=1", 'du' => 'pas une date']))->assertOk()->assertJsonPath('total', 3);
    }

    public function test_on_retrouve_une_suite_par_la_date_de_son_audience(): void
    {
        $this->roleAvecSuites();   // audience du 04/06/2026
        $u = $this->lecteur();
        foreach (['04/06/2026', '4/6/2026', '2026-06-04', '04/06'] as $terme) {
            $this->connecte($u)->getJson(route('audiences.suites.donnees', ['q' => $terme]))->assertJsonPath('total', 3);
        }
        foreach (['05/06/2026', '05/06', '31/02/2026'] as $terme) {
            $this->connecte($u)->getJson(route('audiences.suites.donnees', ['q' => $terme]))->assertJsonPath('total', 0);
        }
    }

    public function test_les_affaires_en_retard_se_voient_avec_les_memes_filtres(): void
    {
        $this->roleAvecSuites();                 // l'une des trois affaires a un renvoi au 02/07/2026, déjà passé
        $oubliee = $this->affaire(4, '2026-06-01');   // première audience passée, jamais portée sur un rôle
        $u = $this->lecteur();

        $r = $this->connecte($u)->getJson(route('audiences.suites.donnees', ['suite' => 'retard']))->assertOk();
        $r->assertJsonPath('total', 2)->assertJsonPath('compteurs.retard', 2)->assertJsonPath('compteurs.juge', 1);
        $libelles = collect($r->json('lignes'))->pluck('suite_libelle')->sort()->values()->all();
        $this->assertSame(['Jamais portée', 'Renvoi dépassé'], $libelles);
        $this->assertStringContainsString('en retard de', $r->json('lignes.0.detail'));
        $this->assertSame($oubliee->numeroAffichage(), collect($r->json('lignes'))->firstWhere('suite_libelle', 'Jamais portée')['numero']);

        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['suite' => 'retard', 'q' => 'SOCIETE EXEMPLE 4']))->assertJsonPath('total', 1);
        $this->connecte($u)->getJson(route('audiences.suites.donnees', ['suite' => 'retard', 'chambre' => 999999]))->assertJsonPath('total', 0);

        // Chacun ne voit que ses chambres, et l'export suit le filtre.
        $this->connecte($this->avecDroits(['suites.voir']))->getJson(route('audiences.suites.donnees', ['suite' => 'retard']))->assertJsonPath('total', 0);
        $exp = $this->avecDroits(['suites.voir', 'suites.exporter', 'affaires.toutes_chambres']);
        $this->connecte($exp)->get(route('audiences.suites.export', ['format' => 'xlsx', 'suite' => 'retard']))->assertOk();
        $this->assertCount(2, SuitesAudience::toutes($exp, SuitesAudience::filtres(['suite' => 'retard'])));
    }

    public function test_chacun_ne_voit_que_ses_chambres(): void
    {
        $this->roleAvecSuites();
        $autre = $this->avecDroits(['suites.voir']);   // sans « toutes les chambres » : rien dans son périmètre
        $this->connecte($autre)->getJson(route('audiences.suites.donnees'))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_l_export_excel_et_pdf_respecte_les_filtres(): void
    {
        $this->roleAvecSuites();
        $u = $this->avecDroits(['suites.voir', 'suites.exporter', 'affaires.toutes_chambres']);

        $pdf = $this->connecte($u)->get(route('audiences.suites.export', ['format' => 'pdf', 'suite' => 'juge']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        // Le PDF des autres pastilles (et de « En retard ») se produit aussi.
        foreach (['renvoi', 'retard', ''] as $suite) {
            $this->assertStringStartsWith('%PDF', $this->connecte($u)->get(route('audiences.suites.export', ['format' => 'pdf', 'suite' => $suite]))->assertOk()->getContent());
        }

        $xlsx = $this->connecte($u)->get(route('audiences.suites.export', ['format' => 'xlsx']))->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('Content-Type'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'suites_exportees']);
    }

    public function test_l_export_excel_est_un_classeur_avec_une_feuille_par_suite(): void
    {
        $this->roleAvecSuites();      // une jugée, une renvoyée (dépassée donc en retard), une radiée
        $u = $this->lecteur();

        $feuilles = SuitesAudience::classeur($u, SuitesAudience::filtres(['suite' => 'juge']));   // le filtre « suite » est ignoré
        $this->assertSame(['Toutes les suites', 'Renvoyées', 'En délibéré', 'Jugées', 'Radiées', 'Jointes', 'Désistements', 'En retard'], array_keys($feuilles));
        $this->assertCount(3, $feuilles['Toutes les suites']);
        $this->assertCount(1, $feuilles['Renvoyées']);
        $this->assertCount(1, $feuilles['Jugées']);
        $this->assertCount(1, $feuilles['Radiées']);
        $this->assertCount(0, $feuilles['En délibéré']);
        $this->assertCount(1, $feuilles['En retard']);

        // Le fichier produit contient bien ces feuilles, dans cet ordre.
        $chemin = SuitesAudience::excel($feuilles);
        $lecteur = new \OpenSpout\Reader\XLSX\Reader;
        $lecteur->open($chemin);
        $noms = [];
        foreach ($lecteur->getSheetIterator() as $feuille) {
            $noms[] = $feuille->getName();
        }
        $lecteur->close();
        @unlink($chemin);
        $this->assertSame(array_keys($feuilles), $noms);
    }

    public function test_les_lignes_d_export_n_ont_que_du_texte(): void
    {
        $this->roleAvecSuites();
        $lignes = SuitesAudience::lignesExport(SuitesAudience::toutes($this->lecteur(), SuitesAudience::filtres([])));
        $this->assertCount(3, $lignes);
        $this->assertCount(count(SuitesAudience::COLONNES), $lignes[0]);
        foreach ($lignes as $l) {
            foreach ($l as $cellule) {
                $this->assertIsString($cellule);
            }
        }
    }

    public function test_le_menu_affiche_les_suites_d_audience(): void
    {
        $page = $this->connecte($this->lecteur())->get(route('audiences.suites'))->getContent();
        $this->assertStringContainsString(e("Suites d'audience"), $page);
        $this->assertSame(1, preg_match_all('#<li><a class="active" href="[^"]*/audiences/suites">#', $page));
    }
}
