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
use App\Models\RoleLigne;
use App\Models\User;
use App\Support\Audiences\SuiviAffaires;
use App\Support\Audiences\TableauAudiences;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot K : feuille d'audience, renvois et délibérés, tableau de bord des audiences. */
class AudiencesFeuilleTest extends TestCase
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

    /** Une formation avec un président et un greffier, pour que la composition soit reprise. */
    private function composer(): void
    {
        $formation = \App\Models\Formation::firstOrCreate(['chambre_id' => $this->chambre->id, 'ordre' => 1], ['actif' => true]);
        foreach ([['Kouassi', 'Marie', 'juge', 'president'], ['Traore', 'Awa', 'greffier', 'greffier']] as [$nom, $prenoms, $fonction, $role]) {
            $agent = \App\Models\Agent::create(['nom' => $nom, 'prenoms' => $prenoms, 'fonction' => $fonction, 'actif' => true]);
            \App\Models\Affectation::create(['formation_id' => $formation->id, 'agent_id' => $agent->id, 'role' => $role]);
        }
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function greffier(): User
    {
        return $this->avecDroits(['roles.voir', 'feuille.voir', 'feuille.modifier', 'renvois.voir', 'renvois.modifier', 'tableau.voir', 'affaires.toutes_chambres']);
    }

    private function affaire(string $audience): Affaire
    {
        static $n = 0;
        $n++;
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 300 + $n, 'numero_complet' => 'RG-'.(300 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-05-20', 'objet' => 'Paiement de loyers '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Kone', 'prenoms' => 'Ibrahima '.$n, 'cle' => 'kone'.$n]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$n, 'cle' => 'ste'.$n]);

        return $affaire;
    }

    /** Un rôle publié avec les affaires de la chambre dont la première audience est ce jour-là. */
    private function rolePublie(string $date, string $statut = 'publie'): RoleAudience
    {
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => $date]);
        GenerateurRole::completer($role);
        $role->forceFill(['statut' => $statut])->save();

        return $role->refresh();
    }

    private function url(string $nom, RoleAudience $role, ?RoleLigne $ligne = null): string
    {
        return $ligne ? route($nom, [$role->id, $ligne->id]) : route($nom, $role->id);
    }

    // ---------------- droits et pages ----------------

    public function test_les_pages_exigent_leurs_droits(): void
    {
        $role = $this->rolePublie('2026-06-04');
        $sans = $this->avecDroits(['roles.voir']);
        foreach (['audiences.tableau', 'audiences.feuille', 'audiences.renvois'] as $page) {
            $this->connecte($sans)->get(route($page))->assertForbidden();
        }
        $this->connecte($sans)->get(route('audiences.feuille.show', $role->id))->assertForbidden();

        $lecteur = $this->avecDroits(['feuille.voir', 'renvois.voir', 'tableau.voir', 'affaires.toutes_chambres']);
        foreach (['audiences.tableau', 'audiences.feuille', 'audiences.renvois'] as $page) {
            $this->connecte($lecteur)->get(route($page))->assertOk();
        }
        $this->connecte($lecteur)->get(route('audiences.feuille.show', $role->id))->assertOk()->assertSee('Feuille d');
        // Lecture seule : on ne note rien.
        $ligne = $role->lignes()->create(['rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '1/26', 'origine' => 'libre']);
        $this->connecte($lecteur)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'juge'])->assertForbidden();
        $this->connecte($lecteur)->postJson($this->url('audiences.feuille.ouvrir', $role))->assertForbidden();
    }

    public function test_la_feuille_se_fait_sur_un_role_publie(): void
    {
        $brouillon = $this->rolePublie('2026-06-04', 'brouillon');
        $this->connecte($this->greffier())->get(route('audiences.feuille.show', $brouillon->id))->assertRedirect(route('audiences.feuille'));
        $ligne = $brouillon->lignes()->create(['rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '1/26', 'origine' => 'libre']);
        $this->connecte($this->greffier())->postJson($this->url('audiences.feuille.suite', $brouillon, $ligne), ['issue' => 'juge'])->assertStatus(422);
    }

    // ---------------- suites ----------------

    public function test_noter_un_renvoi_avec_motif_ouvre_l_audience(): void
    {
        $a = $this->affaire('2026-06-04');
        $role = $this->rolePublie('2026-06-04');
        $ligne = $role->lignes()->firstOrFail();
        $u = $this->greffier();
        $this->assertSame('a_tenir', $role->etatAudience());

        // La date de renvoi est obligatoire et doit suivre l'audience.
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'renvoi'])->assertStatus(422);
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'renvoi', 'renvoi_au' => '2026-06-04'])->assertStatus(422);

        $reponse = $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'renvoi', 'renvoi_au' => '2026-06-18', 'motif' => 'Pour conclusions du défendeur', 'presence' => 'avocats', 'note_audience' => 'Avocat du défendeur absent']);
        $reponse->assertOk()->assertJsonPath('ligne.issue', 'renvoi')->assertJsonPath('resume.traitees', 1)->assertJsonPath('etat', 'en_cours');
        $ligne->refresh();
        $this->assertSame('2026-06-18', $ligne->renvoi_au->toDateString());
        $this->assertSame('Pour conclusions du défendeur', $ligne->motif);
        $this->assertSame('avocats', $ligne->presence);
        $this->assertNotNull($role->refresh()->audience_ouverte_le);

        // Changer de suite efface les champs devenus sans objet ; effacer la suite remet à zéro.
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'juge'])->assertOk();
        $ligne->refresh();
        $this->assertNull($ligne->renvoi_au);
        $this->assertNull($ligne->motif);
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => null])->assertOk()->assertJsonPath('resume.traitees', 0);
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'inconnue'])->assertStatus(422);
        $this->assertNotNull($a);
    }

    public function test_composition_cloture_et_reouverture(): void
    {
        $this->composer();
        $this->affaire('2026-06-11');
        $role = $this->rolePublie('2026-06-11');
        $u = $this->greffier();

        $membres = $role->compositionTenue();
        $this->assertNotEmpty($membres, 'La composition est reprise de la formation.');
        $membres[0]['present'] = false;
        $membres[0]['remplacant'] = 'TRAORE Salif';
        $this->connecte($u)->postJson($this->url('audiences.feuille.composition', $role), ['membres' => $membres])->assertOk()->assertJsonPath('etat', 'a_tenir');
        $role->refresh();
        $this->assertNull($role->audience_ouverte_le, "Noter la composition n'ouvre pas l'audience.");
        $this->assertFalse($role->composition_tenue[0]['present']);
        $this->assertSame('TRAORE Salif', $role->composition_tenue[0]['remplacant']);

        // Terminer : les affaires sans suite sont signalées ; l'audience terminée est verrouillée jusqu'à sa réouverture.
        $ligne = $role->lignes()->first() ?? $role->lignes()->create(['rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '9/26', 'origine' => 'libre']);
        $this->connecte($u)->postJson($this->url('audiences.feuille.terminer', $role), ['note_audience' => 'RAS'])->assertOk()->assertJsonPath('etat', 'terminee');
        $this->assertSame('RAS', $role->refresh()->note_audience);
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'juge'])->assertStatus(422);
        $this->connecte($u)->postJson($this->url('audiences.feuille.composition', $role), ['membres' => $membres])->assertStatus(422);
        $this->connecte($u)->postJson($this->url('audiences.feuille.rouvrir', $role))->assertOk()->assertJsonPath('etat', 'en_cours');
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $ligne), ['issue' => 'juge'])->assertOk();
    }

    // ---------------- retour au rôle ----------------

    public function test_un_delibere_a_date_ne_revient_qu_a_cette_date_et_le_desistement_sort(): void
    {
        $a = $this->affaire('2026-06-04');
        $b = $this->affaire('2026-06-04');
        $role = $this->rolePublie('2026-06-04');
        $u = $this->greffier();
        $la = $role->lignes()->where('affaire_id', $a->id)->firstOrFail();
        $lb = $role->lignes()->where('affaire_id', $b->id)->firstOrFail();

        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $la), ['issue' => 'delibere', 'date_delibere' => '2026-07-02'])->assertOk();
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $lb), ['issue' => 'desiste'])->assertOk();
        $this->connecte($u)->postJson($this->url('audiences.feuille.suite', $role, $la), ['issue' => 'delibere', 'date_delibere' => '2026-06-04'])->assertStatus(422);

        $avant = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-18', 'fait_a' => 'Bingerville', 'date_signature' => '2026-06-18']);
        $apres = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-07-02', 'fait_a' => 'Bingerville', 'date_signature' => '2026-07-02']);
        $this->assertSame([], GenerateurRole::proposer($avant)['deliberes']->pluck('id')->all(), 'Pas avant la date de prononcé.');
        $this->assertSame([$a->id], GenerateurRole::proposer($apres)['deliberes']->pluck('id')->all());
        $this->assertSame([], GenerateurRole::proposer($apres)['anciennes']->pluck('id')->all(), 'Le désistement sort des rôles.');
    }

    // ---------------- renvois et délibérés ----------------

    public function test_la_page_des_renvois_change_la_date_et_proroge_un_delibere(): void
    {
        $a = $this->affaire('2026-06-04');
        $b = $this->affaire('2026-06-04');
        $role = $this->rolePublie('2026-06-04');
        $u = $this->greffier();
        $la = $role->lignes()->where('affaire_id', $a->id)->firstOrFail();
        $lb = $role->lignes()->where('affaire_id', $b->id)->firstOrFail();
        $la->update(['issue' => 'renvoi', 'renvoi_au' => '2026-06-18', 'motif' => 'Pour réplique']);
        $lb->update(['issue' => 'delibere', 'date_delibere' => '2026-06-25']);

        $this->connecte($u)->get(route('audiences.renvois'))->assertOk()->assertSee('SOCIETE EXEMPLE')->assertSee('Pour réplique');

        // Renvoi : nouvelle date postérieure à l'audience, sinon refus.
        $this->connecte($u)->postJson(route('audiences.renvois.date', $la->id), ['renvoi_au' => '2026-06-03'])->assertStatus(422);
        $this->connecte($u)->postJson(route('audiences.renvois.date', $la->id), ['renvoi_au' => '2026-07-09', 'motif' => 'À la demande des parties'])->assertOk();
        $this->assertSame('2026-07-09', $la->refresh()->renvoi_au->toDateString());
        // Une ligne qui n'a pas cette suite n'est pas touchée par la mauvaise action.
        $this->connecte($u)->postJson(route('audiences.renvois.proroger', $la->id), ['date_delibere' => '2026-07-30'])->assertStatus(422);

        // Délibéré : prorogation comptée, date toujours croissante.
        $this->connecte($u)->postJson(route('audiences.renvois.proroger', $lb->id), ['date_delibere' => '2026-06-20'])->assertStatus(422);
        $this->connecte($u)->postJson(route('audiences.renvois.proroger', $lb->id), ['date_delibere' => '2026-07-16'])->assertOk();
        $lb->refresh();
        $this->assertSame('2026-07-16', $lb->date_delibere->toDateString());
        $this->assertSame(1, $lb->proroge);

        // Un passage plus récent de l'affaire : on y modifie la suite, pas sur l'ancienne ligne.
        $suivant = $this->rolePublie('2026-07-09');
        $this->assertTrue($suivant->lignes()->where('affaire_id', $a->id)->exists());
        $this->connecte($u)->postJson(route('audiences.renvois.date', $la->id), ['renvoi_au' => '2026-08-06'])->assertStatus(422);

        $lecteur = $this->avecDroits(['renvois.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->postJson(route('audiences.renvois.date', $la->id), ['renvoi_au' => '2026-08-06'])->assertForbidden();
    }

    // ---------------- état des affaires et tableau de bord ----------------

    public function test_l_etat_des_affaires_et_le_tableau_de_bord(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 30)->startOfDay());
        $renvoyee = $this->affaire('2026-06-04');
        $enRetard = $this->affaire('2026-06-04');
        $deliberee = $this->affaire('2026-06-04');
        $jugee = $this->affaire('2026-06-04');
        $jamais = $this->affaire('2026-06-10');
        $role = $this->rolePublie('2026-06-04');
        foreach ([[$renvoyee, ['issue' => 'renvoi', 'renvoi_au' => '2026-07-10']], [$enRetard, ['issue' => 'renvoi', 'renvoi_au' => '2026-06-20']],
            [$deliberee, ['issue' => 'delibere', 'date_delibere' => '2026-06-29']], [$jugee, ['issue' => 'juge']]] as [$affaire, $suite]) {
            $role->lignes()->where('affaire_id', $affaire->id)->firstOrFail()->update($suite);
        }
        $role->lignes()->where('affaire_id', $jamais->id)->delete();

        $etats = SuiviAffaires::etats([$this->chambre->id])->keyBy('affaire_id');
        $this->assertSame('renvoyee', $etats[$renvoyee->id]['etat']);
        $this->assertFalse($etats[$renvoyee->id]['retard']);
        $this->assertSame('renvoyee', $etats[$enRetard->id]['etat']);
        $this->assertTrue($etats[$enRetard->id]['retard']);
        $this->assertSame(10, $etats[$enRetard->id]['jours_retard']);
        $this->assertTrue($etats[$deliberee->id]['retard'], 'Un délibéré dont la date est passée est échu.');
        $this->assertSame('jugee', $etats[$jugee->id]['etat']);
        $this->assertTrue($etats[$jugee->id]['a_rediger']);
        $this->assertSame('non_portee', $etats[$jamais->id]['etat']);

        $d = TableauAudiences::donnees(null);
        $this->assertSame(4, $d['kpis']['en_cours']);   // renvoyée, en retard, délibérée, jamais portée (la jugée n'est plus en cours)
        $this->assertSame(1, $d['kpis']['deliberes']);
        $this->assertSame(1, $d['kpis']['deliberes_echus']);
        $this->assertSame(1, $d['kpis']['a_rediger']);
        $this->assertSame(3, $d['kpis']['retard']);      // renvoi dépassé, délibéré échu, jamais portée
        $this->assertCount(14, $d['par_jour']);

        $u = $this->greffier();
        $this->connecte($u)->get(route('audiences.tableau'))->assertOk()->assertSee('Tableau de bord des audiences');
        $this->connecte($u)->getJson(route('audiences.tableau.donnees', ['chambre' => $this->chambre->id]))->assertOk()->assertJsonPath('kpis.a_rediger', 1)->assertJsonStructure(['kpis', 'repartition', 'par_chambre', 'magistrats', 'deliberes', 'retards']);

        // Hors périmètre : un compte sans affectation ne voit aucune affaire.
        $limite = $this->avecDroits(['tableau.voir']);
        $this->connecte($limite)->getJson(route('audiences.tableau.donnees', ['chambre' => $this->chambre->id]))->assertOk()->assertJsonPath('kpis.en_cours', 0);
    }

    public function test_une_affaire_jugee_propose_de_rediger_la_decision_si_on_en_a_le_droit(): void
    {
        $this->composer();
        $this->affaire('2026-06-11');
        $role = $this->rolePublie('2026-06-11');

        $redacteur = $this->avecDroits(['feuille.voir', 'feuille.modifier', 'redaction.creer', 'affaires.toutes_chambres']);
        $page = $this->connecte($redacteur)->get(route('audiences.feuille.show', $role->id))->getContent();
        $this->assertStringContainsString(str_replace('/', '\/', route('decisions.redaction')), $page);
        $this->assertStringContainsString('affaire_id', $page, "Chaque ligne porte l'identifiant de son affaire.");

        $sans = $this->avecDroits(['feuille.voir', 'affaires.toutes_chambres']);
        $this->assertStringContainsString('URL_REDIGER = null', $this->connecte($sans)->get(route('audiences.feuille.show', $role->id))->getContent());
    }

    public function test_le_menu_audiences_affiche_les_nouvelles_pages(): void
    {
        $page = $this->connecte($this->greffier())->get(route('audiences.feuille'))->getContent();
        foreach (["Feuille d'audience", 'Renvois et délibérés'] as $entree) {
            $this->assertStringContainsString(e($entree), $page);
        }
        $this->assertStringNotContainsString('/audiences/tableau', $page, "Le tableau de bord des audiences n'est plus dans le menu.");
        $this->assertSame(1, preg_match_all('#<li><a class="active" href="[^"]*/audiences/feuille">#', $page));
    }
}
