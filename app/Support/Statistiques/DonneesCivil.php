<?php

namespace App\Support\Statistiques;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\ResultatAffaire;
use App\Models\RoleLigne;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Statistiques civiles, calculées comme les onglets « RECAP ENROL. CIV » des classeurs du TPI :
 |   affaire NOUVELLE de l'année N   année de sa première audience (à défaut, de son enrôlement) ;
 |   affaire VIDÉE de l'année N      année de sa décision ; issues qui vident : décision au fond, radiation, PV de conciliation totale, désistement ;
 |   stock fin N                     stock début N + nouvelles N − vidées N ; stock début N = stock fin N−1 ;
 |   à juger N                       (à juger N−1 − vidées N−1) + nouvelles N.
 | Une affaire jointe à une autre n'est pas comptée à part. Chacun ne voit que les chambres de son périmètre.
 | La date et le type de sortie viennent, dans cet ordre, de la saisie à la main (resultats_affaire), de la décision validée, puis de l'issue notée à la feuille d'audience.
 */
class DonneesCivil
{
    /** @var array<string, string> */
    public const MOIS = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

    private const ISSUES_FEUILLE = ['juge' => 'decision_au_fond', 'radie' => 'radiation', 'desiste' => 'desistement'];

    /**
     * Les affaires du périmètre avec leur catégorie, leur année d'entrée et leur sortie éventuelle.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function affaires(User $user, ?int $chambre = null): Collection
    {
        $affaires = Affaire::visiblesPour($user)->with(['nature:id,libelle', 'chambre:id,libelle', 'parties'])->where('serie', 'RG')->where('statut', 'enrolee')->whereNull('jointe_a_id')
            ->when($chambre, fn ($q) => $q->where('chambre_id', $chambre))
            ->get(['id', 'numero_complet', 'serie', 'annee', 'numero', 'objet', 'chambre_id', 'nature_affaire_id', 'date_rg', 'date_premiere_audience']);
        if ($affaires->isEmpty()) {
            return collect();
        }
        $ids = $affaires->pluck('id')->all();

        $manuel = ResultatAffaire::whereIn('affaire_id', $ids)->get()->keyBy('affaire_id');
        $toutesDecisions = Decision::whereIn('affaire_id', $ids)->whereIn('statut', Decision::STATUTS_VALIDES)->get(['affaire_id', 'date_decision', 'validee_le', 'numero', 'numero_seq', 'numero_annee'])->groupBy('affaire_id');
        $decisions = $toutesDecisions->map(fn ($g) => $g->map(fn ($d) => ($d->date_decision ?? $d->validee_le)?->copy()->startOfDay())->filter()->min());
        $numerosDecision = $toutesDecisions->map(fn ($g) => $g->sortBy(fn ($d) => $d->date_decision ?? $d->validee_le)->first())
            ->map(fn ($d) => $d->numero ? ((int) ($d->numero_seq ?: $d->numero)).'/'.substr((string) $d->numero_annee, -2) : null);
        $feuille = RoleLigne::query()->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')
            ->whereIn('role_lignes.affaire_id', $ids)->whereIn('role_lignes.issue', array_keys(self::ISSUES_FEUILLE))
            ->orderBy('roles_audience.date_audience')->get(['role_lignes.affaire_id', 'role_lignes.issue', 'roles_audience.date_audience'])->groupBy('affaire_id')->map->first();

        return $affaires->map(function (Affaire $a) use ($manuel, $decisions, $feuille, $numerosDecision) {
            $m = $manuel->get($a->id);
            $entree = ($a->date_premiere_audience ?? $a->date_rg)?->copy()->startOfDay();
            [$type, $date] = [null, null];
            if ($m?->type_sortie && $m->date_sortie) {
                [$type, $date] = [$m->type_sortie, $m->date_sortie->copy()->startOfDay()];
            } elseif ($decisions->get($a->id)) {
                [$type, $date] = ['decision_au_fond', $decisions->get($a->id)];
            } elseif ($f = $feuille->get($a->id)) {
                [$type, $date] = [self::ISSUES_FEUILLE[$f->issue], \Illuminate\Support\Carbon::parse($f->date_audience)->startOfDay()];
            }
            $categorie = CategoriesStats::categorie($a);

            return [
                'id' => $a->id, 'numero' => $a->numeroAffichage(), 'objet' => (string) $a->objet, 'chambre_id' => $a->chambre_id, 'chambre' => $a->chambre?->libelle,
                'categorie' => $categorie, 'sous' => CategoriesStats::sousCategorie($a, $categorie, $m?->sous_categorie),
                'entree' => $entree, 'sortie' => $date, 'type_sortie' => $type, 'resultat' => $m?->resultat,
                'filles' => $m?->adoption_filles, 'garcons' => $m?->adoption_garcons, 'sans_audience' => $a->date_premiere_audience === null,
                // Pour les registres au modèle du tribunal :
                'date_rg' => $a->date_rg?->copy()->startOfDay(), 'audience' => $a->date_premiere_audience?->copy()->startOfDay(), 'nature' => (string) $a->nature?->libelle, 'parties' => $a->parties, 'decision_numero' => $numerosDecision->get($a->id),
                'caractere' => $m?->caractere, 'adoptant_ivoirien' => $m?->adoptant_ivoirien, 'adoptant_etranger' => $m?->adoptant_etranger, 'observations' => $m?->observations, 'chambre_libelle' => $a->chambre?->libelle,
            ];
        })->filter(fn ($x) => $x['entree'] !== null)->values();
    }

    /**
     * Le récapitulatif annuel et mensuel.
     *
     * @return array<string, mixed>
     */
    public static function recapitulatif(Collection $affaires, int $depuis, int $jusqua, int $anneeMois): array
    {
        $annees = range($depuis, $jusqua);
        $cats = CategoriesStats::CATEGORIES;
        $an = fn ($d) => $d?->year;

        $bloc = function (Collection $liste) use ($annees, $an) {
            $sortie = ['stock_debut' => 0, 'nouvelles' => [], 'videes' => [], 'a_juger' => [], 'stock_fin' => []];
            $sortie['stock_debut'] = $liste->filter(fn ($x) => $an($x['entree']) < $annees[0] && (! $x['sortie'] || $an($x['sortie']) >= $annees[0]))->count();
            $stock = $sortie['stock_debut'];
            $ajuger = $stock;
            foreach ($annees as $i => $y) {
                $n = $liste->filter(fn ($x) => $an($x['entree']) === $y)->count();
                $v = $liste->filter(fn ($x) => $x['sortie'] && $an($x['sortie']) === $y)->count();
                $ajuger = $i === 0 ? $stock + $n : ($ajuger - $sortie['videes'][$annees[$i - 1]]) + $n;
                $stock = $stock + $n - $v;
                [$sortie['nouvelles'][$y], $sortie['videes'][$y], $sortie['a_juger'][$y], $sortie['stock_fin'][$y]] = [$n, $v, $ajuger, $stock];
            }

            return $sortie;
        };

        $parCategorie = [];
        foreach ($cats as $cle => $libelle) {
            $parCategorie[$cle] = $bloc($affaires->where('categorie', $cle));
        }
        $total = $bloc($affaires);

        // Mois de l'année demandée : nouvelles, vidées et stock fin cumulé, par catégorie et au total.
        $mois = [];
        foreach (['total' => $affaires] + array_map(fn ($c) => $affaires->where('categorie', $c), array_combine(array_keys($cats), array_keys($cats))) as $cle => $liste) {
            $stock = $liste->filter(fn ($x) => $an($x['entree']) < $anneeMois && (! $x['sortie'] || $an($x['sortie']) >= $anneeMois))->count();
            $ligne = ['stock_debut' => $stock, 'nouvelles' => [], 'videes' => [], 'stock_fin' => []];
            foreach (self::MOIS as $m => $_) {
                $n = $liste->filter(fn ($x) => $an($x['entree']) === $anneeMois && $x['entree']->month === $m)->count();
                $v = $liste->filter(fn ($x) => $x['sortie'] && $an($x['sortie']) === $anneeMois && $x['sortie']->month === $m)->count();
                $stock += $n - $v;
                [$ligne['nouvelles'][$m], $ligne['videes'][$m], $ligne['stock_fin'][$m]] = [$n, $v, $stock];
            }
            $mois[$cle] = $ligne;
        }

        return ['annees' => $annees, 'categories' => $cats, 'par_categorie' => $parCategorie, 'total' => $total, 'mois' => $mois, 'annee_mois' => $anneeMois];
    }

