<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\MotDePasse;
use App\Services\CodesSecours;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** « Mon compte » : l'utilisateur change son propre mot de passe. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $moi = $request->user();
        $agent = $moi->agent;

        return view('profil.edit', [
            'moi' => $moi,
            'agent' => $agent,
            'affectations' => $agent ? $agent->affectationsEnCours() : collect(),
            'services' => $agent ? $agent->servicesEnCours() : collect(),
            'codesSecours' => app(CodesSecours::class)->etat($moi),
        ]);
    }

    /** Génère huit nouveaux codes de secours (les anciens sont annulés) ; ils ne s'affichent qu'une fois. Le mot de passe est redemandé. */
    public function genererCodes(Request $request, CodesSecours $codes): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password']], ['current_password.required' => 'Saisissez votre mot de passe.', 'current_password.current_password' => 'Mot de passe incorrect.']);

        $user = $request->user();
        $liste = $codes->generer($user);
        Journal::audit('codes_secours_generes', $user, $user->email, [], ['nombre' => count($liste)]);

        return response()->json(['codes' => $liste, 'genere_le' => now()->format('d/m/Y à H:i')]);
    }

    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        $donnees = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', MotDePasse::regle()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $donnees['password'], 'mot_de_passe_modifie_le' => now()])->save();

        // Les autres sessions de ce compte (autres postes) sont fermées.
        Auth::logoutOtherDevices($donnees['password']);

        Journal::connexion(Journal::MDP_MODIFIE, $user, true, 'changement par l\'utilisateur', $user->email);
        Journal::audit('mot_de_passe_modifie', $user, $user->email);

        $message = 'Votre mot de passe a été modifié. Vos autres sessions ont été fermées.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
