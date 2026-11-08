<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\AffaireAttribution;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Models\Decision;
use App\Models\Fermeture;
use App\Models\Formation;
use App\Models\ModeleDecision;
use Database\Seeders\ModelesDecisionSeeder;
use Illuminate\Support\Carbon;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\ObjetLitige;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use App\Services\NumerotationRegistre;
use App\Support\RapprochementObjet;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AffairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'automatique')]);   // les premiers tests couvrent l'attribution automatique ; les autres passent en « manuel »
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

    private function charger(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    /** Formulaire d'enrôlement complet ; $modifs remplace des champs. */
    private function formulaire(array $modifs = []): array
    {
        return array_replace_recursive([
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->firstOrFail()->id,
            'objet' => 'Demande en paiement de loyers impayés',
            'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->firstOrFail()->id,
            'matiere_id' => Matiere::firstOrFail()->id,
            'chambre_id' => Chambre::firstOrFail()->id,
            'parties' => [
                0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'KOUASSI', 'prenoms' => 'Yao Marc', 'naissance_date' => '1980-05-12', 'lieu_naissance' => 'Bouaké', 'nationalite' => 'ivoirienne', 'domicile' => 'Bingerville', 'avocat' => 'Maître YAO'],
                1 => ['role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'Société ABC SARL', 'domicile' => 'Abidjan'],
            ],
        ], $modifs);
    }

    private function enroler(User $user, array $modifs = [])
    {
        return $this->connecte($user)->post(route('affaires.enrolement.store'), $this->formulaire($modifs));
    }

    public function test_numerotation_sans_doublon_ni_reutilisation(): void
    {
        $a = NumerotationRegistre::attribuer('RG', 2026);
        $b = NumerotationRegistre::attribuer('RG', 2026);
        $this->assertSame('RG-0001/26', $a['libelle']);
        $this->assertSame('RG-0002/26', $b['libelle']);
        $this->assertSame('RG-0001/27', NumerotationRegistre::attribuer('RG', 2027)['libelle']);   // redémarre chaque année
        $this->assertSame('RP-0001/26', NumerotationRegistre::attribuer('RP', 2026)['libelle']);   // une série par registre
        $this->assertSame('RG-0003/26', NumerotationRegistre::attribuer('RG', 2026)['libelle']);
        $this->assertSame('RG-0012/26', NumerotationRegistre::formater('RG', 12, 2026));
    }

    public function test_un_numero_ne_se_modifie_ni_ne_se_supprime(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin)->assertRedirect();
        $affaire = Affaire::firstOrFail();

        $this->expectException(LogicException::class);
        $affaire->update(['numero' => 99]);
    }

    public function test_une_affaire_ne_se_supprime_pas(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin);

        $this->expectException(LogicException::class);
        Affaire::firstOrFail()->delete();
    }

    public function test_enrolement_complet(): void
    {
        $this->charger();
        $admin = User::factory()->create();

        $this->connecte($admin)->get(route('affaires.liste'))->assertOk()->assertSee('Enrôler une affaire')->assertSee('Mode de saisine')->assertSee('Les demandeurs')->assertSee('Les défendeurs');

        $this->enroler($admin, ['parties' => [0 => ['autres' => 2]]])->assertSessionHasNoErrors()->assertRedirect();
        $affaire = Affaire::with('parties')->firstOrFail();
        $this->assertSame(sprintf('RG-0001/%02d', now()->year % 100), $affaire->numero_complet);
        $this->assertSame(now()->toDateString(), $affaire->date_rg->toDateString());
        $this->assertCount(2, $affaire->parties);
        $this->assertSame('KOUASSI Yao Marc et 2 autres', $affaire->parties->first()->libelle());
        $this->assertSame('Société ABC SARL', $affaire->parties->last()->libelle());
        $this->assertSame('date', $affaire->parties->first()->naissance_mode);
        $this->assertSame('société abc sarl', mb_strtolower($affaire->parties->last()->denomination));
        $this->assertSame('societe abc sarl', $affaire->parties->last()->cle);
        $this->assertDatabaseHas('journal_audit', ['action' => 'affaire_enrolee']);

        $this->connecte($admin)->get(route('affaires.liste.fiche', $affaire->id))->assertOk()->assertSee($affaire->numeroAffichage())->assertSee('Société ABC SARL')->assertSee('Maître YAO')->assertSee('Né le 12 mai 1980');
        $this->connecte($admin)->get(route('affaires.liste'))->assertOk();
        $this->assertStringContainsString($affaire->numeroAffichage(), json_encode($this->connecte($admin)->getJson(route('affaires.liste.donnees', ['length' => 100]))->assertOk()->json('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));     // les lignes viennent du serveur, page par page
    }

    public function test_naissance_par_annee_seulement(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin, ['parties' => [0 => ['naissance_date' => '', 'naissance_annee' => '1970']]])->assertSessionHasNoErrors();
        $p = Partie::where('role', 'demandeur')->firstOrFail();

        $this->assertSame('annee', $p->naissance_mode);
        $this->assertSame('vers 1970', $p->naissance());
    }

    public function test_controles_du_formulaire(): void
    {
        $this->charger();
        $admin = User::factory()->create();

        // Pas de demandeur.
        $this->enroler($admin, ['parties' => [0 => ['role' => 'defendeur']]])->assertSessionHasErrors('parties');
        // Une personne physique sans nom, une personne morale sans dénomination.
        $this->enroler($admin, ['parties' => [0 => ['nom' => '']]])->assertSessionHasErrors('parties.0.nom');
        $this->enroler($admin, ['parties' => [1 => ['denomination' => '']]])->assertSessionHasErrors('parties.1.denomination');
        // Champs obligatoires, valeurs hors liste, date passée, naissance future.
        $this->enroler($admin, ['objet' => ''])->assertSessionHasErrors('objet');
        $this->enroler($admin, ['mode_saisine_id' => ModeSaisine::where('registre', 'penal')->firstOrFail()->id])->assertSessionHasErrors('mode_saisine_id');
        $this->enroler($admin, ['nature_affaire_id' => NatureAffaire::where('registre', 'RP')->firstOrFail()->id])->assertSessionHasErrors('nature_affaire_id');
        $this->enroler($admin, ['date_premiere_audience' => '2020-01-01'])->assertSessionHasErrors('date_premiere_audience');
        $this->enroler($admin, ['parties' => [0 => ['nature' => 'robot']]])->assertSessionHasErrors('parties.0.nature');
        $this->enroler($admin, ['parties' => [0 => ['naissance_date' => '2999-01-01']]])->assertSessionHasErrors('parties.0.naissance_date');

        $this->assertSame(0, Affaire::count());   // aucun numéro consommé par un enrôlement refusé
        $this->assertSame(0, \DB::table('compteurs_registre')->sum('dernier'));
    }

    public function test_un_nom_pieges_est_neutralise(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin, ['parties' => [0 => ['nom' => '<script>alert(1)</script>KOUASSI']]])->assertSessionHasNoErrors();

        $this->assertSame('alert(1)KOUASSI', Partie::where('role', 'demandeur')->firstOrFail()->nom);
    }

    public function test_propositions_par_mots_cles(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $objet = ObjetLitige::where('actif', true)->firstOrFail();

        // L'objet saisi librement (autre ordre, majuscules, pluriel) retrouve l'objet connu.
        $r = $this->connecte($admin)->postJson(route('affaires.enrolement.suggestions'), ['objet' => 'DEMANDE concernant '.mb_strtoupper($objet->libelle).' (urgent)'])->assertOk();
        $this->assertContains($objet->libelle, collect($r->json('propositions'))->pluck('libelle')->all());
        $this->assertSame($objet->nature_affaire_id, collect($r->json('propositions'))->firstWhere('libelle', $objet->libelle)['nature_id']);

        $this->connecte($admin)->postJson(route('affaires.enrolement.suggestions'), ['objet' => 'zzzz qqqq'])->assertOk()->assertJson(['propositions' => []]);
        $this->connecte($admin)->postJson(route('affaires.enrolement.suggestions'), ['objet' => ''])->assertStatus(422);
    }

    public function test_alerte_de_doublon(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin)->assertSessionHasNoErrors();

        // Mêmes parties (à la casse et aux accents près) : alerte, rien n'est enregistré.
        $meme = ['parties' => [0 => ['nom' => 'kouassi', 'prenoms' => 'YAO marc'], 1 => ['denomination' => 'SOCIETE ABC SARL']]];
        $this->enroler($admin, $meme)->assertSessionHas('doublons')->assertRedirect();
        $this->assertSame(1, Affaire::count());

        // Confirmé : enregistré sous un nouveau numéro.
        $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->formulaire($meme) + ['confirmer_doublon' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, Affaire::count());

        // Autres parties : pas d'alerte.
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'DIALLO']]])->assertSessionMissing('doublons');
        $this->assertSame(3, Affaire::count());
    }

    public function test_annulation_et_numero_non_reutilise(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'A']]]);
        $premiere = Affaire::firstOrFail();

        $this->connecte($admin)->post(route('affaires.liste.annuler', $premiere->id), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->connecte($admin)->post(route('affaires.liste.annuler', $premiere->id), ['motif' => 'Enrôlement en double'])->assertSessionHas('success');
        $premiere->refresh();
        $this->assertSame('annulee', $premiere->statut);
        $this->assertSame('Enrôlement en double', $premiere->motif_annulation);
        $this->assertDatabaseHas('journal_audit', ['action' => 'affaire_annulee']);
        $this->connecte($admin)->post(route('affaires.liste.annuler', $premiere->id), ['motif' => 'Encore'])->assertForbidden();

        // Le numéro suivant continue : l'annulé n'est pas réutilisé, et son affaire n'est plus un doublon.
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'A']]])->assertSessionHasNoErrors();
        $this->assertSame(sprintf('RG-0002/%02d', now()->year % 100), Affaire::orderByDesc('id')->first()->numero_complet);
    }

    public function test_reattribution_a_une_autre_chambre(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin);
        $affaire = Affaire::firstOrFail();
        $autre = Chambre::where('id', '!=', $affaire->chambre_id)->firstOrFail();

        $this->connecte($admin)->post(route('affaires.liste.reattribuer', $affaire->id), ['chambre_id' => $autre->id, 'motif' => ''])->assertSessionHasErrors('motif');
        $this->connecte($admin)->post(route('affaires.liste.reattribuer', $affaire->id), ['chambre_id' => $autre->id, 'motif' => 'Compétence de cette chambre'])->assertSessionHas('success');
        $this->assertSame($autre->id, $affaire->refresh()->chambre_id);
        $trace = AffaireAttribution::firstOrFail();
        $this->assertSame($autre->id, $trace->chambre_apres_id);
        $this->assertSame('Compétence de cette chambre', $trace->motif);
        $this->assertDatabaseHas('journal_audit', ['action' => 'affaire_reattribuee']);

        $this->connecte($admin)->get(route('affaires.liste.fiche', $affaire->id))->assertSee('Réattributions')->assertSee('Compétence de cette chambre');
        $this->connecte($admin)->post(route('affaires.liste.reattribuer', $affaire->id), ['chambre_id' => $autre->id, 'motif' => 'Déjà là'])->assertSessionHas('error');
    }

    public function test_droits(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin);
        $affaire = Affaire::firstOrFail();

        $aucun = $this->avecDroits([]);
        $this->connecte($aucun)->post(route('affaires.enrolement.store'), [])->assertForbidden();
        $this->connecte($aucun)->get(route('affaires.liste'))->assertForbidden();
        $this->connecte($aucun)->get(route('affaires.liste.fiche', $affaire->id))->assertForbidden();

        // Lecture : on voit, on n'enrôle pas, on n'annule pas, on ne réattribue pas.
        $lecteur = $this->avecDroits(['enrolement.voir', 'affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->get(route('affaires.liste'))->assertOk()->assertDontSee('Enrôler une affaire')->assertDontSee('Les demandeurs');
        $this->connecte($lecteur)->get(route('affaires.liste.fiche', $affaire->id))->assertOk()->assertDontSee('Annuler l\'affaire');
        $this->enroler($lecteur)->assertForbidden();
        $this->connecte($lecteur)->postJson(route('affaires.enrolement.suggestions'), ['objet' => 'loyers'])->assertForbidden();
        $this->connecte($lecteur)->post(route('affaires.liste.annuler', $affaire->id), ['motif' => 'Essai non autorisé'])->assertForbidden();
        $this->connecte($lecteur)->post(route('affaires.liste.reattribuer', $affaire->id), ['chambre_id' => 1, 'motif' => 'Essai non autorisé'])->assertForbidden();

        // Le droit d'annuler n'est pas celui de réattribuer (réservé à la Présidente).
        $gestionnaire = $this->avecDroits(['affaires.voir', 'affaires.annuler', 'affaires.toutes_chambres']);
        $this->connecte($gestionnaire)->get(route('affaires.liste.fiche', $affaire->id))->assertSee('Annuler l\'affaire', false)->assertDontSee('Réattribuer');
        $this->connecte($gestionnaire)->post(route('affaires.liste.reattribuer', $affaire->id), ['chambre_id' => 1, 'motif' => 'Essai non autorisé'])->assertForbidden();
        $presidente = $this->avecDroits(['affaires.voir', 'affaires.reattribuer', 'affaires.toutes_chambres']);
        $this->connecte($presidente)->get(route('affaires.liste.fiche', $affaire->id))->assertSee('Réattribuer');
    }

    /** Une chambre qui siège le jeudi ; on enrôle au plus tard le lundi à 16 h pour l'audience du jeudi suivant. */
    private function chambreAvecAudience(): Chambre
    {
        $annee = AnneeJudiciaire::where('courante', true)->firstOrFail();
        $c = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => \App\Models\Section::firstOrFail()->id, 'libelle' => '9e chambre test', 'ordre' => 97, 'actif' => true]);
        $f = Formation::create(['chambre_id' => $c->id, 'ordre' => 1, 'actif' => true]);
        Creneau::create(['formation_id' => $f->id, 'type' => 'Audience', 'jour' => 4, 'heure' => '09:00', 'frequence' => 'hebdomadaire']);
        DelaiEnrolement::updateOrCreate(['jour_audience' => 4], ['jour_limite' => 1, 'heure' => '16:00']);

        return $c;
    }

    public function test_date_limite_d_enrolement_et_derogation(): void
    {
        $this->charger();
        $chambre = $this->chambreAvecAudience();
        $admin = User::factory()->create();
        Carbon::setTestNow('2026-11-04 10:00:00');   // mercredi : la limite du jeudi 5 (lundi 2 à 16 h) est passée

        // Date limite dépassée : refusée, avec la prochaine audience possible.
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-05'])->assertSessionHasErrors('date_premiere_audience');
        $this->assertStringContainsString('jeudi 12 novembre 2026', session('errors')->first('date_premiere_audience'));
        $this->assertSame(0, Affaire::count());

        // Sans droit de dérogation, le motif ne suffit pas.
        $greffier = $this->avecDroits(['enrolement.voir', 'enrolement.creer']);
        $this->enroler($greffier, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-05', 'derogation_motif' => 'Urgence reconnue par la Présidente'])->assertSessionHasErrors('date_premiere_audience');
        $this->assertSame(0, Affaire::count());

        // La Présidente déroge avec un motif : l'affaire est enrôlée, la dérogation tracée.
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-05', 'derogation_motif' => 'Urgence reconnue par la Présidente'])->assertSessionHasNoErrors();
        $a = Affaire::firstOrFail();
        $this->assertSame('2026-11-05', $a->date_premiere_audience->toDateString());
        $this->assertSame('Urgence reconnue par la Présidente', $a->derogation_motif);
        $this->assertSame($admin->id, $a->derogation_par);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee('Urgence reconnue par la Présidente');

        Carbon::setTestNow();
    }

    public function test_heure_des_audiences(): void
    {
        $this->charger();
        $chambre = $this->chambreAvecAudience();
        $formation = $chambre->formations()->firstOrFail();
        // Même jour, deux créneaux (9 h puis 14 h 30), et un créneau sans heure précisée par l'ordonnance.
        Creneau::create(['formation_id' => $formation->id, 'type' => 'Audience', 'jour' => 4, 'heure' => '14:30', 'frequence' => 'hebdomadaire']);
        Creneau::create(['formation_id' => $formation->id, 'type' => 'Audience', 'jour' => 2, 'heure' => null, 'precision' => "Après l'audience de la formation A", 'frequence' => 'hebdomadaire']);
        $admin = User::factory()->create();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $dates = $this->connecte($admin)->postJson(route('affaires.enrolement.dates'), ['chambre_id' => $chambre->id])->assertOk()->json('dates');
        $jeudi = collect($dates)->where('date', '2026-11-12')->values();
        $this->assertSame(['09:00', '14:30'], $jeudi->pluck('heure')->all());
        $this->assertStringContainsString('à 09h00', $jeudi[0]['libelle']);
        $mardi = collect($dates)->firstWhere('date', '2026-11-10');
        $this->assertNull($mardi['heure']);
        $this->assertStringContainsString("après l'audience de la formation a", $mardi['libelle']);

        // L'heure choisie est enregistrée, affichée sur la fiche, et contrôlée.
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-12', 'heure_premiere_audience' => '14:30'])->assertSessionHasNoErrors();
        $a = Affaire::firstOrFail();
        $this->assertSame('14h30', $a->heureAudience());
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee('12/11/2026 à 14h30');
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-12', 'heure_premiere_audience' => '25:99', 'parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasErrors('heure_premiere_audience');
        // Sans date, pas d'heure.
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'heure_premiere_audience' => '09:00', 'date_premiere_audience' => '', 'parties' => [0 => ['nom' => 'TROIS']]]);

        Carbon::setTestNow();
    }

    public function test_mots_cles_penaux_et_d_instruction(): void
    {
        $this->charger();
        $chambre = fn (string $objet, array $registres) => collect(RapprochementObjet::proposer($objet, 5, $registres))->pluck('chambre')->all();

        // Délits : la chambre correctionnelle qui les juge ; mots de trois lettres compris (« vol »).
        foreach (['vol', 'menaces', 'coups', 'chèque sans provision', 'abandon de famille', 'destruction de biens', 'détournement de deniers', 'corruption', 'cambriolage'] as $mot) {
            $this->assertContains('1re chambre correctionnelle', $chambre($mot, ['RP']), $mot);
        }
        $this->assertContains('3e chambre correctionnelle', $chambre('ivresse au volant', ['RP']));
        $this->assertContains('3e chambre correctionnelle', $chambre('accident', ['RP']));
        $this->assertContains('2e chambre correctionnelle', $chambre('flagrant délit', ['RP']));
        // Crimes : tribunal criminel (sans nature d'affaire).
        foreach (['meurtre', 'assassinat', 'viol', 'braquage'] as $mot) {
            $this->assertContains('Tribunal criminel', $chambre($mot, ['RP']), $mot);
        }
        // Instruction : les deux cabinets sont proposés pour les adultes, le juge des enfants pour les mineurs.
        $this->assertEqualsCanonicalizing(["1er cabinet d'instruction", "2e cabinet d'instruction"], array_unique(array_filter($chambre('meurtre', ['RI', 'RE']), fn ($c) => str_contains($c, 'cabinet d'))));
        $this->assertContains('Cabinet du juge des enfants', $chambre('mineur', ['RI', 'RE']));
        // Un mot de liaison ne propose rien.
        $this->assertSame([], $chambre('sur par que', ['RP']));
    }

    public function test_dates_d_audience_du_penal_sans_date_limite(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $chambre = Chambre::where('libelle', '1re chambre correctionnelle')->firstOrFail();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $r = $this->connecte($admin)->postJson(route('affaires.enrolement.dates'), ['chambre_id' => $chambre->id, 'registre' => 'penal'])->assertOk();
        $this->assertTrue($r->json('sans_limite'));
        $this->assertSame('mardi', $r->json('jours'));
        $premiere = $r->json('dates.0');
        $this->assertSame('2026-11-10', $premiere['date']);
        $this->assertSame('10:00', $premiere['heure']);
        $this->assertNull($premiere['limite']);          // pas de date limite d'enrôlement au pénal
        $this->assertTrue($premiere['ouverte']);

        // Cabinet d'instruction : aucun jour d'audience, donc aucune proposition.
        $cabinet = Chambre::where('libelle', '1er cabinet d\'instruction')->firstOrFail();
        $this->connecte($admin)->postJson(route('affaires.enrolement.dates'), ['chambre_id' => $cabinet->id, 'registre' => 'penal'])->assertOk()->assertJson(['dates' => []]);
        $this->connecte($admin)->postJson(route('affaires.enrolement.dates'), ['chambre_id' => $chambre->id, 'registre' => 'inconnu'])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_date_proposee_jours_et_fermetures(): void
    {
        $this->charger();
        $chambre = $this->chambreAvecAudience();
        $admin = User::factory()->create();
        Carbon::setTestNow('2026-11-04 10:00:00');

        // Sans date : la plus proche audience encore ouverte est proposée (jeudi 12).
        $this->enroler($admin, ['chambre_id' => $chambre->id])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-12', Affaire::firstOrFail()->date_premiere_audience->toDateString());

        // Une date qui n'est pas un jour d'audience de la chambre.
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-13', 'parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasErrors('date_premiere_audience');
        // Un jour de fermeture : l'audience est sautée dans les propositions et refusée si on la choisit.
        Fermeture::create(['annee_judiciaire_id' => AnneeJudiciaire::where('courante', true)->value('id'), 'type' => 'fermeture', 'libelle' => 'Fermeture test', 'debut' => '2026-11-12', 'fin' => '2026-11-12']);
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'date_premiere_audience' => '2026-11-12', 'parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasErrors('date_premiere_audience');
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-19', Affaire::orderByDesc('id')->first()->date_premiere_audience->toDateString());

        // Les référés ne sont pas soumis à la date limite.
        $refere = NatureAffaire::where('exclue_enrolement', true)->where('registre', 'RG')->firstOrFail();
        $this->enroler($admin, ['chambre_id' => $chambre->id, 'nature_affaire_id' => $refere->id, 'date_premiere_audience' => '2026-11-05', 'parties' => [0 => ['nom' => 'REFERE']]])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-05', Affaire::orderByDesc('id')->first()->date_premiere_audience->toDateString());

        // La page propose les prochaines audiences.
        $r = $this->connecte($admin)->postJson(route('affaires.enrolement.dates'), ['chambre_id' => $chambre->id])->assertOk();
        $this->assertSame('jeudi', $r->json('jours'));
        $this->assertSame('2026-11-19', collect($r->json('dates'))->firstWhere('ouverte', true)['date']);

        Carbon::setTestNow();
    }

    public function test_correction_tracee_d_une_affaire(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin);
        $a = Affaire::with('parties')->firstOrFail();
        $numero = $a->numeroAffichage();

        // La correction est une fenêtre de la fiche, avec le numéro dans son titre et les valeurs actuelles dans le formulaire.
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertSee('id="modal-correction"', false)->assertSee('Correction : '.$numero)->assertSee('value="KOUASSI"', false)->assertSee('Enregistrer les corrections');

        $nouvelles = $this->formulaire(['objet' => 'Objet corrigé', 'parties' => [0 => ['nom' => 'KOUASSI-CORRIGE'], 1 => ['denomination' => 'Société XYZ']]]);
        unset($nouvelles['chambre_id']);
        $this->connecte($admin)->post(route('affaires.liste.modifier.enregistrer', $a->id), $nouvelles)->assertSessionHasNoErrors()->assertRedirect(route('affaires.liste.fiche', $a->id));
        $a->refresh()->load('parties');

        $this->assertSame('Objet corrigé', $a->objet);
        $this->assertSame($numero, $a->numeroAffichage());
        $this->assertSame('KOUASSI-CORRIGE', $a->parties->first()->nom);
        $this->assertDatabaseHas('journal_audit', ['action' => 'affaire_modifiee']);

        // Pas d'alerte de doublon contre soi-même ; droits ; annulée : plus de correction.
        $this->connecte($admin)->post(route('affaires.liste.modifier.enregistrer', $a->id), $nouvelles)->assertSessionMissing('doublons');
        $lecteur = $this->avecDroits(['affaires.voir', 'affaires.toutes_chambres']);
        $this->connecte($lecteur)->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertDontSee('modal-correction', false)->assertDontSee('Corriger');
        $this->connecte($lecteur)->post(route('affaires.liste.modifier.enregistrer', $a->id), $nouvelles)->assertForbidden();
        $this->connecte($admin)->post(route('affaires.liste.annuler', $a->id), ['motif' => 'Annulée pour le test']);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertDontSee('modal-correction', false);   // une affaire annulée ne se corrige plus
    }

    public function test_jonction_des_causes(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'A']]]);
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'B']]]);
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'C']]]);
        [$a, $b, $c] = Affaire::orderBy('id')->get()->all();

        $this->connecte($admin)->post(route('affaires.liste.joindre', $a->id), ['affaire_id' => $a->id])->assertSessionHasErrors('affaire_id');
        $this->connecte($admin)->post(route('affaires.liste.joindre', $a->id), ['affaire_id' => $b->id])->assertSessionHas('success');
        $this->assertSame($a->id, $b->refresh()->jointe_a_id);
        $this->assertSame(sprintf('RG-0002/%02d', now()->year % 100), $b->numero_complet);   // chacune garde son numéro
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee($b->numeroAffichage());
        $this->connecte($admin)->get(route('affaires.liste.fiche', $b->id))->assertSee('Disjoindre');

        // Une affaire déjà jointe, ou qui est principale, ne se joint pas ailleurs ; on joint à la principale.
        $this->connecte($admin)->post(route('affaires.liste.joindre', $c->id), ['affaire_id' => $b->id])->assertSessionHasErrors('affaire_id');
        $this->connecte($admin)->post(route('affaires.liste.joindre', $b->id), ['affaire_id' => $c->id])->assertSessionHasErrors('affaire_id');
        $this->connecte($admin)->post(route('affaires.liste.joindre', $c->id), ['affaire_id' => $a->id])->assertSessionHasErrors('affaire_id');
        $this->connecte($admin)->post(route('affaires.liste.joindre', $a->id), ['affaire_id' => $c->id])->assertSessionHas('success');

        // Une autre chambre : refusé.
        $autre = $this->chambreAvecAudience();
        $this->enroler($admin, ['chambre_id' => $autre->id, 'parties' => [0 => ['nom' => 'D']]]);
        $this->connecte($admin)->post(route('affaires.liste.joindre', $a->id), ['affaire_id' => Affaire::orderByDesc('id')->value('id')])->assertSessionHasErrors('affaire_id');

        // Disjonction, puis annulation de la principale : les jointes sont libérées.
        $this->connecte($admin)->post(route('affaires.liste.disjoindre', $b->id))->assertSessionHas('success');
        $this->assertNull($b->refresh()->jointe_a_id);
        $this->connecte($admin)->post(route('affaires.liste.disjoindre', $b->id))->assertForbidden();
        $this->connecte($admin)->post(route('affaires.liste.annuler', $a->id), ['motif' => 'Annulée pour le test']);
        $this->assertNull($c->refresh()->jointe_a_id);

        $this->connecte($this->avecDroits(['affaires.voir', 'affaires.toutes_chambres']))->post(route('affaires.liste.joindre', $a->id), ['affaire_id' => $b->id])->assertForbidden();
    }

    public function test_rediger_une_decision_a_partir_de_l_affaire(): void
    {
        $this->charger();
        $this->seed(ModelesDecisionSeeder::class);
        $admin = User::factory()->create();
        $this->enroler($admin, ['parties' => [0 => ['nom' => 'TRAORE', 'prenoms' => 'Salif', 'nationalite' => 'malienne', 'naissance_date' => '1970-03-01']]]);
        $a = Affaire::firstOrFail();
        $modele = ModeleDecision::firstOrFail();

        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee('Rédiger une décision');
        $this->connecte($admin)->post(route('affaires.liste.rediger', $a->id), ['modele_decision_id' => $modele->id])->assertRedirect();
        $d = Decision::firstOrFail();

        $this->assertSame($a->id, $d->affaire_id);
        $this->assertSame($a->chambre_id, $d->chambre_id);
        $this->assertSame($a->numeroAffichage(), $d->reference);
        $this->assertSame('TRAORE Salif', $d->valeurs['demandeur.nom']);
        $this->assertSame('m', $d->valeurs['demandeur.genre']);
        $this->assertSame('malienne', $d->valeurs['demandeur.nationalite']);
        $this->assertSame(now()->toDateString(), $d->valeurs['date_rg']);
        $this->assertSame(['mode' => 'date', 'date' => '1970-03-01'], $d->valeurs['demandeur.naissance']);
        $this->assertSame('1/'.sprintf('%02d', now()->year % 100), $d->valeursCompletes()['numero_rg']);   // numéro RG repris de l'affaire

        // Pas sans le droit de rédiger, ni pour une affaire annulée.
        $this->connecte($this->avecDroits(['affaires.voir']))->post(route('affaires.liste.rediger', $a->id), ['modele_decision_id' => $modele->id])->assertForbidden();
        $this->connecte($admin)->post(route('affaires.liste.annuler', $a->id), ['motif' => 'Annulée pour le test']);
        $this->connecte($admin)->post(route('affaires.liste.rediger', $a->id), ['modele_decision_id' => $modele->id])->assertForbidden();
    }

    /** Formulaire du mode « numéro saisi à la main » (Bingerville). */
    private function saisie(array $modifs = []): array
    {
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'manuel')]);

        return array_replace_recursive($this->formulaire() + ['numero_rg' => '1391/25', 'date_rg' => '2025-12-10'], $modifs);
    }

    public function test_numero_rg_saisi_a_la_main(): void
    {
        $this->charger();
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->saisie(['date_premiere_audience' => '2025-12-18']))->assertSessionHasNoErrors()->assertRedirect();
        $a = Affaire::firstOrFail();

        $this->assertSame(1391, $a->numero);
        $this->assertSame(2025, $a->annee);
        $this->assertSame('1391/25', $a->numeroAffichage());       // ce que les écrans montrent
        $this->assertSame('RG-1391/25', $a->numero_complet);       // le format du cahier des charges, gardé en base
        $this->assertTrue((bool) $a->numero_saisi);
        $this->assertSame('2025-12-10', $a->date_rg->toDateString());
        $this->assertSame('2025-12-18', $a->date_premiere_audience->toDateString());   // aucune date limite contrôlée : le greffe gère l'enrôlement
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee('1391/25')->assertDontSee('RG-1391');

        // Format et date du RG contrôlés.
        foreach (['1391', '1391/2025', 'abc/25', '/25', '1391-25'] as $mauvais) {
            $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->saisie(['numero_rg' => $mauvais, 'parties' => [0 => ['nom' => 'X'.$mauvais]]]))->assertSessionHasErrors('numero_rg');
        }
        $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->saisie(['numero_rg' => '1400/25', 'date_rg' => '2999-01-01']))->assertSessionHasErrors('date_rg');
        $this->assertSame(1, Affaire::count());
    }

    public function test_doublon_de_numero_bloque_sauf_jonction(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $poster = fn (array $m = []) => $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->saisie($m));
        $poster(['parties' => [0 => ['nom' => 'PREMIERE']]])->assertSessionHasNoErrors();
        $principale = Affaire::firstOrFail();

        // Même numéro sans jonction : doublon, bloqué (même avec d'autres parties).
        $r = $poster(['parties' => [0 => ['nom' => 'AUTRE']]])->assertSessionHasErrors('numero_rg');
        $this->assertStringContainsString('Doublon', session('errors')->first('numero_rg'));
        $this->assertStringContainsString('Jonction', session('errors')->first('numero_rg'));
        $this->assertSame(1, Affaire::count());

        // Interrupteur « Jonction » sur un numéro inconnu : refusé.
        $poster(['numero_rg' => '1500/25', 'jonction' => 1, 'parties' => [0 => ['nom' => 'SEULE']]])->assertSessionHasErrors('numero_rg');

        // Jonction : même numéro, rattachée à la principale, même si les parties se ressemblent (pas d'alerte de parties).
        $poster(['jonction' => 1, 'parties' => [0 => ['nom' => 'PREMIERE']]])->assertSessionHasNoErrors();
        $jointe = Affaire::orderByDesc('id')->first();
        $this->assertSame(1391, $jointe->numero);
        $this->assertSame($principale->id, $jointe->jointe_a_id);
        $this->assertTrue((bool) $jointe->meme_numero);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $jointe->id))->assertSee('même numéro RG')->assertSee('Disjoindre');
        $this->connecte($admin)->get(route('affaires.liste.fiche', $principale->id))->assertSee('Jonction des causes');

        // Une troisième se joint toujours à la principale.
        $poster(['jonction' => 1, 'parties' => [0 => ['nom' => 'TROISIEME']]])->assertSessionHasNoErrors();
        $this->assertSame($principale->id, Affaire::orderByDesc('id')->first()->jointe_a_id);

        // Une jonction se fait dans la même chambre.
        $autre = Chambre::where('id', '!=', $principale->chambre_id)->firstOrFail();
        $poster(['jonction' => 1, 'chambre_id' => $autre->id, 'parties' => [0 => ['nom' => 'AILLEURS']]])->assertSessionHasErrors('chambre_id');

        // Le numéro d'une affaire annulée n'est jamais repris.
        $poster(['numero_rg' => '1600/25', 'parties' => [0 => ['nom' => 'ANNULEE']]])->assertSessionHasNoErrors();
        $this->connecte($admin)->post(route('affaires.liste.annuler', Affaire::orderByDesc('id')->value('id')), ['motif' => 'Saisie par erreur']);
        $poster(['numero_rg' => '1600/25', 'parties' => [0 => ['nom' => 'AUTRE2']]])->assertSessionHasErrors('numero_rg');
        $poster(['numero_rg' => '1600/25', 'jonction' => 1, 'parties' => [0 => ['nom' => 'AUTRE3']]])->assertSessionHasErrors('numero_rg');
        $this->assertSame(4, Affaire::count());
    }

    public function test_verification_du_numero_pendant_la_saisie(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('affaires.enrolement.store'), $this->saisie(['parties' => [0 => ['nom' => 'PREMIERE']]]));

        $r = $this->connecte($admin)->postJson(route('affaires.enrolement.numero'), ['numero_rg' => '1391/25'])->assertOk();
        $this->assertTrue($r->json('existe'));
        $this->assertSame('1391/25', $r->json('affaires.0.numero'));
        $this->connecte($admin)->postJson(route('affaires.enrolement.numero'), ['numero_rg' => '1392/25'])->assertOk()->assertJson(['existe' => false]);
        $this->connecte($admin)->postJson(route('affaires.enrolement.numero'), ['numero_rg' => 'xx'])->assertStatus(422);
        $this->connecte($this->avecDroits(['enrolement.voir']))->postJson(route('affaires.enrolement.numero'), ['numero_rg' => '1391/25'])->assertForbidden();
    }

    public function test_vocabulaire_selon_le_mode(): void
    {
        $this->charger();
        $greffier = $this->avecDroits(['enrolement.voir', 'enrolement.creer', 'affaires.voir']);

        // Bingerville : le greffier de chambre ne voit jamais « enrôlement » ; il saisit les affaires du rôle.
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'manuel')]);
        $page = $this->connecte($greffier)->get(route('affaires.liste'))->assertOk();
        $page->assertSee('Saisir une affaire')->assertSee('Numéro RG')->assertSee('Jonction');
        $visible = preg_replace(['#<script.*?</script>#s', '#<!--.*?-->#s'], '', $page->getContent());   // le texte à l'écran, pas le code
        $this->assertStringNotContainsStringIgnoringCase('enrôl', $visible);

        // Un TPI qui enrôle dans l'application : le mot est de retour, le numéro est attribué.
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'automatique')]);
        $this->connecte($greffier)->get(route('affaires.liste'))->assertOk()->assertSee('Enrôler une affaire')->assertDontSee('name="numero_rg"', false);
    }

    public function test_le_ministere_public_est_une_partie(): void
    {
        $this->charger();
        $admin = User::factory()->create();
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'automatique')]);

        $this->enroler($admin, ['parties' => [1 => ['nature' => 'mp', 'denomination' => '']]])->assertSessionHasNoErrors();
        $this->assertSame('MP', Partie::where('role', 'defendeur')->firstOrFail()->libelle());
    }
}
