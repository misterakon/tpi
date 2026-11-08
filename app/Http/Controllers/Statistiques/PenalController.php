<?php

namespace App\Http\Controllers\Statistiques;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use App\Support\Statistiques\DonneesPenal;
use App\Support\Statistiques\ExportCivil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Statistiques pénales d'après le répertoire pénal. Droits : stats_penal.voir (chiffres seulement), stats_penal.detail (liste des décisions dans l'export Excel,
 | avec les colonnes du classeur « AUDIENCE PENALE ») et stats_penal.exporter (Excel et PDF).
 */
class PenalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stats_penal.voir');
        $perimetre = PerimetreChambres::de(auth()->user());

        return view('statistiques.penal.index', [
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'peutExporter' => Gate::allows('stats_penal.exporter'),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('stats_penal.voir');
        [$depuis, $jusqua, $chambre] = $this->filtres($request);
        $r = DonneesPenal::recapitulatif(DonneesPenal::decisions($request->user(), $depuis, $jusqua, $chambre), $depuis, $jusqua);

        return response()->json($r);
    }

    public function exporter(Request $request)
    {
        Gate::authorize('stats_penal.exporter');
        [$depuis, $jusqua, $chambre] = $this->filtres($request);
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $liste = DonneesPenal::decisions($request->user(), $depuis, $jusqua, $chambre);
        $onglets = DonneesPenal::onglets(DonneesPenal::recapitulatif($liste, $depuis, $jusqua), $format === 'xlsx' && Gate::allows('stats_penal.detail') ? $liste : null);
        $periode = 'Années '.$depuis.' à '.$jusqua.($chambre ? ' · '.Chambre::whereKey($chambre)->value('libelle') : ' · toutes les chambres');
        Journal::audit('statistiques_exportees', null, 'Statistiques pénales ('.$format.')', [], ['depuis' => $depuis, 'jusqua' => $jusqua, 'chambre' => $chambre, 'detail' => isset($onglets['AUDIENCE PENALE'])]);

        $nom = 'statistiques-penales-'.$depuis.'-'.$jusqua.'-'.now()->format('Ymd-Hi');
        if ($format === 'pdf') {
            return response(ExportCivil::pdf($onglets, 'Statistiques pénales', $periode), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download(ExportCivil::excel($onglets, 'Statistiques pénales', $periode), $nom.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** @return array{0: int, 1: int, 2: ?int} */
    private function filtres(Request $request): array
    {
        $courante = (int) now()->year;
        $jusqua = min(max((int) $request->query('jusqua', $courante), 2000), $courante + 1);
        $depuis = min(max((int) $request->query('depuis', $jusqua - 2), $jusqua - 9), $jusqua);
        $chambre = $request->filled('chambre') ? (int) $request->query('chambre') : null;
        if ($chambre !== null && ! PerimetreChambres::voit($request->user(), $chambre)) {
            $chambre = null;
        }

        return [$depuis, $jusqua, $chambre];
    }
}
