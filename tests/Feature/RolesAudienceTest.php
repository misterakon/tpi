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
use App\Models\Salle;
use App\Models\User;
use App\Support\Roles\GenerateurRole;
use App\Support\Roles\RoleDocument;
use App\Support\Roles\RoleExport;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/** Rôles d'audience (lot J) : composition automatique, suites, publication et versions, conflits de salle, exports. */
class RolesAudienceTest extends TestCase
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

    private function greffier(): User
    {
        return $this->avecDroits(['roles.voir', 'roles.creer', 'roles.modifier', 'roles.supprimer', 'roles.imprimer', 'roles.publier', 'affaires.toutes_chambres']);
    }

    private function affaire(string $audience, array $a = []): Affaire
    {
        static $n = 0;
        $n++;
        $affaire = Affaire::create($a + [
            'serie' => 'RG', 'annee' => 2026, 'numero' => 100 + $n, 'numero_complet' => 'RG-'.(100 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-05-20', 'objet' => 'Paiement de dommages-intérêts '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Kone', 'prenoms' => 'Ibrahima '.$n, 'cle' => 'kone'.$n, 'avocat' => 'Pierre Dagbo']);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$n, 'cle' => 'ste'.$n]);

        return $affaire;
    }

    private function role(string $date, array $a = []): RoleAudience
    {
        return RoleAudience::create($a + ['chambre_id' => $this->chambre->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => $date]);
    }

    // ---------------- droits ----------------

    public function test_les_pages_exigent_leurs_droits(): void
    {
        $r = $this->role('2026-06-04');
        $lecteur = $this->avecDroits(['roles.voir', 'affaires.toutes_chambres']);
        $aucun = $this->avecDroits(['affaires.voir']);

        $this->connecte($aucun)->get(route('audiences.roles'))->assertForbidden();
        $this->connecte($lecteur)->get(route('audiences.roles'))->assertOk();
        $this->connecte($lecteur)->get(route('audiences.roles.planning'))->assertOk();
        $this->connecte($lecteur)->get(route('audiences.roles.show', $r->id))->assertOk();
        $this->connecte($lecteur)->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-11'])->assertForbidden();
        $this->connecte($lecteur)->post(route('audiences.roles.completer', $r->id))->assertForbidden();
        $this->connecte($lecteur)->get(route('audiences.roles.export', [$r->id, 'pdf']))->assertForbidden();
        $this->connecte($lecteur)->post(route('audiences.roles.publier', $r->id))->assertForbidden();
    }

    // ---------------- composition ----------------

    public function test_le_role_se_compose_des_affaires_nouvelles(): void
    {
        $a = $this->affaire('2026-06-04');
        $this->affaire('2026-06-11');   // une autre date : n'y figure pas
        $this->affaire('2026-06-04', ['statut' => 'annulee']);

        $this->connecte($this->greffier())->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04', 'heure' => '09:00'])->assertSessionHasNoErrors();
        $role = RoleAudience::firstOrFail();
        $lignes = $role->lignes;
        $this->assertCount(1, $lignes);
        $this->assertSame(['nouvelles', $a->id, 'KONE Ibrahima 1'], [$lignes[0]->rubrique, $lignes[0]->affaire_id, $lignes[0]->demandeurs]);
        $this->assertSame('SOCIETE EXEMPLE 1', $lignes[0]->defendeurs);
        $this->assertSame('Pierre Dagbo', $lignes[0]->avocats_demandeurs);

        // Un second rôle pour la même chambre et la même date est refusé.
        $this->connecte($this->greffier())->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04'])->assertSessionHasErrors('date_audience');
    }

    public function test_les_suites_alimentent_le_role_suivant(): void
    {
        $delibere = $this->affaire('2026-06-04');
        $renvoyee = $this->affaire('2026-06-04');
        $renvoyeeLoin = $this->affaire('2026-06-04');
        $jugee = $this->affaire('2026-06-04');
        $sansSuite = $this->affaire('2026-06-04');
        $nouvelle = $this->affaire('2026-06-11');

        $premier = $this->role('2026-06-04');
        GenerateurRole::completer($premier);
        $this->assertSame(5, $premier->lignes()->count());
        $suite = fn (Affaire $a, string $issue, ?string $renvoi = null) => $premier->lignes()->where('affaire_id', $a->id)->update(['issue' => $issue, 'renvoi_au' => $renvoi]);
        $suite($delibere, 'delibere');
        $suite($renvoyee, 'renvoi', '2026-06-11');
        $suite($renvoyeeLoin, 'renvoi', '2026-09-10');
        $suite($jugee, 'juge');

        $second = $this->role('2026-06-11');
        $proposees = GenerateurRole::proposer($second);
        $this->assertEqualsCanonicalizing([$delibere->id], $proposees['deliberes']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$renvoyee->id, $sansSuite->id], $proposees['anciennes']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$nouvelle->id], $proposees['nouvelles']->pluck('id')->all());

        $this->assertSame(4, GenerateurRole::completer($second));
        $this->assertSame(0, GenerateurRole::completer($second), 'Compléter deux fois ne double rien.');
    }

    public function test_les_affaires_reprises_et_jointes_n_entrent_pas_d_elles_memes(): void
    {
        $principale = $this->affaire('2026-06-04');
        $jointe = $this->affaire('2026-06-04', ['jointe_a_id' => $principale->id]);
        $role = $this->role('2026-06-04');
        GenerateurRole::completer($role);

        $this->assertSame([$principale->id], $role->lignes()->pluck('affaire_id')->all());
        $this->assertSame("{$principale->numeroAffichage()}\nEt {$jointe->numeroAffichage()}", $role->lignes()->first()->numero);
    }

    // ---------------- édition ----------------

    public function test_ajouter_deplacer_modifier_retirer(): void
    {
        $a = $this->affaire('2026-01-01');
        $b = $this->affaire('2026-01-01');
        $role = $this->role('2026-06-04');
        $u = $this->greffier();

        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'anciennes', 'numero' => $a->numero.'/26'])->assertSessionHasNoErrors();
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'anciennes', 'numero' => $b->numero.'/26'])->assertSessionHasNoErrors();
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'anciennes', 'numero' => $a->numero.'/26'])->assertSessionHasErrors('numero');
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'anciennes', 'numero' => 'abc'])->assertSessionHasErrors('numero');
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'anciennes', 'numero' => '99999/26'])->assertSessionHasErrors('numero');
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'nouvelles', 'numero' => ''])->assertSessionHasNoErrors();   // ligne libre
        $this->assertSame(3, $role->lignes()->count());

        $premiere = $role->lignes()->where('rubrique', 'anciennes')->orderBy('ordre')->first();
        $this->assertSame($a->id, $premiere->affaire_id);
        $this->connecte($u)->post(route('audiences.roles.ligne.deplacer', [$role->id, $premiere->id]), ['sens' => 'bas'])->assertRedirect();
        $this->assertSame($b->id, $role->lignes()->where('rubrique', 'anciennes')->orderBy('ordre')->first()->affaire_id);

        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $premiere->id]), [
            'rubrique' => 'deliberes', 'numero' => '690/26'."\n".'Et 848/26', 'demandeurs' => "A\nB", 'defendeurs' => 'C', 'objet' => "Nullité de contrat\x07", 'observations' => 'Renvoi',
        ])->assertSessionHasNoErrors();
        $premiere->refresh();
        $this->assertSame(['deliberes', "690/26\nEt 848/26", 'Nullité de contrat'], [$premiere->rubrique, $premiere->numero, $premiere->objet]);

        $this->connecte($u)->post(route('audiences.roles.ligne.retirer', [$role->id, $premiere->id]))->assertRedirect();
        $this->assertSame(2, $role->lignes()->count());
    }

    public function test_une_date_de_renvoi_est_obligatoire_et_posterieure(): void
    {
        $role = $this->role('2026-06-04');
        $l = $role->lignes()->create(['rubrique' => 'anciennes', 'ordre' => 1, 'numero' => '1/26', 'origine' => 'libre']);
        $u = $this->greffier();
        $base = ['rubrique' => 'anciennes', 'numero' => '1/26'];

        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), $base + ['issue' => 'renvoi'])->assertSessionHasErrors('renvoi_au');
        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), $base + ['issue' => 'renvoi', 'renvoi_au' => '2026-06-04'])->assertSessionHasErrors('renvoi_au');
        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), $base + ['issue' => 'renvoi', 'renvoi_au' => '2026-06-11'])->assertSessionHasNoErrors();
        $this->assertSame('2026-06-11', $l->refresh()->renvoi_au->format('Y-m-d'));
        // Une autre issue efface la date de renvoi.
        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), $base + ['issue' => 'juge', 'renvoi_au' => '2026-06-11']);
        $this->assertNull($l->refresh()->renvoi_au);
    }

    // ---------------- publication et versions ----------------

    public function test_publication_version_et_reouverture(): void
    {
        $this->affaire('2026-06-04');
        $role = $this->role('2026-06-04');
        $u = $this->greffier();

        // Un rôle vide ne se publie pas.
        $this->connecte($u)->post(route('audiences.roles.publier', $role->id))->assertSessionHasErrors('publier');
        GenerateurRole::completer($role);
        $this->connecte($u)->post(route('audiences.roles.publier', $role->id))->assertSessionHasNoErrors();
        $role->refresh();
        $this->assertSame(['publie', 1, 1], [$role->statut, $role->version, $role->versions()->count()]);

        // Publié : le texte ne change plus, la suite donnée s'y note, on ne supprime plus.
        $l = $role->lignes()->first();
        $this->connecte($u)->post(route('audiences.roles.completer', $role->id))->assertForbidden();
        $this->connecte($u)->post(route('audiences.roles.ligne.retirer', [$role->id, $l->id]))->assertForbidden();
        $this->connecte($u)->post(route('audiences.roles.supprimer', $role->id))->assertForbidden();
        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), ['objet' => 'Autre objet', 'issue' => 'delibere'])->assertSessionHasNoErrors();
        $l->refresh();
        $this->assertSame(['delibere', true], [$l->issue, $l->objet !== 'Autre objet']);

        // Réouverture avec motif : version 2, la version 1 reste telle qu'elle a été diffusée.
        $this->connecte($u)->post(route('audiences.roles.rouvrir', $role->id), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->connecte($u)->post(route('audiences.roles.rouvrir', $role->id), ['motif' => 'Affaire oubliée'])->assertSessionHasNoErrors();
        $role->refresh();
        $this->assertSame(['brouillon', 2], [$role->statut, $role->version]);
        $this->connecte($u)->post(route('audiences.roles.ligne.ajouter', $role->id), ['rubrique' => 'nouvelles', 'numero' => ''])->assertSessionHasNoErrors();
        $this->connecte($u)->post(route('audiences.roles.publier', $role->id), ['motif' => 'Affaire oubliée'])->assertSessionHasNoErrors();
        $this->assertSame([1, 2], $role->versions()->reorder('version')->pluck('version')->all());
        $v1 = $role->versions()->where('version', 1)->first()->contenu();
        $v2 = $role->versions()->where('version', 2)->first()->contenu();
        $this->assertSame([1, 2], [$v1['total'], $v2['total']]);
        $this->assertSame('Affaire oubliée', $role->versions()->where('version', 2)->first()->motif);
    }

    public function test_un_role_jamais_publie_se_supprime(): void
    {
        $role = $this->role('2026-06-04');
        $this->connecte($this->greffier())->post(route('audiences.roles.supprimer', $role->id))->assertRedirect(route('audiences.roles'));
        $this->assertSame(0, RoleAudience::count());
    }

    // ---------------- salles ----------------

    public function test_deux_chambres_ne_se_partagent_pas_une_salle_a_la_meme_heure(): void
    {
        $salle = Salle::create(['nom' => 'Salle 4', 'type' => 'audience']);
        $autreChambre = Chambre::where('id', '!=', $this->chambre->id)->firstOrFail();
        RoleAudience::create(['chambre_id' => $autreChambre->id, 'date_audience' => '2026-06-04', 'heure' => '09:00', 'salle_id' => $salle->id]);
        $u = $this->greffier();

        $this->connecte($u)->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04', 'heure' => '10:30', 'salle_id' => (string) $salle->id])->assertSessionHasErrors('date_audience');
        $this->assertSame(1, RoleAudience::count());
        // Trois heures plus tard, la salle est libre.
        $this->connecte($u)->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-04', 'heure' => '12:00', 'salle_id' => $salle->id])->assertSessionHasNoErrors();
        $this->assertSame(2, RoleAudience::count());

        // Déplacer le rôle vers l'heure occupée est refusé.
        $role = RoleAudience::where('chambre_id', $this->chambre->id)->firstOrFail();
        $this->connecte($u)->post(route('audiences.roles.entete', $role->id), ['date_audience' => '2026-06-04', 'heure' => '09:30', 'salle_id' => $salle->id, 'fait_a' => 'Bingerville', 'signataire' => 'Le Président'])->assertSessionHasErrors('salle_id');
        $this->assertStringStartsWith('12:00', $role->refresh()->heure);
    }

    public function test_la_date_de_renvoi_avertit_et_propose_la_prochaine_audience(): void
    {
        $formation = \App\Models\Formation::create(['chambre_id' => $this->chambre->id, 'ordre' => 1, 'actif' => true]);
        \App\Models\Creneau::create(['formation_id' => $formation->id, 'type' => 'Audience', 'frequence' => 'hebdomadaire', 'jour' => 4, 'heure' => '09:00']);
        $role = $this->role('2026-11-05');
        $u = $this->greffier();
        $url = fn (string $date) => route('audiences.roles.renvoi', $role->id).'?date='.$date;

        // Un dimanche : la chambre ne siège pas ; on propose le jeudi suivant.
        $this->connecte($u)->getJson($url('2026-11-08'))->assertOk()->assertJson(['siege' => false, 'ferme' => null, 'suggestion' => ['date' => '2026-11-12']]);
        // Un jeudi libre : rien à signaler.
        $this->connecte($u)->getJson($url('2026-11-12'))->assertOk()->assertJson(['siege' => true, 'ferme' => null, 'suggestion' => null]);
        // Un jeudi fermé : avertissement, et la proposition saute au jeudi d'après.
        \App\Models\Fermeture::create(['annee_judiciaire_id' => \App\Models\AnneeJudiciaire::where('courante', true)->value('id'), 'type' => 'fermeture', 'libelle' => 'Fermeture test', 'debut' => '2026-11-12', 'fin' => '2026-11-12']);
        $this->connecte($u)->getJson($url('2026-11-12'))->assertOk()->assertJson(['ferme' => 'Fermeture test', 'suggestion' => ['date' => '2026-11-19']]);
        // Une date mal écrite est refusée proprement.
        $this->connecte($u)->getJson($url('n-importe-quoi'))->assertStatus(422);
        // La date reste libre : l'enregistrement d'un renvoi un jour fermé n'est pas bloqué.
        $ligne = $role->lignes()->create(['rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '1/26', 'origine' => 'libre']);
        $role->forceFill(['statut' => 'publie'])->save();
        $this->connecte($u)->post(route('audiences.roles.ligne.modifier', [$role->id, $ligne->id]), ['issue' => 'renvoi', 'renvoi_au' => '2026-11-12'])->assertSessionHasNoErrors();
    }

    public function test_le_planning_montre_la_semaine(): void
    {
        $salle = Salle::create(['nom' => 'Salle 4', 'type' => 'audience']);
        $this->role('2026-06-04', ['salle_id' => $salle->id]);

        $this->connecte($this->greffier())->get(route('audiences.roles.planning', ['du' => '2026-06-01']))->assertOk()->assertSee('Salle 4')->assertSee('Semaine du 1 juin');
        // Une date mal écrite ne casse rien.
        $this->connecte($this->greffier())->get(route('audiences.roles.planning', ['du' => 'n importe quoi']))->assertOk();
    }

    public function test_le_changement_de_semaine_ne_renvoie_que_la_zone_du_planning(): void
    {
        $u = $this->greffier();
        $page = $this->connecte($u)->get(route('audiences.roles.planning', ['du' => '2026-06-01']));
        $page->assertOk()->assertSee('planning-zone', false)->assertSee('<html', false);

        $zone = $this->connecte($u)->get(route('audiences.roles.planning', ['du' => '2026-06-08']), ['X-Requested-With' => 'XMLHttpRequest']);
        $zone->assertOk()->assertSee('Semaine du 8 juin', false)->assertDontSee('<html', false)->assertDontSee('planning-zone', false);
    }

    public function test_le_menu_n_active_qu_une_entree_a_la_fois(): void
    {
        $u = $this->greffier();
        $roles = $this->connecte($u)->get(route('audiences.roles'))->getContent();
        $planning = $this->connecte($u)->get(route('audiences.roles.planning'))->getContent();
        $actifs = fn (string $html) => preg_match_all('#<li><a class="active" href="[^"]*/audiences/roles(/planning)?">#', $html, $m) ? $m[0] : [];

        $this->assertCount(1, $actifs($roles));
        $this->assertStringContainsString('/audiences/roles"', $actifs($roles)[0]);
        $this->assertCount(1, $actifs($planning));
        $this->assertStringContainsString('/planning"', $actifs($planning)[0]);
    }

    // ---------------- types de rôle : référé et pénal (parquet) ----------------

    private function affairePenale(string $audience): Affaire
    {
        static $n = 0;
        $n++;
        $chambre = Chambre::where('libelle', 'like', '%correctionnelle%')->orderBy('id')->firstOrFail();
        $affaire = Affaire::create([
            'serie' => 'RP', 'annee' => 2026, 'numero' => 1150 + $n, 'numero_complet' => 'RP-'.(1150 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-09-25', 'objet' => 'Vol portant sur un sac',
            'articles' => '457, 458, 461 et 462 du CP', 'mode_saisine_id' => ModeSaisine::where('registre', 'penal')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RP')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'prevenu', 'nature' => 'homme', 'nom' => 'Bance', 'prenoms' => 'Germain', 'cle' => 'bance'.$n, 'naissance_mode' => 'annee', 'naissance_annee' => 1994,
            'nationalite' => 'burkinabé', 'profession' => 'maçon', 'mandat_depot_date' => '2026-09-23']);

        return $affaire;
    }

    public function test_le_role_penal_est_reserve_au_parquet(): void
    {
        $chambre = Chambre::where('libelle', 'like', '%correctionnelle%')->orderBy('id')->firstOrFail();
        $a = $this->affairePenale('2026-10-07');
        $greffier = $this->greffier();
        $parquet = $this->avecDroits(['roles.voir', 'roles.creer', 'roles.modifier', 'roles.imprimer', 'roles.penal', 'affaires.toutes_chambres']);
        $demande = ['chambre_id' => $chambre->id, 'date_audience' => '2026-10-07', 'heure' => '10:00', 'type' => 'penal', 'intitule' => 'AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS'];

        $this->connecte($greffier)->post(route('audiences.roles.store'), $demande)->assertForbidden();
        $this->connecte($parquet)->post(route('audiences.roles.store'), $demande)->assertSessionHasNoErrors();
        $role = RoleAudience::firstOrFail();
        $this->connecte($parquet)->get(route('audiences.roles.show', $role->id))->assertOk()->assertSee('IDENTITE DES PREVENUS')->assertSee('PRESIDENT :')->assertSee('LE PROCUREUR DE LA REPUBLIQUE');
        $this->assertSame(['penal', 'Parquet de Bingerville', 'LE PROCUREUR DE LA REPUBLIQUE'], [$role->type, $role->fait_a, $role->signataire]);

        // La ligne reprend l'affaire : numéro RP, identité du prévenu, mandat, nature et articles.
        $l = $role->lignes()->firstOrFail();
        $this->assertSame(['RP '.$a->numero.'/26', $a->id, '23-09-26'], [$l->numero, $l->affaire_id, $l->defendeurs]);
        $this->assertStringContainsString("BANCE Germain\n", (string) $l->demandeurs);
        $this->assertStringContainsString('burkinabé', (string) $l->demandeurs);
        $this->assertStringContainsString("Arts : 457, 458, 461 et 462 du CP", (string) $l->objet);

        // Seul le parquet modifie ce rôle.
        $this->connecte($greffier)->post(route('audiences.roles.completer', $role->id))->assertForbidden();
        $this->connecte($greffier)->post(route('audiences.roles.ligne.modifier', [$role->id, $l->id]), ['rubrique' => 'nouvelles'])->assertForbidden();
        $this->connecte($parquet)->post(route('audiences.roles.entete', $role->id), [
            'date_audience' => '2026-10-07', 'heure' => '10:00', 'fait_a' => 'Parquet de Bingerville', 'signataire' => 'LE PROCUREUR DE LA REPUBLIQUE', 'intitule' => 'AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS',
            'president' => "N'GUESSAN Roger", 'procureur' => 'ESSIENNE Josiane', 'additif' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(["N'GUESSAN Roger", true], [$role->refresh()->president, $role->additif]);
    }

    public function test_le_document_penal_suit_le_modele_du_parquet(): void
    {
        $a = $this->affairePenale('2026-10-07');
        $chambre = Chambre::where('libelle', 'like', '%correctionnelle%')->orderBy('id')->firstOrFail();
        $role = RoleAudience::create(['chambre_id' => $chambre->id, 'type' => 'penal', 'date_audience' => '2026-10-07', 'heure' => '10:00', 'intitule' => 'AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS',
            'additif' => true, 'fait_a' => 'Parquet de Bingerville', 'signataire' => 'LE PROCUREUR DE LA REPUBLIQUE', 'date_signature' => '2026-10-05', 'president' => 'N\'GUESSAN Roger']);
        GenerateurRole::completer($role);

        $doc = RoleDocument::depuis($role->fresh());
        $this->assertSame(['N° d\'ordre', 'IDENTITE DES PREVENUS', 'DATE DU MANDAT DE DEPOT', 'NATURE DE LA PREVENTION', 'OBSERVATION'], array_column($doc['colonnes'], 'titre'));
        $this->assertSame("ROLE\nAUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS", $doc['titre_chambre']);
        $this->assertSame("DU MERCREDI 07 OCTOBRE 2026\n(Rôle additif)", $doc['titre_quand']);
        $this->assertSame('10 HEURES', $doc['heure_droite']);
        $this->assertSame([['PRESIDENT :', "N'GUESSAN Roger"], ['ASSESSEURS :', null], ['PROCUREUR :', null], ['GREFFIER :', null]], $doc['equipe']);
        $this->assertSame('Fait au ', $doc['signe_prefixe']);
        $this->assertStringContainsString('PARQUET PRES LE TRIBUNAL DE', implode(' ', $doc['entete_gauche']));
        $this->assertSame([['t' => '01', 'b' => true, 'i' => false], ['t' => 'RP '.$a->numero.'/26', 'b' => true, 'i' => false]], $doc['rubriques'][0]['lignes'][0]['cellules'][0]);
        // Nom du prévenu et date du mandat en gras, précisions en italique, articles visés en gras italique.
        $cellules = $doc['rubriques'][0]['lignes'][0]['cellules'];
        $this->assertSame([true, false], [$cellules[1][0]['b'], $cellules[1][0]['i']]);
        $this->assertSame([false, true], [$cellules[1][1]['b'], $cellules[1][1]['i']]);
        $this->assertSame(true, $cellules[2][0]['b']);
        $this->assertSame([true, true], [$cellules[3][1]['b'], $cellules[3][1]['i']]);

        // Sans aucun mandat de dépôt, la colonne disparaît et l'observation devient « décision du tribunal ».
        $role->lignes()->update(['defendeurs' => null]);
        $this->assertSame(['N° d\'ordre', 'IDENTITE DES PREVENUS', 'NATURE DE LA PREVENTION', 'DECISION DU TRIBUNAL'], array_column(RoleDocument::depuis($role->fresh())['colonnes'], 'titre'));
        $role->lignes()->update(['defendeurs' => '23-09-26']);

        $parquet = $this->avecDroits(['roles.voir', 'roles.imprimer', 'roles.penal', 'affaires.toutes_chambres']);
        $zip = new ZipArchive;
        $chemin = tempnam(sys_get_temp_dir(), 'pen');
        file_put_contents($chemin, $this->connecte($parquet)->get(route('audiences.roles.export', [$role->id, 'docx']))->getContent());
        $this->assertTrue($zip->open($chemin));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($chemin);
        $this->assertTrue((new \DOMDocument)->loadXML($xml));
        foreach (['PRESIDENT :', 'IDENTITE DES PREVENUS', 'DATE DU MANDAT DE DEPOT', '10 HEURES', '(Rôle additif)', 'Fait au Parquet de Bingerville', 'LE PROCUREUR DE LA REPUBLIQUE', 'RP '.$a->numero.'/26'] as $texte) {
            $this->assertStringContainsString($texte, $xml);
        }
        $this->assertStringStartsWith('%PDF', $this->connecte($parquet)->get(route('audiences.roles.export', [$role->id, 'pdf']))->getContent());
    }

    public function test_le_role_de_refere_propose_expul_ou_ordin(): void
    {
        $this->affaire('2026-10-21', ['objet' => 'Assignation en référé aux fins de déguerpissement']);
        $this->affaire('2026-10-21', ['objet' => 'Assignation en référé aux fins de suspension de travaux']);
        $role = $this->role('2026-10-21', ['type' => 'refere', 'intitule' => 'REFERE EXPULSION – ORDINAIRE – HEURE A HEURE']);
        GenerateurRole::completer($role);

        $this->assertEqualsCanonicalizing(['EXPUL.', 'ORDIN.'], $role->lignes()->pluck('observations')->all());
        $doc = RoleDocument::depuis($role->fresh());
        $this->assertSame("ROLE D'AUDIENCE DE REFERE", $doc['titre_chambre']);
        $this->assertSame("DU MERCREDI 21 OCTOBRE 2026 A 09 HEURES\n(REFERE EXPULSION – ORDINAIRE – HEURE A HEURE)", $doc['titre_quand']);
        $this->assertSame('Fait à ', $doc['signe_prefixe']);
    }

    public function test_une_version_figee_avant_les_types_reste_lisible(): void
    {
        $ancien = ['version' => 1, 'statut' => 'publie', 'salle' => 'SALLE 4', 'titre' => 'ROLE D\'AUDIENCE X', 'avec_avocats' => true, 'total' => 1, 'fait_a' => 'Bingerville', 'date_signature' => '02/06/2026', 'signataire' => 'Le Président',
            'rubriques' => [['code' => 'nouvelles', 'titre' => 'AFFAIRES NOUVELLES', 'lignes' => [['n' => '01', 'numero' => ['587/26'], 'demandeurs' => ['KONE'], 'defendeurs' => ['MP'], 'avocats_demandeurs' => ['(Me X)'], 'avocats_defendeurs' => [], 'objet' => ['Objet'], 'observations' => []]]]]];
        $doc = RoleDocument::normaliser($ancien);

        $this->assertSame('civil', $doc['type']);
        $this->assertCount(6, $doc['colonnes']);
        $this->assertSame([[['t' => '01', 'b' => true, 'i' => false]], ['587/26'], [['t' => 'KONE', 'b' => true, 'i' => false], ['t' => '(Me X)', 'b' => true, 'i' => true]], [['t' => 'MP', 'b' => true, 'i' => false]], ['Objet'], []], $doc['rubriques'][0]['lignes'][0]['cellules']);
        $this->assertStringStartsWith('%PDF', RoleExport::pdf($ancien));
        $this->assertNotEmpty(RoleExport::docx($ancien));
    }

    public function test_la_fenetre_de_creation_propose_le_type_penal_pour_une_chambre_penale(): void
    {
        $parquet = $this->avecDroits(['roles.voir', 'roles.creer', 'roles.penal', 'affaires.toutes_chambres']);
        $page = $this->connecte($parquet)->get(route('audiences.roles'))->assertOk();
        $page->assertSee('data-type="penal"', false)->assertSee('data-type="civil"', false)->assertSee('Rôle pénal (parquet)');
        $this->assertMatchesRegularExpression('#<option value="\d+" data-type="penal"[^>]*>[^<]*correctionnelle#', $page->getContent());

        // Sans le droit du parquet, le type pénal n'est pas proposé du tout.
        $this->connecte($this->greffier()->forceFill([]))->get(route('audiences.roles'))->assertOk();
        $sans = $this->avecDroits(['roles.voir', 'roles.creer', 'affaires.toutes_chambres']);
        $this->connecte($sans)->get(route('audiences.roles'))->assertOk()->assertDontSee('Rôle pénal (parquet)');
    }

    // ---------------- avertissement : magistrat ou greffier déjà pris ----------------

    public function test_un_magistrat_deja_pris_a_la_meme_heure_donne_un_avertissement_sans_bloquer(): void
    {
        \App\Models\Affectation::query()->delete();
        $autreChambre = Chambre::where('id', '!=', $this->chambre->id)->orderBy('id')->firstOrFail();
        $juge = \App\Models\Agent::create(['nom' => 'Kouassi', 'prenoms' => 'Marie', 'fonction' => 'juge', 'actif' => true]);
        $suppleant = \App\Models\Agent::create(['nom' => 'Traore', 'prenoms' => 'Ali', 'fonction' => 'juge', 'actif' => true]);
        foreach ([$this->chambre, $autreChambre] as $c) {
            $formation = \App\Models\Formation::create(['chambre_id' => $c->id, 'ordre' => 1, 'actif' => true]);
            \App\Models\Affectation::create(['formation_id' => $formation->id, 'agent_id' => $juge->id, 'role' => 'president']);
            \App\Models\Affectation::create(['formation_id' => $formation->id, 'agent_id' => $suppleant->id, 'role' => 'assesseur', 'suppleant' => true]);
        }
        $premier = $this->role('2026-06-04', ['heure' => '09:00']);
        $this->role('2026-06-04', ['chambre_id' => $autreChambre->id, 'heure' => '10:30']);
        $u = $this->greffier();

        // Même magistrat à 09h00 et 10h30 : avertissement (le suppléant n'est pas compté), mais rien n'est bloqué.
        $avertissements = $premier->agentsDejaPris();
        $this->assertCount(1, $avertissements);
        $this->assertSame([$juge->id, $autreChambre->id], [$avertissements[0]['agent']->id, $avertissements[0]['autre']->chambre_id]);
        $this->connecte($u)->get(route('audiences.roles.show', $premier->id))->assertOk()->assertSee('Avertissement')->assertSee('KOUASSI Marie');
        // Les deux rôles concernés portent chacun l'avertissement.
        $this->connecte($u)->get(route('audiences.roles.planning', ['du' => '2026-06-01']))->assertOk()->assertSee('2 avertissements');
        $l = $premier->lignes()->create(['rubrique' => 'nouvelles', 'ordre' => 1, 'numero' => '1/26', 'origine' => 'libre']);
        $this->connecte($u)->post(route('audiences.roles.publier', $premier->id))->assertSessionHasNoErrors();
        $this->assertSame('publie', $premier->refresh()->statut);

        // Trois heures d'écart ou plus : rien à signaler. Sans heure : on ne dit rien.
        RoleAudience::where('chambre_id', $autreChambre->id)->update(['heure' => '12:00']);
        $this->assertCount(0, $premier->fresh()->agentsDejaPris());
        RoleAudience::where('chambre_id', $autreChambre->id)->update(['heure' => null]);
        $this->assertCount(0, $premier->fresh()->agentsDejaPris());
        $this->assertNotNull($l);
    }

    public function test_un_jour_ferie_est_signale_dans_le_planning_et_l_editeur(): void
    {
        $annee = \App\Models\AnneeJudiciaire::where('courante', true)->firstOrFail();
        \App\Models\Fermeture::updateOrCreate(['type' => 'ferie', 'debut' => '2026-12-25'], ['annee_judiciaire_id' => $annee->id, 'libelle' => 'Noël', 'fin' => '2026-12-25']);
        $role = $this->role('2026-12-25');
        $u = $this->greffier();

        // Planning : le libellé du jour férié figure dans l'en-tête de la colonne (lundi à samedi seulement).
        $this->connecte($u)->get(route('audiences.roles.planning', ['du' => '2026-12-21']))->assertOk()->assertSee('rl-grille__f', false)->assertSee('Noël');
        $this->connecte($u)->get(route('audiences.roles.planning', ['du' => '2026-06-01']))->assertOk()->assertDontSee('rl-grille__f', false);
        // Éditeur : le rôle reste modifiable, avec la mention orange.
        $this->connecte($u)->get(route('audiences.roles.show', $role->id))->assertOk()->assertSee('le tribunal est fermé')->assertSee('Noël');
    }

    // ---------------- en-tête et exports ----------------

    public function test_le_titre_suit_le_modele_de_la_greffiere_en_chef(): void
    {
        $role = $this->role('2026-06-04', ['heure' => '09:00']);
        $this->assertStringStartsWith("ROLE D'AUDIENCE DE LA ", $role->titre());
        $this->assertStringEndsWith('DU JEUDI 04 JUIN 2026 A 09 HEURES', $role->titre());
        $role->update(['heure' => '14:30']);
        $this->assertStringEndsWith('A 14 HEURES 30', $role->refresh()->titre());
    }

    public function test_exports_word_et_pdf(): void
    {
        $this->affaire('2026-06-04');
        $role = $this->role('2026-06-04', ['avec_avocats' => true]);
        GenerateurRole::completer($role);
        $role->lignes()->create(['rubrique' => 'deliberes', 'ordre' => 1, 'numero' => '587/26', 'demandeurs' => 'KONE <&> "Test"', 'defendeurs' => 'MP', 'objet' => 'Liquidation', 'origine' => 'libre']);
        $u = $this->greffier();

        $doc = RoleDocument::depuis($role);
        $this->assertSame(['AFFAIRES EN DELIBERE', 'AFFAIRES NOUVELLES'], array_column($doc['rubriques'], 'titre'));
        $this->assertSame(['01', '02'], [$doc['rubriques'][0]['lignes'][0]['n'], $doc['rubriques'][1]['lignes'][0]['n']]);
        $this->assertSame(['N° d\'ordre', 'N° RG', 'DEMANDEUR(S)', 'DEFENDEUR(S)', 'OBJET', 'OBSERVATIONS'], array_column($doc['colonnes'], 'titre'));
        $this->assertSame(['t' => '(Me Pierre Dagbo)', 'b' => true, 'i' => true], $doc['rubriques'][1]['lignes'][0]['cellules'][2][1]);
        $this->assertSame(['t' => '01', 'b' => true, 'i' => false], $doc['rubriques'][0]['lignes'][0]['cellules'][0][0], 'Le numéro d\'ordre est en gras.');

        $word = $this->connecte($u)->get(route('audiences.roles.export', [$role->id, 'docx']));
        $word->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $chemin = tempnam(sys_get_temp_dir(), 'tst');
        file_put_contents($chemin, $word->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($chemin));
        $xml = $zip->getFromName('word/document.xml');
        $this->assertNotFalse($zip->getFromName('word/media/balance.png'));
        $zip->close();
        @unlink($chemin);
        $this->assertTrue((new \DOMDocument)->loadXML($xml), 'Le document Word est un XML valide.');
        $this->assertStringContainsString('AFFAIRES EN DELIBERE', $xml);
        $this->assertStringContainsString('KONE &lt;&amp;&gt; &quot;Test&quot;', $xml);
        $this->assertStringContainsString('w:orient="landscape"', $xml);
        $this->assertStringContainsString('(Me Pierre Dagbo)', $xml);

        $pdf = $this->connecte($u)->get(route('audiences.roles.export', [$role->id, 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        // Aperçu : même PDF, affiché dans la fenêtre du rôle (cadre autorisé pour l'application seule) ; le téléchargement reste protégé.
        $this->assertStringContainsString('attachment', $pdf->headers->get('content-disposition'));
        $this->assertSame('DENY', $pdf->headers->get('x-frame-options'));
        $apercu = $this->connecte($u)->get(route('audiences.roles.export', [$role->id, 'pdf']).'?apercu=1');
        $apercu->assertOk()->assertHeader('x-frame-options', 'SAMEORIGIN');
        $this->assertStringContainsString('inline', $apercu->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $apercu->getContent());
        $this->connecte($u)->get(route('audiences.roles.export', [$role->id, 'xls']))->assertNotFound();
        $this->assertSame(3, \App\Models\JournalAudit::where('action', 'role_exporte')->count());

        // Sans le droit d'exporter : refus. La version publiée s'exporte telle quelle.
        $this->connecte($this->avecDroits(['roles.voir', 'affaires.toutes_chambres']))->get(route('audiences.roles.export', [$role->id, 'docx']))->assertForbidden();
        $this->connecte($u)->post(route('audiences.roles.publier', $role->id));
        $this->connecte($u)->get(route('audiences.roles.version', [$role->id, 1, 'pdf']))->assertOk();
        $this->connecte($u)->get(route('audiences.roles.version', [$role->id, 9, 'pdf']))->assertNotFound();
        $this->assertTrue(is_file(RoleExport::logo()));
    }

    public function test_une_chambre_hors_perimetre_reste_invisible(): void
    {
        $role = $this->role('2026-06-04');
        $limite = $this->avecDroits(['roles.voir', 'roles.modifier', 'roles.imprimer']);   // pas « toutes chambres », aucune affectation

        $this->connecte($limite)->get(route('audiences.roles.show', $role->id))->assertNotFound();
        $this->connecte($limite)->get(route('audiences.roles.export', [$role->id, 'pdf']))->assertNotFound();
        $this->connecte($limite)->post(route('audiences.roles.store'), ['chambre_id' => $this->chambre->id, 'date_audience' => '2026-06-11'])->assertForbidden();
    }
}
