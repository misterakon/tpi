<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Infraction;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Infractions et leurs articles. Droits : infractions.voir / creer / modifier / supprimer. */
class InfractionController extends Controller
{
    private const CHAMPS = ['libelle', 'articles', 'actif'];

    public function index(): View
    {
        Gate::authorize('infractions.voir');

        return view('referentiels.infractions.index', ['total' => Infraction::count(), 'sansArticles' => Infraction::whereNull('articles')->count()]);
    }

    /** Les lignes de la page, une page à la fois (tri et recherche dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('infractions.voir');

        return TableauServeur::repondre(
            $request, Infraction::orderBy('libelle'),
            [0 => ['libelle'], 1 => ['articles'], 2 => ['actif']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['libelle', 'articles']),
            fn (Infraction $l) => view('referentiels.infractions._ligne', ['l' => $l])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('infractions.voir');
        $i = Infraction::findOrFail($id);

        return response()->json(['id' => $i->id] + $i->only(self::CHAMPS));
    }

    public function store(Request $request): RedirectResponse
    {
        $i = $request->filled('id_') ? Infraction::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($i ? 'infractions.modifier' : 'infractions.creer');

        $d = $request->validate(['libelle' => ['required', 'string', 'max:200'], 'articles' => ['nullable', 'string', 'max:300'], 'actif' => ['nullable', 'boolean']], ['libelle.required' => 'Indiquez l\'infraction.']);
        $d['libelle'] = trim($d['libelle']);
        $d['articles'] = filled($d['articles'] ?? null) ? trim($d['articles']) : null;
        $d['actif'] = $request->boolean('actif');
        if (Infraction::where('libelle', $d['libelle'])->when($i, fn ($q) => $q->whereKeyNot($i->id))->exists()) {
            throw ValidationException::withMessages(['libelle' => 'Cette infraction existe déjà.']);
        }

        $avant = $i ? $i->only(self::CHAMPS) : [];
        $i ??= new Infraction;
        $i->forceFill($d)->save();
        Journal::audit($avant ? 'infraction_modifiee' : 'infraction_creee', $i, $i->libelle, $avant, $i->only(self::CHAMPS));

        return redirect()->route('referentiels.infractions')->with('success', $avant ? 'Infraction modifiée.' : 'Infraction ajoutée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('infractions.supprimer');
        $i = Infraction::findOrFail($id);
        Journal::audit('infraction_supprimee', $i, $i->libelle, $i->only(self::CHAMPS));
        $i->delete();

        return response()->json(['status' => true, 'message' => 'Infraction supprimée.']);
    }
}
