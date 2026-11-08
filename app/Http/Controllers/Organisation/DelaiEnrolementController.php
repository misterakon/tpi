<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Dates limites d'enrôlement (article 7) : pour chaque jour d'audience, jour et heure de clôture de la semaine précédente.
 | Droits : delais.voir / creer / modifier / supprimer.
 */
class DelaiEnrolementController extends Controller
{
    private const CHAMPS = ['jour_audience', 'jour_limite', 'heure', 'remarque'];

    public function index(): View
    {
        Gate::authorize('delais.voir');

        return view('organisation.enrolement.index', ['lignes' => DelaiEnrolement::orderBy('jour_audience')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('delais.voir');
        $d = DelaiEnrolement::findOrFail($id);

        return response()->json(['id' => $d->id, 'jour_audience' => $d->jour_audience, 'jour_limite' => $d->jour_limite, 'heure' => substr($d->heure, 0, 5), 'remarque' => $d->remarque]);
    }

    /** Calcul d'essai : date limite d'enrôlement pour une date d'audience. */
    public function calculer(Request $request): JsonResponse
    {
        Gate::authorize('delais.voir');
        $request->validate(['date' => ['required', 'date']], ['date.required' => "Indiquez la date de l'audience.", 'date.date' => 'Date invalide.']);

        $limite = DelaiEnrolement::limitePour($request->query('date'));

        return response()->json([
            'status' => (bool) $limite,
            'message' => $limite
                ? 'Enrôlement jusqu\'au '.Creneau::JOURS[$limite->isoWeekday()].' '.$limite->format('d/m/Y').' à '.$limite->format('H\hi').'.'
                : "Aucune règle d'enrôlement pour ce jour d'audience.",
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $d = $request->filled('id_') ? DelaiEnrolement::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($d ? 'delais.modifier' : 'delais.creer');

        $donnees = $request->validate([
            'jour_audience' => ['required', 'integer', 'between:1,7'],
            'jour_limite' => ['required', 'integer', 'between:1,7'],
            'heure' => ['required', 'date_format:H:i'],
            'remarque' => ['nullable', 'string', 'max:255'],
        ], [
            'jour_audience.required' => "Choisissez le jour de l'audience.", 'jour_limite.required' => 'Choisissez le jour limite.',
            'heure.required' => "L'heure limite est obligatoire.", 'heure.date_format' => "L'heure est invalide (exemple : 16:00).",
        ]);

        $donnees['remarque'] = filled($donnees['remarque'] ?? null) ? trim($donnees['remarque']) : null;

        if (DelaiEnrolement::where('jour_audience', $donnees['jour_audience'])->when($d, fn ($q) => $q->whereKeyNot($d->id))->exists()) {
            throw ValidationException::withMessages(['jour_audience' => 'Une règle existe déjà pour ce jour d\'audience : modifiez-la.']);
        }

        $avant = $d ? $d->only(self::CHAMPS) : [];
        $d ??= new DelaiEnrolement;
        $d->forceFill($donnees)->save();

        Journal::audit($avant ? 'enrolement_modifie' : 'enrolement_cree', $d, 'Audience du '.mb_strtolower(Creneau::JOURS[$d->jour_audience]), $avant, $d->only(self::CHAMPS));

        return redirect()->route('organisation.enrolement')->with('success', $avant ? 'Règle modifiée.' : 'Règle ajoutée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delais.supprimer');
        $d = DelaiEnrolement::findOrFail($id);

        Journal::audit('enrolement_supprime', $d, 'Audience du '.mb_strtolower(Creneau::JOURS[$d->jour_audience]), $d->only(self::CHAMPS));
        $d->delete();

        return response()->json(['status' => true, 'message' => 'Règle supprimée.']);
    }
}
