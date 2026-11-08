<?php

namespace App\Http\Controllers\Statistiques;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Support\FamillesAffaires;
use App\Support\PerimetreChambres;
use App\Support\Statistiques\DonneesInstruction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/* Statistiques de l'instruction : dossiers, inculpés, victimes, durées, commissions rogatoires. Droit : stats_instruction.voir (chiffres seulement ; les registres Excel ont leur droit). */
class InstructionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stats_instruction.voir');
        $perimetre = PerimetreChambres::de(auth()->user());

        return view('statistiques.instruction.index', [
            'cabinets' => Chambre::whereIn('id', FamillesAffaires::chambresInstruction())->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('stats_instruction.voir');
        $courante = (int) now()->year;
        $jusqua = min(max((int) $request->query('jusqua', $courante), 2000), $courante + 1);
        $depuis = min(max((int) $request->query('depuis', $jusqua - 2), $jusqua - 9), $jusqua);
        $chambre = $request->filled('chambre') ? (int) $request->query('chambre') : null;
        if ($chambre !== null && (! in_array($chambre, FamillesAffaires::chambresInstruction(), true) || ! PerimetreChambres::voit($request->user(), $chambre))) {
            $chambre = null;
        }

        return response()->json(DonneesInstruction::calculer($request->user(), $depuis, $jusqua, $chambre));
    }
}
