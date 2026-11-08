<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\User;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot M2 : la décision se rédige pour une affaire du registre (plus de référence tapée à la main). */
class RedactionAffaireTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    private ModeleDecision $modele;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        $this->modele = ModeleDecision::firstOrFail();
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function redacteur(): User
    {
        return $this->avecDroits(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction']);   // voit tout, familles protégées comprises
    }

    private function affaire(int $n, string $nom = 'Kone'): Affaire
    {
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 800 + $n, 'numero_complet' => 'RG-'.(800 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-05-20', 'objet' => 'Paiement de loyers '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-06-04',
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => $nom, 'prenoms' => 'Ibrahima', 'cle' => 'p'.$n]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$n, 'cle' => 'd'.$n]);

        return $affaire;
    }

    /** Un rôle publié où l'affaire est jugée. */
    private function juger(Affaire ...$affaires): void
    {
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04', 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => '2026-06-04']);
        GenerateurRole::completer($role);
        $role->forceFill(['statut' => 'publie'])->save();
        foreach ($role->refresh()->lignes()->whereIn('affaire_id', collect($affaires)->pluck('id'))->get() as $ligne) {
            $ligne->update(['issue' => 'juge']);
        }
    }

    public function test_la_decision_est_rattachee_a_l_affaire_choisie(): void
    {
        $affaire = $this->affaire(1);
        $u = $this->redacteur();

        $this->connecte($u)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $this->modele->id, 'affaire_id' => $affaire->id, 'chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $d = Decision::latest('id')->firstOrFail();
        $this->assertSame($affaire->id, $d->affaire_id);
        $this->assertSame($affaire->numeroAffichage(), $d->reference, 'La référence est celle du registre.');
        $this->assertSame('KONE Ibrahima', $d->valeurs['demandeur.nom'] ?? null, 'Le demandeur est repris de la fiche de l\'affaire.');
    }

    public function test_une_affaire_inconnue_ou_hors_perimetre_est_refusee(): void
    {
        $affaire = $this->affaire(2);
        $u = $this->redacteur();
        $donnees = ['modele_decision_id' => $this->modele->id, 'chambre_id' => $this->chambre->id];

        $this->connecte($u)->post(route('decisions.redaction.creer'), $donnees + ['affaire_id' => 999999])->assertSessionHasErrors('affaire_id');
        $this->connecte($u)->post(route('decisions.redaction.creer'), $donnees)->assertSessionHasErrors('affaire_id');

        $limite = $this->avecDroits(['redaction.voir', 'redaction.creer', 'affaires.voir']);   // sans « toutes les chambres »
        // Hors périmètre, le serveur refuse : la chambre, puis l'affaire elle-même.
        $this->connecte($limite)->post(route('decisions.redaction.creer'), $donnees + ['affaire_id' => $affaire->id])->assertSessionHasErrors('chambre_id');
        $this->assertSame(0, Decision::count());
    }

    public function test_la_liste_et_l_acces_aux_decisions_suivent_le_perimetre_des_chambres(): void
    {
        $autreChambre = Chambre::where('id', '!=', $this->chambre->id)->where('libelle', 'like', '%chambre civile%')->firstOrFail();
        $mienne = Decision::create(['modele_decision_id' => $this->modele->id, 'chambre_id' => $this->chambre->id, 'version_numero' => 1, 'reference' => 'MA CHAMBRE 1/26', 'statut' => 'brouillon', 'valeurs' => []]);
        $autre = Decision::create(['modele_decision_id' => $this->modele->id, 'chambre_id' => $autreChambre->id, 'version_numero' => 1, 'reference' => 'AUTRE CHAMBRE 2/26', 'statut' => 'brouillon', 'valeurs' => []]);

        $greffier = $this->avecDroits(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'redaction.annuler', 'redaction.valider']);
        $agent = \App\Models\Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $greffier->id]);
        \App\Models\Affectation::create(['formation_id' => $this->chambre->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);

        // La liste ne montre que ses chambres.
        $this->connecte($greffier)->get(route('decisions.redaction'))->assertOk();
        $lignes = $this->lignesServeur($greffier, 'decisions.redaction.donnees');
        $this->assertStringContainsString('MA CHAMBRE 1/26', $lignes);
        $this->assertStringNotContainsString('AUTRE CHAMBRE 2/26', $lignes);

        // Il ouvre la sienne, mais l'autre est « introuvable » sur chaque route.
        $this->connecte($greffier)->get(route('decisions.redaction.edition', $mienne->id))->assertOk();
        $this->connecte($greffier)->get(route('decisions.redaction.edition', $autre->id))->assertNotFound();
        $this->connecte($greffier)->postJson(route('decisions.redaction.apercu', $autre->id))->assertNotFound();
        $this->connecte($greffier)->post(route('decisions.redaction.store', $autre->id), ['reference' => 'X'])->assertNotFound();
        $this->connecte($greffier)->post(route('decisions.redaction.valider', $autre->id))->assertNotFound();
        $this->connecte($greffier)->post(route('decisions.redaction.corriger', $autre->id))->assertNotFound();
        $this->connecte($greffier)->get(route('decisions.redaction.imprimer', $autre->id))->assertNotFound();
        $this->connecte($greffier)->deleteJson(route('decisions.redaction.delete', $autre->id))->assertNotFound();
        $this->assertDatabaseHas('decisions', ['id' => $autre->id]);

        // Une décision sans chambre (ancienne saisie) n'est visible que de son auteur, ou de qui voit toutes les chambres.
        $ancienne = Decision::create(['modele_decision_id' => $this->modele->id, 'chambre_id' => null, 'version_numero' => 1, 'reference' => 'ANCIENNE 3/26', 'statut' => 'brouillon', 'valeurs' => [], 'cree_par' => $greffier->id]);
        $this->connecte($greffier)->get(route('decisions.redaction.edition', $ancienne->id))->assertOk();
        $this->connecte($this->redacteur())->get(route('decisions.redaction.edition', $ancienne->id))->assertOk();
        $autreGreffier = $this->avecDroits(['redaction.voir']);
        $this->connecte($autreGreffier)->get(route('decisions.redaction.edition', $ancienne->id))->assertNotFound();
    }

    public function test_un_greffier_ne_redige_que_pour_les_affaires_de_sa_chambre(): void
    {
        $sienne = $this->affaire(20);                                   // dans sa chambre
        $autre = $this->affaire(21);
        $autreChambre = Chambre::where('id', '!=', $this->chambre->id)->where('libelle', 'like', '%chambre civile%')->firstOrFail();
        $autre->forceFill(['chambre_id' => $autreChambre->id])->save();   // dans une autre chambre
        $this->juger($sienne, $autre);

        $greffier = $this->avecDroits(['redaction.voir', 'redaction.creer', 'affaires.voir']);
        $agent = \App\Models\Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $greffier->id]);
        \App\Models\Affectation::create(['formation_id' => $this->chambre->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);

        // La recherche (avec ou sans terme) ne montre que sa chambre.
        foreach (['', 'loyers', '821'] as $terme) {
            $numeros = collect($this->connecte($greffier)->getJson(route('decisions.redaction.affaires', ['q' => $terme]))->assertOk()->json('resultats'))->pluck('numero');
            $this->assertNotContains($autre->numeroAffichage(), $numeros, "« $terme » ne doit pas révéler l'affaire d'une autre chambre.");
        }
        $this->assertContains($sienne->numeroAffichage(), collect($this->connecte($greffier)->getJson(route('decisions.redaction.affaires'))->json('resultats'))->pluck('numero'));

        // Le serveur refuse l'affaire d'une autre chambre, même avec son identifiant, et accepte la sienne.
        $donnees = ['modele_decision_id' => $this->modele->id, 'chambre_id' => $this->chambre->id];
        $this->connecte($greffier)->post(route('decisions.redaction.creer'), $donnees + ['affaire_id' => $autre->id])->assertSessionHasErrors('affaire_id');
        $this->assertSame(0, Decision::count());
        $this->connecte($greffier)->post(route('decisions.redaction.creer'), $donnees + ['affaire_id' => $sienne->id])->assertSessionHasNoErrors();
        $this->assertSame($sienne->id, Decision::firstOrFail()->affaire_id);

        // Depuis la feuille : l'affaire d'une autre chambre n'est pas préchoisie.
        $this->assertStringContainsString('PRESELECTION = null', $this->connecte($greffier)->get(route('decisions.redaction', ['affaire' => $autre->id]))->getContent());
        $this->assertStringContainsString('PRESELECTION = JSON.parse', $this->connecte($greffier)->get(route('decisions.redaction', ['affaire' => $sienne->id]))->getContent());
    }

    public function test_le_formulaire_est_prerempli_avec_les_donnees_de_l_affaire_de_la_requete_et_de_l_audience(): void
    {
        $affaire = $this->affaire(30);
        $affaire->forceFill(['matiere_id' => Matiere::where('libelle', 'Civile')->value('id')])->save();
        \App\Models\Requete::create([
            'annee' => 2026, 'numero' => 1, 'numero_complet' => 'RQ-0001/26', 'type' => 'heredite', 'date_depot' => '2026-05-12', 'objet' => 'Jugement d\'hérédité', 'requerants' => 'Kone Ibrahima',
            'defunt_nom' => 'DIALLO Mariam', 'defunt_date_deces' => '2026-04-16', 'defunt_lieu_deces' => 'la clinique Sainte-Marie de Bingerville', 'affaire_id' => $affaire->id,
        ]);
        $this->juger($affaire);
        $role = RoleAudience::firstOrFail();
        $role->forceFill(['composition_tenue' => [
            ['agent_id' => 0, 'nom' => 'KOUASSI Marie', 'role' => 'Président', 'present' => false, 'remplacant' => 'TRAORE Salif'],
            ['agent_id' => 0, 'nom' => 'BAMBA Idrissa', 'role' => 'Juge', 'present' => true, 'remplacant' => null],
            ['agent_id' => 0, 'nom' => 'ABSENT Paul', 'role' => 'Juge', 'present' => false, 'remplacant' => null],
            ['agent_id' => 0, 'nom' => 'SERY Lou', 'role' => 'Greffier', 'present' => true, 'remplacant' => null],
        ]])->save();
        $role->lignes()->where('affaire_id', $affaire->id)->update(['presence' => 'toutes']);

        $u = $this->redacteur();
        $this->connecte($u)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $this->modele->id, 'affaire_id' => $affaire->id, 'chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04'])
            ->assertSessionHasNoErrors();
        $v = Decision::latest('id')->firstOrFail()->valeurs;

        // On ne contrôle que les champs que le modèle utilise (les autres sont écartés).
        $attendu = [
            'matiere' => "civile d'état des personnes", 'type_audience' => 'ordinaire', 'date_decision' => '2026-06-04', 'nature_decision' => 'contradictoire',
            'date_requete' => '2026-05-12', 'defunt.nom' => 'DIALLO Mariam', 'date_deces' => '2026-04-16', 'lieu_deces' => 'la clinique Sainte-Marie de Bingerville',
            'president.nom' => 'TRAORE Salif', 'greffier.nom' => 'SERY Lou',
        ];
        $codes = collect(\App\Support\Redaction\ChampsDecision::pour($this->modele->load('variables'), $this->modele->versionCourante()))->pluck('code')->all();
        $verifies = 0;
        foreach ($attendu as $code => $valeur) {
            if (in_array($code, $codes, true)) {
                $this->assertSame($valeur, $v[$code] ?? null, "« $code » doit être prérempli.");
                $verifies++;
            }
        }
        $this->assertGreaterThanOrEqual(6, $verifies, 'Le modèle utilise plusieurs de ces champs.');
        if (in_array('assesseurs', $codes, true)) {
            // Le genre d'un assesseur inconnu est à indiquer ; l'absent sans remplaçant ne figure pas.
            $this->assertSame([['nom' => 'BAMBA Idrissa', 'genre' => '']], $v['assesseurs']);
        }
        // La décision s'ouvre et son aperçu se calcule malgré le genre à compléter.
        $id = Decision::latest('id')->value('id');
        $this->connecte($u)->get(route('decisions.redaction.edition', $id))->assertOk();
        $this->connecte($u)->postJson(route('decisions.redaction.apercu', $id))->assertOk();
    }

    public function test_la_recherche_trouve_par_numero_et_par_partie(): void
    {
        $this->affaire(3, 'Yapi');
        $this->affaire(4, 'Bakayoko');
        $u = $this->redacteur();

        $this->connecte($u)->getJson(route('decisions.redaction.affaires', ['q' => '803']))->assertOk()->assertJsonCount(1, 'resultats')->assertJsonPath('resultats.0.numero', '803/26');
        $this->connecte($u)->getJson(route('decisions.redaction.affaires', ['q' => 'Bakayoko']))->assertOk()->assertJsonCount(1, 'resultats')->assertJsonPath('resultats.0.numero', '804/26');
        $this->connecte($u)->getJson(route('decisions.redaction.affaires', ['q' => 'EXEMPLE 4']))->assertJsonCount(1, 'resultats');
        // Les jokers de recherche sont du texte, pas des motifs.
        $this->connecte($u)->getJson(route('decisions.redaction.affaires', ['q' => '%']))->assertOk()->assertJsonCount(0, 'resultats');
    }

    public function test_sans_recherche_on_propose_les_affaires_jugees_a_rediger(): void
    {
        $jugee = $this->affaire(5);
        $validee = $this->affaire(6);
        $this->affaire(7);   // jamais jugée
        $this->juger($jugee, $validee);
        Decision::create(['modele_decision_id' => $this->modele->id, 'affaire_id' => $validee->id, 'version_numero' => 1, 'reference' => $validee->numeroAffichage(), 'statut' => 'validee', 'valeurs' => []]);
        $u = $this->redacteur();

        $r = $this->connecte($u)->getJson(route('decisions.redaction.affaires'))->assertOk();
        $r->assertJsonCount(3, 'resultats')->assertJsonPath('resultats.0.numero', '805/26')->assertJsonPath('resultats.0.a_rediger', true)->assertJsonPath('resultats.0.audience', '2026-06-04');
        // Les autres affaires complètent la liste, après celles à rédiger.
        $this->assertSame([true, false, false], collect($r->json('resultats'))->pluck('a_rediger')->all());

        // En cherchant, l'affaire jugée sans décision validée passe en premier.
        $this->connecte($u)->getJson(route('decisions.redaction.affaires', ['q' => 'loyers']))->assertJsonPath('resultats.0.numero', '805/26');
    }

    public function test_la_recherche_exige_le_droit_de_creer(): void
    {
        $this->affaire(8);
        $lecteur = $this->avecDroits(['redaction.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->getJson(route('decisions.redaction.affaires'))->assertForbidden();
    }

    public function test_la_reference_d_une_decision_rattachee_ne_se_retape_pas(): void
    {
        $affaire = $this->affaire(9);
        $u = $this->redacteur();
        $this->connecte($u)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $this->modele->id, 'affaire_id' => $affaire->id, 'chambre_id' => $this->chambre->id])->assertSessionHasNoErrors();
        $d = Decision::latest('id')->firstOrFail();

        $this->connecte($u)->post(route('decisions.redaction.store', $d->id), ['reference' => 'AUTRE 1/2026', 'valeurs' => []])->assertRedirect();
        $this->assertSame($affaire->numeroAffichage(), $d->refresh()->reference);

        $page = $this->connecte($u)->get(route('decisions.redaction.edition', $d->id))->assertOk()->getContent();
        $this->assertStringContainsString('ouvrir la fiche de l', $page);
        $this->assertStringContainsString('readonly', $page);
    }

    public function test_la_page_s_ouvre_avec_l_affaire_deja_choisie_depuis_la_feuille(): void
    {
        $affaire = $this->affaire(10);
        $this->juger($affaire);
        $u = $this->redacteur();

        $page = $this->connecte($u)->get(route('decisions.redaction', ['affaire' => $affaire->id]))->assertOk()->getContent();
        $this->assertStringContainsString('PRESELECTION = JSON.parse', $page);
        $this->assertStringContainsString('810', $page);
        $this->assertStringContainsString('Rédiger une décision', $page);
        // Sans le droit de créer, rien n'est préchoisi.
        $lecteur = $this->avecDroits(['redaction.voir', 'affaires.toutes_chambres']);
        $this->assertStringContainsString('PRESELECTION = null', $this->connecte($lecteur)->get(route('decisions.redaction', ['affaire' => $affaire->id]))->getContent());
    }

    public function test_une_decision_validee_fait_sortir_l_affaire_de_la_liste_a_rediger(): void
    {
        $affaire = $this->affaire(11);
        $this->juger($affaire);
        $u = $this->redacteur();
        $this->assertTrue($this->connecte($u)->getJson(route('decisions.redaction.affaires'))->json('resultats.0.a_rediger'));

        Decision::create(['modele_decision_id' => $this->modele->id, 'affaire_id' => $affaire->id, 'version_numero' => 1, 'reference' => $affaire->numeroAffichage(), 'statut' => 'validee', 'valeurs' => []]);
        $this->assertFalse($this->connecte($u)->getJson(route('decisions.redaction.affaires'))->json('resultats.0.a_rediger'));
    }
}
