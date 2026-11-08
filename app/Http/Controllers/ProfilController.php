<?php

namespace App\Http\Controllers;

use App\Models\Profil;
use App\Services\Journal;
use App\Support\Droits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Profils d'utilisateurs : autant que nécessaire, chacun avec un niveau d'accès par sous-menu. */
class ProfilController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Profil::class);

        return view('profils.index', [
            'profils' => Profil::withCount('users')->orderByDesc('systeme')->orderBy('nom')->get(),
            'catalogue' => Droits::catalogue(),
        ]);
    }

    /** Une ligne en JSON, pour préremplir la fenêtre de modification. */
    public function show(Profil $profil): JsonResponse
    {
        Gate::authorize('viewAny', Profil::class);

        return response()->json([
            'id' => $profil->id,
            'nom' => $profil->nom,
            'description' => $profil->description,
            'systeme' => $profil->systeme,
        ] + Droits::versChoix($profil->droits ?? []));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $profil = $request->filled('id_') ? Profil::findOrFail($request->input('id_')) : null;
        $profil ? Gate::authorize('update', $profil) : Gate::authorize('create', Profil::class);

        $pages = Droits::pages();
        $speciales = array_merge(...array_values(array_map(fn ($p) => array_keys($p['speciales']), $pages)) ?: [[]]);

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80', Rule::unique('profils', 'nom')->ignore($profil?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'niveaux' => ['nullable', 'array'],
            'niveaux.*' => ['string', Rule::in(array_keys(Droits::NIVEAUX))],
            'speciales' => ['nullable', 'array'],
            'speciales.*' => ['string', Rule::in($speciales)],
        ], [
            'nom.required' => 'Le nom du profil est obligatoire.',
            'nom.unique' => 'Un profil porte déjà ce nom.',
        ]);

        // Chaque niveau choisi doit exister pour son sous-menu (ex. le journal n'a pas de niveau « Gestion »).
        foreach ($donnees['niveaux'] ?? [] as $page => $niveau) {
            if (! isset($pages[$page]) || ($niveau !== 'aucun' && ! isset($pages[$page]['niveaux'][$niveau]))) {
                throw ValidationException::withMessages(['niveaux' => 'Niveau d\'accès invalide pour « '.($pages[$page]['libelle'] ?? $page).' ».']);
            }
        }

        $nouveaux = Droits::depuisChoix($donnees['niveaux'] ?? [], $donnees['speciales'] ?? []);
        $avant = $profil ? ['nom' => $profil->nom, 'description' => $profil->description, 'droits' => $profil->droits] : [];

        $profil ??= new Profil;
        $profil->fill(['nom' => trim($donnees['nom']), 'description' => $donnees['description'] ?? null, 'droits' => $nouveaux])->save();

        Journal::audit($avant ? 'profil_modifie' : 'profil_cree', $profil, $profil->nom, $avant, ['nom' => $profil->nom, 'description' => $profil->description, 'droits' => $nouveaux]);

        return redirect()->route('profils.index')->with('success', $avant ? 'Profil modifié.' : 'Profil créé.');
    }

    public function destroy(Profil $profil): JsonResponse
    {
        Gate::authorize('viewAny', Profil::class);

        if ($profil->systeme) {
            return response()->json(['status' => false, 'message' => 'Le profil Admin ne peut pas être supprimé.']);
        }
        if ($profil->users()->exists()) {
            return response()->json(['status' => false, 'message' => 'Ce profil est attribué à des comptes : changez d\'abord leur profil.']);
        }
        Gate::authorize('delete', $profil);

        Journal::audit('profil_supprime', $profil, $profil->nom, ['nom' => $profil->nom, 'droits' => $profil->droits]);
        $profil->delete();

        return response()->json(['status' => true, 'message' => 'Profil supprimé.']);
    }
}
