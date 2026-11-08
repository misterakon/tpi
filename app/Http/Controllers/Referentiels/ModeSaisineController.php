<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\ModeSaisine;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Modes de saisine. Droits : modes_saisine.voir / creer / modifier / supprimer. */
class ModeSaisineController extends Controller
{
    private const CHAMPS = ['libelle', 'registre', 'actif', 'a_valider'];

    public function index(): View
    {
        Gate::authorize('modes_saisine.voir');

        return view('referentiels.modes_saisine.index', [
            'lignes' => ModeSaisine::orderBy('libelle')->get(),
        ] + $this->listes());
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('modes_saisine.voir');
        $m = ModeSaisine::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? ModeSaisine::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'modes_saisine.modifier' : 'modes_saisine.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'registre' => ['required', Rule::in(array_keys(ModeSaisine::REGISTRES))],
            'actif' => ['nullable', 'boolean'],
            'a_valider' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Mode de saisine » est obligatoire.',
            'registre.required' => 'Le champ « Registre » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['registre'] = filled($donnees['registre'] ?? null) ? $donnees['registre'] : null;
        $donnees['actif'] = $request->boolean('actif');
        $donnees['a_valider'] = $request->boolean('a_valider');
        $doublon = ModeSaisine::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($donnees['registre'] !== null, fn ($q) => $q->where('registre', $donnees['registre']), fn ($q) => $q->whereNull('registre'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Ce mode de saisine existe déjà pour ce registre.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new ModeSaisine;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'mode_saisine_modifie' : 'mode_saisine_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('referentiels.modes_saisine')->with('success', $avant ? 'Mode de saisine modifié.' : 'Mode de saisine ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('modes_saisine.supprimer');
        $m = ModeSaisine::findOrFail($id);

        Journal::audit('mode_saisine_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Mode de saisine supprimé.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    private function listes(): array
    {
        return [
            'opt_registre' => ModeSaisine::REGISTRES,
        ];
    }
}
