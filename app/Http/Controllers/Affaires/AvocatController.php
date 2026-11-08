<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Avocat;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Référentiel des avocats et cabinets. Droits : avocats.voir / creer / modifier / supprimer.
 | Un avocat déjà cité dans des affaires ne se supprime pas : on le désactive (il ne sera plus proposé à l'enrôlement).
 */
class AvocatController extends Controller
{
    private const CHAMPS = ['type', 'nom', 'prenoms', 'cabinet', 'barreau_numero', 'telephone', 'email', 'actif', 'a_completer'];

    public function index(): View
    {
        Gate::authorize('avocats.voir');

        return view('affaires.avocats.index', ['total' => Avocat::count(), 'acompleter' => Avocat::where('a_completer', true)->count()]);
    }

    /** Les lignes de la page, une page à la fois (tri, recherche et filtre d'état dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('avocats.voir');
        $base = Avocat::withCount('parties')->orderBy('nom')->orderBy('prenoms');
        $f = (string) $request->input('filtre.4', '');
        if ($f === 'completer') {
            $base->where('a_completer', true);
        } elseif ($f === 'inactif') {
            $base->where('actif', false);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['nom', 'prenoms'], 1 => ['barreau_numero'], 3 => ['parties_count'], 4 => ['actif']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['nom', 'prenoms', 'cabinet', 'barreau_numero', 'telephone', 'email']),
            fn (Avocat $l) => view('affaires.avocats._ligne', ['l' => $l])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('avocats.voir');
        $a = Avocat::findOrFail($id);

        return response()->json(['id' => $a->id] + $a->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $a = $request->filled('id_') ? Avocat::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($a ? 'avocats.modifier' : 'avocats.creer');

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Avocat::TYPES))],
            'nom' => ['required', 'string', 'max:160'],
            'prenoms' => ['nullable', 'string', 'max:160'],
            'cabinet' => ['nullable', 'string', 'max:200'],
            'barreau_numero' => ['nullable', 'string', 'max:60'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
        ], [
            'nom.required' => 'Le nom est obligatoire.',
            'type.required' => "Précisez s'il s'agit d'un avocat ou d'un cabinet.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
        ]);

        foreach (['nom', 'prenoms', 'cabinet', 'barreau_numero', 'telephone', 'email'] as $champ) {
            $donnees[$champ] = filled($donnees[$champ] ?? null) ? trim($donnees[$champ]) : null;
        }
        if ($donnees['type'] === 'cabinet') {
            $donnees['prenoms'] = null;
        }
        $donnees['actif'] = $request->boolean('actif');
        $donnees['a_completer'] = false;
        $donnees['cle'] = Avocat::cle($donnees['type'] === 'cabinet' ? $donnees['nom'] : trim($donnees['nom'].' '.$donnees['prenoms']));
        if ($donnees['cle'] === '') {
            throw ValidationException::withMessages(['nom' => 'Le nom doit contenir des lettres.']);
        }

        $autre = Avocat::where('cle', $donnees['cle'])->when($a, fn ($q) => $q->whereKeyNot($a->id))->first();
        if ($autre) {
            throw ValidationException::withMessages(['nom' => 'Cette personne figure déjà dans la liste : '.$autre->libelle().'.']);
        }

        $avant = $a ? $a->only(self::CHAMPS) : [];
        $a ??= new Avocat;
        $a->forceFill($donnees)->save();
        // Les affaires déjà enrôlées gardent l'écriture de la fiche à jour.
        $a->parties()->update(['avocat' => $a->libelle()]);

        Journal::audit($avant ? 'avocat_modifie' : 'avocat_cree', $a, $a->libelle(), $avant, $a->only(self::CHAMPS));

        return redirect()->route('affaires.avocats')->with('success', $avant ? 'Avocat modifié.' : 'Avocat ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('avocats.supprimer');
        $a = Avocat::withCount('parties')->findOrFail($id);

        if ($a->parties_count > 0) {
            return response()->json(['status' => false, 'message' => 'Cet avocat figure dans '.$a->parties_count." partie(s) d'affaires : désactivez-le plutôt que de le supprimer."]);
        }
        Journal::audit('avocat_supprime', $a, $a->libelle(), $a->only(self::CHAMPS));
        $a->delete();

        return response()->json(['status' => true, 'message' => 'Avocat supprimé.']);
    }
}
