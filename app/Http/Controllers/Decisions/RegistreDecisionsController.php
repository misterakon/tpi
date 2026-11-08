<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\Decision;
use App\Services\Journal;
use App\Support\Decisions\RegistreDecisions;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Registre des décisions : les décisions dont le texte est figé, avec numéro, date, chambre et remises. Consultation filtrée (année, chambre, statut,
 | recherche) et export Excel ou PDF. Chacun ne voit que les chambres de son périmètre.
 */
class RegistreDecisionsController extends Controller
{
    private const PAR_PAGE = 20;

    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());

        return view('decisions.registre.index', [
            'annees' => RegistreDecisions::annees($request->user()),
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'statuts' => array_intersect_key(Decision::STATUTS, array_flip(Decision::STATUTS_VALIDES)),
            'peutExporter' => Gate::allows('registre_decisions.exporter'),
            'urlDecision' => Gate::allows('redaction.voir') ? route('decisions.redaction.edition', 0) : null,
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        $f = RegistreDecisions::filtres($request->query());
        $page = RegistreDecisions::requete($request->user(), $f)->paginate(self::PAR_PAGE, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        // Les compteurs suivent les filtres, sauf le statut (comme les pastilles d'une liste).
        $compteurs = [];
        foreach (Decision::STATUTS_VALIDES as $statut) {
            $compteurs[$statut] = RegistreDecisions::requete($request->user(), array_merge($f, ['statut' => $statut]))->toBase()->getCountForPagination();
        }

        return response()->json([
            'lignes' => $page->getCollection()->map(fn (Decision $d) => RegistreDecisions::ecran($d))->values()->all(),
            'total' => $page->total(), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'par_page' => self::PAR_PAGE, 'compteurs' => $compteurs,
        ]);
    }

    public function exporter(Request $request)
    {
        Gate::authorize('registre_decisions.exporter');
        $f = RegistreDecisions::filtres($request->query());
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $lignes = RegistreDecisions::lignes(RegistreDecisions::requete($request->user(), $f)->limit(20000)->get());
        Journal::audit('registre_decisions_exporte', null, $format === 'pdf' ? 'PDF' : 'Excel', [], ['decisions' => count($lignes)] + array_filter($f));

        $nom = 'registre-decisions-'.now()->format('Ymd-Hi');
        if ($format === 'pdf') {
            return response(RegistreDecisions::pdf($lignes, $this->criteres($f)), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download(RegistreDecisions::excel($lignes), $nom.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** @return list<string> */
    private function criteres(array $f): array
    {
        $c = [];
        if ($f['annee']) {
            $c[] = 'Année '.$f['annee'];
        }
        if ($f['chambre']) {
            $c[] = (string) Chambre::whereKey($f['chambre'])->value('libelle');
        }
        if ($f['statut']) {
            $c[] = Decision::STATUTS[$f['statut']];
        }
        if ($f['q'] !== '') {
            $c[] = 'Recherche « '.$f['q'].' »';
        }

        return $c;
    }
}
