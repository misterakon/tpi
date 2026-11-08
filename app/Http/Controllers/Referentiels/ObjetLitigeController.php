<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\NatureAffaire;
use App\Models\ObjetLitige;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Objets du litige. Droits : objets_litige.voir / creer / modifier / supprimer. */
class ObjetLitigeController extends Controller
{
    private const CHAMPS = ['libelle', 'matiere_id', 'nature_affaire_id', 'chambre_libelle', 'actif'];

    public function index(): View
    {
        Gate::authorize('objets_litige.voir');

        return view('referentiels.objets_litige.index', [
            'total' => ObjetLitige::count(),
        ] + $this->listes());
    }

    /** Les lignes de la page, une page à la fois (tri et recherche dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('objets_litige.voir');

        return TableauServeur::repondre(
            $request, ObjetLitige::with('matiere', 'nature')->orderBy('libelle'),
            [0 => ['libelle'], 3 => ['actif']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['libelle', 'chambre_libelle']),
            fn (ObjetLitige $l) => view('referentiels.objets_litige._ligne', ['l' => $l])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('objets_litige.voir');
        $m = ObjetLitige::findOrFail($id);

        return response()->json(['id' => $m->id] + $m->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? ObjetLitige::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'objets_litige.modifier' : 'objets_litige.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'matiere_id' => ['required', 'integer', 'exists:matieres,id'],
            'nature_affaire_id' => ['nullable', 'integer', 'exists:natures_affaire,id'],
            'chambre_libelle' => ['nullable', 'string', 'max:120'],
            'actif' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le champ « Objet du litige » est obligatoire.',
            'matiere_id.required' => 'Le champ « Matière » est obligatoire.',
        ]);

        $donnees['libelle'] = filled($donnees['libelle'] ?? null) ? trim($donnees['libelle']) : null;
        $donnees['matiere_id'] = filled($donnees['matiere_id'] ?? null) ? (int) $donnees['matiere_id'] : null;
        $donnees['nature_affaire_id'] = filled($donnees['nature_affaire_id'] ?? null) ? (int) $donnees['nature_affaire_id'] : null;
        $donnees['chambre_libelle'] = filled($donnees['chambre_libelle'] ?? null) ? $donnees['chambre_libelle'] : null;
        $donnees['actif'] = $request->boolean('actif');
        $doublon = ObjetLitige::query()
            ->when($donnees['libelle'] !== null, fn ($q) => $q->where('libelle', $donnees['libelle']), fn ($q) => $q->whereNull('libelle'))
            ->when($donnees['chambre_libelle'] !== null, fn ($q) => $q->where('chambre_libelle', $donnees['chambre_libelle']), fn ($q) => $q->whereNull('chambre_libelle'))
            ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['libelle' => 'Cet objet existe déjà pour cette chambre.']);
        }

        $avant = $m ? $m->only(self::CHAMPS) : [];
        $m ??= new ObjetLitige;
        $m->forceFill($donnees)->save();

        Journal::audit($avant ? 'objet_litige_modifie' : 'objet_litige_cree', $m, $m->libelle, $avant, $m->only(self::CHAMPS));

        return redirect()->route('referentiels.objets_litige')->with('success', $avant ? 'Objet du litige modifié.' : 'Objet du litige ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('objets_litige.supprimer');
        $m = ObjetLitige::findOrFail($id);

        Journal::audit('objet_litige_supprime', $m, $m->libelle, $m->only(self::CHAMPS));
        $m->delete();

        return response()->json(['status' => true, 'message' => 'Objet du litige supprimé.']);
    }

    /** Listes déroulantes du formulaire. @return array<string, array<int|string, string>> */
    private function listes(): array
    {
        return [
            'opt_matiere_id' => Matiere::orderBy('libelle')->pluck('libelle', 'id')->all(),
            'opt_nature_affaire_id' => NatureAffaire::orderBy('registre')->orderBy('libelle')->get()->mapWithKeys(fn ($n) => [$n->id => $n->libelle.' ('.$n->registre.')'])->all(),
            'opt_chambre_libelle' => Chambre::query()->distinct()->orderBy('libelle')->pluck('libelle', 'libelle')->all(),
        ];
    }
}
