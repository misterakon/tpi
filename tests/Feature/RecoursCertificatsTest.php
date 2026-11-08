<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\DelaiRecours;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Profil;
use App\Models\Requete;
use App\Models\User;
use App\Support\Recours\DelaisRecours;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Lot P : signature réservée (AUT-05), recours et certificats de non-appel (CLO-03). */
class RecoursCertificatsTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);
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

    private function greffe(array $en_plus = []): User
    {
        return $this->utilisateur(array_merge(['recours.voir', 'recours.creer', 'recours.modifier', 'recours.annuler', 'affaires.voir', 'affaires.toutes_chambres'], $en_plus));
    }

    private function decisionSignee(string $signee = '2026-08-01'): Decision
    {
        static $n = 0;
        $n++;
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 900 + $n, 'numero_complet' => 'RG-'.(900 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-05-20', 'objet' => 'Essai '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-06-04',
        ]);

        return Decision::create([
            'modele_decision_id' => ModeleDecision::firstOrFail()->id, 'chambre_id' => $this->chambre->id, 'affaire_id' => $affaire->id, 'version_numero' => 1, 'reference' => 'D'.$n,
            'statut' => 'signee', 'valeurs' => [], 'date_signature' => $signee, 'numero' => $n, 'numero_seq' => $n, 'numero_annee' => 2026,
        ]);
    }

    private function confirmerDelais(): void
    {
        DelaiRecours::query()->update(['valide' => true]);
    }

    public function test_echeance_prorogee_au_jour_ouvre(): void
    {
        $this->confirmerDelais();
        $delai = DelaiRecours::where('nature', 'appel')->where('registre', 'RG')->whereNull('nature_affaire_id')->firstOrFail();
        $this->assertSame('mois', $delai->unite);
        // 1 mois après le 10/10/2026 (samedi 10/11 ? non : 10/11/2026 est un mardi) ; 1 mois après le 20/09/2026 = 20/10/2026 (mardi).
        $this->assertSame('2026-11-10', DelaisRecours::echeance(Carbon::parse('2026-10-10'), $delai)->toDateString());
        // 1 mois après le 14/02/2026 = samedi 14/03/2026 → reporté au lundi 16/03/2026.
        $this->assertSame('2026-03-16', DelaisRecours::echeance(Carbon::parse('2026-02-14'), $delai)->toDateString());
    }

    public function test_pas_de_certificat_sans_signification_ni_delai_confirme(): void
    {
        DelaiRecours::query()->update(['valide' => false]);      // les délais de la norme sont confirmés d'office ; ici on teste le cas d'un délai non confirmé
        $d = $this->decisionSignee();
        $e = DelaisRecours::etat($d);
        $this->assertSame(['a_signifier', false], [$e['etat'], $e['certifiable']]);

        $this->connecte($this->greffe())->post(route('decisions.recours.signification', $d->id), ['date_signification' => '2026-08-05', 'nature' => 'appel'])->assertSessionHasNoErrors();
        $e = DelaisRecours::etat($d);
        $this->assertSame('expire', $e['etat']);
        $this->assertFalse($e['certifiable']);   // délai provisoire, non confirmé
        $this->assertStringContainsString('pas encore confirmé', $e['obstacle']);

        $certificateur = $this->greffe(['recours.certifier']);
        $this->connecte($certificateur)->post(route('decisions.recours.certifier', $d->id), ['delivre_a' => 'Me KONE'])->assertSessionHasErrors('delivre_a');
        $this->assertSame(0, $d->certificats()->count());
    }

    public function test_certificat_delivre_numerote_et_imprimable(): void
    {
        $this->confirmerDelais();
        $d = $this->decisionSignee();
        $greffe = $this->greffe(['recours.certifier']);
        $this->connecte($greffe)->post(route('decisions.recours.signification', $d->id), ['date_signification' => '2026-08-05', 'nature' => 'appel'])->assertSessionHasNoErrors();

        // Droit nécessaire.
        $this->connecte($this->greffe())->post(route('decisions.recours.certifier', $d->id), ['delivre_a' => 'Me KONE'])->assertForbidden();

        $this->connecte($greffe)->post(route('decisions.recours.certifier', $d->id), ['delivre_a' => 'Me KONE', 'qualite' => 'avocat'])->assertSessionHasNoErrors();
        $c = $d->certificats()->firstOrFail();
        $this->assertSame(['non_appel', '1/2026'], [$c->type, $c->numeroAffichage()]);
        $this->assertSame('2026-09-07', $c->echeance->toDateString());   // 05/09/2026 est un samedi → lundi 07/09
        $this->assertDatabaseHas('journal_audit', ['action' => 'certificat_delivre']);

        // Un seul certificat par décision ; numérotation continue pour la suivante.
        $this->connecte($greffe)->post(route('decisions.recours.certifier', $d->id), ['delivre_a' => 'Me KONE'])->assertSessionHasErrors('delivre_a');
        $d2 = $this->decisionSignee();
        $this->connecte($greffe)->post(route('decisions.recours.signification', $d2->id), ['date_signification' => '2026-08-05', 'nature' => 'appel']);
        $this->connecte($greffe)->post(route('decisions.recours.certifier', $d2->id), ['delivre_a' => 'Me DIALLO']);
        $this->assertSame('2/2026', $d2->certificats()->firstOrFail()->numeroAffichage());

        $this->connecte($greffe)->get(route('decisions.recours.imprimer', $c->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_un_recours_bloque_le_certificat_et_l_abandon_le_libere(): void
    {
        $this->confirmerDelais();
        $d = $this->decisionSignee();
        $greffe = $this->greffe(['recours.certifier']);
        $this->connecte($greffe)->post(route('decisions.recours.signification', $d->id), ['date_signification' => '2026-08-05', 'nature' => 'appel']);

        $this->connecte($greffe)->post(route('decisions.recours.enregistrer', $d->id), ['nature' => 'appel', 'auteur' => 'SOCIETE EXEMPLE', 'date_recours' => '2026-08-20'])->assertSessionHasNoErrors();
        $this->assertSame('recours', DelaisRecours::etat($d)['etat']);
        $this->connecte($greffe)->post(route('decisions.recours.certifier', $d->id), ['delivre_a' => 'Me KONE'])->assertSessionHasErrors('delivre_a');

        $r = \App\Models\Recours::firstOrFail();
        $this->connecte($greffe)->post(route('decisions.recours.transmettre', $r->id), ['date_transmission' => '2026-08-10'])->assertSessionHasErrors('date_transmission');   // avant le recours
        $this->connecte($greffe)->post(route('decisions.recours.transmettre', $r->id), ['date_transmission' => '2026-08-25', 'reference_cour' => 'CA-123'])->assertSessionHasNoErrors();
        $this->assertSame('transmis', $r->refresh()->statut);

        $this->connecte($greffe)->post(route('decisions.recours.abandonner', $r->id))->assertSessionHasNoErrors();
        $this->assertTrue(DelaisRecours::etat($d)['certifiable']);
    }

    public function test_delais_modifies_et_confirmes_par_qui_a_le_droit(): void
    {
        $delai = DelaiRecours::where('nature', 'appel')->where('registre', 'RG')->whereNull('nature_affaire_id')->firstOrFail();
        $this->assertTrue((bool) $delai->valide);       // confirmé d'office (norme usuelle)
        $delai->update(['valide' => false]);
        $donnees = ['delais' => [$delai->id => ['valeur' => 1, 'unite' => 'mois', 'valide' => 1]]];

        $this->connecte($this->greffe())->post(route('decisions.recours.delais'), $donnees)->assertForbidden();
        $this->connecte($this->greffe(['recours.delais']))->post(route('decisions.recours.delais'), $donnees)->assertSessionHasNoErrors();
        $this->assertTrue((bool) $delai->refresh()->valide);
        $this->assertNotNull($delai->valide_par);

        // Une valeur modifiée sans cocher « confirmé » perd la confirmation.
        $this->connecte($this->greffe(['recours.delais']))->post(route('decisions.recours.delais'), ['delais' => [$delai->id => ['valeur' => 2, 'unite' => 'mois']]]);
        $this->assertFalse((bool) $delai->refresh()->valide);
    }

    public function test_perimetre_et_page(): void
    {
        $d = $this->decisionSignee();
        $this->connecte($this->greffe())->get(route('decisions.recours'))->assertOk();
        $this->assertStringContainsString($d->numeroAffichage(), $this->lignesServeur($this->greffe(), 'decisions.recours.donnees'));
        $this->connecte($this->greffe())->getJson(route('decisions.recours.compteurs'))->assertOk()->assertJsonStructure(['a_signifier', 'en_cours', 'a_certifier', 'recours']);
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('decisions.recours'))->assertForbidden();
        // Hors du périmètre de chambres : la décision n'est pas accessible.
        $sansChambres = $this->utilisateur(['recours.voir', 'recours.modifier']);
        $this->connecte($sansChambres)->post(route('decisions.recours.signification', $d->id), ['date_signification' => '2026-08-05', 'nature' => 'appel'])->assertNotFound();
    }

    // ---------------- AUT-05 : signature réservée ----------------

    public function test_signature_reservee_des_ordonnances_sur_requete(): void
    {
        $greffe = $this->utilisateur(['requetes.voir', 'requetes.creer', 'requetes.modifier', 'affaires.toutes_chambres']);
        $signataire = $this->utilisateur(['requetes.voir', 'requetes.modifier', 'requetes.signer', 'affaires.toutes_chambres']);
        $r = Requete::create(['type' => 'injonction', 'numero_complet' => 'RQ-0001/26', 'numero' => 1, 'annee' => 2026, 'date_depot' => '2026-11-02', 'objet' => 'Injonction de payer', 'requerants' => 'KOUASSI Yao','statut' => 'repartie', 'chambre_id' => $this->chambre->id]);
        $cloturer = fn (User $u) => $this->connecte($u)->post(route('affaires.requetes.cloturer', $r->id), ['issue' => 'traitee', 'reference' => 'Ordonnance n° 5/26', 'date' => '2026-11-05']);

        $cloturer($greffe)->assertForbidden();
        $this->assertSame('repartie', $r->refresh()->statut);
        $cloturer($signataire)->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame(['traitee', $signataire->id], [$r->statut, $r->signee_par]);
        $this->assertNotNull($r->signee_le);
    }
}
