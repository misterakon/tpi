<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\RoleLigne;
use App\Services\Journal;
use App\Support\Audiences\SuitesAudience;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Suites d'audience : tout ce qui a été noté aux audiences (jugée, radiée, jointe, désistement, renvoi, délibéré), à consulter avec des filtres
 | (suite, chambre, période, recherche) et à exporter en Excel ou en PDF. Chacun ne voit que les chambres de son périmètre.
 */
class SuitesAudienceController extends Controller
{
    private const PAR_PAGE = 12;

    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());

        return view('audiences.suites.index', [
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'suites' => RoleLigne::ISSUES,
            'peutExporter' => Gate::allows('suites.exporter'),
            'peutVoirAffaire' => Gate::allows('affaires.voir'),
            'urlFeuille' => Gate::allows('feuille.voir') ? route('audiences.feuille.show', 0) : null,
            'urlFiche' => Gate::allows('affaires.voir') ? route('affaires.liste.fiche', 0) : null,
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        $f = SuitesAudience::filtres($request->query());
        $numero = max(1, (int) $request->query('page', 1));

        if ($f['suite'] === SuitesAudience::RETARD) {
            // Les affaires en retard viennent du suivi des affaires, pas d'une requête sur les lignes de rôle : on pagine la liste calculée.
            $retards = SuitesAudience::retards($request->user(), $f);
            $pages = max(1, (int) ceil($retards->count() / self::PAR_PAGE));
            $numero = min($numero, $pages);
            $lignes = $retards->slice(($numero - 1) * self::PAR_PAGE, self::PAR_PAGE)->values()->all();
            $total = $retards->count();
        } else {
            $page = SuitesAudience::liste($request->user(), $f)->paginate(self::PAR_PAGE, ['*'], 'page', $numero);
            $chambres = SuitesAudience::chambres();
            $lignes = $page->getCollection()->map(fn ($l) => SuitesAudience::ecran($l, $chambres))->values()->all();
            [$total, $numero, $pages] = [$page->total(), $page->currentPage(), $page->lastPage()];
        }

        return response()->json([
            'lignes' => $lignes, 'total' => $total, 'page' => $numero, 'pages' => $pages, 'par_page' => self::PAR_PAGE,
            'compteurs' => SuitesAudience::compteurs($request->user(), $f),
        ]);
    }

    public function exporter(Request $request)
    {
        Gate::authorize('suites.exporter');
        $f = SuitesAudience::filtres($request->query());
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $nom = 'suites-audience-'.now()->format('Ymd-Hi');

        // PDF : seulement la suite affichée (la pastille où l'on se tient). Excel : un classeur avec toutes les suites, une feuille chacune.
        if ($format === 'pdf') {
            $lignes = SuitesAudience::lignesExport(SuitesAudience::toutes($request->user(), $f));
            Journal::audit('suites_exportees', null, 'PDF', [], ['suites' => count($lignes)] + array_filter($f));

            return response(SuitesAudience::pdf($lignes, $this->criteres($f), $f['suite']), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $feuilles = SuitesAudience::classeur($request->user(), $f);
        Journal::audit('suites_exportees', null, 'Excel', [], ['suites' => count($feuilles['Toutes les suites']), 'feuilles' => count($feuilles)] + array_filter(array_diff_key($f, ['suite' => 1])));

        return response()->download(SuitesAudience::excel($feuilles), $nom.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** Les filtres en clair, pour l'en-tête du PDF. @return list<string> */
    private function criteres(array $f): array
    {
        $c = [];
        if ($f['chambre']) {
            $c[] = (string) Chambre::whereKey($f['chambre'])->value('libelle');
        }
        if ($f['du'] || $f['au']) {
            $c[] = 'Du '.($f['du'] ? date('d/m/Y', strtotime($f['du'])) : '…').' au '.($f['au'] ? date('d/m/Y', strtotime($f['au'])) : '…');
        }
        if ($f['q'] !== '') {
            $c[] = 'Recherche « '.$f['q'].' »';
        }

        return $c;
    }
}
