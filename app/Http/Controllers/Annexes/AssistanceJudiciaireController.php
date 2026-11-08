<?php

namespace App\Http\Controllers\Annexes;

use App\Http\Controllers\Controller;
use App\Models\AssistanceJudiciaire;
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
 | Assistance judiciaire : les demandes examinées par le bureau local (demandeur, objet, nature, audience, décision, recours), comme dans le classeur du tribunal.
 | Droits : assistance.voir / creer / modifier / supprimer. Les chiffres du récapitulatif (reçues, traitées, accords, rejets, durée) se calculent seuls.
 */
class AssistanceJudiciaireController extends Controller
{
    public function index(): View
    {
        Gate::authorize('assistance.voir');
        $base = AssistanceJudiciaire::query();
        $durees = (clone $base)->whereNotNull('date_decision')->get(['date_rg', 'date_decision'])->map->dureeJours()->filter(fn ($d) => $d !== null);
        $traitees = (clone $base)->whereNotNull('decision')->count();

        return view('annexes.assistance.index', [
            'natures' => AssistanceJudiciaire::NATURES, 'decisions' => AssistanceJudiciaire::DECISIONS,
            'compte' => [
                'recues' => (clone $base)->count(), 'traitees' => $traitees, 'accords' => (clone $base)->where('decision', 'accord')->count(), 'rejets' => (clone $base)->where('decision', 'rejet')->count(),
                'a_traiter' => (clone $base)->whereNull('decision')->count(), 'duree_moyenne' => $durees->isEmpty() ? null : (int) round($durees->avg()), 'recours' => (clone $base)->whereNotNull('date_recours')->count(),
            ],
            'peutCreer' => Gate::allows('assistance.creer'), 'peutModifier' => Gate::allows('assistance.modifier'), 'peutSupprimer' => Gate::allows('assistance.supprimer'),
        ]);
    }

    /** Les lignes de la page, une page à la fois (tri, recherche et filtre de décision dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('assistance.voir');
        $base = AssistanceJudiciaire::orderByDesc('date_rg')->orderByDesc('id');
        $f = (string) $request->input('filtre.5', '');
        if ($f === 'a_traiter') {
            $base->whereNull('decision');
        } elseif (in_array($f, ['accord', 'rejet'], true)) {
            $base->where('decision', $f);
        }
        $vars = ['decisions' => AssistanceJudiciaire::DECISIONS, 'peutModifier' => Gate::allows('assistance.modifier'), 'peutSupprimer' => Gate::allows('assistance.supprimer')];

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['date_rg', 'id'], 3 => ['date_audience'], 4 => ['decision'], 5 => ['date_recours']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['numero', 'demandeur', 'objet', 'nature', 'numero_decision', 'observation']),
            fn (AssistanceJudiciaire $l) => view('annexes.assistance._ligne', $vars + ['l' => $l])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('assistance.voir');
        $a = AssistanceJudiciaire::findOrFail($id);
        $date = fn ($d) => $d?->format('Y-m-d');

        return response()->json($a->only(['id', 'numero', 'demandeur', 'objet', 'nature', 'decision', 'numero_decision', 'observation']) + [
            'date_rg' => $date($a->date_rg), 'date_audience' => $date($a->date_audience), 'date_decision' => $date($a->date_decision), 'date_recours' => $date($a->date_recours), 'date_transmission_recours' => $date($a->date_transmission_recours),
        ]);
    }

    /** Création (id_ vide) ou modification. */
    public function store(Request $request): RedirectResponse
    {
        $a = $request->filled('id_') ? AssistanceJudiciaire::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($a ? 'assistance.modifier' : 'assistance.creer');
        $v = $request->validate([
            'numero' => ['required', 'string', 'max:40'], 'date_rg' => ['required', 'date', 'before_or_equal:today'], 'demandeur' => ['required', 'string', 'max:200'], 'objet' => ['nullable', 'string', 'max:300'],
            'nature' => ['nullable', 'string', 'max:60'], 'date_audience' => ['nullable', 'date'], 'decision' => ['nullable', Rule::in(array_keys(AssistanceJudiciaire::DECISIONS))], 'numero_decision' => ['nullable', 'string', 'max:40'],
            'date_decision' => ['nullable', 'date', 'before_or_equal:today'], 'date_recours' => ['nullable', 'date', 'before_or_equal:today'], 'date_transmission_recours' => ['nullable', 'date', 'before_or_equal:today'], 'observation' => ['nullable', 'string', 'max:500'],
        ], ['numero.required' => 'Indiquez le numéro et la date du RG.', 'date_rg.required' => 'Indiquez la date du RG.', 'demandeur.required' => 'Indiquez le nom du demandeur.', 'date_rg.before_or_equal' => 'La date du RG ne peut pas être dans le futur.']);

        $e = [];
        if (! empty($v['date_audience']) && $v['date_audience'] < $v['date_rg']) {
            $e['date_audience'] = 'L\'audience ne peut pas précéder la réception de la demande.';
        }
        if (! empty($v['date_decision']) && $v['date_decision'] < $v['date_rg']) {
            $e['date_decision'] = 'La décision ne peut pas précéder la réception de la demande.';
        }
        if (! empty($v['date_decision']) && empty($v['decision'])) {
            $e['decision'] = 'Indiquez la décision (accord ou rejet).';
        }
        if (! empty($v['decision']) && empty($v['date_decision'])) {
            $e['date_decision'] = 'Indiquez la date de la décision.';
        }
        if (! empty($v['date_recours']) && (empty($v['date_decision']) || $v['date_recours'] < $v['date_decision'])) {
            $e['date_recours'] = 'Le recours suit la décision.';
        }
        if (! empty($v['date_transmission_recours']) && (empty($v['date_recours']) || $v['date_transmission_recours'] < $v['date_recours'])) {
            $e['date_transmission_recours'] = 'La transmission du recours suit le recours.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $a ? $a->update($v) : $a = AssistanceJudiciaire::create($v + ['cree_par' => $request->user()?->id]);
        Journal::audit($request->filled('id_') ? 'assistance_modifiee' : 'assistance_enregistree', $a, $a->numero.' : '.$a->demandeur, [], array_filter(['decision' => $a->decision, 'date_decision' => $a->date_decision?->toDateString()]));

        return redirect()->route('assistance')->with('success', 'Demande enregistrée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('assistance.supprimer');
        $a = AssistanceJudiciaire::findOrFail($id);
        Journal::audit('assistance_supprimee', $a, $a->numero.' : '.$a->demandeur);
        $a->delete();

        return response()->json(['status' => true, 'message' => 'Demande supprimée.']);
    }
}
