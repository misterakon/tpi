<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\TypeAudience;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Types d'audience. Droits : types_audience.voir / creer / modifier / supprimer. */
class TypeAudienceController extends Controller
{
    private const CHAMPS = ['libelle', 'actif'];

    public function index(): View
    {
        Gate::authorize('types_audience.voir');

        return view('referentiels.types_audience.index', [
            'lignes' => TypeAudience::orderBy('libelle')->get(),
        ] + $this->listes());
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('types_audience.voir');
        $m = TypeAudience::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? TypeAudience::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'types_audience.modifier' : 'types_audience.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'actif' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Nature de l\'audience » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['actif'] = $request->boolean('actif');
        $doublon = TypeAudience::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Ce type d\'audience existe déjà.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new TypeAudience;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'type_audience_modifie' : 'type_audience_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('referentiels.types_audience')->with('success', $avant ? 'Type d\'audience modifié.' : 'Type d\'audience ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('types_audience.supprimer');
        $m = TypeAudience::findOrFail($id);

        Journal::audit('type_audience_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Type d\'audience supprimé.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    private function listes(): array
    {
        return [
        ];
    }
}
