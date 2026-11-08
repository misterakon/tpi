<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\NatureAffaire;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Natures d'affaire. Droits : natures_affaire.voir / creer / modifier / supprimer. */
class NatureAffaireController extends Controller
{
    private const CHAMPS = ['libelle', 'registre', 'actif', 'exclue_enrolement'];

    public function index(): View
    {
        Gate::authorize('natures_affaire.voir');

        return view('referentiels.natures_affaire.index', [
            'lignes' => NatureAffaire::orderBy('libelle')->get(),
        ] + $this->listes());
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('natures_affaire.voir');
        $m = NatureAffaire::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? NatureAffaire::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'natures_affaire.modifier' : 'natures_affaire.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'registre' => ['required', Rule::in(array_keys(NatureAffaire::REGISTRES))],
            'actif' => ['nullable', 'boolean'],
            'exclue_enrolement' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Nature d\'affaire » est obligatoire.',
            'registre.required' => 'Le champ « Registre » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['registre'] = filled($donnees['registre'] ?? null) ? $donnees['registre'] : null;
        $donnees['actif'] = $request->boolean('actif');
        $donnees['exclue_enrolement'] = $request->boolean('exclue_enrolement');
        $doublon = NatureAffaire::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($donnees['registre'] !== null, fn ($q) => $q->where('registre', $donnees['registre']), fn ($q) => $q->whereNull('registre'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Cette nature d\'affaire existe déjà pour ce registre.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new NatureAffaire;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'nature_affaire_modifie' : 'nature_affaire_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('referentiels.natures_affaire')->with('success', $avant ? 'Nature d\'affaire modifiée.' : 'Nature d\'affaire ajoutée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('natures_affaire.supprimer');
        $m = NatureAffaire::findOrFail($id);

        if (DB::table('objets_litige')->where('nature_affaire_id', $m->id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Cet élément est utilisé par des objets du litige : modifiez-les d\'abord.']);
        }

        if (DB::table('delais_procedure')->where('nature_affaire_id', $m->id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Cet élément est utilisé par des délais de procédure : modifiez-les d\'abord.']);
        }

        Journal::audit('nature_affaire_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Nature d\'affaire supprimée.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    private function listes(): array
    {
        return [
            'opt_registre' => NatureAffaire::REGISTRES,
        ];
    }
}
