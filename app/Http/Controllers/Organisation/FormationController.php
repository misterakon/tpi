<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Services\Journal;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/*
 | Formations d'une chambre (facultatives) : « Formation A », « 1re formation »… Elles se gèrent depuis la page « Composition » de la chambre.
 | Une chambre sans formation nommée a une formation unique, sans nom, qui porte sa composition.
 | Droits : formations.voir / creer / modifier / supprimer.
 */
class FormationController extends Controller
{
    private const CHAMPS = ['chambre_id', 'nom', 'intitule', 'attributions', 'ordre', 'actif'];

    public function show(int $id): JsonResponse
    {
        Gate::authorize('formations.voir');

        return response()->json(Formation::findOrFail($id)->only(['id', ...self::CHAMPS]));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $formation = $request->filled('id_') ? Formation::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($formation ? 'formations.modifier' : 'formations.creer');

        $request->merge(['attributions' => TexteRiche::nettoyer($request->input('attributions'))]);

        $donnees = $request->validate([
            'chambre_id' => ['required', 'integer', 'exists:chambres,id'],
            'nom' => ['nullable', 'string', 'max:120'],
            'intitule' => ['nullable', 'string', 'max:160'],
            'attributions' => ['nullable', 'string', 'max:30000'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'actif' => ['nullable', 'boolean'],
        ], ['attributions.max' => 'Les attributions sont trop longues.']);

        if ($formation && (int) $donnees['chambre_id'] !== $formation->chambre_id) {
            abort(422, 'Une formation ne peut pas changer de chambre.');
        }

        foreach (['nom', 'intitule', 'attributions'] as $champ) {
            $donnees[$champ] = filled($donnees[$champ] ?? null) ? trim($donnees[$champ]) : null;
        }
        $donnees['actif'] = $request->boolean('actif');
        $donnees['ordre'] = (int) ($donnees['ordre'] ?? 0);
        if (! $formation && $donnees['ordre'] === 0) {
            $donnees['ordre'] = (int) Formation::where('chambre_id', $donnees['chambre_id'])->max('ordre') + 1;   // à la suite des formations existantes
        }

        $doublon = Formation::where('chambre_id', $donnees['chambre_id'])
            ->when($donnees['nom'], fn ($q, $nom) => $q->where('nom', $nom), fn ($q) => $q->whereNull('nom'))
            ->when($formation, fn ($q) => $q->whereKeyNot($formation->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['nom' => $donnees['nom'] ? 'Cette formation existe déjà dans la chambre.' : 'La composition de la chambre (sans nom de formation) existe déjà.']);
        }

        $avant = $formation ? $formation->only(self::CHAMPS) : [];
        $formation ??= new Formation;
        $formation->forceFill($donnees)->save();

        Journal::audit($avant ? 'formation_modifie' : 'formation_cree', $formation, $formation->libelleComplet(), $avant, $formation->only(self::CHAMPS));

        return redirect()->route('organisation.affectations', $formation->chambre_id)->with('success', $avant ? 'Formation modifiée.' : 'Formation créée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('formations.supprimer');
        $formation = Formation::findOrFail($id);

        if ($formation->affectations()->exists() || $formation->creneaux()->exists()) {
            return response()->json(['status' => false, 'message' => "Cette formation a une composition ou un calendrier : retirez-les d'abord, ou désactivez la formation."]);
        }

        Journal::audit('formation_supprime', $formation, $formation->libelleComplet(), $formation->only(self::CHAMPS));
        $formation->delete();

        return response()->json(['status' => true, 'message' => 'Formation supprimée.']);
    }
}
