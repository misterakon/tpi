<?php

namespace Tests\Feature;

use App\Models\Matiere;
use App\Models\NatureAffaire;
use App\Models\ObjetLitige;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferentielsTest extends TestCase
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

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}> clé, table, données valides, champ modifiable */
    public static function pages(): array
    {
        return [
            'types_audience' => ['types_audience', 'types_audience', ['libelle' => 'Audience foraine', 'ordre' => 0, 'actif' => 1], 'libelle'],
            'modes_saisine' => ['modes_saisine', 'modes_saisine', ['libelle' => 'Convocation', 'registre' => 'penal'], 'libelle'],
            'matieres' => ['matieres', 'matieres', ['libelle' => 'Douanière'], 'libelle'],
            'natures_affaire' => ['natures_affaire', 'natures_affaire', ['libelle' => 'Tutelle', 'registre' => 'RG', 'exclue_enrolement' => 1], 'libelle'],
            'delais_procedure' => ['delais_procedure', 'delais_procedure', ['libelle' => 'Rédaction du jugement', 'etape' => 'redaction', 'acteur' => 'juge', 'registre' => 'RG', 'valeur' => 15, 'unite' => 'jours_ouvrables', 'point_depart' => 'mise_en_delibere', 'renvois_max' => 3], 'libelle'],
        ];
    }

    /** @dataProvider pages */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_creation_modification_suppression_et_droits(string $cle, string $table, array $donnees, string $champ): void
    {
        $slug = str_replace('_', '-', $cle);
        $admin = User::factory()->create();

        // Les délais de procédure se gèrent dans la page « Délais » du menu Alertes : l'ancienne adresse y renvoie.
        if ($cle === 'delais_procedure') {
            $this->connecte($admin)->get(route("referentiels.$cle"))->assertRedirect(route('alertes.delais'));
            $this->connecte($admin)->get(route('alertes.delais'))->assertOk()->assertSee('Délais de procédure');
        } else {
            $this->connecte($admin)->get(route("referentiels.$cle"))->assertOk();
        }

        $this->connecte($admin)->post(route("referentiels.$cle.store"), $donnees)->assertSessionHasNoErrors();
        $this->assertDatabaseHas($table, [$champ => $donnees[$champ]]);
        $id = \DB::table($table)->where($champ, $donnees[$champ])->value('id');
        $this->assertDatabaseHas('journal_audit', ['action' => str_replace(['types_audience', 'caracteres_decision', 'issues_audience', 'modes_comparution', 'qualites_procedurales', 'natures_partie', 'modes_saisine', 'matieres', 'natures_affaire', 'delais_procedure', 'textes_loi'], ['type_audience', 'caractere_decision', 'issue_audience', 'mode_comparution', 'qualite_procedurale', 'nature_partie', 'mode_saisine', 'matiere', 'nature_affaire', 'delai_procedure', 'texte_loi'], $cle).'_cree']);

        // Lecture JSON et modification
        $this->connecte($admin)->getJson(route("referentiels.$cle.get", $id))->assertOk()->assertJsonPath($champ, $donnees[$champ]);
        $this->connecte($admin)->post(route("referentiels.$cle.store"), ['id_' => $id, $champ => 'Modifié'] + $donnees)->assertSessionHasNoErrors();
        $this->assertSame('Modifié', \DB::table($table)->where('id', $id)->value($champ));

        // Doublon refusé (même clé d'unicité que l'élément existant)
        $this->connecte($admin)->post(route("referentiels.$cle.store"), ['id_' => '', $champ => 'Modifié'] + $donnees)->assertSessionHasErrors();

        // Droits : sans droit tout est refusé ; en lecture seule on voit sans écrire
        $this->connecte($this->avecDroits([]))->get(route("referentiels.$cle"))->assertForbidden();
        $lecteur = $this->avecDroits(["$cle.voir"]);
        if ($cle === 'delais_procedure') {
            $this->connecte($lecteur)->get(route('alertes.delais'))->assertOk()->assertSee('Délais de procédure')->assertDontSee('onclick="add_form()"', false);
        } else {
            $this->connecte($lecteur)->get(route("referentiels.$cle"))->assertOk()->assertDontSee('onclick="add_form()"', false);
        }
        $this->connecte($lecteur)->post(route("referentiels.$cle.store"), $donnees)->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route("referentiels.$cle.delete", $id))->assertForbidden();

        $this->connecte($admin)->deleteJson(route("referentiels.$cle.delete", $id))->assertJson(['status' => true]);
        $this->assertDatabaseMissing($table, ['id' => $id]);
    }

    public function test_champs_obligatoires_et_valeurs_invalides(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('referentiels.types_audience.store'), ['libelle' => ''])->assertSessionHasErrors('libelle');
        $this->connecte($admin)->post(route('referentiels.modes_saisine.store'), ['libelle' => 'X', 'registre' => 'inconnu'])->assertSessionHasErrors('registre');
        $this->connecte($admin)->post(route('referentiels.delais_procedure.store'), ['libelle' => 'X', 'etape' => 'audience', 'valeur' => 'abc', 'unite' => 'jours', 'point_depart' => 'enrolement'])->assertSessionHasErrors('valeur');
        $this->connecte($admin)->post(route('referentiels.delais_procedure.store'), ['libelle' => 'X', 'etape' => 'audience', 'valeur' => 5, 'unite' => 'siècles', 'point_depart' => 'enrolement'])->assertSessionHasErrors('unite');
        $this->connecte($admin)->post(route('referentiels.objets_litige.store'), ['libelle' => 'X', 'matiere_id' => 9999])->assertSessionHasErrors('matiere_id');
    }

    public function test_les_donnees_de_depart_se_chargent_sans_doublon(): void
    {
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $avant = [Matiere::count(), NatureAffaire::count(), ObjetLitige::count()];
        $this->assertGreaterThan(80, $avant[2]);

        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->assertSame($avant, [Matiere::count(), NatureAffaire::count(), ObjetLitige::count()]);

        $refere = NatureAffaire::where('libelle', 'Référé-expulsion')->firstOrFail();
        $this->assertTrue($refere->exclue_enrolement);
        $this->assertSame(1, \DB::table('delais_procedure')->where('nature_affaire_id', $refere->id)->value('renvois_max'));
    }

    public function test_une_matiere_ou_nature_utilisee_ne_se_supprime_pas(): void
    {
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $admin = User::factory()->create();

        $this->connecte($admin)->deleteJson(route('referentiels.matieres.delete', Matiere::where('libelle', 'Sociale')->value('id')))
            ->assertJson(['status' => false]);
        $this->connecte($admin)->deleteJson(route('referentiels.natures_affaire.delete', NatureAffaire::where('libelle', 'Référé-expulsion')->value('id')))
            ->assertJson(['status' => false]);
        $this->assertDatabaseHas('matieres', ['libelle' => 'Sociale']);
    }

    public function test_un_objet_du_litige_se_rattache_a_une_matiere_et_une_chambre(): void
    {
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $admin = User::factory()->create();
        $civile = Matiere::where('libelle', 'Civile')->value('id');
        $nature = NatureAffaire::where('libelle', 'Matrimoniale')->value('id');

        $this->connecte($admin)->post(route('referentiels.objets_litige.store'), ['libelle' => 'Séparation de biens', 'matiere_id' => $civile, 'nature_affaire_id' => $nature, 'chambre_libelle' => '3e chambre civile'])->assertSessionHasNoErrors();
        $objet = ObjetLitige::where('libelle', 'Séparation de biens')->firstOrFail();
        $this->assertSame('3e chambre civile', $objet->chambre_libelle);
        $this->assertSame('Civile', $objet->matiere->libelle);

        // Le même libellé reste possible dans une autre chambre, pas dans la même.
        $this->connecte($admin)->post(route('referentiels.objets_litige.store'), ['libelle' => 'Séparation de biens', 'matiere_id' => $civile, 'chambre_libelle' => '4e chambre civile'])->assertSessionHasNoErrors();
        $this->connecte($admin)->post(route('referentiels.objets_litige.store'), ['libelle' => 'Séparation de biens', 'matiere_id' => $civile, 'chambre_libelle' => '3e chambre civile'])->assertSessionHasErrors('libelle');
    }

    public function test_delai_de_procedure_coherence_registre_acteur_et_renvois(): void
    {
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $admin = User::factory()->create();
        $refere = NatureAffaire::where('libelle', 'Référé-expulsion')->firstOrFail();

        // Le registre se déduit de la nature d'affaire, même si le formulaire en envoie un autre.
        $this->connecte($admin)->post(route('referentiels.delais_procedure.store'), [
            'libelle' => 'Test procédure', 'etape' => 'procedure_complete', 'acteur' => 'juge', 'registre' => 'RP', 'nature_affaire_id' => $refere->id,
            'valeur' => 15, 'unite' => 'jours', 'renvois_max' => 1, 'point_depart' => 'ouverture_audience',
        ])->assertSessionHasNoErrors();
        $d = \App\Models\DelaiProcedure::where('libelle', 'Test procédure')->firstOrFail();
        $this->assertSame('RG', $d->registre);
        $this->assertNull($d->acteur);   // une procédure complète n'a pas de responsable unique
        $this->assertSame(1, $d->renvois_max);

        // Les renvois n'ont pas de sens pour une étape comme la rédaction.
        $this->connecte($admin)->post(route('referentiels.delais_procedure.store'), [
            'libelle' => 'Rédaction civile', 'etape' => 'redaction', 'acteur' => 'juge', 'registre' => 'RG',
            'valeur' => 30, 'unite' => 'jours_ouvrables', 'renvois_max' => 2, 'point_depart' => 'mise_en_delibere',
        ])->assertSessionHasNoErrors();
        $r = \App\Models\DelaiProcedure::where('libelle', 'Rédaction civile')->firstOrFail();
        $this->assertNull($r->renvois_max);
        $this->assertSame('juge', $r->acteur);
    }
}
