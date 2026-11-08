<?php

namespace App\Http\Controllers;

use App\Models\DelaiEtape;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/* Délais par étape du circuit (Paramètres) : saisie des suites, rédaction de la décision… Ils servent de base aux échéances des alertes. */
class DelaisEtapeController extends Controller
{
    public function index(): View
    {
        // La page réunit deux séries de délais ; chacun voit celle dont il a le droit.
        abort_unless(Gate::any(['delais_etape.voir', 'delais_procedure.voir']), 403);

        return view('alertes.delais', ['peutVoirEtapes' => Gate::allows('delais_etape.voir'), 'delais' => Gate::allows('delais_etape.voir') ? DelaiEtape::orderBy('ordre')->get() : collect(), 'unites' => DelaiEtape::UNITES, 'peutModifier' => Gate::allows('delais_etape.modifier'), 'peutVoirProcedure' => Gate::allows('delais_procedure.voir')]
            + (Gate::allows('delais_procedure.voir') ? \App\Http\Controllers\Referentiels\DelaiProcedureController::donnees() : []));
    }

    public function modifier(Request $request, int $id): JsonResponse
    {
        Gate::authorize('delais_etape.modifier');
        $delai = DelaiEtape::findOrFail($id);
        $donnees = $request->validate([
            'valeur' => ['required', 'integer', 'between:0,365'],
            'unite' => ['required', Rule::in(array_keys(DelaiEtape::UNITES))],
        ], ['valeur.required' => 'Indiquez le délai.', 'valeur.between' => 'Le délai doit être compris entre 0 et 365.']);

        $avant = $delai->only(['valeur', 'unite']);
        $delai->forceFill(['valeur' => (int) $donnees['valeur'], 'unite' => $donnees['unite']])->save();
        DelaiEtape::oublier();
        Journal::audit('delai_etape_modifie', $delai, $delai->libelle, $avant, $delai->only(['valeur', 'unite']));

        return response()->json(['status' => true, 'message' => 'Délai modifié : '.$delai->libelleDelai().'.', 'libelle_delai' => $delai->libelleDelai()]);
    }
}
