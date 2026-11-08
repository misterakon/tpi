<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\Agent;
use App\Models\Avocat;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Delivrance;
use App\Models\ModeleDecision;
use App\Models\Partie;
use App\Models\Requete;
use App\Models\User;
use App\Services\NumeroDecision;
use App\Services\NumerotationRegistre;
use App\Support\Recours\DelaisRecours;
use App\Support\Redaction\ChampsDecision;
use App\Support\Redaction\Preremplissage;
use App\Support\Redaction\RenduDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 | Prépare TOUTES les données du test pratique complet (docs/TEST_PRATIQUE_COMPLET.md et docs/DONNEES_TEST_COMPLET.md) :
 |
 |   php artisan tpi:formation             efface les données d'essai existantes, puis recrée le jeu complet
 |   php artisan tpi:formation --retirer   efface seulement les données d'essai
 |   php artisan tpi:formation --sans-stats  n'ajoute pas les ~500 affaires fictives des statistiques
 |   php artisan tpi:formation --force     sans demande de confirmation
 |
 | Ce qui est effacé : les affaires d'essai (ancien numéro « DEMO » ou « DEMO-STAT ») et tout ce qui s'y rattache (rôles, décisions,
 | recours, certificats, mises en état, décisions pénales, instruction, scellés, pièces), les requêtes, séances, demandes d'assistance et
 | avocats marqués d'essai, les alertes (elles se recalculent) et les notifications des comptes de test.
 | Ce qui n'est JAMAIS touché : l'organisation (ordonnance), les référentiels, les modèles et blocs, les réglages, les profils et comptes,
 | les journaux. La commande refuse de s'exécuter s'il existe une affaire qui n'est pas d'essai (donnée réelle).
 */
class FormationDonnees extends Command
{
    protected $signature = 'tpi:formation {--retirer : Efface seulement les données d\'essai} {--sans-stats : N\'ajoute pas les affaires des statistiques} {--force : Sans confirmation}';

    protected $description = "Efface les données d'essai puis prépare le jeu complet du test pratique (jamais les données réelles)";

    private const MARQUE = 'Données de formation';

    private int $auteur = 0;

