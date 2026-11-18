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
use Illuminate\Support\Facades\DB;

/*
 | Jeu de DÉMONSTRATION PRATIQUE (affaires fictives, 1re chambre civile), à lancer APRÈS « demo:soutenance » :
 |   - le rôle d'AUJOURD'HUI reçoit trois affaires nouvelles (RG 2301 à 2303) et une affaire ancienne renvoyée à aujourd'hui (RG 2304) ;
 |   - une audience d'HIER, tenue et terminée, dont trois affaires sont jugées : la décision est à rédiger (RG 2311, 2313) ou le brouillon
 |     n'a que la qualité préparée et le factum reste à rédiger (RG 2312) ;
 |   - cinq décisions à des étapes différentes (RG 2321 à 2325) : rédigée, validée, deux signées à transmettre au greffe, transmise au greffe (vaut délivrée).
 | Reconnaissables à l'ancien numéro « PRATIQUE » ; « demo:pratique --retirer » les retire.
 */
class DemoPratique extends Command
{
    protected $signature = 'demo:pratique {--retirer : Retire seulement les affaires de ce jeu}';

    protected $description = "Prépare le rôle d'aujourd'hui, une audience d'hier à rédiger et des décisions à toutes les étapes";

    private int $auteur = 0;

    public function handle(): int
    {
        $this->retirer();
        if ($this->option('retirer')) {
            $this->info('Jeu pratique retiré.');

            return self::SUCCESS;
        }
        $bail = ModeleDecision::where('nom', "Jugement civil : bail d'habitation")->first();
        if (! $bail) {
            $this->error("Modèles de jugement absents : lancez d'abord « php artisan db:seed --class=ModelesJugementsCivilsSeeder ».");

            return self::FAILURE;
        }
        $this->auteur = (int) (User::where('email', 'like', 'ramata.doumbia@%')->value('id') ?? User::where('email', 'like', 'myriam.diako@%')->value('id') ?? User::value('id'));
        $chambre = Chambre::where('libelle', '1re chambre civile')->firstOrFail();
        $salle = Salle::where('nom', 'like', '%n°3%')->value('id') ?? Salle::value('id');

        $this->roleDuJour($chambre, $salle);
        $this->audienceHier($chambre, $salle);
        $this->decisions($chambre);
        $this->corrigerTransmissions();
        $this->corrigerDatesRg();

        $this->info('Jeu pratique prêt :');
        $this->line("  Rôle d'aujourd'hui : 2101 (ancienne), 2304 (ancienne, renvoyée), 2301 à 2303 (nouvelles)");
        $this->line("  Audience d'hier (terminée) : 2311 et 2313 à rédiger, 2312 brouillon (qualité faite, factum à rédiger)");
        $this->line('  Décisions : 2325 rédigée · 2324 validée · 2323 et 2322 signées à transmettre · 2321 transmise au greffe (vaut délivrée)');

        return self::SUCCESS;
    }

