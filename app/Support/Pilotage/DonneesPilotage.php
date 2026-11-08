<?php

namespace App\Support\Pilotage;

use App\Http\Controllers\AlertesController;
use App\Models\Affaire;
use App\Models\AnneeJudiciaire;
use App\Models\Alerte;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Partie;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Models\User;
use App\Support\Audiences\SuiviAffaires;
use App\Support\PerimetreChambres;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 | Les chiffres de la page « Analyse et pilotage » : une vue de tout le tribunal (ou des chambres de l'utilisateur) sur une période.
 |   stock       affaires en cours aujourd'hui           entrées  affaires enrôlées sur la période
 |   sorties     affaires jugées, radiées, jointes ou désistées sur la période (une affaire compte une fois)
 |   délai moyen jours entre l'enrôlement et la sortie   retards  affaires dont une échéance est dépassée
 | Tout est calculé à partir de ce que l'application sait déjà. Aucun nom de partie ni numéro d'affaire ne sort de cette classe vers l'IA
 | (voir RedacteurIA::charge) : seuls des totaux, par chambre.
 */
class DonneesPilotage
{
    public const PERIODES = ['30j' => '30 derniers jours', '90j' => '90 derniers jours', 'annee' => 'Année judiciaire'];

    /** @return array{type: string, debut: Carbon, fin: Carbon, libelle: string, jours: int} */
    public static function periode(?string $type): array
    {
        $type = array_key_exists((string) $type, self::PERIODES) ? $type : '30j';
        $fin = today();
        if ($type === 'annee') {
            $annee = AnneeJudiciaire::courante();
            $debut = $annee?->debut?->copy() ?? Carbon::create($fin->year, 1, 1);
            $libelle = $annee ? 'Année judiciaire '.$annee->libelle : 'Année en cours';
        } else {
            $jours = $type === '90j' ? 90 : 30;
            $debut = $fin->copy()->subDays($jours - 1);
            $libelle = self::PERIODES[$type];
        }

        return ['type' => $type, 'debut' => $debut->startOfDay(), 'fin' => $fin, 'libelle' => $libelle, 'jours' => (int) $debut->diffInDays($fin) + 1];
    }

    /** @return array<string, mixed> */
    public static function calculer(User $user, array $periode): array
    {
        $perimetre = PerimetreChambres::de($user);
        $debut = $periode['debut']->toDateString();
        $fin = $periode['fin']->toDateString();
        $chambres = Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']);
        $ids = $chambres->pluck('id')->all();

        $etats = SuiviAffaires::etats($perimetre, today());
        $enCours = $etats->where('en_cours', true);

        // Entrées : affaires enrôlées sur la période.
        $entrees = Affaire::visiblesPour($user)->where('statut', 'enrolee')->whereBetween('date_rg', [$debut, $fin])->whereNotNull('chambre_id')->get(['id', 'chambre_id'])->groupBy('chambre_id')->map->count();

        // Sorties : une affaire sort quand sa dernière suite finale est notée sur un rôle publié de la période.
        $sorties = RoleLigne::query()->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->join('affaires', 'affaires.id', '=', 'role_lignes.affaire_id')
            ->where('roles_audience.statut', 'publie')->whereIn('role_lignes.issue', RoleLigne::ISSUES_FINALES)->whereBetween('roles_audience.date_audience', [$debut, $fin])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('roles_audience.chambre_id', $perimetre ?: [0]))
            ->orderBy('roles_audience.date_audience')->orderBy('role_lignes.id')
            ->get(['role_lignes.affaire_id', 'role_lignes.issue', 'roles_audience.chambre_id', 'roles_audience.date_audience as date_role', 'affaires.date_rg'])->keyBy('affaire_id');   // la dernière écrase

        $delais = $sorties->map(function ($s) {
            $jours = Carbon::parse($s->date_rg)->startOfDay()->diffInDays(Carbon::parse($s->date_role)->startOfDay(), false);

            return ['chambre' => (int) $s->chambre_id, 'jours' => max(0, (int) $jours)];
        });

        // Suites notées sur la période (toutes) : répartition et taux de renvoi.
        $suites = RoleLigne::query()->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')->whereNotNull('role_lignes.issue')
            ->whereBetween('roles_audience.date_audience', [$debut, $fin])->when($perimetre !== null, fn ($q) => $q->whereIn('roles_audience.chambre_id', $perimetre ?: [0]))
            ->get(['role_lignes.issue', 'roles_audience.chambre_id']);
        $parIssue = $suites->groupBy('issue')->map->count();
        $renvoisParChambre = $suites->where('issue', 'renvoi')->groupBy('chambre_id')->map->count();
        $suitesParChambre = $suites->groupBy('chambre_id')->map->count();

        // Audiences passées et feuilles saisies.
        $roles = RoleAudience::where('statut', 'publie')->whereBetween('date_audience', [$debut, min($fin, today()->toDateString())])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('chambre_id', $perimetre ?: [0]))->withCount(['lignes as suites_notees' => fn ($q) => $q->whereNotNull('issue')])->get(['id', 'chambre_id']);
        $audiencesPassees = $roles->count();
        $feuillesSaisies = $roles->where('suites_notees', '>', 0)->count();

        // Décisions validées sur la période.
        $validees = Decision::visiblesPour($user)->whereIn('statut', Decision::STATUTS_VALIDES)->whereBetween('validee_le', [$periode['debut'], $periode['fin']->copy()->endOfDay()])->get(['id', 'chambre_id'])->groupBy('chambre_id')->map->count();

        // Alertes ouvertes.
        $alertes = Alerte::visiblesPour($user)->ouvertes()->get();
        $alertesParChambre = $alertes->groupBy('chambre_id');

        $parChambre = $chambres->map(function ($c) use ($enCours, $etats, $entrees, $sorties, $delais, $renvoisParChambre, $suitesParChambre, $validees, $alertesParChambre) {
            $stock = $enCours->where('chambre_id', $c->id);
            $nbSorties = $sorties->where('chambre_id', $c->id)->count();
            $nbEntrees = (int) ($entrees[$c->id] ?? 0);
            $d = $delais->where('chambre', $c->id)->pluck('jours');
            $al = $alertesParChambre->get($c->id, collect());

            return [
                'id' => (int) $c->id, 'libelle' => $c->libelle, 'stock' => $stock->count(), 'entrees' => $nbEntrees, 'sorties' => $nbSorties,
                'taux_sortie' => $nbEntrees > 0 ? (int) round($nbSorties / $nbEntrees * 100) : null,
                'delai_moyen' => $d->isNotEmpty() ? (int) round($d->avg()) : null,
                'retards' => $etats->where('chambre_id', $c->id)->where('retard', true)->count(),
                'a_rediger' => $etats->where('chambre_id', $c->id)->where('a_rediger', true)->count(),
                'renvois_pct' => ($suitesParChambre[$c->id] ?? 0) > 0 ? (int) round(($renvoisParChambre[$c->id] ?? 0) / $suitesParChambre[$c->id] * 100) : null,
                'decisions' => (int) ($validees[$c->id] ?? 0), 'alertes' => $al->count(), 'critiques' => $al->where('niveau', 'critique')->count(),
            ];
        })->values()->all();

        $nbEntrees = (int) $entrees->sum();
        $nbSorties = $sorties->count();
        $delaiMoyen = $delais->isNotEmpty() ? (int) round($delais->avg('jours')) : null;
        $aRediger = $etats->where('a_rediger', true);
        $plusAncienne = $aRediger->map(fn ($e) => $e['derniere_audience'] ? (int) $e['derniere_audience']->diffInDays(today()) : 0)->max();

        $detenus = Partie::with('affaire:id,chambre_id,statut')->whereNotNull('mandat_depot_date')->whereNull('libere_le')->get()
            ->filter(fn (Partie $p) => $p->affaire && $p->affaire->statut === 'enrolee' && ($perimetre === null || in_array((int) $p->affaire->chambre_id, $perimetre, true)))
            ->filter(fn (Partie $p) => ($j = $p->joursRestants()) !== null && $j <= (int) config('tpi.penal.alerte_jours', 30));

        return [
            'periode' => ['type' => $periode['type'], 'libelle' => $periode['libelle'], 'debut' => $debut, 'fin' => $fin, 'jours' => $periode['jours']],
            'perimetre' => $perimetre === null ? 'Toutes les chambres' : ($chambres->pluck('libelle')->implode(', ') ?: 'Aucune chambre'),
            'kpis' => [
                'stock' => $enCours->count(), 'entrees' => $nbEntrees, 'sorties' => $nbSorties, 'taux_sortie' => $nbEntrees > 0 ? (int) round($nbSorties / $nbEntrees * 100) : null,
                'delai_moyen' => $delaiMoyen, 'a_rediger' => $aRediger->count(), 'plus_ancienne_decision' => $plusAncienne ?: 0, 'decisions_validees' => (int) $validees->sum(),
                'retards' => $etats->where('retard', true)->count(), 'alertes_ouvertes' => $alertes->count(), 'alertes_critiques' => $alertes->where('niveau', 'critique')->count(),
                'alertes_en_retard' => $alertes->filter(fn (Alerte $a) => $a->retard() > 0)->count(),
                'audiences_passees' => $audiencesPassees, 'feuilles_saisies_pct' => $audiencesPassees > 0 ? (int) round($feuillesSaisies / $audiencesPassees * 100) : null,
                'renvois_pct' => $suites->count() > 0 ? (int) round($suites->where('issue', 'renvoi')->count() / $suites->count() * 100) : null,
                'detenus_proches' => $detenus->count(), 'affaires_renvoyees_3_fois' => $etats->where('nb_renvois', '>=', 3)->where('en_cours', true)->count(),
            ],
            'par_chambre' => $parChambre,
            'mensuel' => self::mensuel($user, $perimetre),
            'suites' => collect(RoleLigne::ISSUES)->map(fn ($libelle, $cle) => ['cle' => $cle, 'libelle' => $libelle, 'n' => (int) ($parIssue[$cle] ?? 0)])->values()->all(),
            'alertes_par_type' => $alertes->groupBy('type')->map->count()->sortDesc()->map(fn ($n, $type) => ['type' => $type, 'libelle' => Alerte::TYPES[$type] ?? $type, 'n' => $n])->values()->all(),
            'alertes_recentes' => $alertes->sortBy([fn ($a, $b) => (['critique' => 3, 'attention' => 2, 'info' => 1][$b->niveau] ?? 0) <=> (['critique' => 3, 'attention' => 2, 'info' => 1][$a->niveau] ?? 0), fn ($a, $b) => $a->echeance <=> $b->echeance])
                ->take(6)->map(fn (Alerte $a) => AlertesController::forme($a->loadMissing('chambre:id,libelle')))->values()->all(),
            'agents_sans_compte' => self::agentsSansCompte(),
        ];
    }

    /** Entrées et sorties des douze derniers mois, pour la courbe. @return array{mois: list<string>, entrees: list<int>, sorties: list<int>} */
    private static function mensuel(User $user, ?array $perimetre): array
    {
        $debut = today()->startOfMonth()->subMonths(11);
        $mois = collect(range(0, 11))->map(fn ($i) => $debut->copy()->addMonths($i));

        $entrees = Affaire::visiblesPour($user)->where('statut', 'enrolee')->where('date_rg', '>=', $debut->toDateString())->pluck('date_rg')->map(fn ($d) => Carbon::parse($d)->format('Y-m'))->countBy();
        $sorties = RoleLigne::query()->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')
            ->whereIn('role_lignes.issue', RoleLigne::ISSUES_FINALES)->where('roles_audience.date_audience', '>=', $debut->toDateString())
            ->when($perimetre !== null, fn ($q) => $q->whereIn('roles_audience.chambre_id', $perimetre ?: [0]))->get(['role_lignes.affaire_id', 'roles_audience.date_audience'])
            ->unique('affaire_id')->map(fn ($l) => Carbon::parse($l->date_audience)->format('Y-m'))->countBy();

        return [
            'mois' => $mois->map(fn ($m) => ucfirst($m->locale('fr')->translatedFormat('M Y')))->all(),
            'entrees' => $mois->map(fn ($m) => (int) ($entrees[$m->format('Y-m')] ?? 0))->all(),
            'sorties' => $mois->map(fn ($m) => (int) ($sorties[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    /** Les personnes qui devraient recevoir des alertes mais n'ont pas de compte : sans elles, les alertes vont aux administrateurs. */
    private static function agentsSansCompte(): int
    {
        $ids = \App\Models\Affectation::where('suppleant', false)->whereIn('role', ['greffier', 'president'])
            ->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()->toDateString()))->pluck('agent_id')->unique();

        return \App\Models\Agent::whereIn('id', $ids)->where('actif', true)->whereNull('user_id')->count();
    }
}
