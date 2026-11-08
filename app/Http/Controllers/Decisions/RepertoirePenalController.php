<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\DecisionPenale;
use App\Models\Partie;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Répertoire pénal : la décision rendue pour chaque prévenu (issue, peine, amende), puis la transmission de la minute.
 | C'est le répertoire des audiences pénales tenu à la main aujourd'hui. Droits : repertoire_penal.voir / creer / modifier / supprimer.
 | Un prévenu relaxé ou acquitté qui était détenu est remis en liberté à la date de la décision (le suivi des détenus se met à jour).
 */
class RepertoirePenalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('repertoire_penal.voir');
        $user = auth()->user();
        $base = DecisionPenale::visiblesPour($user);
        $limite = today()->subDays((int) config('tpi.penal.minute_vigilance_jours', 30))->toDateString();

        $affaires = Gate::allows('repertoire_penal.creer') || Gate::allows('repertoire_penal.modifier')
            ? Affaire::visiblesPour($user)->with('parties')->whereIn('serie', ['RP', 'RI', 'RE'])->where('statut', 'enrolee')->orderByDesc('date_rg')->limit(500)->get() : collect();

        return view('decisions.repertoire_penal.index', [
            'compte' => [
                'total' => (clone $base)->count(), 'condamnes' => (clone $base)->where('issue', 'condamne')->count(),
                'minutes' => (clone $base)->whereNull('date_transmission_minute')->count(), 'retard' => (clone $base)->whereNull('date_transmission_minute')->where('date_decision', '<', $limite)->count(),
            ],
            'vigilance' => (int) config('tpi.penal.minute_vigilance_jours', 30),
            'affaires' => $affaires,
            'infractions' => \App\Models\Infraction::where('actif', true)->orderBy('libelle')->pluck('libelle')->all(),
            'prevenus' => $affaires->mapWithKeys(fn (Affaire $a) => [$a->id => $a->parties->whereIn('role', ['prevenu', 'inculpe'])->map(fn (Partie $p) => ['id' => $p->id, 'nom' => $p->libelle()])->values()->all()])->all(),
        ]);
    }

    /** Les lignes du répertoire, une page à la fois (tri, recherche et filtre de minute dans la base). */
    public function donnees(\Illuminate\Http\Request $request): JsonResponse
    {
        Gate::authorize('repertoire_penal.voir');
        $base = DecisionPenale::visiblesPour($request->user())->with(['affaire.chambre', 'partie'])->orderByDesc('date_decision')->orderByDesc('id');
        $f = (string) $request->input('filtre.5', '');
        if ($f === 'minute_a_transmettre') {
            $base->whereNull('date_transmission_minute');
        } elseif ($f === 'minute_transmise') {
            $base->whereNotNull('date_transmission_minute');
        }

        return \App\Support\TableauServeur::repondre(
            $request, $base,
            [0 => ['date_decision', 'id'], 1 => ['affaire_id'], 5 => ['date_transmission_minute']],
            fn ($q, $mots) => \App\Support\RechercheTexte::appliquer($q, $mots, ['numero_decision', 'autres_peines', 'minute_transmise_a'], 'affaire', ['numero_complet', 'objet', 'articles']),
            fn (DecisionPenale $d) => view('decisions.repertoire_penal._ligne', ['l' => $d])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('repertoire_penal.voir');
        $d = DecisionPenale::visiblesPour(auth()->user())->findOrFail($id);

        return response()->json($d->only(['id', 'affaire_id', 'partie_id', 'contradiction', 'issue', 'emprisonnement_mois', 'sursis_mois', 'amende', 'autres_peines', 'dispositif', 'numero_decision', 'infraction_retenue', 'nature_infraction', 'traite', 'victimes_hommes', 'victimes_femmes', 'victimes_garcons', 'victimes_filles']) + ['date_decision' => $d->date_decision->format('Y-m-d')]);
    }

    /** Création (id_ vide) ou correction (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $d = $request->filled('id_') ? DecisionPenale::visiblesPour($request->user())->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($d ? 'repertoire_penal.modifier' : 'repertoire_penal.creer');

        $v = $request->validate([
            'affaire_id' => ['required', 'integer'], 'partie_id' => ['nullable', 'integer'], 'date_decision' => ['required', 'date', 'before_or_equal:today'],
            'contradiction' => ['required', 'in:'.implode(',', array_keys(DecisionPenale::CONTRADICTIONS))], 'issue' => ['required', 'in:'.implode(',', array_keys(DecisionPenale::ISSUES))],
            'emprisonnement_mois' => ['nullable', 'integer', 'min:1', 'max:1200'], 'sursis_mois' => ['nullable', 'integer', 'min:1', 'max:1200'], 'amende' => ['nullable', 'integer', 'min:1', 'max:2000000000'],
            'autres_peines' => ['nullable', 'string', 'max:300'], 'infraction_retenue' => ['nullable', 'string', 'max:200'], 'nature_infraction' => ['nullable', 'in:crime,delit,contravention'], 'traite' => ['nullable', 'boolean'],
            'victimes_hommes' => ['nullable', 'integer', 'between:0,500'], 'victimes_femmes' => ['nullable', 'integer', 'between:0,500'], 'victimes_garcons' => ['nullable', 'integer', 'between:0,500'], 'victimes_filles' => ['nullable', 'integer', 'between:0,500'], 'dispositif' => ['nullable', 'string', 'max:4000'], 'numero_decision' => ['nullable', 'string', 'max:40'],
        ], ['affaire_id.required' => 'Choisissez l\'affaire.', 'date_decision.required' => 'Indiquez la date de la décision.', 'date_decision.before_or_equal' => 'La décision ne peut pas être datée du futur.', 'issue.required' => 'Indiquez l\'issue.']);

        $affaire = Affaire::visiblesPour($request->user())->whereIn('serie', ['RP', 'RI', 'RE'])->find((int) $v['affaire_id']);
        if (! $affaire) {
            throw ValidationException::withMessages(['affaire_id' => 'Cette affaire pénale est introuvable ou hors de votre périmètre.']);
        }
        if ($d && (int) $d->affaire_id !== $affaire->id) {
            throw ValidationException::withMessages(['affaire_id' => 'L\'affaire d\'une décision enregistrée ne se change pas : supprimez-la et recommencez.']);
        }
        $partie = null;
        if (! empty($v['partie_id'])) {
            $partie = Partie::where('affaire_id', $affaire->id)->whereIn('role', ['prevenu', 'inculpe'])->find((int) $v['partie_id']);
            if (! $partie) {
                throw ValidationException::withMessages(['partie_id' => 'Ce prévenu ne figure pas dans cette affaire.']);
            }
        }
        if ($v['date_decision'] < $affaire->date_rg->toDateString()) {
            throw ValidationException::withMessages(['date_decision' => 'La décision ne peut pas précéder l\'enregistrement de l\'affaire ('.$affaire->date_rg->format('d/m/Y').').']);
        }

        $aPeine = ! empty($v['emprisonnement_mois']) || ! empty($v['amende']) || filled($v['autres_peines'] ?? null);
        if ($v['issue'] === 'condamne' && ! $aPeine) {
            throw ValidationException::withMessages(['issue' => 'Un prévenu condamné a au moins une peine : emprisonnement, amende ou autre peine.']);
        }
        if (in_array($v['issue'], ['relaxe', 'acquitte', 'irrecevable', 'incompetent'], true) && $aPeine) {
            throw ValidationException::withMessages(['issue' => 'Aucune peine ne se prononce pour cette issue : retirez-la ou choisissez « Condamné ».']);
        }
        if (! empty($v['sursis_mois']) && (empty($v['emprisonnement_mois']) || $v['sursis_mois'] > $v['emprisonnement_mois'])) {
            throw ValidationException::withMessages(['sursis_mois' => 'Le sursis s\'ajoute à une peine d\'emprisonnement et ne peut pas la dépasser.']);
        }

        $champs = $v + ['partie_id' => $partie?->id, 'traite' => $request->boolean('traite')];
        $liberes = [];
        DB::transaction(function () use (&$d, $champs, $affaire, $partie, $request, &$liberes) {
            if ($d) {
                $d->update($champs);
            } else {
                $d = DecisionPenale::create($champs + ['cree_par' => $request->user()?->id]);
            }
            // Relaxé ou acquitté : le prévenu détenu est libéré à la date de la décision (une seule personne, ou tous si la décision vise l'affaire).
            if (in_array($d->issue, ['relaxe', 'acquitte'], true)) {
                $detenus = $affaire->parties()->whereIn('role', ['prevenu', 'inculpe'])->whereNotNull('mandat_depot_date')->whereNull('libere_le')->when($partie, fn ($q) => $q->whereKey($partie->id))->get();
                foreach ($detenus as $p) {
                    $p->forceFill(['libere_le' => $d->date_decision])->save();
                    $liberes[] = $p->libelle();
                }
            }
        });

        Journal::audit($request->filled('id_') ? 'decision_penale_corrigee' : 'decision_penale_enregistree', $affaire, $affaire->numeroAffichage(), [], ['issue' => $d->issue, 'prevenu' => $partie?->libelle(), 'peine' => $d->peineEnClair(), 'liberes' => $liberes]);

        return redirect()->route('decisions.repertoire_penal')->with('success', 'Décision enregistrée'.($liberes ? ' : '.implode(', ', $liberes).' remis en liberté dans le suivi des détenus.' : '.'));
    }

    public function minute(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('repertoire_penal.modifier');
        $d = DecisionPenale::visiblesPour($request->user())->with('affaire')->findOrFail($id);
        $v = $request->validate(['date_transmission_minute' => ['required', 'date', 'before_or_equal:today'], 'minute_transmise_a' => ['nullable', 'string', 'max:120']], ['date_transmission_minute.required' => 'Indiquez la date de transmission de la minute.']);
        if ($v['date_transmission_minute'] < $d->date_decision->toDateString()) {
            throw ValidationException::withMessages(['date_transmission_minute' => 'La minute ne peut pas être transmise avant la décision.']);
        }
        $d->update($v);
        Journal::audit('minute_transmise', $d->affaire, $d->affaire->numeroAffichage(), [], ['date' => $v['date_transmission_minute'], 'a' => $v['minute_transmise_a'] ?? null]);

        return redirect()->route('decisions.repertoire_penal')->with('success', 'Transmission de la minute enregistrée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('repertoire_penal.supprimer');
        $d = DecisionPenale::visiblesPour(auth()->user())->with('affaire')->findOrFail($id);
        Journal::audit('decision_penale_supprimee', $d->affaire, $d->affaire->numeroAffichage(), ['issue' => $d->issue, 'peine' => $d->peineEnClair()]);
        $d->delete();

        return response()->json(['status' => true, 'message' => 'Décision supprimée du répertoire.']);
    }
}
