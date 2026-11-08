<?php

namespace App\Http\Controllers\Statistiques;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\ResultatAffaire;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use App\Support\Statistiques\CategoriesStats;
use App\Support\Statistiques\DonneesCivil;
use App\Support\Statistiques\ExportCivil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Statistiques civiles : récapitulatif annuel et mensuel par catégorie, état civil, affaires matrimoniales (dont adoption), durées, au modèle des classeurs du TPI.
 | Droits : stats_civil.voir (chiffres seulement, sans nom ni numéro de dossier), stats_civil.detail (les affaires derrière un chiffre),
 | stats_civil.qualifier (accordée ou rejetée, adoption, sortie) et stats_civil.exporter (Excel et PDF).
 */
class CivilController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stats_civil.voir');
        $perimetre = PerimetreChambres::de(auth()->user());

        return view('statistiques.civil.index', [
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'peutDetail' => Gate::allows('stats_civil.detail'), 'peutQualifier' => Gate::allows('stats_civil.qualifier'), 'peutExporter' => Gate::allows('stats_civil.exporter'),
            'categories' => CategoriesStats::CATEGORIES, 'sousCategories' => ['etat_civil' => CategoriesStats::ETAT_CIVIL, 'matrimoniale' => CategoriesStats::MATRIMONIAL],
            'sorties' => ResultatAffaire::SORTIES, 'resultats' => ResultatAffaire::RESULTATS, 'caracteres' => ResultatAffaire::CARACTERES, 'adoptants' => ResultatAffaire::ADOPTANTS,
        ]);
    }

    /** Tous les chiffres de la page en une fois (la page se met à jour sans rechargement). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('stats_civil.voir');
        [$depuis, $jusqua, $anneeMois, $chambre] = $this->filtres($request);
        $affaires = DonneesCivil::affaires($request->user(), $chambre);

        $recap = DonneesCivil::recapitulatif($affaires, $depuis, $jusqua, $anneeMois);
        $total = $recap['total'];
        $precedent = $jusqua - 1;
        $debut = $jusqua === $depuis ? $total['stock_debut'] : $total['stock_fin'][$precedent];
        $n = $total['nouvelles'][$jusqua];
        $v = $total['videes'][$jusqua];
        $qualifier = DonneesCivil::aQualifier($affaires);

        return response()->json([
            'recap' => $recap,
            'etat_civil' => DonneesCivil::sousEtat($affaires, 'etat_civil', $depuis, $jusqua),
            'matrimonial' => DonneesCivil::sousEtat($affaires, 'matrimoniale', $depuis, $jusqua),
            'durees' => DonneesCivil::durees($affaires, $jusqua),
            'kpis' => ['annee' => $jusqua, 'stock_debut' => $debut, 'nouvelles' => $n, 'videes' => $v, 'stock_fin' => $total['stock_fin'][$jusqua], 'taux' => $n > 0 ? (int) round($v / $n * 100) : null, 'evolution' => $total['nouvelles'][$precedent] ?? null],
            'a_qualifier' => ['total' => $qualifier->count(), 'liste' => Gate::allows('stats_civil.qualifier') ? $qualifier->take(40)->map(fn ($x) => ['id' => $x['id'], 'numero' => $x['numero'], 'objet' => $x['objet'], 'categorie' => CategoriesStats::CATEGORIES[$x['categorie']], 'date' => $x['sortie']?->format('d/m/Y')])->values()->all() : []],
            'sans_audience' => $affaires->where('sans_audience', true)->count(),
            'total_affaires' => $affaires->count(),
        ]);
    }

    /** Les affaires derrière un chiffre (réservé à qui peut voir le détail des dossiers). */
    public function detail(Request $request): JsonResponse
    {
        Gate::authorize('stats_civil.detail');
        $v = $request->validate([
            'indicateur' => ['required', Rule::in(['nouvelles', 'videes', 'stock_fin', 'accordees', 'rejetees'])], 'categorie' => ['nullable', 'string', 'max:40'], 'sous' => ['nullable', 'string', 'max:40'],
            'annee' => ['required', 'integer', 'between:1990,2100'], 'mois' => ['nullable', 'integer', 'between:1,12'], 'chambre' => ['nullable', 'integer'],
        ]);
        $affaires = DonneesCivil::affaires($request->user(), $v['chambre'] ?? null);
        $liste = DonneesCivil::detail($affaires, $v['indicateur'], $v['categorie'] ?? null, (int) $v['annee'], isset($v['mois']) ? (int) $v['mois'] : null, $v['sous'] ?? null);

        return response()->json([
            'total' => $liste->count(),
            'lignes' => $liste->take(300)->map(fn ($x) => [
                'id' => $x['id'], 'numero' => $x['numero'], 'objet' => $x['objet'], 'chambre' => $x['chambre'], 'entree' => $x['entree']->format('d/m/Y'), 'sortie' => $x['sortie']?->format('d/m/Y'),
                'issue' => $x['type_sortie'] ? ResultatAffaire::SORTIES[$x['type_sortie']] : null, 'resultat' => $x['resultat'] ? ResultatAffaire::RESULTATS[$x['resultat']] : null,
            ])->values()->all(),
        ]);
    }

    /** Précise une affaire : accordée ou rejetée, adoption, sous-catégorie, sortie à la main (ex. PV de conciliation totale). */
    public function qualifier(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('stats_civil.qualifier');
        $v = $request->validate([
            'affaire_id' => ['required', 'integer'], 'resultat' => ['nullable', Rule::in(array_keys(ResultatAffaire::RESULTATS))],
            'sous_categorie' => ['nullable', Rule::in(array_merge(array_keys(CategoriesStats::ETAT_CIVIL), array_keys(CategoriesStats::MATRIMONIAL)))],
            'type_sortie' => ['nullable', Rule::in(array_keys(ResultatAffaire::SORTIES))], 'date_sortie' => ['nullable', 'date', 'before_or_equal:today', 'required_with:type_sortie'],
            'adoption_filles' => ['nullable', 'integer', 'between:0,50'], 'adoption_garcons' => ['nullable', 'integer', 'between:0,50'],
            'caractere' => ['nullable', Rule::in(array_keys(ResultatAffaire::CARACTERES))], 'adoptant_ivoirien' => ['nullable', Rule::in(array_keys(ResultatAffaire::ADOPTANTS))], 'adoptant_etranger' => ['nullable', Rule::in(array_keys(ResultatAffaire::ADOPTANTS))],
            'observations' => ['nullable', 'string', 'max:300'],
        ], ['date_sortie.required_with' => 'Indiquez la date de la sortie.', 'date_sortie.before_or_equal' => 'La date ne peut pas être dans le futur.']);

        $affaire = Affaire::visiblesPour($request->user())->where('serie', 'RG')->find((int) $v['affaire_id']);
        if (! $affaire) {
            throw ValidationException::withMessages(['affaire_id' => 'Cette affaire civile est introuvable ou hors de votre périmètre.']);
        }
        if (! empty($v['date_sortie']) && $affaire->date_rg && $v['date_sortie'] < $affaire->date_rg->toDateString()) {
            throw ValidationException::withMessages(['date_sortie' => 'La sortie ne peut pas précéder l\'enregistrement de l\'affaire.']);
        }

        $champs = [
            'resultat' => $v['resultat'] ?? null, 'sous_categorie' => $v['sous_categorie'] ?? null, 'type_sortie' => $v['type_sortie'] ?? null, 'date_sortie' => $v['date_sortie'] ?? null,
            'adoption_filles' => $v['adoption_filles'] ?? null, 'adoption_garcons' => $v['adoption_garcons'] ?? null, 'caractere' => $v['caractere'] ?? null,
            'adoptant_ivoirien' => $v['adoptant_ivoirien'] ?? null, 'adoptant_etranger' => $v['adoptant_etranger'] ?? null, 'observations' => $v['observations'] ?? null,
        ];
        $r = ResultatAffaire::updateOrCreate(['affaire_id' => $affaire->id], $champs + ['cree_par' => $request->user()?->id]);
        Journal::audit('statistique_affaire_qualifiee', $affaire, $affaire->numeroAffichage(), [], array_filter($r->only(array_keys($champs)), fn ($x) => $x !== null));

        return $request->expectsJson() ? response()->json(['status' => true, 'message' => 'Affaire précisée.']) : redirect()->route('statistiques.civil')->with('success', 'Affaire précisée.');
    }

    public function exporter(Request $request)
    {
        Gate::authorize('stats_civil.exporter');
        [$depuis, $jusqua, $anneeMois, $chambre] = $this->filtres($request);
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $affaires = DonneesCivil::affaires($request->user(), $chambre);
        $onglets = ExportCivil::onglets(
            DonneesCivil::recapitulatif($affaires, $depuis, $jusqua, $anneeMois), DonneesCivil::sousEtat($affaires, 'etat_civil', $depuis, $jusqua),
            DonneesCivil::sousEtat($affaires, 'matrimoniale', $depuis, $jusqua), $anneeMois,
        );
        $periode = 'Années '.$depuis.' à '.$jusqua.' · mois de '.$anneeMois.($chambre ? ' · '.Chambre::whereKey($chambre)->value('libelle') : ' · toutes les chambres');
        Journal::audit('statistiques_exportees', null, 'Statistiques civiles ('.$format.')', [], ['depuis' => $depuis, 'jusqua' => $jusqua, 'chambre' => $chambre]);

        $nom = 'statistiques-civiles-'.$depuis.'-'.$jusqua.'-'.now()->format('Ymd-Hi');
        if ($format === 'pdf') {
            return response(ExportCivil::pdf($onglets, 'Statistiques civiles', $periode), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download(ExportCivil::excel($onglets, 'Statistiques civiles', $periode), $nom.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** @return array{0: int, 1: int, 2: int, 3: ?int} */
    private function filtres(Request $request): array
    {
        $courante = (int) now()->year;
        $jusqua = min(max((int) $request->query('jusqua', $courante), 2000), $courante + 1);
        $depuis = min(max((int) $request->query('depuis', $jusqua - 4), $jusqua - 9), $jusqua);
        $mois = min(max((int) $request->query('annee_mois', $jusqua), $depuis), $jusqua);
        $chambre = $request->filled('chambre') ? (int) $request->query('chambre') : null;
        if ($chambre !== null && ! PerimetreChambres::voit($request->user(), $chambre)) {
            $chambre = null;
        }

        return [$depuis, $jusqua, $mois, $chambre];
    }
}