    private function retirer(): void
    {
        $ids = DB::table('affaires')->where('ancien_numero', 'PRATIQUE')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $decisions = DB::table('decisions')->whereIn('affaire_id', $ids)->pluck('id');
        DB::table('delivrances')->whereIn('decision_id', $decisions)->delete();
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

    /** Crée une affaire civile de la 1re chambre. $parties : [demandeur, defendeur] (tableaux de partie). */
    private function affaire(Chambre $chambre, int $numero, string $objet, Carbon $premiere, Carbon $rg, array $parties): Affaire
    {
        $annee = (int) now()->year;
        $a = Affaire::create([
            'serie' => 'RG', 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater('RG', $numero, $annee), 'numero_saisi' => true,
            'ancien_numero' => 'PRATIQUE', 'date_rg' => $rg->toDateString(), 'statut' => 'enrolee', 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('libelle', 'Assignation')->value('id') ?? ModeSaisine::where('registre', 'civil')->value('id'),
            'nature_affaire_id' => NatureAffaire::where('libelle', 'Civile ordinaire')->value('id') ?? NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::where('libelle', 'Civile')->value('id') ?? Matiere::value('id'),
            'chambre_id' => $chambre->id, 'date_premiere_audience' => $premiere->toDateString(),
        ]);
        foreach ($parties as $i => $p) {
            if (! empty($p['avocat'])) {
                [$p['avocat_id'], $p['avocat']] = Avocat::rattacher($p['avocat'], true);
            }
            $physique = in_array($p['nature'], ['homme', 'femme'], true);
            $a->parties()->create($p + ['ordre' => $i, 'cle' => Partie::cle($physique ? ($p['nom'] ?? '').' '.($p['prenoms'] ?? '') : (string) ($p['denomination'] ?? ''))]);
        }

        return $a->load('parties');
    }

    private function homme(string $nom, string $prenoms, string $role, ?string $avocat = null): array
    {
        return ['role' => $role, 'nature' => 'homme', 'nom' => $nom, 'prenoms' => $prenoms, 'nationalite' => 'ivoirienne', 'domicile' => 'Bingerville'] + ($avocat ? ['avocat' => $avocat] : []);
    }

    private function femme(string $nom, string $prenoms, string $role, ?string $avocat = null): array
    {
        return ['role' => $role, 'nature' => 'femme', 'nom' => $nom, 'prenoms' => $prenoms, 'nationalite' => 'ivoirienne', 'domicile' => 'Bingerville'] + ($avocat ? ['avocat' => $avocat] : []);
    }

    private function morale(string $denomination, string $role, ?string $avocat = null): array
    {
        return ['role' => $role, 'nature' => 'morale', 'denomination' => $denomination, 'domicile' => 'Abidjan'] + ($avocat ? ['avocat' => $avocat] : []);
    }

    /** Ajoute des lignes à un rôle (le crée publié s'il n'existe pas) puis enregistre une nouvelle version. */
    private function publier(Chambre $chambre, ?int $salle, Carbon $date, array $lignes, bool $termine): RoleAudience
    {
        $ville = ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville')));
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
            $role->forceFill(['audience_ouverte_le' => $date->copy()->setTime(9, 15), 'audience_terminee_le' => $date->copy()->setTime(13, 0)])->save();
        }

        return $role;
    }