    /**
     * État civil ou affaires matrimoniales : reçues, jugées (vidées), accordées et rejetées par sous-catégorie et par année.
     *
     * @return array<string, mixed>
     */
    public static function sousEtat(Collection $affaires, string $categorie, int $depuis, int $jusqua): array
    {
        $annees = range($depuis, $jusqua);
        $liste = $affaires->where('categorie', $categorie);
        $lignes = [];
        foreach (CategoriesStats::sous($categorie) as $cle => $libelle) {
            $sous = $liste->where('sous', $cle);
            $r = ['libelle' => $libelle, 'stock_debut' => $sous->filter(fn ($x) => $x['entree']->year < $annees[0] && (! $x['sortie'] || $x['sortie']->year >= $annees[0]))->count(),
                'nouvelles' => [], 'videes' => [], 'accordees' => [], 'rejetees' => [], 'stock_fin' => [], 'filles' => [], 'garcons' => []];
            $stock = $r['stock_debut'];
            foreach ($annees as $y) {
                $n = $sous->filter(fn ($x) => $x['entree']->year === $y)->count();
                $jugees = $sous->filter(fn ($x) => $x['sortie'] && $x['sortie']->year === $y);
                $stock += $n - $jugees->count();
                $r['nouvelles'][$y] = $n;
                $r['videes'][$y] = $jugees->count();
                $r['accordees'][$y] = $jugees->where('type_sortie', 'decision_au_fond')->where('resultat', 'accord')->count();
                $r['rejetees'][$y] = $jugees->where('type_sortie', 'decision_au_fond')->where('resultat', 'rejet')->count();
                $r['stock_fin'][$y] = $stock;
                $r['filles'][$y] = (int) $jugees->where('resultat', 'accord')->sum('filles');
                $r['garcons'][$y] = (int) $jugees->where('resultat', 'accord')->sum('garcons');
            }
            $lignes[$cle] = $r;
        }

        return ['annees' => $annees, 'lignes' => $lignes, 'adoption' => str_starts_with((string) array_key_first(CategoriesStats::sous($categorie)), 'divorce')];
    }

