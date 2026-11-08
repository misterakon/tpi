<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Matiere;
use App\Models\Profil;
use App\Models\Requete;
use App\Models\Scelle;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Droit de corriger un numéro saisi, détails du pénal (détenus, échéances, scellés) et requêtes (ENR-10). */
class NumeroPenalRequetesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function affaire(array $a = []): Affaire
    {
        static $n = 0;
        $n++;
        $serie = $a['serie'] ?? 'RG';

        return Affaire::create($a + [
            'serie' => $serie, 'annee' => 2026, 'numero' => 7000 + $n, 'numero_complet' => $serie.'-'.(7000 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => 'Objet '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', $serie)->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::firstOrFail()->id, 'statut' => 'enrolee',
        ]);
    }

    // ---------------- corriger un numéro ----------------

    public function test_corriger_un_numero_saisi(): void
    {
        $a = $this->affaire(['numero' => 1931, 'numero_complet' => 'RG-1931/26']);
        $autre = $this->affaire(['numero' => 1400, 'numero_complet' => 'RG-1400/26']);
        $greffier = $this->avecDroits(['affaires.voir', 'affaires.modifier', 'affaires.toutes_chambres']);
        $chef = $this->avecDroits(['affaires.voir', 'affaires.corriger_numero', 'affaires.toutes_chambres']);

        // Le droit est à part : « Corriger » ne suffit pas.
        $this->connecte($greffier)->post(route('affaires.liste.numero', $a->id), ['numero_rg' => '1391/26', 'motif' => 'Chiffres inversés'])->assertForbidden();
        $this->connecte($greffier)->get(route('affaires.liste.fiche', $a->id))->assertDontSee('Corriger le numéro');
        $this->connecte($chef)->get(route('affaires.liste.fiche', $a->id))->assertSee('Corriger le numéro');

        // Contrôles : numéro mal écrit, motif absent, même numéro, numéro déjà pris.
        $url = route('affaires.liste.numero', $a->id);
        $this->connecte($chef)->post($url, ['numero_rg' => 'abc', 'motif' => 'Chiffres inversés'])->assertSessionHasErrors('numero_rg');
        $this->connecte($chef)->post($url, ['numero_rg' => '1391/26', 'motif' => ''])->assertSessionHasErrors('motif');
        $this->connecte($chef)->post($url, ['numero_rg' => '1931/26', 'motif' => 'Chiffres inversés'])->assertSessionHasErrors('numero_rg');
        $this->connecte($chef)->post($url, ['numero_rg' => '1400/26', 'motif' => 'Chiffres inversés'])->assertSessionHasErrors('numero_rg');
        $this->assertSame(1931, $a->refresh()->numero);

        $this->connecte($chef)->post($url, ['numero_rg' => '1391/26', 'motif' => 'Chiffres inversés à la saisie'])->assertSessionHasNoErrors()->assertRedirect(route('affaires.liste.fiche', $a->id));
        $a->refresh();
        $this->assertSame([1391, 'RG-1391/26'], [$a->numero, $a->numero_complet]);
        $c = $a->numerosCorriges()->firstOrFail();
        $this->assertSame(['1931/26', '1391/26', 'Chiffres inversés à la saisie', $chef->id], [$c->ancien, $c->nouveau, $c->motif, $c->par]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'numero_corrige']);
        $this->connecte($chef)->get(route('affaires.liste.fiche', $a->id))->assertSee('Numéro corrigé')->assertSee('1931/26');
    }

    public function test_un_numero_attribue_une_affaire_annulee_ou_jointe_ne_se_corrige_pas(): void
    {
        $chef = $this->avecDroits(['affaires.voir', 'affaires.corriger_numero', 'affaires.toutes_chambres']);
        $corriger = fn (Affaire $a) => $this->connecte($chef)->post(route('affaires.liste.numero', $a->id), ['numero_rg' => '55/26', 'motif' => 'Faute de frappe']);

        $automatique = $this->affaire(['numero_saisi' => false]);
        $corriger($automatique)->assertForbidden();                                      // attribué par l'application : jamais modifiable
        $annulee = $this->affaire(['statut' => 'annulee']);
        $corriger($annulee)->assertForbidden();
        $principale = $this->affaire();
        $jointe = $this->affaire(['jointe_a_id' => $principale->id, 'meme_numero' => true]);
        $corriger($principale)->assertForbidden();
        $corriger($jointe)->assertForbidden();
        $this->assertSame(0, \App\Models\NumeroCorrige::count());
    }

    // ---------------- détenus et scellés ----------------

    private function affairePenale(array $parties): Affaire
    {
        $a = $this->affaire(['serie' => 'RP', 'chambre_id' => Chambre::where('libelle', '1re chambre correctionnelle')->value('id')]);
        foreach ($parties as $p) {
            $a->parties()->create($p + ['role' => 'prevenu', 'nature' => 'homme', 'cle' => strtolower($p['nom'])]);
        }

        return $a;
    }

    public function test_echeance_des_detenus(): void
    {
        Carbon::setTestNow('2026-11-02');
        config(['tpi.penal.detention_jours' => 120, 'tpi.penal.alerte_jours' => 30]);
        $a = $this->affairePenale([
            ['nom' => 'CALCULE', 'mandat_depot_date' => '2026-08-01'],                                         // échéance calculée : 29 novembre, dans 27 jours
            ['nom' => 'SAISIE', 'mandat_depot_date' => '2026-06-01', 'echeance_detention' => '2026-10-20'],     // échéance saisie, dépassée de 13 jours
            ['nom' => 'LOIN', 'mandat_depot_date' => '2026-10-20'],
            ['nom' => 'LIBRE'],
            ['nom' => 'LIBERE', 'mandat_depot_date' => '2026-05-01', 'libere_le' => '2026-09-01'],
        ])->parties->keyBy('nom');

        $this->assertSame(['proche', 27, '2026-11-29', true], [$a['CALCULE']->niveauDetention(), $a['CALCULE']->joursRestants(), $a['CALCULE']->echeance()->toDateString(), $a['CALCULE']->echeanceCalculee()]);
        $this->assertSame(['depassee', -13, false], [$a['SAISIE']->niveauDetention(), $a['SAISIE']->joursRestants(), $a['SAISIE']->echeanceCalculee()]);
        $this->assertSame('normal', $a['LOIN']->niveauDetention());
        $this->assertNull($a['LIBRE']->niveauDetention());
        $this->assertFalse($a['LIBERE']->estDetenu());                                                          // mis en liberté : plus détenu

        $lecteur = $this->avecDroits(['detenus.voir', 'affaires.toutes_chambres']);
        $page = $this->connecte($lecteur)->get(route('affaires.detenus'))->assertOk();
        $lignes = strip_tags(implode(' ', array_map(fn ($l) => implode(' ', $l), $this->connecte($lecteur)->getJson(route('affaires.detenus.donnees', ['length' => 100]))->assertOk()->json('data'))));
        $this->assertTrue(strpos($lignes, 'SAISIE') < strpos($lignes, 'CALCULE') && strpos($lignes, 'CALCULE') < strpos($lignes, 'LOIN'));   // les plus pressants d'abord
        $this->assertStringNotContainsString('LIBRE', $lignes);
        $this->assertStringNotContainsString('LIBERE', $lignes);
        $this->assertStringContainsString('3 détenu(s)', $page->getContent());
        $this->connecte($this->avecDroits(['affaires.voir']))->get(route('affaires.detenus'))->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_les_detenus_respectent_le_perimetre_de_chambre(): void
    {
        $this->affairePenale([['nom' => 'VISIBLE', 'mandat_depot_date' => '2026-10-01']]);
        $sansPerimetre = $this->avecDroits(['detenus.voir']);                        // ni « toutes les chambres », ni agent : rien
        $this->connecte($sansPerimetre)->get(route('affaires.detenus'))->assertOk();
        $this->assertSame(0, $this->connecte($sansPerimetre)->getJson(route('affaires.detenus.donnees'))->assertOk()->json('recordsTotal'));
    }

    public function test_scelles_ajout_modification_etat_et_droits(): void
    {
        $a = $this->affairePenale([['nom' => 'KONAN']]);
        $greffier = $this->avecDroits(['affaires.voir', 'affaires.toutes_chambres', 'scelles.voir', 'scelles.creer', 'scelles.modifier']);
        $admin = User::factory()->create();
        $url = route('affaires.scelles.store', $a->id);

        $this->connecte($greffier)->post($url, ['numero' => 'S1', 'designation' => 'Un pistolet automatique', 'nature' => 'arme', 'etat' => 'conserve', 'date_saisie' => '2026-10-01', 'lieu_conservation' => 'Greffe, armoire 2'])->assertSessionHasNoErrors()->assertRedirect(route('affaires.liste.fiche', $a->id).'#scelles');
        $s = Scelle::firstOrFail();
        $this->assertSame(['S1', 'arme', 'conserve', null], [$s->numero, $s->nature, $s->etat, $s->date_etat]);
        $page = $this->connecte($greffier)->get(route('affaires.liste.fiche', $a->id))->assertOk();
        $page->assertSee('data-onglet-bouton="scelles"', false)->assertSee('Un pistolet automatique')->assertSee('Greffe, armoire 2');

        // Doublon de numéro, nature inconnue, date future.
        $this->connecte($greffier)->post($url, ['numero' => 'S1', 'designation' => 'Autre', 'nature' => 'arme', 'etat' => 'conserve'])->assertSessionHasErrors('numero', null, 'scelle');
        $this->connecte($greffier)->post($url, ['numero' => 'S2', 'designation' => 'Autre', 'nature' => 'dragon', 'etat' => 'conserve'])->assertSessionHasErrors('nature');
        $this->connecte($greffier)->post($url, ['numero' => 'S3', 'designation' => 'Autre', 'nature' => 'arme', 'etat' => 'conserve', 'date_saisie' => '2099-01-01'])->assertSessionHasErrors('date_saisie');

        // Un scellé restitué prend une date d'état ; il garde sa ligne.
        $this->connecte($greffier)->post($url, ['id_' => $s->id, 'numero' => 'S1', 'designation' => 'Un pistolet automatique', 'nature' => 'arme', 'etat' => 'restitue', 'observations' => 'Restitué au propriétaire'])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame(['restitue', now()->toDateString()], [$s->etat, $s->date_etat->toDateString()]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'scelle_modifie']);

        // Pas de droit « supprimer » ni « créer » pour un lecteur.
        $this->connecte($greffier)->deleteJson(route('affaires.scelles.delete', $s->id))->assertForbidden();
        $lecteur = $this->avecDroits(['affaires.voir', 'affaires.toutes_chambres', 'scelles.voir']);
        $this->connecte($lecteur)->post($url, ['numero' => 'S9', 'designation' => 'X', 'nature' => 'arme', 'etat' => 'conserve'])->assertForbidden();
        $this->connecte($lecteur)->get(route('affaires.liste.fiche', $a->id))->assertSee('Un pistolet automatique')->assertDontSee('Ajouter un scellé');
        $this->connecte($this->avecDroits(['affaires.voir', 'affaires.toutes_chambres']))->get(route('affaires.liste.fiche', $a->id))->assertDontSee('data-onglet-bouton="scelles"', false);

        $this->connecte($admin)->deleteJson(route('affaires.scelles.delete', $s->id))->assertOk()->assertJson(['status' => true]);
        $this->assertSame(0, Scelle::count());

        // Pas de scellés au civil.
        $civile = $this->affaire();
        $this->connecte($admin)->post(route('affaires.scelles.store', $civile->id), ['numero' => 'S1', 'designation' => 'X', 'nature' => 'arme', 'etat' => 'conserve'])->assertNotFound();
        $this->connecte($admin)->get(route('affaires.liste.fiche', $civile->id))->assertDontSee('data-onglet-bouton="scelles"', false);
    }

    // ---------------- requêtes (ENR-10) ----------------

    private function requete(User $u, array $plus = [])
    {
        return $this->connecte($u)->post(route('affaires.requetes.store'), $plus + [
            'type' => 'heredite', 'date_depot' => now()->toDateString(), 'objet' => "Établissement de la qualité d'héritiers", 'requerants' => "KOUASSI Yao Marc\nKOUASSI Adjoua",
            'defunt_nom' => 'KOUASSI Jean', 'defunt_date_deces' => '2026-05-10', 'defunt_lieu_deces' => 'Bingerville',
        ]);
    }

    public function test_requete_d_heredite_depot_repartition_et_cloture(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');
        $greffier = $this->avecDroits(['requetes.voir', 'requetes.creer', 'requetes.modifier']);
        $secretariat = $this->avecDroits(['requetes.voir', 'requetes.repartir']);
        $chambre = Chambre::where('libelle', '2e chambre civile')->firstOrFail();

        $this->requete($greffier)->assertSessionHasNoErrors();
        $r = Requete::firstOrFail();
        $this->assertSame(['RQ-0001/26', 'recue', 'RQ 1/26', ['KOUASSI Yao Marc', 'KOUASSI Adjoua']], [$r->numero_complet, $r->statut, $r->numeroAffichage(), $r->listeRequerants()]);
        $this->requete($greffier, ['objet' => 'Deuxième'])->assertSessionHasNoErrors();
        $this->assertSame('RQ-0002/26', Requete::orderByDesc('id')->value('numero_complet'));      // numérotation propre, sans trou

        // Hérédité : le défunt est obligatoire ; un autre type n'en a pas.
        $this->requete($greffier, ['defunt_nom' => ''])->assertSessionHasErrors('defunt_nom');
        $this->requete($greffier, ['type' => 'injonction', 'defunt_nom' => 'Ignoré', 'objet' => 'Injonction'])->assertSessionHasNoErrors();
        $this->assertNull(Requete::orderByDesc('id')->first()->defunt_nom);
        $this->requete($greffier, ['requerants' => ''])->assertSessionHasErrors('requerants');

        // Seul le secrétariat répartit.
        $url = route('affaires.requetes.repartir', $r->id);
        $this->connecte($greffier)->post($url, ['chambre_id' => $chambre->id])->assertForbidden();
        $this->connecte($secretariat)->post($url, ['chambre_id' => Chambre::where('libelle', '1re chambre correctionnelle')->value('id')])->assertSessionHasErrors('chambre_id');   // pas une chambre civile
        $this->connecte($secretariat)->post($url, ['chambre_id' => $chambre->id, 'note' => 'Urgent'])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame(['repartie', $chambre->id, $secretariat->id], [$r->statut, $r->chambre_id, $r->reparti_par]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'requete_repartie']);

        // Clôture : traitée seulement si répartie ; la date ne précède pas le dépôt.
        $cloturer = fn (User $u, Requete $x, array $d) => $this->connecte($u)->post(route('affaires.requetes.cloturer', $x->id), $d);
        $nonRepartie = Requete::where('objet', 'Deuxième')->firstOrFail();
        $cloturer($greffier, $nonRepartie, ['issue' => 'traitee', 'reference' => 'Ordonnance n° 5/26', 'date' => '2026-11-02'])->assertForbidden();
        $cloturer($greffier, $r, ['issue' => 'traitee', 'reference' => "Jugement d'hérédité n° 8/26", 'date' => '2026-10-01'])->assertSessionHasErrors('date');
        $cloturer($greffier, $r, ['issue' => 'traitee', 'reference' => "Jugement d'hérédité n° 8/26", 'date' => '2026-11-02'])->assertSessionHasNoErrors();
        $this->assertSame(['traitee', "Jugement d'hérédité n° 8/26"], [$r->refresh()->statut, $r->reference_decision]);
        $cloturer($greffier, $r, ['issue' => 'traitee', 'reference' => 'Encore', 'date' => '2026-11-02'])->assertForbidden();   // déjà clôturée
        $this->requete($greffier, ['id_' => $r->id])->assertForbidden();                                                          // et plus modifiable

        // Classer sans suite demande le droit d'annuler.
        $cloturer($greffier, $nonRepartie, ['issue' => 'classee', 'reference' => 'Requérant désisté', 'date' => '2026-11-02'])->assertForbidden();
        $chef = $this->avecDroits(['requetes.voir', 'requetes.annuler', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction']);
        $cloturer($chef, $nonRepartie, ['issue' => 'classee', 'reference' => 'Requérant désisté', 'date' => '2026-11-02'])->assertSessionHasNoErrors();
        $this->assertSame('classee', $nonRepartie->refresh()->statut);
        Carbon::setTestNow();
    }

    public function test_retard_de_repartition_et_visibilite(): void
    {
        Carbon::setTestNow('2026-11-02');
        $greffier = $this->avecDroits(['requetes.voir', 'requetes.creer']);
        $secretariat = $this->avecDroits(['requetes.voir', 'requetes.repartir']);
        $autre = $this->avecDroits(['requetes.voir', 'requetes.creer']);

        $this->requete($greffier, ['date_depot' => '2026-10-20', 'objet' => 'Ancienne requête'])->assertSessionHasNoErrors();
        $this->assertTrue(Requete::firstOrFail()->enRetard());
        $this->assertSame(13, Requete::firstOrFail()->joursSansRepartition());

        // Le secrétariat voit tout ; l'auteur voit les siennes ; un autre greffier (sans chambre) ne voit pas celles des autres.
        $this->connecte($secretariat)->get(route('affaires.requetes'))->assertOk();
        $this->assertStringContainsString('Ancienne requête', $this->lignesServeur($secretariat, 'affaires.requetes.donnees'));
        $this->assertStringContainsString('depuis 13 jours', $this->lignesServeur($secretariat, 'affaires.requetes.donnees'));
        $this->assertStringContainsString('Ancienne requête', $this->lignesServeur($greffier, 'affaires.requetes.donnees'));
        $this->assertStringNotContainsString('Ancienne requête', $this->lignesServeur($autre, 'affaires.requetes.donnees'));
        $this->connecte($autre)->getJson(route('affaires.requetes.get', Requete::firstOrFail()->id))->assertNotFound();
        $this->connecte($this->avecDroits(['affaires.voir']))->get(route('affaires.requetes'))->assertForbidden();
        Carbon::setTestNow();
    }
}
