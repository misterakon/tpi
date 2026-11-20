<?php

namespace App\Http\Controllers;

use App\Support\Accueil\TravailDuJour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/* « Mon travail du jour » : la page des tâches de la personne connectée (menu du profil, en haut à droite) et le total affiché sur ce menu. */
class TravailDuJourController extends Controller
{
    public function index(Request $request): View
    {
        return view('travail.jour', ['t' => TravailDuJour::pour($request->user()), 'groupes' => TravailDuJour::GROUPES]);
    }

    /** Le total des tâches, lu par l'en-tête après le chargement de la page (jamais bloquant). */
    public function compte(Request $request): JsonResponse
    {
        return response()->json(['n' => TravailDuJour::total($request->user())])->header('Cache-Control', 'no-store');
    }
}
