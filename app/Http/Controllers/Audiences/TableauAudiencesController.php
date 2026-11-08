<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Support\Audiences\TableauAudiences;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tableau de bord des audiences (AUD-06) : par chambre et par magistrat, actualisé sans recharger la page. */
class TableauAudiencesController extends Controller
{
    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());
        $chambres = Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']);

        return view('audiences.tableau.index', [
            'donnees' => $this->calculer($request),
            'chambres' => $chambres,
            'magistrats' => TableauAudiences::magistrats($perimetre),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        return response()->json($this->calculer($request));
    }

    private function calculer(Request $request): array
    {
        $donnees = $request->validate(['chambre' => ['nullable', 'integer'], 'agent' => ['nullable', 'integer']]);

        return TableauAudiences::donnees(PerimetreChambres::de($request->user()), $donnees['chambre'] ?? null, $donnees['agent'] ?? null);
    }
}
