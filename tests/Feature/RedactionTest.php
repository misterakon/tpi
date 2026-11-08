<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Formation;
use App\Models\Section;
use App\Models\ModeleDecision;
use App\Models\Profil;
use App\Models\User;
use App\Support\Redaction\ChampsDecision;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tpi.decisions.numero_moment' => 'signature']);   // ces tests valident sans passer par le greffier ; sa règle a ses tests dans RedactionCycleTest
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function charger(): ModeleDecision
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);

        return ModeleDecision::firstOrFail();
    }

    private function ouvrir(User $user, ModeleDecision $modele, string $reference = 'RG 017/2026'): Decision
    {
        $this->connecte($user)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => $reference, 'chambre_id' => Chambre::firstOrFail()->id])->assertRedirect();

        return Decision::latest('id')->firstOrFail();
    }

    /** Les valeurs d'exemple du modèle, telles qu'un formulaire les enverrait. */
    private function exemples(Decision $d): array
    {
        $champs = ChampsDecision::pour($d->modele, $d->version());

        return ChampsDecision::exemplesPour($champs);
    }

    public function test_les_champs_viennent_des_variables_utilisees_par_le_modele(): void
    {
        $modele = $this->charger();
        $champs = collect(ChampsDecision::pour($modele, $modele->versionCourante()));
        $codes = $champs->pluck('code')->all();

        foreach (['demandeur.nom', 'demandeur.genre', 'date_audience', 'president.nom', 'assesseurs', 'defunt.nom', 'enfants', 'nombre_enfants'] as $attendu) {
            $this->assertContains($attendu, $codes, $attendu);
        }
        // Les ensembles (demandeur, president…) n'ont pas de champ propre : leurs éléments suffisent.
        $this->assertNotContains('demandeur', $codes);
        // « Maître {{ greffier.nom }} » : la civilité est imposée, le genre du greffier ne sert pas ; celui des assesseurs sert (Madame / Monsieur).
        $this->assertNotContains('greffier.genre', $codes);
        $this->assertNotContains('assesseurs.0.nom', $codes);   // les assesseurs sont une liste, pas deux champs fixes
        $this->assertSame('personnes', $champs->firstWhere('code', 'assesseurs')['type']);
        $this->assertContains('en personne', $champs->firstWhere('code', 'demandeur.comparution')['options']);
        $this->assertContains('la séparation de biens', $champs->firstWhere('code', 'regime_matrimonial')['options']);
        $this->assertSame('liste', $champs->firstWhere('code', 'enfants')['type']);
        // Ce que l'application connaît n'est jamais demandé ; matière et type d'audience sont des listes.
        foreach (['juridiction', 'cour_appel', 'type_decision', 'numero_rg'] as $connu) {
            $this->assertNotContains($connu, $codes, $connu);
        }
        $this->assertContains('ordinaire', $champs->firstWhere('code', 'type_audience')['options']);
    }

    public function test_ouvrir_une_decision_et_voir_le_formulaire(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();

        $this->connecte($admin)->get(route('decisions.redaction'))->assertOk()->assertSee('Rédaction');
        $d = $this->ouvrir($admin, $modele);

        $this->assertSame('brouillon', $d->statut);
        $this->assertSame($modele->versionCourante()->numero, $d->version_numero);
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_creee']);

        $this->connecte($admin)->get(route('decisions.redaction.edition', $d->id))->assertOk()
            ->assertSee('Demandeur : nom et prénoms')->assertSee('Défunt : nom et prénoms')->assertSee('name="valeurs[demandeur.nom]"', false)
            ->assertSee('name="valeurs[enfants]"', false)->assertSee('feuille-acte.js', false);

        $this->connecte($admin)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => ''])->assertSessionHasErrors('reference');
    }

    public function test_enregistrer_et_apercu(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        // Aperçu sans rien saisir : les champs vides sont signalés.
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => ['demandeur.nom' => '']])->assertOk();
        $this->assertContains('demandeur.nom', $r->json('manquantes'));

        // Avec des valeurs : le texte est rempli et accordé.
        $valeurs = ['demandeur.nom' => 'KOUADIO Yao', 'demandeur.genre' => 'f', 'defunt.nom' => 'DIALLO Mariam', 'defunt.genre' => 'f', 'enfants' => "Aminata\nMoussa", 'nombre_enfants' => '2'];
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => $valeurs])->assertOk();
        $q = strip_tags($r->json('qualite'));
        $this->assertStringContainsString('Madame KOUADIO Yao', $q);
        $this->assertStringContainsString('Demanderesse, comparaissant', $q);
        $this->assertStringContainsString('1- Aminata', strip_tags($r->json('factum')));

        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'RG 018/2026', 'valeurs' => $valeurs])->assertSessionHasNoErrors();
        $d->refresh();
        $this->assertSame('RG 018/2026', $d->reference);
        $this->assertSame('KOUADIO Yao', $d->valeurs['demandeur.nom']);
        $this->assertSame(['Aminata', 'Moussa'], $d->valeurs['enfants']);
        $this->assertSame(2, $d->valeurs['nombre_enfants']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_modifiee']);
    }

    public function test_les_valeurs_sont_controlees_et_protegees(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => ['date_audience' => '31/02/2026']])->assertSessionHasErrors();
        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => ['nombre_enfants' => 'beaucoup']])->assertSessionHasErrors();
        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => ['demandeur.genre' => 'z']])->assertSessionHasErrors();
        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => ''])->assertSessionHasErrors('reference');

        // Un nom piégé est neutralisé (balises retirées) puis protégé à l'affichage.
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => ['demandeur.nom' => '<script>alert(1)</script>KONE Awa', 'demandeur.genre' => 'f']])->assertOk();
        $this->assertStringNotContainsString('<script>', $r->json('qualite'));
        $this->assertStringContainsString('alert(1)KONE Awa', strip_tags($r->json('qualite')));
    }

    public function test_les_informations_connues_sont_remplies_d_avance(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $annee = AnneeJudiciaire::where('courante', true)->firstOrFail();
        $section = Section::firstOrFail();
        $chambre = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '7e chambre test', 'ordre' => 99, 'actif' => true]);
        $formation = Formation::create(['chambre_id' => $chambre->id, 'ordre' => 1, 'actif' => true]);
        $pres = Agent::create(['nom' => 'ZADI', 'prenoms' => 'Awa', 'sexe' => 'F', 'fonction' => 'vice_president', 'actif' => true]);
        $juge = Agent::create(['nom' => 'BAMBA', 'prenoms' => 'Idrissa', 'sexe' => 'M', 'fonction' => 'juge', 'actif' => true]);
        $greffe = Agent::create(['nom' => 'SERY', 'prenoms' => 'Lou', 'sexe' => 'F', 'fonction' => 'greffier', 'actif' => true]);
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $pres->id, 'role' => 'president']);
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $juge->id, 'role' => 'assesseur']);
        Affectation::create(['formation_id' => $formation->id, 'agent_id' => $greffe->id, 'role' => 'greffier']);

        $this->connecte($admin)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => 'RG 5/2026', 'chambre_id' => $chambre->id, 'date_audience' => '2026-11-06'])->assertSessionHasNoErrors();
        $v = Decision::latest('id')->firstOrFail()->valeurs;

        $this->assertSame('ZADI Awa', $v['president.nom']);
        $this->assertSame('f', $v['president.genre']);
        $this->assertSame('vice_president', $v['president.fonction']);
        $this->assertSame([['nom' => 'BAMBA Idrissa', 'genre' => 'm']], $v['assesseurs']);
        $this->assertSame('SERY Lou', $v['greffier.nom']);
        $this->assertSame('2026-11-06', $v['date_audience']);
        $this->assertSame('7ème chambre test', $v['chambre']);
        $this->assertSame('septième chambre test', $v['chambre_lettres']);
        $this->assertArrayNotHasKey('demandeur.nom', $v);   // propre à l'affaire : à saisir
    }

    public function test_un_champ_vide_est_une_pastille_et_un_brouillon_suit_le_modele(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => ['demandeur.genre' => 'f']])->assertOk();
        $this->assertStringContainsString('class="acte-vide"', $r->json('qualite'));
        $this->assertStringContainsString('[ Demandeur : nom et prénoms ]', $r->json('qualite'));
        $this->assertStringNotContainsString('{{', $r->json('qualite'));
        // Juridiction, cour d'appel, type de décision et numéro du RG viennent de l'application, sans saisie.
        $this->assertStringContainsString('RG N° 017/2026', strip_tags($this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => []])->json('entete')));
        $this->assertContains('Demandeur : nom et prénoms', collect($r->json('a_completer'))->pluck('libelle')->all());

        // Le modèle change : le brouillon suit la dernière version.
        $this->connecte($admin)->post(route('decisions.modeles.store'), [
            'id_' => $modele->id, 'nom' => $modele->nom, 'etat' => 'actif', 'entete' => '<p>NOUVELLE VERSION</p>', 'qualite' => '<p>NOUVELLE VERSION</p>', 'factum' => '<p>NOUVELLE VERSION</p>',
        ])->assertSessionHasNoErrors();
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => []])->assertOk();
        $this->assertStringContainsString('NOUVELLE VERSION', $r->json('qualite'));
    }

    public function test_retouche_a_la_main_du_texte(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);
        $d->update(['valeurs' => $this->exemples($d)]);

        // Une partie retouchée remplace le texte généré (le HTML est nettoyé).
        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retouches' => ['qualite' => '<p>Texte <strong>retouché</strong><script>x()</script></p>']])->assertOk();
        $d->refresh();
        $this->assertSame('<p>Texte <strong>retouché</strong></p>', $d->retouches['qualite']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_retouchee']);

        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => ['demandeur.nom' => 'ZZ', 'demandeur.genre' => 'm']])->assertOk();
        $this->assertSame('<p>Texte <strong>retouché</strong></p>', $r->json('qualite'));
        $this->assertSame(['qualite'], $r->json('retouchees'));
        $this->assertStringContainsString('ZZ', $r->json('generes.qualite'));   // le texte généré reste disponible pour « Rétablir »

        // Une partie retouchée ne bloque pas la validation, même si ses champs sont vides ; le texte retouché est figé.
        $d->update(['valeurs' => []]);
        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retouches' => ['entete' => '<p>EN-TÊTE</p>', 'factum' => '<p>FACTUM</p>']])->assertOk();
        $this->connecte($admin)->post(route('decisions.redaction.valider', $d->id))->assertSessionHas('success');
        $this->assertSame('<p>Texte <strong>retouché</strong></p>', $d->refresh()->rendu['qualite']);
        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retablir' => ['qualite']])->assertForbidden();
    }

    public function test_retablir_et_corriger_une_retouche(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retouches' => ['factum' => '<p>À la main</p>']])->assertOk();
        $this->assertSame(['factum'], array_keys($d->refresh()->retouches));

        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retablir' => ['factum'], 'retouches' => ['qualite' => '<p>Q</p>']])->assertOk();
        $this->assertSame(['qualite'], array_keys($d->refresh()->retouches));

        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retouches' => ['qualite' => '<p><br></p>']])->assertOk();   // vidée : retour au texte généré
        $this->assertNull($d->refresh()->retouches);

        $this->connecte($admin)->postJson(route('decisions.redaction.retoucher', $d->id), ['retablir' => ['inconnue']])->assertStatus(422);
        $this->connecte($this->avecDroits(['redaction.voir']))->postJson(route('decisions.redaction.retoucher', $d->id), ['retouches' => ['qualite' => '<p>x</p>']])->assertForbidden();
    }

    public function test_la_fonction_est_une_liste_accordee_au_genre(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $this->connecte($admin)->get(route('decisions.redaction.edition', $d->id))->assertOk()
            ->assertSee('<option value="vice_president"', false)->assertSee('Greffier')->assertSee('red-rangee__titre', false);

        $valeurs = ['president.nom' => 'ZADI Awa', 'president.genre' => 'f', 'president.fonction' => 'vice_president'];
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => $valeurs])->assertOk();
        $this->assertStringContainsString('Vice-Présidente du Tribunal', strip_tags($r->json('qualite')));
        $valeurs['president.genre'] = 'm';
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => $valeurs])->assertOk();
        $this->assertStringContainsString('Vice-Président du Tribunal', strip_tags($r->json('qualite')));
    }

    public function test_reprendre_la_composition_de_l_ordonnance(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $annee = AnneeJudiciaire::where('courante', true)->firstOrFail();
        $chambre = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => Section::firstOrFail()->id, 'libelle' => '8e chambre test', 'ordre' => 98, 'actif' => true]);
        $formation = Formation::create(['chambre_id' => $chambre->id, 'ordre' => 1, 'actif' => true]);
        foreach ([['ZADI', 'F', 'president'], ['BAMBA', 'M', 'assesseur'], ['TANOH', 'F', 'assesseur'], ['SERY', 'F', 'assesseur'], ['LOBA', 'M', 'greffier']] as [$nom, $sexe, $role]) {
            $agent = Agent::create(['nom' => $nom, 'prenoms' => 'X', 'sexe' => $sexe, 'fonction' => $role === 'greffier' ? 'greffier' : 'juge', 'actif' => true]);
            Affectation::create(['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => $role]);
        }

        // Trois assesseurs : la liste n'est pas limitée à deux.
        $this->connecte($admin)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => 'RG 9/2026', 'chambre_id' => $chambre->id])->assertSessionHasNoErrors();
        $d = Decision::latest('id')->firstOrFail();
        $this->assertCount(3, $d->valeurs['assesseurs']);
        $this->assertSame('LOBA X', $d->valeurs['greffier.nom']);

        // Après une saisie à la main, « Composition de l'ordonnance » remet celle de l'ordonnance sans toucher au reste.
        $d->update(['valeurs' => ['president.nom' => 'AUTRE', 'assesseurs' => [['nom' => 'SEUL', 'genre' => 'm']], 'enfants' => ['Awa']]]);
        $this->connecte($admin)->post(route('decisions.redaction.composition', $d->id))->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('ZADI X', $d->valeurs['president.nom']);
        $this->assertCount(3, $d->valeurs['assesseurs']);
        $this->assertSame(['Awa'], $d->valeurs['enfants']);

        // Une décision ouverte sans chambre en reçoit une au moment de reprendre la composition.
        $d->update(['chambre_id' => null, 'valeurs' => []]);
        $this->connecte($admin)->post(route('decisions.redaction.composition', $d->id), ['chambre_id' => $chambre->id])->assertSessionHas('success');
        $this->assertSame($chambre->id, $d->refresh()->chambre_id);
        $this->assertSame('ZADI X', $d->valeurs['president.nom']);

        $this->connecte($this->avecDroits(['redaction.voir']))->post(route('decisions.redaction.composition', $d->id))->assertForbidden();
    }

    public function test_naissance_nationalite_et_indices(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $page = $this->connecte($admin)->get(route('decisions.redaction.edition', $d->id))->assertOk();
        $page->assertSee('name="valeurs[demandeur.naissance][mode]"', false)->assertSee('année seulement')->assertSee('<option value="ivoirienne"', false)->assertSee('<option value="burkinabè"', false)
            ->assertSee('placeholder="Ex. : Bouaké"', false)->assertSee('red-champ--double', false);

        $base = ['demandeur.nom' => 'KONE Awa', 'demandeur.genre' => 'f', 'demandeur.lieu_naissance' => 'Bouaké', 'demandeur.nationalite' => 'burkinabè'];
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => $base + ['demandeur.naissance' => ['mode' => 'date', 'date' => '1970-03-01']]])->assertOk();
        $this->assertStringContainsString('née le 01 mars 1970 à Bouaké, de nationalité burkinabè', strip_tags($r->json('qualite')));
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => $base + ['demandeur.naissance' => ['mode' => 'annee', 'annee' => '1970']]])->assertOk();
        $this->assertStringContainsString('née vers 1970 à Bouaké', strip_tags($r->json('qualite')));

        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => ['demandeur.naissance' => ['mode' => 'annee', 'annee' => '3000']]])->assertSessionHasErrors();
        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => ['demandeur.naissance' => ['mode' => 'date', 'date' => '1970-13-45']]])->assertSessionHasErrors();
    }

    public function test_nature_de_la_decision(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        // Le champ est une liste tirée des caractères de décision de la base.
        $champs = collect(ChampsDecision::pour($modele, $modele->versionCourante()));
        $this->assertSame(['contradictoire', 'par défaut', 'réputé contradictoire'], $champs->firstWhere('code', 'nature_decision')['options']);
        $this->connecte($admin)->get(route('decisions.redaction.edition', $d->id))->assertOk()->assertSee('<option value="par défaut"', false);

        // « contradictoire » s'écrit à l'adverbe, les autres natures telles quelles.
        $fait = fn (string $nature) => strip_tags($this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id), ['valeurs' => ['nature_decision' => $nature]])->json('factum'));
        $this->assertStringContainsString('Statuant publiquement, contradictoirement,', $fait('contradictoire'));
        $this->assertStringContainsString('Statuant publiquement, par défaut,', $fait('par défaut'));
        $this->assertStringContainsString('Statuant publiquement, réputé contradictoire,', $fait('réputé contradictoire'));
    }

    public function test_validation_fige_le_texte(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        // Incomplète : refusée, avec la liste de ce qui manque.
        $this->connecte($admin)->post(route('decisions.redaction.valider', $d->id))->assertRedirect()->assertSessionHas('error');
        $this->assertSame('brouillon', $d->refresh()->statut);

        // Complète (avec les exemples du modèle) : validée.
        $d->update(['valeurs' => $this->exemples($d)]);
        $this->connecte($admin)->post(route('decisions.redaction.valider', $d->id))->assertRedirect()->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('validee', $d->statut);
        $this->assertNotNull($d->validee_le);
        $this->assertStringContainsString('TRAORE', strip_tags($d->rendu['qualite']));
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_validee']);
        $figee = $d->rendu;

        // Le modèle change, la décision validée non.
        $this->connecte($admin)->post(route('decisions.modeles.store'), [
            'id_' => $modele->id, 'nom' => $modele->nom, 'etat' => 'actif', 'entete' => '<p>ZZMODIFIE</p>', 'qualite' => '<p>ZZMODIFIE</p>', 'factum' => '<p>ZZMODIFIE</p>',
        ])->assertSessionHasNoErrors();
        $this->assertSame($figee, $d->refresh()->rendu);
        $r = $this->connecte($admin)->postJson(route('decisions.redaction.apercu', $d->id))->assertOk();
        $this->assertTrue($r->json('fige'));
        $this->assertStringNotContainsString('ZZMODIFIE', $r->json('qualite'));

        // Plus de modification, ni de suppression.
        $this->connecte($admin)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X', 'valeurs' => []])->assertForbidden();
        $this->connecte($admin)->post(route('decisions.redaction.valider', $d->id))->assertForbidden();
        $this->connecte($admin)->deleteJson(route('decisions.redaction.delete', $d->id))->assertJson(['status' => false]);
    }

    public function test_corriger_cree_une_nouvelle_decision_en_brouillon(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);
        $d->update(['valeurs' => $this->exemples($d)]);
        $this->connecte($admin)->post(route('decisions.redaction.valider', $d->id));

        $this->connecte($admin)->post(route('decisions.redaction.corriger', $d->id))->assertRedirect();
        $copie = Decision::latest('id')->firstOrFail();

        $this->assertNotSame($d->id, $copie->id);
        $this->assertSame('brouillon', $copie->statut);
        $this->assertSame($d->refresh()->valeurs, $copie->valeurs);
        $this->assertSame('validee', $d->statut);   // l'original ne bouge pas
    }

    public function test_suppression_d_un_brouillon_et_impression(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $page = $this->connecte($admin)->get(route('decisions.redaction.imprimer', $d->id))->assertOk();
        $page->assertSee('Imprimer ou enregistrer en PDF')->assertSee('Brouillon')->assertSee('const PARTIES', false);
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_imprimee']);

        $this->connecte($admin)->deleteJson(route('decisions.redaction.delete', $d->id))->assertJson(['status' => true]);
        $this->assertDatabaseMissing('decisions', ['id' => $d->id]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'decision_supprimee']);
    }

    public function test_droits(): void
    {
        $modele = $this->charger();
        $admin = User::factory()->create();
        $d = $this->ouvrir($admin, $modele);

        $this->connecte($this->avecDroits([]))->get(route('decisions.redaction'))->assertForbidden();
        $this->connecte($this->avecDroits([]))->get(route('decisions.redaction.edition', $d->id))->assertForbidden();

        $lecteur = $this->avecDroits(['redaction.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->get(route('decisions.redaction'))->assertOk()->assertDontSee('onclick="add_form()"', false);
        $this->connecte($lecteur)->get(route('decisions.redaction.edition', $d->id))->assertOk()->assertDontSee('fa-floppy-disk');
        $this->connecte($lecteur)->postJson(route('decisions.redaction.apercu', $d->id))->assertOk();   // lecture seule : valeurs enregistrées
        $this->connecte($lecteur)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => 'X'])->assertForbidden();
        $this->connecte($lecteur)->post(route('decisions.redaction.store', $d->id), ['reference' => 'X'])->assertForbidden();
        $this->connecte($lecteur)->post(route('decisions.redaction.valider', $d->id))->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('decisions.redaction.delete', $d->id))->assertForbidden();

        // Rédiger sans valider : le droit de validation est à part.
        $redacteur = $this->avecDroits(['redaction.voir', 'redaction.creer', 'redaction.modifier', 'affaires.toutes_chambres']);
        $this->connecte($redacteur)->post(route('decisions.redaction.store', $d->id), ['reference' => 'RG 1/2026', 'valeurs' => []])->assertSessionHasNoErrors();
        $this->connecte($redacteur)->post(route('decisions.redaction.valider', $d->id))->assertForbidden();
        $this->connecte($this->avecDroits(['redaction.voir', 'redaction.valider', 'affaires.toutes_chambres']))->post(route('decisions.redaction.valider', $d->id))->assertRedirect();
    }

    public function test_un_modele_archive_ne_s_ouvre_pas(): void
    {
        $modele = $this->charger();
        $modele->update(['etat' => 'archive']);
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('decisions.redaction.creer'), ['modele_decision_id' => $modele->id, 'reference' => 'RG 1/2026', 'chambre_id' => Chambre::firstOrFail()->id])->assertSessionHasErrors('modele_decision_id');
        $this->assertSame(0, Decision::count());
    }
}