    /** Durées entre la première audience et la décision (jours), pour l'année donnée ; les dates manquantes sont comptées à part. */
    public static function durees(Collection $affaires, int $annee): array
    {
        $jugees = $affaires->filter(fn ($x) => $x['sortie'] && $x['sortie']->year === $annee && $x['type_sortie'] === 'decision_au_fond');
        $valides = $jugees->filter(fn ($x) => ! $x['sans_audience'] && $x['sortie']->gte($x['entree']))->map(fn ($x) => (int) $x['entree']->diffInDays($x['sortie']))->sort()->values();
        $n = $valides->count();
        $mediane = $n === 0 ? null : ($n % 2 ? $valides[intdiv($n, 2)] : (int) round(($valides[$n / 2 - 1] + $valides[$n / 2]) / 2));
        $delai = (int) config('tpi.delais_jugement.civil_jours', 183);   // 6 mois : ordonnance, page 8

        return [
            'jugees' => $jugees->count(), 'mesurees' => $n, 'manquantes' => $jugees->count() - $n, 'moyenne' => $n ? (int) round($valides->avg()) : null, 'mediane' => $mediane,
            'dans_le_delai' => $n ? (int) round($valides->filter(fn ($d) => $d <= $delai)->count() / $n * 100) : null, 'delai_jours' => $delai,
        ];
    }

    /** Les affaires jugées au fond en état civil ou matrimonial à qualifier (accord ou rejet non renseigné). */
    public static function aQualifier(Collection $affaires): Collection
    {
        return $affaires->filter(fn ($x) => in_array($x['categorie'], ['etat_civil', 'matrimoniale'], true) && $x['sortie'] && $x['type_sortie'] === 'decision_au_fond' && ! $x['resultat'])->values();
    }

    /** Le détail des affaires derrière un chiffre. */
    public static function detail(Collection $affaires, string $indicateur, ?string $categorie, int $annee, ?int $mois = null, ?string $sous = null): Collection
    {
        return $affaires->filter(function ($x) use ($indicateur, $categorie, $annee, $mois, $sous) {
            if ($categorie && $categorie !== 'total' && $x['categorie'] !== $categorie) {
                return false;
            }
            if ($sous && $x['sous'] !== $sous) {
                return false;
            }
            $dansPeriode = fn ($d) => $d && $d->year === $annee && ($mois === null || $d->month === $mois);

            return match ($indicateur) {
                'nouvelles' => $dansPeriode($x['entree']),
                'videes' => $dansPeriode($x['sortie']),
                'accordees' => $dansPeriode($x['sortie']) && $x['resultat'] === 'accord',
                'rejetees' => $dansPeriode($x['sortie']) && $x['resultat'] === 'rejet',
                'stock_fin' => $x['entree']->lte(now()->setYear($annee)->setMonth($mois ?? 12)->endOfMonth()) && (! $x['sortie'] || $x['sortie']->gt(now()->setYear($annee)->setMonth($mois ?? 12)->endOfMonth())),
                default => false,
            };
        })->values();
    }
}
