<?php

namespace App\Http\Controllers\Statistiques;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use App\Support\Statistiques\ModeleTribunal;
use App\Support\Statistiques\RegistresTribunal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Registres au modèle du tribunal : les classeurs Excel que le greffe tient aujourd'hui à la main (audience civile, référés, matrimoniale, état des personnes,
 | tutelle, hérédité, mise en état, pénal), remplis avec les affaires de l'application. Les récapitulatifs du classeur se calculent à l'ouverture.
 | Droits : stats_registres.voir (voir les registres et leurs effectifs), stats_registres.exporter (télécharger : les registres contiennent les noms des parties).
 */
class RegistresController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stats_registres.voir');
        $perimetre = PerimetreChambres::de(auth()->user());

        return view('statistiques.registres.index', [
            'registres' => RegistresTribunal::REGISTRES,
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'peutExporter' => Gate::allows('stats_registres.exporter'),
        ]);
    }

    public function effectifs(Request $request): JsonResponse
    {
        Gate::authorize('stats_registres.voir');

        return response()->json(RegistresTribunal::compter($request->user(), $this->chambre($request)));
    }

    public function exporter(Request $request, string $code)
    {
        Gate::authorize('stats_registres.exporter');
        abort_unless(isset(RegistresTribunal::REGISTRES[$code]), 404);
        $chambre = $this->chambre($request);
        [$fichier, $nombre] = RegistresTribunal::fichier($code, $request->user(), $chambre);
        Journal::audit('registre_exporte', null, RegistresTribunal::REGISTRES[$code][0], [], ['lignes' => $nombre, 'chambre' => $chambre]);

        $extension = $code === 'activites_jap' ? 'xlsm' : 'xlsx';      // le classeur du JAP garde ses macros
        $nom = 'Registre - '.RegistresTribunal::REGISTRES[$code][0].' - TPI Bingerville - '.now()->format('Ymd').'.'.$extension;

        return response()->download($fichier, $nom, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'] + ($extension === 'xlsm' ? ['Content-Type' => 'application/vnd.ms-excel.sheet.macroEnabled.12'] : []))->deleteFileAfterSend(true);
    }

    private function chambre(Request $request): ?int
    {
        $c = $request->filled('chambre') ? (int) $request->query('chambre') : null;

        return $c !== null && PerimetreChambres::voit($request->user(), $c) ? $c : null;
    }
}
