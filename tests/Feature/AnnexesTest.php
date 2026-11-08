<?php

namespace Tests\Feature;

use App\Models\ActiviteJap;
use App\Models\AssistanceJudiciaire;
use App\Models\Profil;
use App\Models\User;
use App\Support\Statistiques\RegistresTribunal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/** Lot S : assistance judiciaire, activités du JAP, et leurs registres au modèle du tribunal. */
class AnnexesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
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

    public function test_assistance_judiciaire_saisie_droits_et_coherence(): void
    {
        $u = $this->utilisateur(['assistance.voir', 'assistance.creer', 'assistance.modifier', 'assistance.supprimer']);
        $base = ['numero' => '12/26', 'date_rg' => '2026-03-02', 'demandeur' => 'KONE Awa', 'objet' => 'Divorce', 'nature' => 'Affaire matrimoniale'];
        $this->connecte($u)->post(route('assistance.store'), $base + ['decision' => 'accord'])->assertSessionHasErrors('date_decision');                       // une décision sans date
        $this->connecte($u)->post(route('assistance.store'), $base + ['decision' => 'accord', 'date_decision' => '2026-02-01'])->assertSessionHasErrors('date_decision');   // avant la réception
        $this->connecte($u)->post(route('assistance.store'), $base + ['decision' => 'accord', 'date_decision' => '2026-03-20', 'date_recours' => '2026-03-10'])->assertSessionHasErrors('date_recours');
        $this->connecte($u)->post(route('assistance.store'), $base + ['decision' => 'accord', 'date_decision' => '2026-03-20', 'numero_decision' => '5/26'])->assertSessionHasNoErrors();
        $a = AssistanceJudiciaire::firstOrFail();
        $this->assertSame(18, $a->dureeJours());
        $this->connecte($u)->get(route('assistance'))->assertOk();
        $this->assertStringContainsString('KONE Awa', $this->lignesServeur($u, 'assistance.donnees'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'assistance_enregistree']);

        $lecteur = $this->utilisateur(['assistance.voir']);
        $this->connecte($lecteur)->post(route('assistance.store'), $base)->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('assistance.delete', $a->id))->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('assistance'))->assertForbidden();
        $this->connecte($u)->deleteJson(route('assistance.delete', $a->id))->assertOk();
    }

    public function test_activites_du_jap_et_coherence_du_stock(): void
    {
        $u = $this->utilisateur(['jap.voir', 'jap.modifier']);
        $vide = fn () => array_fill(1, 12, 0);
        $valeurs = ['placement_stock' => $vide(), 'placement_recues' => $vide(), 'placement_traitees' => $vide(), 'placement_accord' => $vide(), 'placement_rejet' => $vide(), 'visites_effectuees' => $vide()];
        $valeurs['placement_stock'][1] = 2;
        $valeurs['placement_recues'][1] = 3;
        $valeurs['placement_traitees'][1] = 4;
        $valeurs['placement_accord'][1] = 3;
        $valeurs['placement_rejet'][1] = 1;
        $valeurs['placement_traitees'][2] = 2;       // février : stock de début 1 (5 − 4), aucune nouvelle demande : 2 traitées > 1
        $valeurs['visites_effectuees'][3] = 2;
        $this->connecte($u)->postJson(route('jap.enregistrer'), ['annee' => 2026, 'valeurs' => $valeurs])->assertStatus(422);

        $valeurs['placement_traitees'][2] = 1;
        $valeurs['placement_accord'][2] = 2;         // plus d'accords que de demandes traitées
        $this->connecte($u)->postJson(route('jap.enregistrer'), ['annee' => 2026, 'valeurs' => $valeurs])->assertStatus(422);

        $valeurs['placement_accord'][2] = 1;
        $valeurs['placement_stock'][2] = 99;         // ignoré : le stock de février est celui de janvier restant
        $this->connecte($u)->postJson(route('jap.enregistrer'), ['annee' => 2026, 'valeurs' => $valeurs])->assertOk();
        $this->assertSame(0, ActiviteJap::where('cle', 'placement_stock')->where('mois', 2)->count());
        $this->assertSame(3, (int) ActiviteJap::where(['cle' => 'placement_recues', 'mois' => 1])->value('valeur'));
        $this->connecte($u)->getJson(route('jap.donnees', ['annee' => 2026]))->assertOk()->assertJsonPath('valeurs.visites_effectuees.3', 2);
        $this->connecte($this->utilisateur(['jap.voir']))->postJson(route('jap.enregistrer'), ['annee' => 2026, 'valeurs' => $valeurs])->assertForbidden();
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('jap'))->assertForbidden();
        $this->connecte($u)->get(route('jap'))->assertOk()->assertSee('Activités du juge');
    }

    public function test_registres_assistance_et_jap_au_modele(): void
    {
        $admin = User::factory()->create();
        AssistanceJudiciaire::create(['numero' => '12/26', 'date_rg' => '2026-03-02', 'demandeur' => 'KONE Awa', 'decision' => 'rejet', 'date_decision' => '2026-03-20']);
        ActiviteJap::create(['annee' => 2026, 'mois' => 1, 'cle' => 'visites_effectuees', 'valeur' => 2]);
        ActiviteJap::create(['annee' => 2026, 'mois' => 3, 'cle' => 'placement_recues', 'valeur' => 7]);
        ActiviteJap::create(['annee' => 2026, 'mois' => 2, 'cle' => 'placement_stock', 'valeur' => 9]);      // jamais écrit : formule du classeur

        [$f, $n] = RegistresTribunal::fichier('assistance_judiciaire', $admin);
        $this->assertSame(1, $n);
        $zip = new ZipArchive;
        $zip->open($f);
        $this->assertStringContainsString('KONE Awa', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Rejet', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        [$f] = RegistresTribunal::fichier('activites_jap', $admin);
        $zip->open($f);
        $this->assertNotFalse($zip->getFromName('xl/vbaProject.bin'));                       // les macros du classeur sont conservées
        $feuille = '';
        for ($i = 1; $i <= 10; $i++) {
            $x = (string) $zip->getFromName("xl/worksheets/sheet$i.xml");
            if (str_contains($x, '<c r="B5"') && preg_match('/<c r="B5"[^>]*><v>2<\/v>/', $x)) {
                $feuille = $x;
            }
        }
        $zip->close();
        $this->assertNotSame('', $feuille);
        $this->assertMatchesRegularExpression('/<c r="D12"[^>]*><v>7<\/v>/', $feuille);       // demandes de placement reçues en mars
        $this->assertDoesNotMatchRegularExpression('/<c r="C11"[^>]*><v>9<\/v>/', $feuille);  // le stock de février est une formule
        $this->assertSame(3, RegistresTribunal::compter($admin)['activites_jap']);
    }
}