    /** Rôle d'aujourd'hui : trois affaires nouvelles et une ancienne (renvoyée à aujourd'hui). */
    private function roleDuJour(Chambre $chambre, ?int $salle): void
    {
        $nouvelles = [
            [2301, 'Paiement de loyers impayés et expulsion', [$this->homme('KOFFI', 'Aka Marcel', 'demandeur', 'Maître KONE Catherine'), $this->femme('TRAORE', 'Fatoumata', 'defendeur')]],
            [2302, 'Paiement de créance civile', [$this->morale('Société AGRO-CÔTE SARL', 'demandeur', 'Cabinet ABI & Associés'), $this->homme('YAO', 'Kouamé Alphonse', 'defendeur')]],
            [2303, 'Déguerpissement', [$this->femme('BAMBA', 'Awa', 'demandeur', "Maître N'GUESSAN Paul"), $this->homme('SORO', 'Lacina', 'defendeur')]],
        ];
        $lignes = [];
        foreach ($nouvelles as [$n, $objet, $parties]) {
            $lignes[] = [$this->affaire($chambre, $n, $objet, today(), today()->subDays(14), $parties), 'nouvelles', []];
        }
        // L'ancienne : première audience il y a trois semaines, renvoyée à aujourd'hui.
        $ancienne = $this->affaire($chambre, 2304, 'Validation de congé avec expulsion', today()->subDays(21), today()->subDays(35), [
            $this->homme('DOSSO', 'Mamadou', 'demandeur', 'Maître KONE Catherine'), $this->femme('KOUAME', 'Adjoua Rose', 'defendeur'),
        ]);
        $this->publier($chambre, $salle, today()->subDays(21), [[$ancienne, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => today()->toDateString(), 'motif' => 'Pour conclusions du défendeur', 'presence' => 'demandeur']]], true);
        $lignes[] = [$ancienne, 'anciennes', []];
        $this->publier($chambre, $salle, today(), $lignes, false);
    }

    /** Audience d'hier, terminée : trois affaires jugées dont la décision est à rédiger. */
    private function audienceHier(Chambre $chambre, ?int $salle): void
    {
        $hier = today()->subDay();
        $a1 = $this->affaire($chambre, 2311, 'Déguerpissement et démolition de constructions', $hier, $hier->copy()->subDays(14), [$this->homme('ZOKORA', 'Pascal', 'demandeur', "Maître N'GUESSAN Paul"), $this->homme('KRA', 'Konan Désiré', 'defendeur')]);
        $a2 = $this->affaire($chambre, 2312, 'Paiement de créances civiles', $hier, $hier->copy()->subDays(14), [$this->morale('Société BTP IVOIRE SA', 'demandeur', 'Cabinet ABI & Associés'), $this->homme('COULIBALY', 'Seydou', 'defendeur')]);
        $a3 = $this->affaire($chambre, 2313, 'Validation de congé avec expulsion', $hier, $hier->copy()->subDays(14), [$this->femme('ADOU', 'Marie-Laure', 'demandeur', 'Maître KONE Catherine'), $this->homme('BOLOU', 'Tapé Honoré', 'defendeur')]);
        $juge = ['issue' => 'juge', 'presence' => 'toutes'];
        $this->publier($chambre, $salle, $hier, [[$a1, 'nouvelles', $juge], [$a2, 'nouvelles', $juge], [$a3, 'nouvelles', $juge]], true);

        // 2312 : brouillon commencé par le greffier : la qualité est préparée, le factum reste à rédiger.
        $modele = ModeleDecision::where('nom', 'Jugement civil : paiement et contrats')->first();
        if ($modele) {
            $version = $modele->versionCourante();
            $champs = ChampsDecision::pour($modele->load('variables'), $version);
            $connues = Preremplissage::pour($modele, $chambre, $hier->toDateString(), $a2->fresh(['parties']), null);
            Decision::create([
                'modele_decision_id' => $modele->id, 'chambre_id' => $chambre->id, 'affaire_id' => $a2->id, 'version_numero' => $version->numero, 'reference' => $a2->numeroAffichage(),
                'statut' => 'brouillon', 'valeurs' => ChampsDecision::nettoyer($champs, $connues + ['date_audience' => $hier->toDateString()]), 'cree_par' => $this->auteur, 'created_at' => now()->subHours(3),
            ]);
        }
    }

    /** Cinq décisions (modèle bail) à des étapes différentes. */
    private function decisions(Chambre $chambre): void
    {
        $paires = [
            2321 => [$this->femme('GNAGNE', 'Prisca', 'demandeur', "Maître N'GUESSAN Paul"), $this->homme('TOURE', 'Ibrahim', 'defendeur'), 'transmise', 14],
            2322 => [$this->homme('OUATTARA', 'Drissa', 'demandeur', 'Maître KONE Catherine'), $this->femme('KONAN', 'Affoué Hélène', 'defendeur'), 'signee', 11],
            2323 => [$this->femme('SILUE', 'Mariam', 'demandeur'), $this->homme('GUEI', 'Bertin', 'defendeur'), 'signee', 9],
            2324 => [$this->homme('AKA', 'Jean-Baptiste', 'demandeur', 'Maître KONE Catherine'), $this->femme('DJE', 'Yvonne', 'defendeur'), 'validee', 6],
            2325 => [$this->femme('LOUKOU', 'Estelle', 'demandeur'), $this->homme('TANOH', 'Emmanuel', 'defendeur'), 'redigee', 4],
        ];
        foreach ($paires as $numero => [$dem, $def, $etat, $jours]) {
            $a = $this->affaire($chambre, $numero, 'Validation de congé avec expulsion', today()->subDays($jours + 30), today()->subDays($jours + 45), [$dem, $def]);
            $this->decision($a, $chambre, $etat, $jours);
        }
    }

    private function decision(Affaire $affaire, Chambre $chambre, string $etat, int $jours): Decision
    {
        $modele = ModeleDecision::where('nom', "Jugement civil : bail d'habitation")->firstOrFail();
        $version = $modele->versionCourante();
        $champs = ChampsDecision::pour($modele->load('variables'), $version);
        $date = today()->subDays($jours);
        $dem = $affaire->parties->firstWhere('role', 'demandeur');
        $def = $affaire->parties->firstWhere('role', 'defendeur');
        $nomDem = trim(($dem->nature === 'femme' ? 'Madame ' : 'Monsieur ').$dem->nom.' '.$dem->prenoms);
        $nomDef = trim(($def->nature === 'femme' ? 'Madame ' : 'Monsieur ').$def->nom.' '.$def->prenoms);
        $connues = Preremplissage::pour($modele, $chambre, $date->toDateString(), $affaire->fresh(['parties']), null);
        $saisie = [
            'date_audience' => $date->toDateString(), 'date_decision' => $date->toDateString(), 'nature_decision' => 'contradictoire', 'matiere' => 'civile', 'type_audience' => 'ordinaire',
            'demandeurs_comparution' => 'Comparaissant', 'defendeurs_comparution' => 'Comparaissant', 'greffier.genre' => 'm',
            'date_exploit' => $date->copy()->subDays(40)->toDateString(), 'commissaire_justice' => "Maître AKE ESSOH Christophe, Commissaire de Justice près la Cour d'Appel d'Abidjan",
            'date_premier_renvoi' => $date->copy()->subDays(20)->toDateString(), 'motif_premier_renvoi' => 'à la demande des parties',
            'demandes' => "Valider le congé servi à $nomDef\nOrdonner son expulsion de tous occupants de son chef\nOrdonner l'exécution provisoire de la décision à intervenir\nCondamner le défendeur aux entiers dépens",
            'expose_demandeur' => "Au soutien de son action, $nomDem expose qu'il a servi congé à son locataire pour reprendre le logement.\nIl sollicite la validation de ce congé et l'expulsion du locataire.",
            'moyens_defense' => "$nomDef conteste le motif du congé et sollicite un délai pour quitter les lieux.",
            'conge' => 'validé', 'date_conge' => $date->copy()->subDays(150)->toDateString(), 'motif_conge' => "qu'il voulait reprendre le logement pour y loger un membre de sa famille",
            'conge_motifs' => "Que le congé a été servi dans les formes et délais légaux et que son motif est légitime et établi par les pièces produites ;\nQue le locataire ne rapporte aucune preuve de la fausseté de ce motif ;",
            'expulsion' => 'ordonnée', 'expulsion_motifs' => 'Attendu que le congé étant validé, le défendeur est sans droit ni titre à se maintenir dans les lieux ;',
            'quittances' => 'non demandées', 'di' => 'non demandés', 'issue_fond' => 'partiellement fondé', 'execution_provisoire' => 'refusée', 'depens' => 'à la charge du défendeur', 'type_jugement' => 'au fond',
        ];
        $valeurs = ChampsDecision::nettoyer($champs, $saisie + $connues);
        $d = Decision::create([
            'modele_decision_id' => $modele->id, 'chambre_id' => $chambre->id, 'affaire_id' => $affaire->id, 'version_numero' => $version->numero, 'reference' => $affaire->numeroAffichage(),
            'statut' => 'brouillon', 'valeurs' => $valeurs, 'cree_par' => $this->auteur, 'created_at' => now()->subDays(max(1, $jours - 1)),
        ]);
        $moment = NumeroDecision::moment();
        $d->forceFill(['statut' => 'redigee', 'redigee_le' => now()->subDays(max(1, $jours - 1)), 'redigee_par' => $this->auteur, 'date_decision' => $date->toDateString()])->save();
        if ($moment === 'redaction') {
            NumeroDecision::attribuer($d);
        }
        if ($etat === 'redigee') {
            return $d->fresh();
        }
        if ($moment === 'validation') {
            NumeroDecision::attribuer($d);
        }
        $rendu = RenduDecision::rendre($d->version(), $d->valeursCompletes(), [], []);
        $d->forceFill(['statut' => 'validee', 'rendu' => ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']], 'validee_le' => now()->subDays(max(1, $jours - 2)), 'validee_par' => $this->auteur])->save();
        if ($etat === 'validee') {
            return $d->fresh();
        }
        if ($moment === 'signature') {
            NumeroDecision::attribuer($d);
        }
        $d->forceFill(['statut' => 'signee', 'date_signature' => today()->subDays(max(1, $jours - 3))->toDateString(), 'signee_le' => now()->subDays(max(1, $jours - 3)), 'signee_par' => $this->auteur])->save();
        if ($etat === 'signee') {
            return $d->fresh();
        }
        // Dernière étape : la transmission au greffe, dont la date vaut date de délivrance.
        $quand = now()->subDays(max(1, $jours - 4));
        $d->forceFill(['statut' => 'transmise', 'transmise_le' => $quand, 'transmise_par' => $this->auteur, 'delivree_le' => $quand])->save();

        return $d->fresh();
    }

    /** Une décision transmise au greffe porte la même date de transmission et de délivrance. */
    private function corrigerTransmissions(): void
    {
        DB::table('decisions')->where('statut', 'transmise')->whereNull('delivree_le')->update(['delivree_le' => DB::raw('transmise_le')]);
    }

    /** La date du RG ne peut pas être dans le futur. */
    private function corrigerDatesRg(): void
    {
        DB::table('affaires')->where('date_rg', '>', today()->toDateString())->update(['date_rg' => today()->toDateString()]);
    }
}
