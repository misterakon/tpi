<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Matières. Droits : matieres.voir / creer / modifier / supprimer. */
class MatiereController extends Controller
{
    private const CHAMPS = ['libelle', 'actif'];

    public function index(): View
    {
        Gate::authorize('matieres.voir');

        return view('referentiels.matieres.index', [
            'lignes' => Matiere::withCount('objets')->orderBy('libelle')->get(),
        ] + $this->listes());
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('matieres.voir');
        $m = Matiere::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? Matiere::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'matieres.modifier' : 'matieres.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:120'],
            'actif' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Matière » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['actif'] = $request->boolean('actif');
        $doublon = Matiere::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Cette matière existe déjà.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new Matiere;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'matiere_modifie' : 'matiere_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('referentiels.matieres')->with('success', $avant ? 'Matière modifiée.' : 'Matière ajoutée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('matieres.supprimer');
        $m = Matiere::findOrFail($id);

        if (DB::table('objets_litige')->where('matiere_id', $m->id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Cet élément est utilisé par des objets du litige : modifiez-les d\'abord.']);
        }

        Journal::audit('matiere_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Matière supprimée.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    private function listes(): array
    {
        return [
        ];
    }
}
