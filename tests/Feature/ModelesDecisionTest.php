<?php

namespace Tests\Feature;

use App\Models\BlocTexte;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ObjetLitige;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\ModelesDecisionSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelesDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function charger(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesDecisionSeeder::class);
    }

    private function donnees(array $plus = []): array
    {
        return $plus + [
            'nom' => 'Jugement de test', 'etat' => 'brouillon', 'qualite' => '<p>RG {{ numero_rg }}</p>', 'factum' => '<p>{{ demandeur.nom }}</p>',
        ];
    }

    public function test_le_chargement_cree_le_modele_d_heredite_sans_doublon(): void
    {
        $this->charger();
        $this->assertSame(7, BlocTexte::count());
        $this->assertSame(1, ModeleDecision::count());

        $m = ModeleDecision::firstOrFail();
        $this->assertSame("Jugement d'hérédité", $m->nom);
        $this->assertSame(1, $m->versionCourante()->numero);
        $this->assertSame(['3e chambre civile'], $m->chambres());
        $this->assertGreaterThan(0, $m->objets()->count());
        $this->assertCount(18, $m->variables);

        $this->seed(ModelesDecisionSeeder::class);
        $this->assertSame(1, ModeleDecision::count());
        $this->assertSame(1, $m->versions()->count());
    }

    public function test_l_apercu_du_modele_d_heredite_remplit_toutes_les_variables(): void
    {
        $this->charger();
        $m = ModeleDecision::firstOrFail();
        $v = $m->versionCourante();
        $admin = User::factory()->create();

        $r = $this->connecte($admin)->postJson(route('decisions.modeles.apercu'), [
            'entete' => $v->entete, 'qualite' => $v->qualite, 'factum' => $v->factum,
            'variables' => $m->variables->map(fn ($x) => ['code' => $x->code, 'libelle' => $x->libelle, 'type' => $x->type, 'exemple' => $x->exemple])->all(),
        ])->assertOk()->assertJsonPath('manquantes', [])->assertJsonPath('inconnues', []);

        $q = strip_tags($r->json('qualite'));
        $this->assertStringContainsString('audience publique ordinaire du vendredi six novembre deux mil vingt-six', $q);
        $this->assertStringContainsString('Madame ADJOUA Prisca, Vice-Président du Tribunal', $q);
        $this->assertStringContainsString('Madame KOFFI Marie et Monsieur DOSSO Ibrahim, Juges au siège', $q);
        $this->assertStringContainsString('Avec l\'assistance de Maître YAO Aminata, Greffier', $q);
        $this->assertStringContainsString('Monsieur TRAORE Salif, né le 01 mars 1970 à Bouaké, de nationalité ivoirienne, domicilié à Bingerville', $q);
        $this->assertStringContainsString('Demandeur, comparaissant en personne', $q);
        $this->assertStringContainsString('A rendu le jugement dont la teneur suit', $q);   // bloc inséré

        // Le timbre : la colonne de gauche de la première page.
        $e = strip_tags($r->json('entete'));
        $this->assertStringContainsString("COUR D'APPEL D'ABIDJAN", html_entity_decode($e));
        $this->assertStringContainsString('RG N° 017/2026 du 02/02/2026', $e);
        $this->assertStringContainsString('TRAORE SALIF', $e);
        $this->assertStringContainsString('MINISTERE PUBLIC', $e);
        // Le titre tient sur deux lignes : le début de la qualité ne bouge pas selon la longueur de la date.
        $this->assertStringContainsString('<p class="ql-align-center"><strong><u>AUDIENCE PUBLIQUE</u></strong></p>', $r->json('qualite'));
        $this->assertStringContainsString('DU VENDREDI 06 NOVEMBRE 2026', $q);
        // Les deux traits du timbre sont des éléments de mise en page fournis par le système.
        $this->assertStringContainsString('acte-trait--pointille', $r->json('entete'));
        $this->assertStringContainsString('acte-trait--plein', $r->json('entete'));

        $f = strip_tags($r->json('factum'));
        $this->assertStringContainsString('feue DIALLO Mariam est décédée le 16 août 2026', $f);
        $this->assertStringContainsString('en laissant à sa survivance trois (03) enfants', $f);
        $this->assertStringContainsString('dit que la défunte a laissé pour recueillir sa succession', $f);
        $this->assertStringContainsString('son veuf, TRAORE Salif', $f);
        $this->assertStringContainsString('1- Aminata TRAORE', $f);
        $this->assertStringContainsString('3- Fanta TRAORE', $f);   // une ligne numérotée par enfant, comme dans le document Word
        $this->assertStringContainsString('Vu les conclusions écrites du Ministère Public du 20 octobre 2026', $f);
    }

    public function test_l_apercu_protege_contre_le_code_et_signale_les_variables_inconnues(): void
    {
        $admin = User::factory()->create();

        $r = $this->connecte($admin)->postJson(route('decisions.modeles.apercu'), [
            'qualite' => '<p>{{ variable_inventee }} {{ x }}</p>', 'factum' => '<p>{{ nom_piege }}</p>',
            'variables' => [['code' => 'nom_piege', 'libelle' => 'Piège', 'type' => 'texte', 'exemple' => '<script>alert(1)</script>']],
        ])->assertOk();

        $this->assertStringNotContainsString('<script>', $r->json('factum'));
        $this->assertStringContainsString('&lt;script&gt;', $r->json('factum'));
        $this->assertContains('variable_inventee', $r->json('inconnues'));
        $this->assertContains('variable_inventee', $r->json('manquantes'));
    }

    public function test_creation_versions_et_retour_a_une_version(): void
    {
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees())->assertSessionHasNoErrors();
        $m = ModeleDecision::firstOrFail();
        $this->assertSame(1, $m->versionCourante()->numero);
        $this->assertDatabaseHas('journal_audit', ['action' => 'modele_decision_cree']);

        // Mêmes textes : pas de nouvelle version. Textes changés : version 2.
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'description' => 'changée']))->assertSessionHasNoErrors();
        $this->assertSame(1, $m->versions()->count());
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'factum' => '<p>Texte changé</p>', 'note' => 'Factum réécrit']))->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame(2, $m->versionCourante()->numero);
        $this->assertSame('Factum réécrit', $m->versionCourante()->note);

        // Retour à la version 1 : version 3 créée, l'historique est conservé.
        $this->connecte($admin)->post(route('decisions.modeles.restaurer', [$m->id, 1]))->assertRedirect();
        $m->refresh();
        $this->assertSame(3, $m->versionCourante()->numero);
        $this->assertSame('<p>'.'{{ demandeur.nom }}'.'</p>', $m->versionCourante()->factum);
        $this->assertSame(3, $m->versions()->count());

        $this->connecte($admin)->get(route('decisions.modeles.edition', $m->id))->assertOk()->assertSee('Retour à la version 1');
    }

    public function test_le_timbre_fait_partie_des_versions_et_se_restaure(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['entete' => '<p>TIMBRE A</p>']))->assertSessionHasNoErrors();
        $m = ModeleDecision::firstOrFail();
        $this->assertSame('<p>TIMBRE A</p>', $m->versionCourante()->entete);

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'entete' => '<p>TIMBRE B</p>']))->assertSessionHasNoErrors();
        $this->assertSame(2, $m->refresh()->versionCourante()->numero);   // changer le timbre seul suffit à créer une version

        $this->connecte($admin)->post(route('decisions.modeles.restaurer', [$m->id, 1]));
        $this->assertSame('<p>TIMBRE A</p>', $m->refresh()->versionCourante()->entete);
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'entete' => '<p>{{ x|nexistepas }}</p>']))->assertSessionHasErrors('entete');
    }

    public function test_validations_du_modele(): void
    {
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['nom' => '']))->assertSessionHasErrors('nom');
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['qualite' => '<p>{{ x|nexistepas }}</p>']))->assertSessionHasErrors('qualite');
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['variables' => [['code' => 'Mauvais Code', 'libelle' => 'x', 'type' => 'texte']]]))->assertSessionHasErrors('variables.0.code');
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['variables' => [
            ['code' => 'a', 'libelle' => 'A', 'type' => 'texte'], ['code' => 'a', 'libelle' => 'B', 'type' => 'texte'],
        ]]))->assertSessionHasErrors('variables');
        $this->assertSame(0, ModeleDecision::count());

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees())->assertSessionHasNoErrors();
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees())->assertSessionHasErrors('nom');   // nom déjà pris
    }

    public function test_le_texte_enregistre_est_nettoye(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['qualite' => '<p class="ql-align-center" onclick="x()">Titre</p><script>alert(1)</script><p class="autre">Texte</p>']))->assertSessionHasNoErrors();

        $q = ModeleDecision::firstOrFail()->versionCourante()->qualite;
        $this->assertSame('<p class="ql-align-center">Titre</p><p>Texte</p>', $q);
    }

    public function test_suppression_d_un_modele_et_droits(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['etat' => 'actif']));
        $m = ModeleDecision::firstOrFail();

        $this->connecte($admin)->deleteJson(route('decisions.modeles.delete', $m->id))->assertJson(['status' => false]);   // actif : archiver d'abord
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'etat' => 'archive']));
        $this->connecte($admin)->deleteJson(route('decisions.modeles.delete', $m->id))->assertJson(['status' => true]);
        $this->assertSame(0, ModeleDecision::count());
    }

    public function test_droits_sur_les_modeles(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees());
        $m = ModeleDecision::firstOrFail();

        $this->connecte($this->avecDroits([]))->get(route('decisions.modeles'))->assertForbidden();
        $this->connecte($this->avecDroits([]))->get(route('decisions.modeles.edition', $m->id))->assertForbidden();

        $lecteur = $this->avecDroits(['modeles.voir']);
        $this->connecte($lecteur)->get(route('decisions.modeles'))->assertOk()->assertDontSee('Nouveau modèle');
        $this->connecte($lecteur)->get(route('decisions.modeles.edition', $m->id))->assertOk()->assertSee('Consultation')->assertDontSee('fa-floppy-disk');
        $this->connecte($lecteur)->get(route('decisions.modeles.nouveau'))->assertForbidden();
        $this->connecte($lecteur)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id]))->assertForbidden();
        $this->connecte($lecteur)->post(route('decisions.modeles.restaurer', [$m->id, 1]))->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('decisions.modeles.delete', $m->id))->assertForbidden();
    }

    public function test_rattachement_aux_objets_et_chambres(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $objet = ObjetLitige::where('libelle', 'Divorce et séparation de corps')->firstOrFail();
        $chambre = Chambre::firstOrFail()->libelle;

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees([
            'matiere_id' => Matiere::where('libelle', 'Civile')->value('id'), 'objets' => [$objet->id], 'chambres' => [$chambre],
        ]))->assertSessionHasNoErrors();

        $m = ModeleDecision::where('nom', 'Jugement de test')->firstOrFail();
        $this->assertSame([$objet->id], $m->objets()->pluck('objets_litige.id')->all());
        $this->assertSame([$chambre], $m->chambres());

        $this->connecte($admin)->post(route('decisions.modeles.store'), $this->donnees(['id_' => $m->id, 'objets' => [999999]]))->assertSessionHasErrors('objets.0');
    }

    public function test_blocs_de_texte(): void
    {
        $this->charger();
        $admin = User::factory()->create();

        $this->connecte($admin)->get(route('decisions.blocs'))->assertOk();
        $this->assertStringContainsString('Clôture du jugement', $this->lignesServeur($admin, 'decisions.blocs.donnees'));
        // La liste indique dans combien de modèles chaque bloc est inséré.
        $this->assertStringContainsString('1 modèle', $this->lignesServeur($admin, 'decisions.blocs.donnees'));

        $this->connecte($admin)->post(route('decisions.blocs.store'), ['code' => 'visa_test', 'nom' => 'Visa de test', 'categorie' => 'visas', 'contenu' => '<p>Vu {{ numero_rg }} ;</p>', 'actif' => 1])->assertSessionHasNoErrors();
        $b = BlocTexte::where('code', 'visa_test')->firstOrFail();
        $this->assertStringContainsString('Aucun modèle', $this->lignesServeur($admin, 'decisions.blocs.donnees'));   // le nouveau bloc n'est encore dans aucun modèle

        $this->connecte($admin)->post(route('decisions.blocs.store'), ['code' => 'Visa Test', 'nom' => 'x', 'categorie' => 'visas', 'contenu' => '<p>x</p>'])->assertSessionHasErrors('code');
        $this->connecte($admin)->post(route('decisions.blocs.store'), ['code' => 'visa_test', 'nom' => 'Doublon', 'categorie' => 'visas', 'contenu' => '<p>x</p>'])->assertSessionHasErrors('code');
        $this->connecte($admin)->post(route('decisions.blocs.store'), ['code' => 'visa_faute', 'nom' => 'F', 'categorie' => 'visas', 'contenu' => '<p>{{ x|nexistepas }}</p>'])->assertSessionHasErrors('contenu');

        $this->connecte($admin)->getJson(route('decisions.blocs.get', $b->id))->assertOk()->assertJsonPath('code', 'visa_test');
        $this->connecte($admin)->deleteJson(route('decisions.blocs.delete', $b->id))->assertJson(['status' => true]);

        // Un bloc utilisé par un modèle ne se supprime pas et son code ne change pas.
        $utilise = BlocTexte::where('code', 'formule_jugement')->firstOrFail();
        $this->connecte($admin)->deleteJson(route('decisions.blocs.delete', $utilise->id))->assertJson(['status' => false]);
        $this->connecte($admin)->post(route('decisions.blocs.store'), ['id_' => $utilise->id, 'code' => 'autre_code', 'nom' => $utilise->nom, 'categorie' => 'formule', 'contenu' => '<p>x</p>'])->assertSessionHasErrors('code');
    }

    public function test_droits_sur_les_blocs(): void
    {
        $this->charger();
        $b = BlocTexte::firstOrFail();
        $this->connecte($this->avecDroits([]))->get(route('decisions.blocs'))->assertForbidden();
        $lecteur = $this->avecDroits(['blocs.voir']);
        $this->connecte($lecteur)->get(route('decisions.blocs'))->assertOk()->assertDontSee('onclick="add_form()"', false);
        $this->connecte($lecteur)->post(route('decisions.blocs.store'), ['code' => 'x_y', 'nom' => 'x', 'categorie' => 'visas', 'contenu' => '<p>x</p>'])->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('decisions.blocs.delete', $b->id))->assertForbidden();
    }

    public function test_la_page_d_edition_propose_variables_et_blocs(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $m = ModeleDecision::firstOrFail();

        $this->connecte($admin)->get(route('decisions.modeles.edition', $m->id))->assertOk()
            ->assertSee('Variables propres à ce modèle')->assertSee('Défunt : nom et prénoms')->assertSee('Clôture du jugement')->assertSee('data-alignement', false)
            ->assertSee('Version 1')->assertSee('id="f_entete"', false)->assertSee('feuille-acte.js', false);
        $this->connecte($admin)->get(route('decisions.modeles.nouveau'))->assertOk()->assertSee('Nouveau modèle');
        $this->connecte($admin)->get(route('decisions.modeles'))->assertOk()->assertSee("Jugement d'hérédité");
    }

    public function test_duplication_d_un_modele(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $source = ModeleDecision::firstOrFail();
        $v = $source->versionCourante();

        $this->connecte($admin)->post(route('decisions.modeles.dupliquer', $source->id))->assertRedirect();
        $copie = ModeleDecision::where('nom', "Copie de Jugement d'hérédité")->firstOrFail();

        $this->assertSame('brouillon', $copie->etat);
        $this->assertSame(1, $copie->versions()->count());
        $this->assertSame($v->entete, $copie->versionCourante()->entete);
        $this->assertSame($v->qualite, $copie->versionCourante()->qualite);
        $this->assertSame($v->factum, $copie->versionCourante()->factum);
        $this->assertSame($source->variables()->count(), $copie->variables()->count());
        $this->assertSame($source->objets()->pluck('objets_litige.id')->all(), $copie->objets()->pluck('objets_litige.id')->all());
        $this->assertSame($source->chambres(), $copie->chambres());
        $this->assertStringContainsString("Copie de « Jugement d'hérédité »", $copie->versionCourante()->note);
        $this->assertSame($source->versions()->count(), ModeleDecision::find($source->id)->versions()->count());   // l'original ne bouge pas

        // Une seconde copie reçoit un numéro.
        $this->connecte($admin)->post(route('decisions.modeles.dupliquer', $source->id));
        $this->assertDatabaseHas('modeles_decision', ['nom' => "Copie de Jugement d'hérédité (2)"]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'modele_decision_cree']);

        $this->connecte($this->avecDroits(['modeles.voir']))->post(route('decisions.modeles.dupliquer', $source->id))->assertForbidden();
        $this->connecte($this->avecDroits(['modeles.voir', 'modeles.creer']))->get(route('decisions.modeles.edition', $source->id))->assertOk()->assertSee('Dupliquer');
    }
}
