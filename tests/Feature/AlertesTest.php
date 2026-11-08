<?php

namespace Tests\Feature;

use App\Mail\RecapAlertes;
use App\Models\Affaire;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Alerte;
use App\Models\AlerteEnvoi;
use App\Models\AlerteEvaluation;
use App\Models\Chambre;
use App\Models\DelaiEtape;
use App\Models\Fermeture;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\RegleAlerte;
use App\Models\RoleAudience;
use App\Models\User;
use App\Support\Alertes\CollecteEcheances;
use App\Support\Alertes\MoteurAlertes;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Lot L : échéances du circuit, règles d'alerte, moteur (anti-doublon, rattrapage, escalade, justification), notifications. */
class AlertesTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 08:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        DelaiEtape::oublier();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    /** Relie un compte à l'agent qui occupe ce rôle dans la chambre (d'après l'ordonnance). */
    private function lierCompte(string $role, ?Chambre $chambre = null): User
    {
        $chambre ??= $this->chambre;
        $affectation = Affectation::where('role', $role)->where('suppleant', false)->whereIn('formation_id', $chambre->formations()->select('id'))->firstOrFail();
        $user = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Compte '.uniqid(), 'droits' => ['alertes.voir', 'alertes.justifier', 'affaires.voir']])->id, 'email' => uniqid().'@exemple.test']);
        $affectation->agent->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /** Un agent de la hiérarchie (fonction) avec son compte. */
    private function fonction(string $fonction): User
    {
        $user = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Compte '.uniqid(), 'droits' => ['alertes.voir']])->id, 'email' => uniqid().'@exemple.test']);
        Agent::create(['nom' => strtoupper($fonction).uniqid(), 'prenoms' => 'Test', 'fonction' => $fonction, 'actif' => true, 'user_id' => $user->id]);

        return $user;
    }

    private function affaire(string $audience, ?Chambre $chambre = null): Affaire
    {
        static $n = 0;
        $n++;
        $chambre ??= $this->chambre;
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 900 + $n, 'numero_complet' => 'RG-'.(900 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-09-20', 'objet' => 'Paiement de loyers '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Kone', 'prenoms' => 'Ibrahima '.$n, 'cle' => 'k'.$n]);

        return $affaire;
    }

    private function rolePublie(string $date, ?Chambre $chambre = null, string $statut = 'publie'): RoleAudience
    {
        $role = RoleAudience::create(['chambre_id' => ($chambre ?? $this->chambre)->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => $date]);
        GenerateurRole::completer($role);
        $role->forceFill(['statut' => $statut])->save();

        return $role->refresh();
    }

    /** Une affaire renvoyée à dans $jours jours (négatif : date déjà passée). @return array{0: Affaire, 1: RoleAudience} */
    private function renvoiDans(int $jours): array
    {
        $affaire = $this->affaire('2026-10-01');
        $role = $this->rolePublie('2026-10-01');
        $role->lignes()->where('affaire_id', $affaire->id)->update(['issue' => 'renvoi', 'renvoi_au' => today()->addDays($jours)->toDateString(), 'suite_le' => now()]);

        return [$affaire, $role];
    }

    private function moteur(): MoteurAlertes
    {
        return new MoteurAlertes;
    }

    private function notificationsDe(User $u, ?string $type = null)
    {
        return $u->notifications()->get()->filter(fn ($n) => $type === null || ($n->data['type'] ?? null) === $type)->values();
    }

    // ---------------------------------------------------------------- moteur

    public function test_une_alerte_part_au_greffier_et_au_president_le_jour_de_la_regle(): void
    {
        $greffier = $this->lierCompte('greffier');
        $president = $this->lierCompte('president');
        [$affaire] = $this->renvoiDans(3);   // J-3 : trois jours avant la date de renvoi

        $stats = $this->moteur()->evaluer(today());

        $this->assertGreaterThanOrEqual(2, $stats['envoyees']);
        foreach ([$greffier, $president] as $u) {
            $n = $this->notificationsDe($u, 'renvoi');
            $this->assertCount(1, $n, 'Une notification de renvoi pour chacun.');
            $this->assertSame('J-3', $n[0]->data['regle']);
            $this->assertSame('attention', $n[0]->data['niveau']);
            $this->assertStringContainsString($affaire->numeroAffichage(), $n[0]->data['titre']);
            $this->assertStringStartsWith('/', $n[0]->data['url'], 'Le lien est un chemin relatif.');
        }
        $alerte = Alerte::where('type', 'renvoi')->firstOrFail();
        $this->assertTrue($alerte->estOuverte());
        $this->assertSame($this->chambre->id, $alerte->chambre_id);

        // E-mail : un seul récapitulatif par personne, sans nom de partie.
        Mail::assertSent(RecapAlertes::class, 2);
        Mail::assertSent(RecapAlertes::class, fn ($m) => $m->hasTo($greffier->email) && ! str_contains(json_encode($m->alertes), 'Kone'));
    }

    public function test_une_alerte_ne_part_jamais_deux_fois(): void
    {
        $greffier = $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(3);

        $this->moteur()->evaluer(today());
        $deuxieme = $this->moteur()->evaluer(today());

        $this->assertCount(1, $this->notificationsDe($greffier, 'renvoi'));
        $this->assertGreaterThan(0, $deuxieme['deja_envoyees']);
        Mail::assertSent(RecapAlertes::class, 2);   // pas de second récapitulatif
    }

    public function test_les_jours_manques_sont_rattrapes(): void
    {
        $greffier = $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(1);                                     // J-3 était il y a 2 jours, J-1 est aujourd'hui
        AlerteEvaluation::create(['jour' => today()->subDays(3)->toDateString()]);   // le serveur est resté éteint trois jours

        $this->moteur()->rattraper(today());

        $codes = $this->notificationsDe($greffier, 'renvoi')->pluck('data.regle')->sort()->values()->all();
        $this->assertSame(['J-1', 'J-3'], $codes, 'Les jours manqués sont évalués, pas seulement aujourd\'hui.');
        $this->assertSame(today()->toDateString(), Carbon::parse(AlerteEvaluation::max('jour'))->toDateString());
    }

    public function test_l_alerte_se_referme_quand_l_action_est_faite(): void
    {
        $this->lierCompte('greffier');
        $this->lierCompte('president');
        [$affaire, $role] = $this->renvoiDans(3);
        $this->moteur()->evaluer(today());
        $this->assertTrue(Alerte::where('type', 'renvoi')->firstOrFail()->estOuverte());

        // L'affaire est jugée : plus de renvoi à échéance.
        $role->lignes()->where('affaire_id', $affaire->id)->update(['issue' => 'juge', 'renvoi_au' => null]);
        $stats = $this->moteur()->evaluer(today());

        $this->assertGreaterThanOrEqual(1, $stats['refermees']);
        $this->assertFalse(Alerte::where('type', 'renvoi')->firstOrFail()->estOuverte());
    }

    public function test_un_retard_justifie_suspend_les_relances(): void
    {
        $greffier = $this->lierCompte('greffier');
        $president = $this->lierCompte('president');
        $this->renvoiDans(3);
        $this->moteur()->evaluer(today());
        $alerte = Alerte::where('type', 'renvoi')->firstOrFail();

        // Motif trop court : refusé. Puis accepté.
        $this->connecte($greffier)->postJson(route('alertes.justifier', $alerte->id), ['motif' => 'court', 'jours' => 7])->assertStatus(422);
        $this->connecte($greffier)->postJson(route('alertes.justifier', $alerte->id), ['motif' => 'Pièce demandée à l\'avocat, en attente de réponse', 'jours' => 7])->assertOk()->assertJsonPath('alerte.statut', 'justifiee');
        $this->assertSame(today()->addDays(7)->toDateString(), $alerte->refresh()->justifiee_jusqu->toDateString());
        $this->assertDatabaseHas('journal_audit', ['action' => 'alerte_justifiee']);

        // Deux jours plus tard, la règle J-1 devrait se déclencher : elle est suspendue.
        $avant = $this->notificationsDe($greffier)->count();
        $stats = $this->moteur()->evaluer(today()->addDays(2));
        $this->assertGreaterThanOrEqual(1, $stats['justifiees']);
        $this->assertSame($avant, $this->notificationsDe($greffier)->count());

        // Dix jours plus tard (J+7 de l'échéance), la justification est expirée : la relance « retard persistant » repart, vers le président.
        $this->moteur()->evaluer(today()->addDays(10));
        $n = $this->notificationsDe($president, 'renvoi')->where('data.regle', 'J+7');
        $this->assertCount(1, $n);
        $this->assertSame('critique', $n->first()->data['niveau']);
    }

    public function test_le_retard_grandissant_monte_dans_la_hierarchie(): void
    {
        $greffier = $this->lierCompte('greffier');
        $president = $this->lierCompte('president');
        $presidente = $this->fonction('presidente');
        $enChef = $this->fonction('greffier_en_chef');
        $vice = $this->fonction('vice_president');
        $this->renvoiDans(-15);   // échéance dépassée de 15 jours : J+15 (retard grave)

        $this->moteur()->evaluer(today());

        foreach ([$presidente, $enChef, $vice] as $u) {
            $n = $this->notificationsDe($u, 'renvoi');
            $this->assertCount(1, $n);
            $this->assertSame('J+15', $n[0]->data['regle']);
            $this->assertSame('critique', $n[0]->data['niveau']);
        }
        $this->assertCount(0, $this->notificationsDe($greffier, 'renvoi'), 'J+15 ne vise plus le greffier : il a été prévenu avant.');
    }

    public function test_les_administrateurs_sont_prevenus_par_defaut_des_alertes_critiques(): void
    {
        $admin = User::factory()->create();   // profil Admin du système
        $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->fonction('presidente');
        $this->fonction('greffier_en_chef');
        $this->fonction('vice_president');

        // Les règles critiques installées nomment les administrateurs ; les rappels d'information ou d'attention ne les nomment pas.
        $this->assertSame(['J+15', 'J+30', 'J+7'], \App\Models\RegleAlerte::where('niveau', 'critique')->get()->filter(fn ($r) => in_array('administrateur', $r->destinataires, true))->pluck('code')->sort()->values()->all());
        $this->assertCount(0, \App\Models\RegleAlerte::where('niveau', '!=', 'critique')->get()->filter(fn ($r) => in_array('administrateur', $r->destinataires, true)));

        $this->renvoiDans(-15);   // J+15 : critique, les administrateurs sont aussi prévenus, sans la mention « sans destinataire »
        $this->moteur()->evaluer(today());
        $n = $this->notificationsDe($admin, 'renvoi');
        $this->assertCount(1, $n);
        $this->assertSame('J+15', $n[0]->data['regle']);
        $this->assertFalse((bool) ($n[0]->data['sans_destinataire'] ?? false));
    }

    public function test_un_rappel_d_attention_ne_va_pas_aux_administrateurs(): void
    {
        $admin = User::factory()->create();
        $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(3);   // J-3 : attention, le greffier et le président sont prévenus, pas l'administrateur

        $this->moteur()->evaluer(today());

        $this->assertCount(0, $this->notificationsDe($admin, 'renvoi'));
    }

    public function test_sans_destinataire_l_alerte_va_aux_administrateurs(): void
    {
        $admin = User::factory()->create();   // profil Admin du système
        $this->renvoiDans(3);                 // aucun agent de la chambre n'a de compte

        $stats = $this->moteur()->evaluer(today());

        $this->assertGreaterThanOrEqual(1, $stats['sans_destinataire']);
        $n = $this->notificationsDe($admin, 'renvoi');
        $this->assertCount(1, $n);
        $this->assertTrue($n[0]->data['sans_destinataire']);
        $this->assertDatabaseHas('alertes', ['type' => 'renvoi']);   // l'alerte existe et reste visible dans le suivi
    }

    public function test_une_seule_alerte_par_audience_pour_les_suites_non_notees(): void
    {
        $greffier = $this->lierCompte('greffier');
        $this->lierCompte('president');
        foreach ([1, 2, 3] as $i) {
            $this->affaire('2026-11-04');
        }
        $this->rolePublie('2026-11-04');   // audience passée, aucune suite notée sur ses trois affaires

        $echeances = CollecteEcheances::toutes(today())->where('type', 'feuille_a_saisir');
        $this->assertCount(1, $echeances, 'Une alerte par audience, pas une par affaire.');
        $this->assertStringContainsString('3 affaires sans suite notée', $echeances->first()->titre);
        $this->assertSame(DelaiEtape::echeance('saisie_suites', Carbon::parse('2026-11-04'))->toDateString(), $echeances->first()->date->toDateString());
    }

    public function test_une_requete_non_repartie_previent_le_secretariat_de_la_presidence(): void
    {
        $secretariat = $this->avecDroits(['requetes.voir', 'requetes.repartir']);
        $ailleurs = $this->avecDroits(['requetes.voir']);
        \App\Models\Requete::create(['annee' => 2026, 'numero' => 7, 'numero_complet' => 'RQ-0007/26', 'type' => 'heredite', 'date_depot' => '2026-11-08', 'objet' => "Jugement d'hérédité", 'requerants' => 'Kone', 'statut' => 'recue']);

        // Le délai de répartition est de 2 jours : l'échéance tombe le 10/11, J0 part ce jour-là.
        $this->moteur()->evaluer(today());

        $n = $this->notificationsDe($secretariat, 'repartition_requete');
        $this->assertCount(1, $n);
        $this->assertSame('J0', $n[0]->data['regle']);
        $this->assertStringContainsString('RQ-0007/26', $n[0]->data['titre']);
        $this->assertCount(0, $this->notificationsDe($ailleurs, 'repartition_requete'));
        $this->assertDatabaseHas('alertes', ['type' => 'repartition_requete', 'chambre_id' => null]);
    }

    public function test_la_simulation_n_enregistre_ni_n_envoie_rien(): void
    {
        $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(3);

        $stats = (new MoteurAlertes(true))->evaluer(today());

        $this->assertGreaterThanOrEqual(2, $stats['envoyees']);
        $this->assertSame(0, Alerte::count());
        $this->assertSame(0, AlerteEnvoi::count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('notifications')->count());
        $this->assertSame(0, AlerteEvaluation::count());
        Mail::assertNothingSent();
    }

    public function test_une_regle_inactive_ou_hors_type_n_envoie_rien(): void
    {
        $greffier = $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(3);
        RegleAlerte::where('code', 'J-3')->update(['actif' => false]);
        $this->moteur()->evaluer(today());
        $this->assertCount(0, $this->notificationsDe($greffier, 'renvoi'));

        RegleAlerte::where('code', 'J-3')->update(['actif' => true, 'types' => json_encode(['detention'])]);   // vise seulement la détention
        $this->moteur()->evaluer(today());
        $this->assertCount(0, $this->notificationsDe($greffier, 'renvoi'));
    }

    public function test_la_commande_evalue_et_se_simule(): void
    {
        $this->lierCompte('greffier');
        $this->renvoiDans(3);

        $this->artisan('alertes:evaluer', ['--simuler' => true])->assertExitCode(0);
        $this->assertSame(0, Alerte::count());
        $this->artisan('alertes:evaluer')->assertExitCode(0);
        $this->assertGreaterThan(0, Alerte::count());
        $this->artisan('alertes:evaluer', ['--date' => 'pas-une-date'])->assertExitCode(1);
        $this->artisan('alertes:evaluer', ['--simuler' => true, '--date' => '2026-11-20'])->assertExitCode(0);   // une date valide s'évalue
    }

    // ---------------------------------------------------------------- délais

    public function test_les_jours_ouvrables_ignorent_les_week_ends_et_les_fermetures(): void
    {
        // Le vendredi 13/11/2026 plus 1 jour ouvrable : le lundi 16/11.
        $this->assertSame('2026-11-16', DelaiEtape::ajouter(Carbon::parse('2026-11-13'), 1, 'jours_ouvrables')->toDateString());
        // Un jour de fermeture (le lundi 16/11) est sauté aussi.
        Fermeture::create(['debut' => '2026-11-16', 'fin' => '2026-11-16', 'type' => 'ferie', 'libelle' => 'Jour férié d\'essai', 'annee_judiciaire_id' => \App\Models\AnneeJudiciaire::value('id')]);
        DelaiEtape::oublier();
        $this->assertSame('2026-11-17', DelaiEtape::ajouter(Carbon::parse('2026-11-13'), 1, 'jours_ouvrables')->toDateString());
        // Vers l'arrière, et en mois.
        $this->assertSame('2026-11-12', DelaiEtape::ajouter(Carbon::parse('2026-11-16'), -2, 'jours_ouvrables')->toDateString());
        $this->assertSame('2027-05-30', DelaiEtape::ajouter(Carbon::parse('2026-11-30'), 6, 'mois')->toDateString());
    }

    // ---------------------------------------------------------------- pages

    public function test_la_cloche_les_notifications_et_leur_ouverture(): void
    {
        $greffier = $this->lierCompte('greffier');
        $autre = $this->lierCompte('president');
        $this->renvoiDans(3);
        $this->moteur()->evaluer(today());

        $this->connecte($greffier)->get(route('dashboard'))->assertOk()->assertSee('id="cloche"', false)->assertSee('cloche__nb', false);
        $this->connecte($greffier)->getJson(route('notifications.resume'))->assertOk()->assertJsonPath('non_lues', 1)->assertJsonCount(1, 'recentes');

        $page = $this->connecte($greffier)->get(route('notifications.index'))->assertOk()->assertSee('Mes notifications');
        $this->assertStringContainsString('J-3', $page->getContent());

        // Ouvrir : marque comme lue et renvoie vers l'écran concerné ; on ne peut pas ouvrir celle d'un autre.
        $notif = $greffier->notifications()->firstOrFail();
        $this->connecte($greffier)->get(route('notifications.ouvrir', $notif->id))->assertRedirect();
        $this->assertNotNull($notif->refresh()->read_at);
        $this->connecte($autre)->get(route('notifications.ouvrir', $notif->id))->assertNotFound();
        $this->connecte($autre)->postJson(route('notifications.lire', $notif->id))->assertNotFound();

        $this->connecte($autre)->postJson(route('notifications.tout_lire'))->assertOk();
        $this->assertSame(0, $autre->unreadNotifications()->count());
    }

    public function test_un_chemin_exterieur_n_est_jamais_suivi(): void
    {
        $greffier = $this->lierCompte('greffier');
        $greffier->notify(new \App\Notifications\AlerteNotification(
            Alerte::create(['cle' => 'x:1:2026-11-10', 'type' => 'renvoi', 'echeance' => '2026-11-10', 'titre' => 'T', 'action' => 'A', 'url' => 'https://pirate.example/vol']),
            RegleAlerte::firstOrFail(),
        ));
        $notif = $greffier->notifications()->firstOrFail();

        $reponse = $this->connecte($greffier)->get(route('notifications.ouvrir', $notif->id));
        $reponse->assertRedirect(route('notifications.index'));
    }

    public function test_le_suivi_des_alertes_respecte_les_droits_et_le_perimetre(): void
    {
        $this->lierCompte('greffier');
        $this->lierCompte('president');
        $this->renvoiDans(3);
        $this->moteur()->evaluer(today());

        $this->connecte($this->avecDroits(['roles.voir']))->get(route('alertes.suivi'))->assertForbidden();
        $this->connecte($this->avecDroits(['roles.voir']))->getJson(route('alertes.suivi.donnees'))->assertForbidden();

        $presidente = $this->avecDroits(['alertes.voir', 'affaires.toutes_chambres']);
        $this->connecte($presidente)->get(route('alertes.suivi'))->assertOk()->assertSee('Suivi des alertes')->assertSee('Moteur d\'alertes en marche', false);
        $r = $this->connecte($presidente)->getJson(route('alertes.suivi.donnees'))->assertOk();
        $this->assertGreaterThanOrEqual(1, $r->json('total'));
        $this->assertSame('renvoi', collect($r->json('lignes'))->firstWhere('type', 'renvoi')['type']);

        // Sans « toutes les chambres » ni chambre affectée : rien. Un filtre inconnu est ignoré, pas interprété.
        $limite = $this->avecDroits(['alertes.voir']);
        $this->connecte($limite)->getJson(route('alertes.suivi.donnees'))->assertOk()->assertJsonPath('total', 0);
        $this->connecte($presidente)->getJson(route('alertes.suivi.donnees', ['niveau' => "x' OR 1=1", 'type' => 'inconnu']))->assertOk();
        // On ne justifie pas l'alerte d'une chambre qu'on ne voit pas.
        $this->connecte($this->avecDroits(['alertes.voir', 'alertes.justifier']))->postJson(route('alertes.justifier', Alerte::firstOrFail()->id), ['motif' => 'Un motif suffisamment long', 'jours' => 7])->assertNotFound();
        // Sans le droit de justifier.
        $this->connecte($presidente)->postJson(route('alertes.justifier', Alerte::firstOrFail()->id), ['motif' => 'Un motif suffisamment long', 'jours' => 7])->assertForbidden();
    }

    public function test_le_suivi_signale_un_moteur_arrete(): void
    {
        AlerteEvaluation::create(['jour' => today()->subDays(4)->toDateString()]);
        $u = $this->avecDroits(['alertes.voir', 'affaires.toutes_chambres']);

        $this->connecte($u)->get(route('alertes.suivi'))->assertOk()->assertSee("n'a pas tourné depuis 4 jours", false);
    }

    public function test_les_regles_se_gerent_avec_leurs_droits(): void
    {
        $sans = $this->avecDroits(['alertes.voir']);
        $this->connecte($sans)->get(route('alertes.regles'))->assertForbidden();

        $gestion = $this->avecDroits(['regles_alerte.voir', 'regles_alerte.creer', 'regles_alerte.modifier', 'regles_alerte.annuler', 'affaires.toutes_chambres']);
        $this->connecte($gestion)->get(route('alertes.regles'))->assertOk()->assertSee("Règles d'alerte")->assertDontSee('Contrôle des destinataires');

        $donnees = ['decalage_jours' => -5, 'libelle' => 'Échéance dans cinq jours', 'niveau' => 'info', 'canaux' => ['application'], 'destinataires' => ['greffier_chambre'], 'types' => ['renvoi'], 'actif' => 1];
        $this->connecte($gestion)->postJson(route('alertes.regles.store'), $donnees)->assertOk();
        $regle = RegleAlerte::where('code', 'J-5')->firstOrFail();
        $this->assertSame(['renvoi'], $regle->types);
        $this->assertDatabaseHas('journal_audit', ['action' => 'regle_alerte_creee']);

        // Même moment : refusé. Valeurs hors liste : refusées. Modifier : accepté.
        $this->connecte($gestion)->postJson(route('alertes.regles.store'), $donnees)->assertStatus(422);
        $this->connecte($gestion)->postJson(route('alertes.regles.store'), array_merge($donnees, ['decalage_jours' => -6, 'canaux' => ['sms']]))->assertStatus(422);
        $this->connecte($gestion)->postJson(route('alertes.regles.store'), array_merge($donnees, ['decalage_jours' => -6, 'destinataires' => []]))->assertStatus(422);
        $this->connecte($gestion)->postJson(route('alertes.regles.update', $regle->id), array_merge($donnees, ['libelle' => 'Cinq jours avant', 'destinataires' => ['greffier_chambre', 'presidente']]))->assertOk();
        $this->assertSame(['greffier_chambre', 'presidente'], $regle->refresh()->destinataires);

        $this->connecte($gestion)->postJson(route('alertes.regles.activer', $regle->id))->assertOk()->assertJsonPath('actif', false);
        $this->connecte($gestion)->deleteJson(route('alertes.regles.delete', $regle->id))->assertOk();
        $this->assertDatabaseMissing('regles_alerte', ['id' => $regle->id]);

        // Un profil qui ne peut que voir ne modifie rien.
        $lecteur = $this->avecDroits(['regles_alerte.voir']);
        $this->connecte($lecteur)->postJson(route('alertes.regles.store'), $donnees)->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('alertes.regles.delete', RegleAlerte::firstOrFail()->id))->assertForbidden();
    }

    public function test_le_controle_des_destinataires_montre_les_agents_sans_compte(): void
    {
        $this->lierCompte('greffier');   // un greffier avec compte, le président sans
        $u = $this->avecDroits(['regles_alerte.voir', 'affaires.toutes_chambres']);

        $this->connecte($this->avecDroits(['alertes.voir']))->get(route('alertes.destinataires'))->assertForbidden();
        $page = $this->connecte($u)->get(route('alertes.destinataires'))->assertOk()->assertSee('Destinataires des alertes')->getContent();
        $this->assertStringContainsString('avec compte', $page);
        $this->assertStringContainsString('sans compte', $page);
    }

    public function test_les_delais_par_etape_se_modifient(): void
    {
        $this->connecte($this->avecDroits(['delais_etape.voir']))->get(route('alertes.delais'))->assertOk()->assertSee('Rédaction et validation de la décision')->assertDontSee('Délais de procédure');
        $delai = DelaiEtape::where('code', 'redaction_decision')->firstOrFail();

        $this->connecte($this->avecDroits(['delais_etape.voir']))->postJson(route('alertes.delais.update', $delai->id), ['valeur' => 10, 'unite' => 'jours'])->assertForbidden();
        $gestion = $this->avecDroits(['delais_etape.voir', 'delais_etape.modifier']);
        $this->connecte($gestion)->postJson(route('alertes.delais.update', $delai->id), ['valeur' => 400, 'unite' => 'jours'])->assertStatus(422);
        $this->connecte($gestion)->postJson(route('alertes.delais.update', $delai->id), ['valeur' => 10, 'unite' => 'siecles'])->assertStatus(422);
        $this->connecte($gestion)->postJson(route('alertes.delais.update', $delai->id), ['valeur' => 10, 'unite' => 'jours_ouvrables'])->assertOk()->assertJsonPath('libelle_delai', '10 jours ouvrables');

        $this->assertSame('jours_ouvrables', DelaiEtape::de('redaction_decision')->unite);
        $this->assertDatabaseHas('journal_audit', ['action' => 'delai_etape_modifie']);
    }
}