    public function handle(): int
    {
        $reelles = Affaire::where(fn ($q) => $q->whereNull('ancien_numero')->orWhere('ancien_numero', 'not like', 'DEMO%'))->count();
        if ($reelles > 0) {
            $this->error("$reelles affaire(s) ne sont pas des affaires d'essai : par prudence, rien n'est effacé. Retirez-les ou marquez-les d'abord.");

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Effacer toutes les données d'essai (affaires, rôles, décisions, requêtes…) et recréer le jeu de formation ?")) {
            return self::SUCCESS;
        }

        $this->reinitialiser();
        if ($this->option('retirer')) {
            $this->info("Données d'essai effacées.");

            return self::SUCCESS;
        }

        $this->auteur = (int) (User::where('email', 'not like', '%@tpi.test')->value('id') ?? User::value('id'));
        Artisan::call('affaires:demo');
        $this->line(trim(Artisan::output()));
        if (! $this->option('sans-stats')) {
            Artisan::call('statistiques:demo');
            $this->line(trim(Artisan::output()));
        }

        $this->avocats();
        $this->requetes();
        $this->misesEnEtat();
        $this->seances();
        $this->assistance();
        $this->detenus();
        $this->instruction();
        $this->decisions();

        $this->newLine();
        $this->info('Jeu de formation prêt. Lire docs/DONNEES_TEST_COMPLET.md pour savoir quelle donnée utiliser à chaque étape.');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ effacement */

    private function reinitialiser(): void
    {
        $ids = DB::table('affaires')->where('ancien_numero', 'like', 'DEMO%')->pluck('id');

        // Décisions : celles des affaires d'essai et les brouillons sans affaire, avec tout ce qui en dépend.
        $decisions = DB::table('decisions')->where(fn ($q) => $q->whereIn('affaire_id', $ids)->orWhereNull('affaire_id'))->pluck('id');
        DB::table('delivrances')->whereIn('decision_id', $decisions)->delete();
        DB::table('certificats_recours')->whereIn('decision_id', $decisions)->delete();
        DB::table('suivis_recours')->whereIn('decision_id', $decisions)->delete();
        DB::table('recours')->where(fn ($q) => $q->whereIn('decision_id', $decisions)->orWhereIn('affaire_id', $ids))->delete();
        DB::table('decisions')->whereIn('id', $decisions)->delete();

        DB::table('mises_en_etat')->whereIn('affaire_id', $ids)->delete();
        DB::table('decisions_penales')->whereIn('affaire_id', $ids)->delete();
        DB::table('dossiers_instruction')->whereIn('affaire_id', $ids)->delete();
        DB::table('seance_dossiers')->whereIn('affaire_id', $ids)->delete();
        $parties = DB::table('parties')->whereIn('affaire_id', $ids)->pluck('id');
        DB::table('suivis_inculpes')->whereIn('partie_id', $parties)->delete();
        DB::table('suivis_victimes')->whereIn('partie_id', $parties)->delete();

        // Données marquées « essai » sans affaire.
        $seances = DB::table('seances_hors_audience')->where(fn ($q) => $q->where('observations', self::MARQUE)->orWhere('lieu', 'like', '%(essai)%'))->pluck('id');
        DB::table('seance_dossiers')->whereIn('seance_id', $seances)->delete();
        DB::table('seances_hors_audience')->whereIn('id', $seances)->delete();
        DB::table('assistance_judiciaire')->where('observation', self::MARQUE)->delete();
        DB::table('requetes')->where('objet', 'like', '%(essai)')->delete();
        Avocat::where('email', 'like', '%@formation.test')->delete();

        // Alertes : elles se recalculent à chaque évaluation ; notifications des comptes de test.
        DB::table('alertes_envois')->delete();
        DB::table('alertes')->delete();
        DB::table('alertes_evaluations')->delete();
        DB::table('notifications')->whereIn('notifiable_id', User::where('email', 'like', '%@tpi.test')->pluck('id'))->delete();

        // Les affaires d'essai et ce que leur commande retire d'elle-même (rôles, pièces, scellés, parties…).
        Artisan::call('statistiques:demo', ['--retirer' => true]);
        Artisan::call('affaires:demo', ['--retirer' => true]);

        // Rôles d'essai vides ou restants sans aucune ligne, avec leurs versions.
        $vides = DB::table('roles_audience')->whereNotIn('id', DB::table('role_lignes')->select('role_id'))->pluck('id');
        DB::table('role_versions')->whereIn('role_id', $vides)->delete();
        DB::table('roles_audience')->whereIn('id', $vides)->delete();

        // Compteurs : repartent de zéro seulement s'il ne reste rien qui les utilise.
        if (! DB::table('requetes')->exists()) {
            DB::table('compteurs_registre')->where('serie', 'RQ')->delete();
        }
        if (! DB::table('decisions')->whereNotNull('numero')->exists()) {
            DB::table('numeros_decision')->delete();
        }
        if (! DB::table('certificats_recours')->exists()) {
            DB::table('numeros_certificat')->delete();
        }
    }

    /* ------------------------------------------------------------------ avocats, requêtes, mise en état, séances, assistance */

    private function avocats(): void
    {
        $liste = [
            ['KOUASSI', 'Brou Marc', 'Cabinet Kouassi et Associés', 'BA-1021'], ['TOURE', 'Salimata', 'Cabinet Touré', 'BA-1187'], ['YAO', 'Aminata', 'Étude Yao', 'BA-0954'],
            ['N\'GUESSAN', 'Kouadio Pierre', 'Cabinet N\'Guessan et Fils', 'BA-1302'], ['COULIBALY', 'Mamadou', 'Cabinet Coulibaly', 'BA-1410'], ['ADJOUA', 'Marie-Claire', 'Cabinet Adjoua Conseil', 'BA-0877'],
            ['BAMBA', 'Seydou', 'SCPA Bamba et Diallo', 'BA-1255'], ['KONAN', 'Béatrice', 'Cabinet Konan', 'BA-1366'],
        ];
        foreach ($liste as [$nom, $prenoms, $cabinet, $barreau]) {
            Avocat::create(['type' => 'avocat', 'nom' => $nom, 'prenoms' => $prenoms, 'cabinet' => $cabinet, 'barreau_numero' => $barreau, 'telephone' => '07 '.rand(10, 99).' '.rand(10, 99).' '.rand(10, 99).' '.rand(10, 99),
                'email' => strtolower(preg_replace('/[^A-Za-z]/', '', $nom)).'@formation.test', 'cle' => Avocat::cle("$nom $prenoms"), 'actif' => true, 'a_completer' => false]);
        }
        Avocat::create(['type' => 'cabinet', 'nom' => 'CABINET DIALLO ET ASSOCIÉS', 'cabinet' => 'CABINET DIALLO ET ASSOCIÉS', 'email' => 'diallo@formation.test', 'cle' => Avocat::cle('CABINET DIALLO ET ASSOCIÉS'), 'actif' => true, 'a_completer' => true]);
        Avocat::create(['type' => 'avocat', 'nom' => 'ANCIEN', 'prenoms' => 'Jean', 'cabinet' => 'Étude fermée', 'email' => 'ancien@formation.test', 'cle' => Avocat::cle('ANCIEN Jean'), 'actif' => false, 'a_completer' => false]);
        $this->line('Avocats : 10 (dont 1 à compléter et 1 inactif).');
    }

    private function chambre(string $libelle): Chambre
    {
        return Chambre::where('libelle', $libelle)->firstOrFail();
    }

    private function agent(array $fonctions, ?Chambre $chambre = null): ?Agent
    {
        if ($chambre) {
            $ids = DB::table('affectations')->whereIn('formation_id', DB::table('formations')->where('chambre_id', $chambre->id)->select('id'))->pluck('agent_id');
            $a = Agent::whereIn('id', $ids)->whereIn('fonction', $fonctions)->where('actif', true)->first();
            if ($a) {
                return $a;
            }
        }

        return Agent::whereIn('fonction', $fonctions)->where('actif', true)->first();
    }

    private function requetes(): void
    {
        $civ = $this->chambre('1re chambre civile');
        $civ2 = $this->chambre('2e chambre civile');
        $annee = (int) date('Y');
        $nouvelle = function (string $type, int $joursDepuis, string $objet, string $requerants, array $plus = []) use ($annee) {
            $n = NumerotationRegistre::attribuer('RQ', $annee);

            return Requete::create(array_merge([
                'annee' => $n['annee'], 'numero' => $n['numero'], 'numero_complet' => $n['libelle'], 'type' => $type, 'date_depot' => today()->subDays($joursDepuis)->toDateString(),
                'objet' => $objet.' (essai)', 'requerants' => $requerants, 'statut' => 'recue', 'cree_par' => $this->auteur,
            ], $plus));
        };

        $nouvelle('heredite', 6, 'Requête en hérédité : succession de feu KOFFI Yao', "KOFFI Adjoua\nKOFFI Brou", ['defunt_nom' => 'KOFFI Yao', 'defunt_date_deces' => today()->subDays(40)->toDateString(), 'defunt_lieu_deces' => 'Bingerville']);
        $nouvelle('injonction', 1, "Injonction de payer : 2 400 000 F CFA de loyers", 'Société IMMO-SUD SARL');
        $nouvelle('ordonnance_requete', 4, 'Ordonnance sur requête : constat d\'huissier avant procès', 'DIARRASSOUBA Fatou', [
            'statut' => 'repartie', 'chambre_id' => $civ->id, 'reparti_par' => $this->auteur, 'reparti_le' => now()->subDays(3), 'note_repartition' => 'À examiner cette semaine']);
        $nouvelle('heredite', 12, 'Requête en hérédité : succession de feu TANO Michel', "TANO Aya\nTANO Michel junior", [
            'statut' => 'repartie', 'chambre_id' => $civ2->id, 'reparti_par' => $this->auteur, 'reparti_le' => now()->subDays(9), 'defunt_nom' => 'TANO Michel', 'defunt_date_deces' => today()->subDays(90)->toDateString(), 'defunt_lieu_deces' => 'Abidjan']);
        $nouvelle('heredite', 30, 'Requête en hérédité : succession de feu ZADI Paul', 'ZADI Marthe', [
            'statut' => 'traitee', 'chambre_id' => $civ->id, 'reparti_par' => $this->auteur, 'reparti_le' => now()->subDays(25), 'traitee_le' => today()->subDays(10)->toDateString(), 'reference_decision' => 'Jugement d\'hérédité (essai)',
            'defunt_nom' => 'ZADI Paul', 'defunt_date_deces' => today()->subDays(120)->toDateString(), 'defunt_lieu_deces' => 'Bingerville']);
        $nouvelle('autre', 20, 'Requête diverse : demande de délai de grâce', 'OUATTARA Issa', ['statut' => 'classee', 'reference_decision' => 'Classée sans suite : requérant absent']);
        $this->line('Requêtes : 8 en tout, dont 2 de la commande des affaires d’essai (1 hérédité à répartir, 1 répartie) et 6 de formation (1 en retard à répartir, 1 à répartir récente, 2 réparties, 1 traitée, 1 classée).');
    }

    private function misesEnEtat(): void
    {
        $affaires = Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->where('statut', 'enrolee')->whereNull('jointe_a_id')->orderBy('id')->get()->unique('numero_complet')->values();
        $juge = fn (Affaire $a) => $this->agent(['juge', 'vice_president', 'presidente'], $a->chambre)?->id;
        $ligne = function (Affaire $a, array $v) use ($juge) {
            DB::table('mises_en_etat')->insert($v + ['affaire_id' => $a->id, 'juge_id' => $juge($a), 'cree_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now(), 'observations' => self::MARQUE]);
        };
        $pris = collect(['RG-1391/26', 'RG-1402/26', 'RG-1541/26', 'RG-1532/26'])->map(fn ($n) => $affaires->firstWhere('numero_complet', $n))->filter()->values();
        if ($pris->count() < 4) {
            $this->warn('Pas assez d\'affaires civiles pour la mise en état.');

            return;
        }
        $ligne($pris[0], ['date_reception' => today()->subDays(130)->toDateString(), 'date_premiere_audience' => today()->subDays(120)->toDateString(), 'statut' => 'en_cours']);
        $ligne($pris[1], ['date_reception' => today()->subDays(10)->toDateString(), 'statut' => 'en_cours']);
        $ligne($pris[2], ['date_reception' => today()->subDays(100)->toDateString(), 'date_premiere_audience' => today()->subDays(90)->toDateString(), 'date_cloture' => today()->subDays(20)->toDateString(),
            'numero_ordonnance_cloture' => 'OC-12/'.date('y'), 'chambre_jugement_id' => $pris[2]->chambre_id, 'date_audience_jugement' => today()->addDays(10)->toDateString(), 'statut' => 'cloturee', 'versee_le' => now()->subDays(20)]);
        $ligne($pris[3], ['date_reception' => today()->subDays(60)->toDateString(), 'statut' => 'retiree', 'motif_retrait' => 'Parties parvenues à un accord : retour au rôle ordinaire']);
        $this->line('Mises en état : 4 (1 longue, 1 récente, 1 clôturée, 1 retirée) sur '.$pris->map->numeroAffichage()->implode(', ').'.');
    }

    private function seances(): void
    {
        $civ = $this->chambre('1re chambre civile');
        $juge = $this->agent(['juge', 'vice_president', 'presidente'], $civ)?->id;
        $greffier = $this->agent(['greffier', 'greffier_en_chef', 'secretaire'], $civ)?->id;
        $base = ['chambre_id' => $civ->id, 'juge_id' => $juge, 'greffier_id' => $greffier, 'observations' => self::MARQUE, 'cree_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now()];
        DB::table('seances_hors_audience')->insert($base + ['type' => 'reception_requetes', 'date_seance' => today()->addDays(6)->toDateString(), 'heure' => '09:00', 'lieu' => 'Cabinet du juge', 'statut' => 'prevue']);
        DB::table('seances_hors_audience')->insert($base + ['type' => 'tutelle', 'date_seance' => today()->addDays(13)->toDateString(), 'heure' => '10:30', 'lieu' => 'Salle de tutelle', 'statut' => 'prevue']);
        $tenue = DB::table('seances_hors_audience')->insertGetId($base + ['type' => 'conciliation', 'date_seance' => today()->subDays(7)->toDateString(), 'heure' => '09:30', 'lieu' => 'Cabinet du juge', 'statut' => 'tenue']);
        DB::table('seances_hors_audience')->insert($base + ['type' => 'reception_requetes', 'date_seance' => today()->subDays(3)->toDateString(), 'heure' => '08:30', 'lieu' => 'Cabinet du juge', 'statut' => 'annulee']);
        $a = Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->orderBy('id')->first();
        foreach ([['objet' => 'Conciliation sur le paiement de loyers', 'issue' => 'Conciliation réussie'], ['objet' => 'Tentative de conciliation : bornage', 'issue' => 'Échec, renvoi à l\'audience']] as $i => $d) {
            DB::table('seance_dossiers')->insert($d + ['seance_id' => $tenue, 'affaire_id' => $i === 0 ? $a?->id : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->line('Séances hors audience : 4 (2 à venir, 1 tenue avec 2 dossiers, 1 annulée).');
    }

    private function assistance(): void
    {
        $annee = date('y');
        $lignes = [
            ['AJ 1/'.$annee, 90, 'DEMO KONAN Awa', 'Pension alimentaire', 'Civile', 70, 'accord', 60],
            ['AJ 2/'.$annee, 75, 'DEMO TRAORE Ibrahim', 'Contestation de licenciement', 'Sociale', 55, 'rejet', 50],
            ['AJ 3/'.$annee, 40, 'DEMO BAKAYOKO Rokia', 'Divorce', 'Civile', 25, 'accord', 20],
            ['AJ 4/'.$annee, 15, 'DEMO ZOKORA Hervé', 'Défense au pénal', 'Pénale', null, null, null],
            ['AJ 5/'.$annee, 8, 'DEMO ASSI Prisca', 'Recouvrement', 'Civile', null, null, null],
            ['AJ 6/'.$annee, 100, 'DEMO GBANE Moussa', 'Expulsion', 'Civile', 80, 'rejet', 70],
        ];
        foreach ($lignes as [$num, $jours, $demandeur, $objet, $nature, $audience, $decision, $jDecision]) {
            DB::table('assistance_judiciaire')->insert([
                'numero' => $num, 'date_rg' => today()->subDays($jours)->toDateString(), 'demandeur' => $demandeur, 'objet' => $objet, 'nature' => $nature,
                'date_audience' => $audience ? today()->subDays($audience)->toDateString() : null, 'decision' => $decision, 'numero_decision' => $decision ? 'AJ-D'.$num : null,
                'date_decision' => $jDecision ? today()->subDays($jDecision)->toDateString() : null,
                'date_recours' => $decision === 'rejet' && $jours === 100 ? today()->subDays(55)->toDateString() : null,
                'observation' => self::MARQUE, 'cree_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->line('Assistance judiciaire : 6 demandes (2 accords, 2 rejets dont 1 avec recours, 2 à traiter).');
    }

    /* ------------------------------------------------------------------ détenus, instruction */

    private function detenus(): void
    {
        $prevenus = Partie::whereHas('affaire', fn ($q) => $q->where('ancien_numero', 'DEMO')->where('serie', 'RP')->where('statut', '!=', 'annulee'))->whereIn('role', ['prevenu', 'inculpe'])->orderBy('id')->take(4)->get();
        $jours = [200, 172, 100, 20];   // dépassée, proche, normale, récente (durée du mandat : 180 jours par défaut)
        foreach ($prevenus as $i => $p) {
            $p->forceFill(['mandat_depot_date' => today()->subDays($jours[$i])->toDateString(), 'libere_le' => null, 'lieu_detention' => 'MAMA d\'Abidjan (formation)'])->save();
        }
        $this->line('Détenus : '.$prevenus->count().' prévenus sous mandat de dépôt (1 échéance dépassée, 1 proche, 2 normales).');
    }

    private function instruction(): void
    {
        $affaires = Affaire::where('ancien_numero', 'DEMO')->whereIn('serie', ['RI', 'RE'])->where('statut', 'enrolee')->orderBy('id')->take(3)->get();
        foreach ($affaires as $i => $a) {
            DB::table('dossiers_instruction')->insert([
                'affaire_id' => $a->id, 'date_requisitoire' => today()->subDays(80 - $i * 15)->toDateString(), 'date_faits' => today()->subDays(120)->toDateString(), 'lieu_faits' => 'Bingerville',
                'nature_infraction' => $i === 1 ? 'crime' : 'delit', 'infraction_specifique' => $i === 1 ? 'Vol aggravé' : 'Coups et blessures volontaires', 'observations' => self::MARQUE,
                'date_cloture' => $i === 1 ? today()->subDays(10)->toDateString() : null, 'nature_cloture' => $i === 1 ? 'Renvoi devant le tribunal correctionnel' : null,
                'cree_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->line('Instruction : '.$affaires->count().' dossiers avec réquisitoire ('.$affaires->map->numeroAffichage()->implode(', ').'), dont 1 clôturé (le 2e).');
    }

    /* ------------------------------------------------------------------ décisions, recours, certificats */

    /** Valeurs complètes d'un jugement d'hérédité (le seul modèle installé), pour une affaire et une date de décision. */
    private function valeurs(Affaire $a, Carbon $dateDecision, int $n): array
    {
        $partie = $a->parties()->orderBy('id')->first();

        return [
            'date_decision' => $dateDecision->toDateString(), 'date_audience' => $dateDecision->toDateString(), 'matiere' => 'civile d\'état des personnes', 'type_audience' => 'ordinaire',
            'demandeur.nom' => $partie?->nom ?: 'KOFFI Adjoua', 'demandeur.genre' => 'f', 'demandeur.naissance' => ['mode' => 'date', 'date' => '1982-03-14'], 'demandeur.lieu_naissance' => 'Bingerville',
            'demandeur.nationalite' => 'ivoirienne', 'demandeur.domicile' => 'Bingerville, quartier Cité', 'demandeur.comparution' => 'en personne',
            'acte_deces.centre' => 'Bingerville', 'acte_deces.date' => $dateDecision->copy()->subDays(120)->toDateString(), 'acte_deces.numero' => 'DRP'.(1000 + $n),
            'acte_mariage.date' => '2008-06-21', 'acte_mariage.numero' => (500 + $n).'-08', 'articles_vises' => '731 et suivants du code civil (essai)',
            'date_conclusions_mp' => $dateDecision->copy()->subDays(5)->toDateString(), 'date_deces' => $dateDecision->copy()->subDays(130)->toDateString(), 'date_requete' => $dateDecision->copy()->subDays(40)->toDateString(),
            'defunt.genre' => 'm', 'defunt.nom' => 'TANO Michel '.$n, 'domicile_defunt' => 'Bingerville', 'enfants' => ['TANO Aya', 'TANO Michel junior'], 'lieu_deces' => 'Clinique Mamie',
            'nombre_enfants' => 2, 'nombre_temoins' => 2, 'regime_matrimonial' => 'la communauté de biens', 'temoins' => ['DJOMAN Patrick', 'KOUAKOU Estelle'],
        ];
    }

    /**
     * Fait avancer une décision jusqu'au statut demandé (brouillon, redigee, validee, signee, delivree), comme le ferait le circuit de l'application.
     *
     * @param  int  $joursSignature  nombre de jours écoulés depuis la signature (0 = aujourd'hui)
     */
    private function decision(Affaire $a, string $statut, int $joursSignature, int $n, bool $complete = true): Decision
    {
        $modele = ModeleDecision::firstOrFail();
        $version = $modele->versionCourante();
        $dateDecision = today()->subDays($joursSignature + 3);
        $champs = ChampsDecision::pour($modele->load('variables'), $version);
        $connues = Preremplissage::pour($modele, $a->chambre, $dateDecision->toDateString(), $a, null);
        $valeurs = ChampsDecision::nettoyer($champs, $connues);
        if ($complete) {
            $valeurs = array_merge($valeurs, $this->valeurs($a, $dateDecision, $n));
        }

        $d = Decision::create([
            'modele_decision_id' => $modele->id, 'chambre_id' => $a->chambre_id, 'affaire_id' => $a->id, 'version_numero' => $version->numero, 'reference' => $a->numeroAffichage(),
            'statut' => 'brouillon', 'valeurs' => $valeurs, 'cree_par' => $this->auteur, 'created_at' => now()->subDays($joursSignature + 10),
        ]);
        if ($statut === 'brouillon') {
            return $d;
        }

        $moment = NumeroDecision::moment();
        $d->forceFill(['statut' => 'redigee', 'redigee_le' => now()->subDays($joursSignature + 8), 'redigee_par' => $this->auteur])->save();
        if ($statut === 'redigee') {
            return $d;
        }
        if ($moment === 'redaction') {
            $d->forceFill(['date_decision' => $dateDecision->toDateString()])->save();
            NumeroDecision::attribuer($d);
        }

        $d->forceFill(['date_decision' => $dateDecision->toDateString()])->save();
        if ($moment === 'validation') {
            NumeroDecision::attribuer($d);
        }
        $rendu = RenduDecision::rendre($d->version(), $d->valeursCompletes(), [], []);
        $d->forceFill(['statut' => 'validee', 'rendu' => ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']],
            'validee_le' => now()->subDays($joursSignature + 5), 'validee_par' => $this->auteur])->save();
        if ($statut === 'validee') {
            return $d;
        }

        if ($moment === 'signature') {
            NumeroDecision::attribuer($d);
        }
        $d->forceFill(['statut' => 'signee', 'date_signature' => today()->subDays($joursSignature)->toDateString(), 'signee_le' => now()->subDays($joursSignature), 'signee_par' => $this->auteur])->save();
        if ($statut === 'signee') {
            return $d;
        }

        Delivrance::create(['decision_id' => $d->id, 'type' => 'expedition', 'delivre_a' => 'KOFFI Adjoua', 'qualite' => 'demandeur', 'delivre_le' => today()->subDays(max(0, $joursSignature - 1))->toDateString(), 'delivre_par' => $this->auteur]);
        $d->forceFill(['statut' => 'delivree', 'delivree_le' => now()->subDays(max(0, $joursSignature - 1))])->save();

        return $d;
    }

    private function decisions(): void
    {
        $affaires = Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->where('statut', 'enrolee')->orderBy('id')->skip(4)->take(12)->get()->values();
        if ($affaires->count() < 10) {
            $this->warn('Pas assez d\'affaires civiles pour toutes les décisions de formation.');

            return;
        }
        $suivi = fn (Decision $d, int $jours, string $nature = 'appel') => DB::table('suivis_recours')->insert([
            'decision_id' => $d->id, 'affaire_id' => $d->affaire_id, 'nature' => $nature, 'date_signification' => today()->subDays($jours)->toDateString(),
            'signification_a' => 'KOFFI Adjoua (demandeur)', 'saisi_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = [];

        $r['brouillon'] = $this->decision($affaires[0], 'brouillon', 0, 1, false);
        $r['redigee'] = $this->decision($affaires[1], 'redigee', 8, 2);
        $r['validee'] = $this->decision($affaires[2], 'validee', 6, 3);
        $r['signee'] = $this->decision($affaires[3], 'signee', 2, 4);                       // à signifier
        $r['expiree'] = $this->decision($affaires[4], 'signee', 50, 5);                      // délai expiré : certificat à délivrer
        $suivi($r['expiree'], 40);
        $r['recours'] = $this->decision($affaires[5], 'signee', 25, 6);                      // recours formé
        $suivi($r['recours'], 20);
        DB::table('recours')->insert(['affaire_id' => $affaires[5]->id, 'decision_id' => $r['recours']->id, 'nature' => 'appel', 'auteur' => 'Société BATIMAT SARL', 'date_recours' => today()->subDays(10)->toDateString(),
            'statut' => 'forme', 'observations' => self::MARQUE, 'cree_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now()]);
        $r['en_cours'] = $this->decision($affaires[6], 'signee', 9, 7);                      // délai en cours
        $suivi($r['en_cours'], 5);
        $r['delivree'] = $this->decision($affaires[7], 'delivree', 12, 8);                   // délivrée
        $r['certifiee'] = $this->decision($affaires[8], 'signee', 90, 9);                    // certificat déjà délivré
        $suivi($r['certifiee'], 80);
        $delai = DelaisRecours::pour($r['certifiee']->load('affaire'), 'appel');
        $echeance = $delai ? DelaisRecours::echeance(today()->subDays(80), $delai) : today()->subDays(50);
        DB::table('certificats_recours')->insert(['decision_id' => $r['certifiee']->id, 'affaire_id' => $r['certifiee']->affaire_id, 'type' => 'non_appel', 'annee' => (int) date('Y'),
            'numero' => DelaisRecours::numeroSuivant((int) date('Y')), 'date_signification' => today()->subDays(80)->toDateString(), 'echeance' => $echeance->toDateString(),
            'date_delivrance' => today()->subDays(40)->toDateString(), 'delivre_a' => 'KOFFI Adjoua', 'qualite' => 'demandeur', 'delivre_par' => $this->auteur, 'created_at' => now(), 'updated_at' => now()]);
        $r['rien'] = $this->decision($affaires[9], 'signee', 14, 10);                        // signée, non signifiée depuis 14 jours

        foreach ($r as $cle => $d) {
            $this->line(sprintf('  Décision %-10s %-9s %s %s', $cle, $d->statut, $d->reference, $d->numero ? '(n° '.$d->numeroAffichage().')' : '(sans numéro)'));
        }
        $this->line('Décisions : '.count($r).' (brouillon, rédigée, validée, signées à divers stades de recours, délivrée, certifiée).');
    }
}
