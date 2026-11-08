<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\DelaiProcedure;
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

/** Délais de procédure. Droits : delais_procedure.voir / creer / modifier / supprimer. */
class DelaiProcedureController extends Controller
{
    private const CHAMPS = ['libelle', 'etape', 'acteur', 'registre', 'nature_affaire_id', 'valeur', 'unite', 'renvois_max', 'point_depart', 'actif', 'a_valider'];

    /** Les délais de procédure se gèrent dans la page « Délais » (menu Alertes) : l'ancienne adresse y renvoie. */
    public function index(): RedirectResponse
    {
        Gate::authorize('delais_procedure.voir');

        return redirect()->route('alertes.delais');
    }

    /** Ce que la page « Délais » affiche pour les délais de procédure : la liste et les listes déroulantes du formulaire. @return array<string, mixed> */
    public static function donnees(): array
    {
        return [
            'lignes' => DelaiProcedure::with('nature')->orderBy('libelle')->get(),
            'registresNature' => NatureAffaire::pluck('registre', 'id')->all(),
        ] + self::listes();
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('delais_procedure.voir');
        $m = DelaiProcedure::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? DelaiProcedure::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'delais_procedure.modifier' : 'delais_procedure.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'etape' => ['required', Rule::in(array_keys(DelaiProcedure::ETAPES))],
            'acteur' => ['nullable', Rule::in(array_keys(DelaiProcedure::ACTEURS))],
            'registre' => ['nullable', Rule::in(array_keys(\App\Models\NatureAffaire::REGISTRES))],
            'nature_affaire_id' => ['nullable', 'integer', 'exists:natures_affaire,id'],
            'valeur' => ['required', 'integer', 'min:0', 'max:999'],
            'unite' => ['required', Rule::in(array_keys(DelaiProcedure::UNITES))],
            'renvois_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'point_depart' => ['required', Rule::in(array_keys(DelaiProcedure::POINTS_DEPART))],
            'actif' => ['nullable', 'boolean'],
            'a_valider' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Règle » est obligatoire.',
            'etape.required' => 'Le champ « Étape du circuit » est obligatoire.',
            'valeur.required' => 'Le champ « Délai » est obligatoire.',
            'valeur.integer' => '« Délai » doit être un nombre entier.',
            'unite.required' => 'Le champ « Unité » est obligatoire.',
            'renvois_max.integer' => '« Renvois au plus » doit être un nombre entier.',
            'point_depart.required' => 'Le champ « Le délai court à partir de » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['etape'] = filled($donnees['etape'] ?? null) ? $donnees['etape'] : null;
        $donnees['acteur'] = filled($donnees['acteur'] ?? null) ? $donnees['acteur'] : null;
        $donnees['registre'] = filled($donnees['registre'] ?? null) ? $donnees['registre'] : null;
        $donnees['nature_affaire_id'] = filled($donnees['nature_affaire_id'] ?? null) ? (int) $donnees['nature_affaire_id'] : null;
        $donnees['unite'] = filled($donnees['unite'] ?? null) ? $donnees['unite'] : null;
        $donnees['point_depart'] = filled($donnees['point_depart'] ?? null) ? $donnees['point_depart'] : null;
        $donnees['valeur'] = filled($donnees['valeur'] ?? null) ? (int) $donnees['valeur'] : null;
        $donnees['renvois_max'] = filled($donnees['renvois_max'] ?? null) ? (int) $donnees['renvois_max'] : null;
        $donnees['actif'] = $request->boolean('actif');
        $donnees['a_valider'] = $request->boolean('a_valider');

        // Cohérence : le registre se déduit de la nature d'affaire ; une procédure complète n'a pas de responsable unique ;
        // seuls la procédure complète, l'audience et le renvoi comptent des renvois.
        if ($donnees['nature_affaire_id']) {
            $donnees['registre'] = NatureAffaire::whereKey($donnees['nature_affaire_id'])->value('registre');
        }
        if ($donnees['etape'] === 'procedure_complete') {
            $donnees['acteur'] = null;
        }
        if (! in_array($donnees['etape'], ['procedure_complete', 'audience', 'renvoi_delibere'], true)) {
            $donnees['renvois_max'] = null;
        }

        $doublon = DelaiProcedure::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Cette règle existe déjà.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new DelaiProcedure;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'delai_procedure_modifie' : 'delai_procedure_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('alertes.delais')->with('success', $avant ? 'Délai d\'une étape modifié.' : 'Délai d\'une étape ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delais_procedure.supprimer');
        $m = DelaiProcedure::findOrFail($id);

        Journal::audit('delai_procedure_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Délai d\'une étape supprimé.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    public static function listes(): array
    {
        return [
            'opt_etape' => DelaiProcedure::ETAPES,
            'opt_acteur' => DelaiProcedure::ACTEURS,
            'opt_registre' => \App\Models\NatureAffaire::REGISTRES,
            'opt_nature_affaire_id' => NatureAffaire::orderBy('registre')->orderBy('libelle')->get()->mapWithKeys(fn ($n) => [$n->id => $n->libelle.' ('.$n->registre.')'])->all(),
            'opt_unite' => DelaiProcedure::UNITES,
            'opt_point_depart' => DelaiProcedure::POINTS_DEPART,
        ];
    }
}
