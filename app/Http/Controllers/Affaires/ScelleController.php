<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Scelle;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/*
 | Scellés d'une affaire pénale ou d'instruction (pièces à conviction conservées au greffe). Droits : scelles.voir / creer / modifier / supprimer.
 | Un scellé rendu, détruit ou confisqué garde sa ligne : seul son état change, avec sa date.
 */
class ScelleController extends Controller
{
    private const CHAMPS = ['numero', 'designation', 'nature', 'date_saisie', 'lieu_conservation', 'etat', 'date_etat', 'observations'];

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(int $affaireId, Request $request): RedirectResponse
    {
        $affaire = Affaire::visiblesPour($request->user())->findOrFail($affaireId);
        abort_unless(in_array($affaire->serie, ['RP', 'RI', 'RE'], true), 404);
        abort_if($affaire->estAnnulee(), 403, 'Une affaire annulée ne reçoit plus de scellés.');

        $scelle = $request->filled('id_') ? $affaire->scelles()->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($scelle ? 'scelles.modifier' : 'scelles.creer');

        $donnees = $request->validate([
            'numero' => ['required', 'string', 'max:40'],
            'designation' => ['required', 'string', 'max:250'],
            'nature' => ['required', Rule::in(array_keys(Scelle::NATURES))],
            'date_saisie' => ['nullable', 'date', 'before_or_equal:today'],
            'lieu_conservation' => ['nullable', 'string', 'max:160'],
            'etat' => ['required', Rule::in(array_keys(Scelle::ETATS))],
            'date_etat' => ['nullable', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:500'],
        ], [
            'numero.required' => 'Le numéro du scellé est obligatoire.', 'designation.required' => 'Décrivez le scellé.',
            'nature.required' => 'Choisissez la nature.', 'etat.required' => "Choisissez l'état du scellé.",
        ]);
        foreach (['numero', 'designation', 'lieu_conservation', 'observations'] as $c) {
            $donnees[$c] = filled($donnees[$c] ?? null) ? trim($donnees[$c]) : null;
        }
        // Un scellé qui n'est plus conservé a une date d'état (celle du jour par défaut) ; un scellé conservé n'en a pas.
        $donnees['date_etat'] = $donnees['etat'] === 'conserve' ? null : (($donnees['date_etat'] ?? null) ?: now()->toDateString());

        $doublon = $affaire->scelles()->where('numero', $donnees['numero'])->when($scelle, fn ($q) => $q->whereKeyNot($scelle->id))->exists();
        if ($doublon) {
            return back()->withInput()->withErrors(['numero' => 'Ce numéro de scellé existe déjà dans cette affaire.'], 'scelle');
        }

        $avant = $scelle ? $scelle->only(self::CHAMPS) : [];
        $scelle ??= new Scelle(['affaire_id' => $affaire->id, 'cree_par' => $request->user()?->id]);
        $scelle->forceFill($donnees)->save();
        Journal::audit($avant ? 'scelle_modifie' : 'scelle_cree', $scelle, $affaire->numeroAffichage().' - scellé '.$scelle->numero, $avant, $scelle->only(self::CHAMPS));

        return redirect(route('affaires.liste.fiche', $affaire->id).'#scelles')->with('success', $avant ? 'Scellé modifié.' : 'Scellé ajouté.');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        Gate::authorize('scelles.supprimer');
        $scelle = Scelle::findOrFail($id);
        Affaire::visiblesPour($request->user())->findOrFail($scelle->affaire_id);

        Journal::audit('scelle_supprime', $scelle, $scelle->affaire?->numeroAffichage().' - scellé '.$scelle->numero, $scelle->only(self::CHAMPS));
        $scelle->delete();

        return response()->json(['status' => true, 'message' => 'Scellé supprimé.']);
    }
}
