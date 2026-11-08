<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Chambre;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Composition d'une formation : qui y siège, dans quel rôle, depuis quand et jusqu'à quand.
 | Les remplacements en cours d'année terminent l'affectation précédente (l'historique est conservé).
 | Droits : affectations.voir / creer / modifier / supprimer.
 */
class AffectationController extends Controller
{
    private const CHAMPS = ['formation_id', 'agent_id', 'role', 'suppleant', 'chef_section', 'debut', 'fin', 'remarque'];

    /** Page de composition d'une chambre : ses formations, avec pour chacune les personnes affectées et les audiences. */
    public function index(Chambre $chambre): View
    {
        Gate::authorize('affectations.voir');
        $chambre->load('section', 'annee', 'formations.creneaux.salle', 'formations.affectations.agent');

        $ordreRoles = array_flip(array_keys(Affectation::ROLES));
        foreach ($chambre->formations as $formation) {
            $formation->setRelation('affectations', $formation->affectations->sortBy([
                fn ($a, $b) => ($ordreRoles[$a->role] ?? 99) <=> ($ordreRoles[$b->role] ?? 99),
                fn ($a, $b) => $a->suppleant <=> $b->suppleant,
                fn ($a, $b) => $b->chef_section <=> $a->chef_section,
                fn ($a, $b) => strcmp((string) $a->agent?->nom, (string) $b->agent?->nom),
            ])->values());
        }

        $dejaPresents = $chambre->formations->flatMap->affectations->pluck('agent_id')->all();

        return view('organisation.affectations.index', [
            'chambre' => $chambre,
            'salles' => \App\Models\Salle::where('actif', true)->orderBy('nom')->get(),
            'ordonnances' => \App\Models\Ordonnance::orderByDesc('date_signature')->get(),
            'agents' => Agent::query()->where('actif', true)->orWhereIn('id', $dejaPresents)->orderBy('nom')->orderBy('prenoms')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('affectations.voir');
        $a = Affectation::findOrFail($id);

        return response()->json([
            'id' => $a->id, 'formation_id' => $a->formation_id, 'agent_id' => $a->agent_id, 'role' => $a->role, 'suppleant' => $a->suppleant, 'chef_section' => $a->chef_section,
            'debut' => $a->debut?->format('Y-m-d'), 'fin' => $a->fin?->format('Y-m-d'), 'remarque' => $a->remarque,
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $affectation = $request->filled('id_') ? Affectation::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($affectation ? 'affectations.modifier' : 'affectations.creer');

        $donnees = $request->validate([
            'formation_id' => ['required', 'integer', 'exists:formations,id'],
            'agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(Affectation::ROLES))],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'remarque' => ['nullable', 'string', 'max:200'],
        ], [
            'agent_id.required' => 'Choisissez la personne.', 'role.required' => 'Choisissez le rôle.', 'role.in' => 'Rôle invalide.',
            'fin.after_or_equal' => 'La fin doit être après le début.', 'debut.date' => 'Date de début invalide.', 'fin.date' => 'Date de fin invalide.',
        ]);

        if ($affectation && (int) $donnees['formation_id'] !== $affectation->formation_id) {
            abort(422, 'Une affectation ne peut pas changer de formation.');
        }

        $donnees['suppleant'] = $request->boolean('suppleant');
        $donnees['chef_section'] = $request->boolean('chef_section');
        $donnees['debut'] = $donnees['debut'] ?? null;
        $donnees['fin'] = $donnees['fin'] ?? null;
        $donnees['remarque'] = filled($donnees['remarque'] ?? null) ? trim($donnees['remarque']) : null;

        $this->verifierChevauchement($donnees, $affectation);

        $avant = $affectation ? $this->valeurs($affectation) : [];
        $affectation ??= new Affectation;
        $affectation->forceFill($donnees)->save();

        Journal::audit($avant ? 'affectation_modifie' : 'affectation_cree', $affectation, $this->libelle($affectation), $avant, $this->valeurs($affectation));

        return redirect()->route('organisation.affectations', $affectation->formation->chambre_id)->with('success', $avant ? 'Affectation modifiée.' : 'Personne ajoutée à la formation.');
    }

    /** Remplace une personne à une date donnée : l'ancienne affectation se termine la veille, la nouvelle commence ce jour-là. */
    public function remplacer(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('affectations.modifier');
        Gate::authorize('affectations.creer');
        $ancienne = Affectation::findOrFail($id);

        $donnees = $request->validate([
            'nouvel_agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at'), Rule::notIn([$ancienne->agent_id])],
            'date_effet' => ['required', 'date'],
        ], [
            'nouvel_agent_id.required' => 'Choisissez la personne qui remplace.', 'nouvel_agent_id.not_in' => 'La personne qui remplace doit être différente.',
            'date_effet.required' => "Indiquez la date d'effet du remplacement.", 'date_effet.date' => "Date d'effet invalide.",
        ]);

        $effet = Carbon::parse($donnees['date_effet'])->startOfDay();
        if ($ancienne->debut && $effet->lte($ancienne->debut)) {
            throw ValidationException::withMessages(['date_effet' => "La date d'effet doit être après le début de l'affectation actuelle ({$ancienne->debut->format('d/m/Y')})."]);
        }
        if ($ancienne->fin && $effet->gt($ancienne->fin)) {
            throw ValidationException::withMessages(['date_effet' => "Cette affectation est déjà terminée le {$ancienne->fin->format('d/m/Y')}."]);
        }

        $nouvelle = DB::transaction(function () use ($ancienne, $donnees, $effet) {
            $ancienne->forceFill(['fin' => $effet->copy()->subDay()])->save();

            return Affectation::create([
                'formation_id' => $ancienne->formation_id, 'agent_id' => $donnees['nouvel_agent_id'], 'role' => $ancienne->role,
                'suppleant' => $ancienne->suppleant, 'chef_section' => $ancienne->chef_section, 'debut' => $effet, 'fin' => null,
                'remarque' => 'En remplacement de '.$ancienne->agent->nomComplet(),
            ]);
        });

        Journal::audit('affectation_remplacee', $nouvelle, $this->libelle($nouvelle), ['agent_id' => $ancienne->agent_id, 'fin' => $ancienne->fin?->format('Y-m-d')], $this->valeurs($nouvelle));

        return redirect()->route('organisation.affectations', $ancienne->formation->chambre_id)->with('success', 'Remplacement enregistré : l\'ancienne affectation est terminée, la nouvelle commence le '.$effet->format('d/m/Y').'.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('affectations.supprimer');
        $affectation = Affectation::with('agent', 'formation')->findOrFail($id);

        Journal::audit('affectation_supprime', $affectation, $this->libelle($affectation), $this->valeurs($affectation));
        $affectation->delete();

        return response()->json(['status' => true, 'message' => 'Affectation supprimée.']);
    }

    /** La même personne ne peut pas occuper deux fois le même rôle dans la formation sur des périodes qui se recouvrent. */
    private function verifierChevauchement(array $d, ?Affectation $courante): void
    {
        $debut = $d['debut'] ?? '0001-01-01';
        $fin = $d['fin'] ?? '9999-12-31';

        $autre = Affectation::query()
            ->where('formation_id', $d['formation_id'])->where('agent_id', $d['agent_id'])->where('role', $d['role'])->where('suppleant', $d['suppleant'])
            ->when($courante, fn ($q) => $q->whereKeyNot($courante->id))
            ->get()
            ->first(fn (Affectation $a) => ($a->debut?->format('Y-m-d') ?? '0001-01-01') <= $fin && ($a->fin?->format('Y-m-d') ?? '9999-12-31') >= $debut);

        if ($autre) {
            throw ValidationException::withMessages(['agent_id' => 'Cette personne a déjà cette affectation dans la formation sur cette période.']);
        }
    }

    private function valeurs(Affectation $a): array
    {
        $v = $a->only(self::CHAMPS);
        $v['debut'] = $a->debut?->format('Y-m-d');
        $v['fin'] = $a->fin?->format('Y-m-d');

        return $v;
    }

    private function libelle(Affectation $a): string
    {
        $role = (Affectation::ROLES[$a->role] ?? $a->role).($a->suppleant ? ' suppléant' : '');

        return ($a->agent?->nomComplet() ?? 'Agent n°'.$a->agent_id).' — '.$role.' — '.($a->formation?->libelleComplet() ?? 'Formation n°'.$a->formation_id);
    }
}
