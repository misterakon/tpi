<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Delivrance;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\RoleAudience;
use App\Models\RoleVersion;
use App\Models\Salle;
use App\Models\User;
use App\Services\NumeroDecision;
use App\Services\NumerotationRegistre;
use App\Support\Redaction\ChampsDecision;
use App\Support\Redaction\Preremplissage;
use App\Support\Redaction\RenduDecision;
use App\Support\Roles\GenerateurRole;
use App\Support\Roles\RoleDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 | Trois affaires FICTIVES pour une démonstration de bout en bout (les noms sont inventés), chacune à une étape différente du parcours
 | et chacune pour un des trois modèles de jugement civil :
 |   - RG 2101/26 · foncier (déguerpissement et démolition) : à l'audience AUJOURD'HUI, à tenir en direct sur la feuille d'audience ;
 |   - RG 2102/26 · paiement (créance) : jugée, la décision est « à rédiger » : on la rédige, on la fait valider, signer, délivrer ;
 |   - RG 2103/26 · bail d'habitation (congé et expulsion) : décision déjà signée et délivrée : registre, signification, recours, statistiques.
 | Elles portent l'ancien numéro « DEMO » : « php artisan tpi:formation » ou « php artisan affaires:demo --retirer » les efface avec le reste.
 | Ordre conseillé : tpi:formation (le jeu complet : statistiques, pénal, instruction…), puis demo:soutenance.
 |
 |   php artisan demo:soutenance            crée les trois affaires (relançable : elles sont d'abord retirées)
 |   php artisan demo:soutenance --retirer  les retire seulement
 */
class DemoSoutenance extends Command
{
    protected $signature = 'demo:soutenance {--retirer : Retire seulement les trois affaires de démonstration}';

    protected $description = 'Prépare trois affaires fictives (foncier, paiement, bail) à étapes différentes pour une démonstration de bout en bout';

    private const NUMEROS = [2101, 2102, 2103];

    private int $auteur = 0;

    public function handle(): int
    {
        $this->retirer();
        if ($this->option('retirer')) {
            $this->info('Les trois affaires de démonstration sont retirées.');

            return self::SUCCESS;
        }
        foreach (['Jugement civil : foncier', 'Jugement civil : paiement et contrats', "Jugement civil : bail d'habitation"] as $nom) {
            if (! ModeleDecision::where('nom', $nom)->exists()) {
                $this->error("Le modèle « $nom » est absent : lancez d'abord « php artisan db:seed --class=ModelesJugementsCivilsSeeder ».");

                return self::FAILURE;
            }
        }

        $this->auteur = (int) (User::where('email', 'like', 'myriam.diako@%')->value('id') ?? User::where('email', 'not like', '%@tpi.test')->value('id') ?? User::value('id'));
        $chambre = Chambre::where('libelle', '1re chambre civile')->firstOrFail();
        $salle = Salle::where('nom', 'like', '%n°3%')->value('id') ?? Salle::value('id');

        [$foncier, $paiement, $bail] = $this->affaires($chambre);
        $this->audiences($chambre, $salle, $foncier, $paiement, $bail);
        $decision = $this->decisionBail($bail, $chambre);
        Artisan::call('alertes:evaluer');

        $this->info('Trois affaires de démonstration prêtes :');
        $this->line('  '.$foncier->numeroAffichage().'  foncier · à l\'audience d\'aujourd\'hui (feuille d\'audience à tenir)');
        $this->line('  '.$paiement->numeroAffichage().'  paiement · jugée, décision « à rédiger »');
        $this->line('  '.$bail->numeroAffichage().'  bail · décision '.$decision->numeroAffichage().' signée et délivrée');

        return self::SUCCESS;
    }

    /** Retire les trois affaires et ce qui s'y rattache (rôles, décisions, délivrances, alertes). */
    private function retirer(): void
    {
        $ids = DB::table('affaires')->where('ancien_numero', 'DEMO')->whereIn('numero', self::NUMEROS)->where('serie', 'RG')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $decisions = DB::table('decisions')->whereIn('affaire_id', $ids)->pluck('id');
        foreach (['delivrances', 'certificats_recours', 'recours', 'suivi_recours'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'decision_id')) {
                DB::table($table)->whereIn('decision_id', $decisions)->delete();
            }
        }
        DB::table('decisions')->whereIn('id', $decisions)->delete();
        $roles = DB::table('role_lignes')->whereIn('affaire_id', $ids)->pluck('role_id')->unique();
        DB::table('role_lignes')->whereIn('affaire_id', $ids)->delete();
        foreach ($roles as $role) {
            if (! DB::table('role_lignes')->where('role_id', $role)->exists()) {
                DB::table('role_versions')->where('role_id', $role)->delete();
                DB::table('roles_audience')->where('id', $role)->delete();
            }
        }
        DB::table('parties')->whereIn('affaire_id', $ids)->delete();
        DB::table('affaires')->whereIn('id', $ids)->delete();
    }

    /** @return array{0: Affaire, 1: Affaire, 2: Affaire} */
    private function affaires(Chambre $chambre): array
    {
        $annee = now()->year;
        $mode = ModeSaisine::where('libelle', 'Assignation')->value('id') ?? ModeSaisine::where('registre', 'civil')->value('id');
        $nature = NatureAffaire::where('libelle', 'Civile ordinaire')->value('id') ?? NatureAffaire::where('registre', 'RG')->value('id');
        $matiere = Matiere::where('libelle', 'Civile')->value('id') ?? Matiere::value('id');

        $creer = function (int $numero, string $objet, Carbon $premiere, array $parties) use ($annee, $mode, $nature, $matiere, $chambre) {
            $a = Affaire::create([
                'serie' => 'RG', 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater('RG', $numero, $annee), 'numero_saisi' => true,
                'ancien_numero' => 'DEMO', 'date_rg' => $premiere->copy()->subDays(12)->toDateString(), 'statut' => 'enrolee', 'objet' => $objet,
                'mode_saisine_id' => $mode, 'nature_affaire_id' => $nature, 'matiere_id' => $matiere, 'chambre_id' => $chambre->id, 'date_premiere_audience' => $premiere->toDateString(),
            ]);
            foreach ($parties as $i => $p) {
                if (! empty($p['avocat'])) {
                    [$p['avocat_id'], $p['avocat']] = Avocat::rattacher($p['avocat'], true);
                }
                $physique = in_array($p['nature'], ['homme', 'femme'], true);
                $a->parties()->create($p + ['ordre' => $i, 'cle' => Partie::cle($physique ? ($p['nom'] ?? '').' '.($p['prenoms'] ?? '') : (string) ($p['denomination'] ?? ''))]);
            }

            return $a->load('parties');
        };

        $foncier = $creer(self::NUMEROS[0], 'Déguerpissement et démolition de constructions', today()->subDays(21), [
            ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'KOUASSI', 'prenoms' => 'Yao Hyacinthe', 'naissance_mode' => 'date', 'naissance_date' => '1975-03-18', 'lieu_naissance' => 'Bingerville',
                'nationalite' => 'ivoirienne', 'profession' => 'comptable', 'domicile' => 'Bingerville, Cité Bacid', 'avocat' => 'Maître KONE Catherine'],
            ['role' => 'defendeur', 'nature' => 'homme', 'nom' => 'TIEHI', 'prenoms' => 'Gbalé Roger', 'nationalite' => 'ivoirienne', 'profession' => 'mécanicien', 'domicile' => 'Bingerville, Adjamé'],
        ]);
        $paiement = $creer(self::NUMEROS[1], 'Paiement de créances civiles', today()->subDays(30), [
            ['role' => 'demandeur', 'nature' => 'morale', 'denomination' => 'Société IVOIRE MATERIAUX SA', 'domicile' => 'Abidjan, zone industrielle de Yopougon', 'avocat' => 'Cabinet ABI & Associés'],
            ['role' => 'defendeur', 'nature' => 'homme', 'nom' => 'BAKAYOKO', 'prenoms' => 'Ibrahim', 'naissance_mode' => 'annee', 'naissance_annee' => 1982, 'nationalite' => 'ivoirienne',
                'profession' => 'commerçant', 'domicile' => 'Bingerville, quartier Adjamé'],
        ]);
        $bail = $creer(self::NUMEROS[2], 'Validation de congé avec expulsion', today()->subDays(45), [
            ['role' => 'demandeur', 'nature' => 'femme', 'nom' => 'DIOMANDE', 'prenoms' => 'Salimata', 'naissance_mode' => 'date', 'naissance_date' => '1968-09-02', 'lieu_naissance' => 'Man',
                'nationalite' => 'ivoirienne', 'profession' => 'enseignante', 'domicile' => 'Abidjan-Cocody Riviera Palmeraie', 'avocat' => 'Maître N\'GUESSAN Paul'],
            ['role' => 'defendeur', 'nature' => 'femme', 'nom' => 'ZADI', 'prenoms' => 'Marie-Claire', 'nationalite' => 'ivoirienne', 'profession' => 'couturière', 'domicile' => 'Bingerville, Cité Bacid'],
        ]);

        return [$foncier, $paiement, $bail];
    }

    /** Les rôles publiés qui racontent l'histoire de chaque affaire : renvois, délibéré, jugement ; et le rôle d'aujourd'hui, à tenir. */
    private function audiences(Chambre $chambre, ?int $salle, Affaire $foncier, Affaire $paiement, Affaire $bail): void
    {
        $ville = ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville')));
        $publier = function (Carbon $date, array $lignes, bool $termine) use ($chambre, $salle, $ville) {
            // Un seul rôle par chambre et par jour : s'il existe déjà (jeu de formation), les affaires de démonstration s'y ajoutent.
            $role = RoleAudience::where('chambre_id', $chambre->id)->whereDate('date_audience', $date->toDateString())->first();
            $neuf = $role === null;
            $role ??= RoleAudience::create(['chambre_id' => $chambre->id, 'date_audience' => $date->toDateString(), 'heure' => '09:00', 'salle_id' => $salle, 'fait_a' => $ville, 'date_signature' => $date->toDateString()]);
            foreach ($lignes as [$affaire, $rubrique, $suite]) {
                $ordre = (int) $role->lignes()->where('rubrique', $rubrique)->max('ordre') + 1;
                $role->lignes()->create(GenerateurRole::attributs($affaire, $rubrique, $ordre, 'auto') + $suite + ($suite ? ['suite_le' => $date] : []));
            }
            if ($neuf || $role->statut !== 'publie') {
                $role->forceFill(['statut' => 'publie', 'publie_le' => $date->copy()->subDay()])->save();
            }
            RoleVersion::create(['role_id' => $role->id, 'version' => (int) RoleVersion::where('role_id', $role->id)->max('version') + 1, 'contenu' => json_encode(RoleDocument::depuis($role->fresh()), JSON_UNESCAPED_UNICODE)]);
            if ($termine && $neuf) {
                $role->forceFill(['audience_ouverte_le' => $date, 'audience_terminee_le' => $date->copy()->setTime(13, 0)])->save();
            }

            return $role;
        };
        $j = fn (int $n) => today()->subDays($n);

        // Foncier : une audience passée (renvoi pour les conclusions du défendeur), puis aujourd'hui.
        $publier($j(21), [[$foncier, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => today()->toDateString(), 'motif' => 'Pour conclusions du défendeur', 'presence' => 'demandeur']]], true);
        // Paiement : renvoi, puis mise en délibéré, puis jugée il y a deux jours.
        $publier($j(30), [[$paiement, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $j(16)->toDateString(), 'motif' => 'Pour production de pièces', 'presence' => 'toutes']]], true);
        $publier($j(16), [[$paiement, 'anciennes', ['issue' => 'delibere', 'date_delibere' => $j(2)->toDateString(), 'presence' => 'toutes']]], true);
        // Bail : renvoi, puis jugée il y a 20 jours.
        $publier($j(45), [[$bail, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $j(20)->toDateString(), 'motif' => 'À la demande des parties', 'presence' => 'toutes']]], true);
        $publier($j(20), [[$bail, 'anciennes', ['issue' => 'delibere', 'date_delibere' => $j(12)->toDateString(), 'presence' => 'toutes']]], true);
        $publier($j(12), [[$bail, 'deliberes', ['issue' => 'juge', 'presence' => 'toutes']]], true);
        $publier($j(2), [[$paiement, 'deliberes', ['issue' => 'juge', 'presence' => 'toutes']]], true);
        // Aujourd'hui : le foncier est inscrit, la suite se note en direct sur la feuille d'audience.
        $publier(today(), [[$foncier, 'anciennes', []]], false);
    }

    /** La décision du bail : rédigée avec le modèle, validée, numérotée, signée et délivrée il y a quelques jours. */
    private function decisionBail(Affaire $bail, Chambre $chambre): Decision
    {
        $modele = ModeleDecision::where('nom', "Jugement civil : bail d'habitation")->firstOrFail();
        $version = $modele->versionCourante();
        $champs = ChampsDecision::pour($modele->load('variables'), $version);
        $date = today()->subDays(12);
        $connues = Preremplissage::pour($modele, $chambre, $date->toDateString(), $bail->fresh(['parties']), null);
        $saisie = [
            'date_audience' => $date->toDateString(), 'date_decision' => $date->toDateString(), 'nature_decision' => 'contradictoire', 'matiere' => 'civile', 'type_audience' => 'ordinaire',
            'demandeurs_comparution' => 'Comparaissant', 'defendeurs_comparution' => 'Comparaissant', 'greffier.genre' => 'm',
            'date_exploit' => today()->subDays(55)->toDateString(), 'commissaire_justice' => "Maître AKE ESSOH Christophe, Commissaire de Justice près la Cour d'Appel d'Abidjan",
            'date_premier_renvoi' => today()->subDays(20)->toDateString(), 'motif_premier_renvoi' => 'à la demande des parties',
            'demandes' => "Valider le congé servi à Madame ZADI Marie-Claire\nOrdonner son expulsion de tous occupants de son chef\nOrdonner l'exécution provisoire de la décision à intervenir\nCondamner la défenderesse aux entiers dépens",
            'expose_demandeur' => "Au soutien de son action, Madame DIOMANDE Salimata expose qu'elle a servi congé à sa locataire pour reprendre le logement afin d'y loger un membre de sa famille.\nElle sollicite la validation de ce congé et l'expulsion de la locataire.",
            'moyens_defense' => "Madame ZADI Marie-Claire conteste le motif du congé et sollicite un délai pour quitter les lieux.",
            'conge' => 'validé', 'date_conge' => today()->subDays(150)->toDateString(), 'motif_conge' => "qu'elle voulait reprendre le logement pour y loger un membre de sa famille",
            'conge_motifs' => "Que le congé a été servi dans les formes et délais légaux et que son motif est légitime et établi par les pièces produites ;\nQue la locataire ne rapporte aucune preuve de la fausseté de ce motif ;",
            'expulsion' => 'ordonnée', 'expulsion_motifs' => "Attendu que le congé étant validé, la défenderesse est sans droit ni titre à se maintenir dans les lieux ;",
            'quittances' => 'non demandées', 'di' => 'non demandés', 'issue_fond' => 'partiellement fondé', 'execution_provisoire' => 'refusée', 'depens' => 'à la charge du défendeur', 'type_jugement' => 'au fond',
        ];
        $valeurs = ChampsDecision::nettoyer($champs, $saisie + $connues);

        $d = Decision::create([
            'modele_decision_id' => $modele->id, 'chambre_id' => $chambre->id, 'affaire_id' => $bail->id, 'version_numero' => $version->numero, 'reference' => $bail->numeroAffichage(),
            'statut' => 'brouillon', 'valeurs' => $valeurs, 'cree_par' => $this->auteur, 'created_at' => now()->subDays(11),
        ]);
        $moment = NumeroDecision::moment();
        $d->forceFill(['statut' => 'redigee', 'redigee_le' => now()->subDays(10), 'redigee_par' => $this->auteur, 'date_decision' => $date->toDateString()])->save();
        if ($moment === 'redaction') {
            NumeroDecision::attribuer($d);
        }
        if ($moment === 'validation') {
            NumeroDecision::attribuer($d);
        }
        $rendu = RenduDecision::rendre($d->version(), $d->valeursCompletes(), [], []);
        $d->forceFill(['statut' => 'validee', 'rendu' => ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']], 'validee_le' => now()->subDays(9), 'validee_par' => $this->auteur])->save();
        if ($moment === 'signature') {
            NumeroDecision::attribuer($d);
        }
        $d->forceFill(['statut' => 'signee', 'date_signature' => today()->subDays(8)->toDateString(), 'signee_le' => now()->subDays(8), 'signee_par' => $this->auteur])->save();
        $d->forceFill(['statut' => 'transmise', 'transmise_le' => now()->subDays(7), 'transmise_par' => $this->auteur, 'delivree_le' => now()->subDays(7)])->save();

        return $d->fresh();
    }
}
