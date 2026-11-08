<?php

namespace App\Http\Controllers;

use App\Support\Accueil\DonneesAccueil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/* Le tableau de bord : une page sobre, des blocs selon les droits de chacun, mise à jour sans recharger. */
class AccueilController extends Controller
{
    public function index(): View
    {
        return view('accueil');
    }

    public function donnees(Request $request): JsonResponse
    {
        return response()->json(DonneesAccueil::pour($request->user(), (int) $request->query('mois', 12)));
    }
}
