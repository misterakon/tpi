<?php

namespace Tests\Feature;

use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Delivrance;
use App\Models\ModeleDecision;
use App\Models\Profil;
use App\Models\User;
use App\Services\NumeroDecision;
use App\Support\Alertes\CollecteEcheances;
use App\Support\Decisions\RegistreDecisions;
use App\Support\Redaction\ChampsDecision;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Lot M2 : cycle de vie d'une décision (rédigée, validée, signée, numérotée, délivrée), registre des décisions, alertes de statut. */
class RedactionCycleTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    private ModeleDecision $modele;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        $this->modele = ModeleDecision::firstOrFail();
        config(['tpi.decisions.numero_moment' => 'signature']);   // les tests existants décrivent ce réglage ; la pratique du tribunal (greffier) a ses propres tests
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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

    private function tout(array $en_plus = []): User
    {
        return $this->avecDroits(array_merge(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'redaction.valider', 'redaction.signer', 'redaction.delivrer', 'registre_decisions.voir', 'registre_decisions.exporter', 'affaires.toutes_chambres'], $en_plus));
    }

    /** Une décision complète (valeurs d'exemple) dans la chambre, en brouillon. */
    private function decision(string $dateDecision = '2026-11-05', ?User $auteur = null): Decision
    {
        static $n = 0;
        $n++;
        $d = Decision::create(['modele_decision_id' => $this->modele->id, 'chambre_id' => $this->chambre->id, 'version_numero' => $this->modele->versionCourante()->numero, 'reference' => 'RG '.$n.'/2026', 'statut' => 'brouillon', 'valeurs' => [], 'cree_par' => $auteur?->id]);
        $valeurs = ChampsDecision::exemplesPour(ChampsDecision::pour($d->modele, $d->version()));
        $valeurs['date_decision'] = $dateDecision;
        $d->update(['valeurs' => $valeurs]);

        return $d->refresh();
    }

    private function url(string $nom, Decision $d): string
    {
        return route($nom, $d->id);
    }

    // ---------------------------------------------------------------- le cycle

    public function test_le_cycle_complet_avec_numero_a_la_signature(): void
    {
        config(['tpi.decisions.numero_moment' => 'signature']);
        $u = $this->tout();
        $d = $this->decision();

        // 1. Rédigée.
        $this->connecte($u)->post($this->url('decisions.redaction.redigee', $d))->assertSessionHas('success');
        $this->assertSame('redigee', $d->refresh()->statut);
        $this->assertNotNull($d->redigee_le);

        // 2. Validée : le texte est figé, le numéro n'existe pas encore, des points de suspension tiennent sa place.
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d))->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('validee', $d->statut);
        $this->assertNull($d->numero);
        $this->assertSame('2026-11-05', $d->date_decision->toDateString());
        $this->assertStringContainsString('……', json_encode($d->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // 3. Signée : le numéro est attribué et inscrit dans le texte figé.
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-09'])->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('signee', $d->statut);
        $this->assertSame('1', $d->numero);
        $this->assertSame(2026, $d->numero_annee);
        $this->assertSame('D-1/2026', $d->numeroAffichage());
        $this->assertSame('2026-11-09', $d->date_signature->toDateString());
        $this->assertStringNotContainsString('……', json_encode($d->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString('N° D-1/2026 du', json_encode($d->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Le numéro est dans le texte figé.');

        // 4. Délivrée.
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), ['type' => 'expedition', 'delivre_a' => 'Me DAGBO', 'qualite' => 'avocat', 'delivre_le' => '2026-11-10'])->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('delivree', $d->statut);
        $this->assertCount(1, $d->delivrances);
        foreach (['decision_redigee', 'decision_validee', 'decision_signee', 'decision_delivree'] as $action) {
            $this->assertDatabaseHas('journal_audit', ['action' => $action]);
        }

        // La page de la décision montre la frise, le numéro et les remises.
        $page = $this->connecte($u)->get($this->url('decisions.redaction.edition', $d))->assertOk()->getContent();
        $this->assertStringContainsString('Suivi de la décision', $page);
        $this->assertStringContainsString('Numéro de la décision', $page);   // champ grisé, en lecture seule
        $this->assertMatchesRegularExpression('/id="f_numero_decision"[^>]*value="D-1\/2026"[^>]*disabled/', $page);
        $this->assertStringContainsString('D-1/2026', $page);
        $this->assertStringContainsString('Me DAGBO', $page);
    }

    public function test_le_greffier_de_la_chambre_donne_le_numero_quand_le_juge_a_termine(): void
    {
        config(['tpi.decisions.numero_moment' => 'redaction']);
        $juge = $this->avecDroits(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'redaction.valider', 'redaction.signer', 'affaires.toutes_chambres']);
        $greffier = $this->avecDroits(['redaction.voir', 'redaction.numeroter', 'affaires.toutes_chambres']);
        $d = $this->decision();

        // Le juge n'a pas fini : pas de numéro possible.
        $this->connecte($greffier)->post($this->url('decisions.redaction.numeroter', $d))->assertSessionHas('error');
        $this->assertNull($d->refresh()->numero);

        // Le juge termine sa rédaction : la décision est « rédigée ». Sans le droit, on ne donne pas le numéro.
        $this->connecte($juge)->post($this->url('decisions.redaction.redigee', $d))->assertSessionHas('success');
        $this->connecte($juge)->post($this->url('decisions.redaction.numeroter', $d))->assertForbidden();

        // La validation attend le numéro du greffier.
        $this->connecte($juge)->post($this->url('decisions.redaction.valider', $d))->assertSessionHas('error');
        $this->assertSame('redigee', $d->refresh()->statut);

        // Le greffier donne le numéro, une seule fois.
        $this->connecte($greffier)->post($this->url('decisions.redaction.numeroter', $d))->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('D-1/2026', $d->numeroAffichage());
        $this->assertSame('redigee', $d->statut, 'Numéroter ne valide pas : le juge valide ensuite.');
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_numerotee']);
        $this->connecte($greffier)->post($this->url('decisions.redaction.numeroter', $d))->assertSessionHas('error');
        $this->assertSame('1', $d->refresh()->numero);

        // Le juge valide : le numéro est dans le texte figé dès la validation, et la signature ne le change pas.
        $this->connecte($juge)->post($this->url('decisions.redaction.valider', $d))->assertSessionHas('success');
        $this->assertStringNotContainsString('……', json_encode($d->refresh()->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString('N° D-1/2026 du', json_encode($d->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->connecte($juge)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-09'])->assertSessionHas('success');
        $this->assertSame('D-1/2026', $d->refresh()->numeroAffichage());

        // La frise place « Numérotée » juste après « Rédigée ».
        $codes = array_column($d->etapes(), 'code');
        $this->assertSame(['brouillon', 'redigee', 'numerotee', 'validee', 'signee', 'delivree'], $codes);
    }

    public function test_le_numero_peut_etre_attribue_des_la_validation(): void
    {
        config(['tpi.decisions.numero_moment' => 'validation']);
        $u = $this->tout();
        $d = $this->decision();

        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d))->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('D-1/2026', $d->numeroAffichage(), 'Le numéro est donné à la validation.');
        $this->assertStringContainsString('N° D-1/2026 du', json_encode($d->rendu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // À la signature, le numéro ne change pas et le texte figé non plus.
        $figee = $d->rendu;
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10'])->assertSessionHas('success');
        $this->assertSame('D-1/2026', $d->refresh()->numeroAffichage());
        $this->assertSame($figee, $d->rendu);
    }

    public function test_la_numerotation_est_continue_par_annee_sans_doublon(): void
    {
        $u = $this->tout();
        $a = $this->decision('2026-11-05');
        $b = $this->decision('2026-11-06');
        $c = $this->decision('2027-01-04');   // une autre année : le compteur repart
        foreach ([$a, $b, $c] as $d) {
            $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        }
        // Signées dans un autre ordre : le numéro suit l'ordre de signature.
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $b), ['date_signature' => '2026-11-10']);
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $a), ['date_signature' => '2026-11-10']);
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $c), ['date_signature' => '2026-11-10']);

        $this->assertSame(['D-1/2026', 'D-2/2026', 'D-1/2027'], [$b->refresh()->numeroAffichage(), $a->refresh()->numeroAffichage(), $c->refresh()->numeroAffichage()]);

        // Le service ne redonne jamais un numéro déjà attribué, et la base l'interdit.
        $this->assertSame('D-2/2026', NumeroDecision::attribuer($a)->numeroAffichage());
        $this->expectException(\Illuminate\Database\QueryException::class);
        Decision::where('id', $a->id)->update(['numero_annee' => 2026, 'numero_seq' => 1]);   // déjà pris par $b
    }

    // ---------------------------------------------------------------- règles et droits

    public function test_les_etapes_se_font_dans_l_ordre(): void
    {
        $u = $this->tout();
        $d = $this->decision();

        // Signer ou délivrer un brouillon : refusé avec un message.
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10'])->assertSessionHas('error');
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), ['type' => 'copie', 'delivre_a' => 'X', 'delivre_le' => '2026-11-10'])->assertSessionHas('error');
        $this->assertSame('brouillon', $d->refresh()->statut);

        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        // Délivrer une décision seulement validée : refusé. La date de signature ne peut pas être future.
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), ['type' => 'copie', 'delivre_a' => 'X', 'delivre_le' => '2026-11-10'])->assertSessionHas('error');
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-12-01'])->assertSessionHasErrors('date_signature');
        $this->assertSame('validee', $d->refresh()->statut);
    }

    public function test_une_decision_incomplete_ne_peut_etre_ni_redigee_ni_validee(): void
    {
        $u = $this->tout();
        $d = $this->decision();
        $d->update(['valeurs' => []]);

        $this->connecte($u)->post($this->url('decisions.redaction.redigee', $d))->assertSessionHas('error');
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d))->assertSessionHas('error');
        $this->assertSame('brouillon', $d->refresh()->statut);
    }

    public function test_une_decision_redigee_ne_se_modifie_plus_sauf_par_celui_qui_valide(): void
    {
        $redacteur = $this->avecDroits(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'affaires.toutes_chambres']);
        $valideur = $this->tout();
        $d = $this->decision();
        $this->connecte($redacteur)->post($this->url('decisions.redaction.redigee', $d))->assertSessionHas('success');

        // Le rédacteur ne peut plus enregistrer ; celui qui valide le peut, et peut la renvoyer en rédaction.
        $this->connecte($redacteur)->post($this->url('decisions.redaction.store', $d), ['reference' => 'X', 'valeurs' => []])->assertForbidden();
        $this->connecte($valideur)->post($this->url('decisions.redaction.store', $d), ['reference' => $d->reference, 'valeurs' => $d->valeurs])->assertSessionHasNoErrors();
        $this->connecte($redacteur)->post($this->url('decisions.redaction.renvoyer', $d))->assertForbidden();
        $this->connecte($valideur)->post($this->url('decisions.redaction.renvoyer', $d))->assertSessionHas('success');
        $this->assertSame('brouillon', $d->refresh()->statut);
        $this->assertNull($d->redigee_le);
        $this->connecte($redacteur)->post($this->url('decisions.redaction.store', $d), ['reference' => $d->reference, 'valeurs' => $d->valeurs])->assertSessionHasNoErrors();
    }

    public function test_signer_et_delivrer_ont_leurs_droits(): void
    {
        $sans = $this->avecDroits(['redaction.voir', 'redaction.valider', 'affaires.toutes_chambres']);
        $u = $this->tout();
        $d = $this->decision();
        $this->connecte($sans)->post($this->url('decisions.redaction.valider', $d));

        $this->connecte($sans)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10'])->assertForbidden();
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10']);
        $this->connecte($sans)->post($this->url('decisions.redaction.delivrer', $d), ['type' => 'copie', 'delivre_a' => 'X', 'delivre_le' => '2026-11-10'])->assertForbidden();
        // Les boutons n'apparaissent que pour qui a le droit.
        $this->assertStringNotContainsString('onclick="ouvrir_delivrance()"', $this->connecte($sans)->get($this->url('decisions.redaction.edition', $d))->getContent());
        $this->assertStringContainsString('onclick="ouvrir_delivrance()"', $this->connecte($u)->get($this->url('decisions.redaction.edition', $d))->getContent());
    }

    public function test_une_decision_signee_ne_se_corrige_ni_ne_se_supprime(): void
    {
        $u = $this->tout(['redaction.annuler']);
        $d = $this->decision();
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10']);

        $this->connecte($u)->post($this->url('decisions.redaction.corriger', $d))->assertSessionHas('error');
        $this->assertSame(1, Decision::count(), 'Aucune copie n\'est créée.');
        $this->connecte($u)->deleteJson($this->url('decisions.redaction.delete', $d))->assertOk()->assertJsonPath('status', false);
        $this->connecte($u)->post($this->url('decisions.redaction.store', $d), ['reference' => 'X', 'valeurs' => []])->assertForbidden();
        $this->assertDatabaseHas('decisions', ['id' => $d->id]);
    }

    public function test_une_grosse_n_est_delivree_qu_une_fois_a_la_meme_personne(): void
    {
        $u = $this->tout();
        $d = $this->decision();
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10']);
        $donnees = ['type' => 'grosse', 'delivre_a' => 'Société EXEMPLE', 'qualite' => 'partie', 'delivre_le' => '2026-11-10'];

        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), $donnees)->assertSessionHas('success');
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), array_merge($donnees, ['delivre_a' => ' société  exemple ']))->assertSessionHas('error');   // même personne
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), array_merge($donnees, ['type' => 'copie']))->assertSessionHas('success');             // une copie reste possible
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), array_merge($donnees, ['type' => 'inconnu']))->assertSessionHasErrors('type');
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $d), array_merge($donnees, ['delivre_le' => '2027-01-01']))->assertSessionHasErrors('delivre_le');
        $this->assertSame(2, Delivrance::count());
    }

    public function test_le_perimetre_des_chambres_s_applique_aux_nouvelles_etapes(): void
    {
        $u = $this->tout();
        $d = $this->decision();
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        $limite = $this->avecDroits(['redaction.voir', 'redaction.modifier', 'redaction.valider', 'redaction.signer', 'redaction.delivrer']);   // aucune chambre

        $this->connecte($limite)->post($this->url('decisions.redaction.signer', $d), ['date_signature' => '2026-11-10'])->assertNotFound();
        $this->connecte($limite)->post($this->url('decisions.redaction.delivrer', $d), ['type' => 'copie', 'delivre_a' => 'X', 'delivre_le' => '2026-11-10'])->assertNotFound();
        $this->connecte($limite)->post($this->url('decisions.redaction.redigee', $d))->assertNotFound();
    }

    // ---------------------------------------------------------------- registre

    public function test_le_registre_liste_filtre_et_exporte(): void
    {
        $u = $this->tout();
        $a = $this->decision('2026-11-05');
        $b = $this->decision('2026-11-06');
        $this->decision('2026-11-07');   // brouillon : pas au registre
        foreach ([$a, $b] as $d) {
            $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));
        }
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $a), ['date_signature' => '2026-11-10']);
        $this->connecte($u)->post($this->url('decisions.redaction.delivrer', $a), ['type' => 'expedition', 'delivre_a' => 'Me DAGBO', 'delivre_le' => '2026-11-10']);

        $this->connecte($this->avecDroits(['redaction.voir']))->get(route('decisions.registre'))->assertForbidden();
        $this->connecte($u)->get(route('decisions.registre'))->assertOk()->assertSee('Registre des décisions');

        $r = $this->connecte($u)->getJson(route('decisions.registre.donnees'))->assertOk();
        $r->assertJsonPath('total', 2)->assertJsonPath('compteurs.validee', 1)->assertJsonPath('compteurs.delivree', 1);
        $this->assertSame(['D-1/2026'], collect($r->json('lignes'))->pluck('numero')->filter()->values()->all(), 'Seule la signée a un numéro (réglage : à la signature).');
        $this->connecte($u)->getJson(route('decisions.registre.donnees', ['statut' => 'delivree']))->assertJsonPath('total', 1)->assertJsonPath('lignes.0.remises', 1);
        $this->connecte($u)->getJson(route('decisions.registre.donnees', ['q' => 'D-1/2026']))->assertJsonPath('total', 1);
        $this->connecte($u)->getJson(route('decisions.registre.donnees', ['annee' => '2025']))->assertJsonPath('total', 0);
        $this->connecte($u)->getJson(route('decisions.registre.donnees', ['statut' => "x' OR 1=1", 'annee' => 'abc']))->assertOk()->assertJsonPath('total', 2);   // valeurs inconnues ignorées

        // Exports.
        $pdf = $this->connecte($u)->get(route('decisions.registre.export', ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->connecte($u)->get(route('decisions.registre.export', ['format' => 'xlsx']))->assertOk();
        $this->connecte($this->tout()->forceFill(['profil_id' => Profil::create(['nom' => 'Lecteur', 'droits' => ['registre_decisions.voir', 'affaires.toutes_chambres']])->id]))->get(route('decisions.registre.export', ['format' => 'pdf']))->assertForbidden();
        $this->assertCount(2, RegistreDecisions::lignes(RegistreDecisions::requete($u, RegistreDecisions::filtres([]))->get()));
    }

    public function test_le_registre_ne_montre_que_les_chambres_de_l_utilisateur(): void
    {
        $u = $this->tout();
        $d = $this->decision();
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $d));

        $limite = $this->avecDroits(['registre_decisions.voir']);
        $this->connecte($limite)->getJson(route('decisions.registre.donnees'))->assertOk()->assertJsonPath('total', 0);
    }

    // ---------------------------------------------------------------- alertes de statut

    public function test_les_decisions_en_attente_de_validation_ou_de_signature_ont_une_echeance(): void
    {
        $u = $this->tout();
        $redigee = $this->decision();
        $validee = $this->decision();
        $this->connecte($u)->post($this->url('decisions.redaction.redigee', $redigee));
        $this->connecte($u)->post($this->url('decisions.redaction.valider', $validee));

        $echeances = CollecteEcheances::toutes(today());
        $v = $echeances->firstWhere('type', 'validation_decision');
        $s = $echeances->firstWhere('type', 'signature_decision');
        $this->assertNotNull($v);
        $this->assertNotNull($s);
        $this->assertStringContainsString('à valider', $v->titre);
        $this->assertStringContainsString('à signer', $s->titre);
        $this->assertSame($this->chambre->id, $v->chambreId);
        // Trois jours ouvrables après le mardi 10/11 : le vendredi 13/11.
        $this->assertSame('2026-11-13', $v->date->toDateString());

        // Signée : plus d'échéance de signature.
        $this->connecte($u)->post($this->url('decisions.redaction.signer', $validee), ['date_signature' => '2026-11-10']);
        $this->assertNull(CollecteEcheances::toutes(today())->firstWhere('type', 'signature_decision'));
    }

    public function test_le_reglage_du_moment_du_numero_est_dans_les_reglages(): void
    {
        $this->assertArrayHasKey('tpi.decisions.numero_moment', \App\Services\Reglages::champs());
        $this->assertSame(['redaction', 'validation', 'signature'], array_keys(NumeroDecision::MOMENTS));
        config(['tpi.decisions.numero_moment' => 'nimporte quoi']);
        $this->assertSame('validation', NumeroDecision::moment(), 'Une valeur inconnue retombe sur la pratique du tribunal : le numéro est généré à la validation.');
    }
}
