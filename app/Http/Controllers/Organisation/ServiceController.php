<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Service;
use App\Services\Journal;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | Services, comités, délégations et fonctions particulières fixés par l'ordonnance de l'année, avec les agents concernés
 | (coordination, comité de direction, services annexes, bureaux locaux, juges taxateurs…).
 | Droits : services.voir / creer / modifier / supprimer.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('services.voir');

        $annees = AnneeJudiciaire::orderByDesc('debut')->get();
        $annee = $annees->firstWhere('id', (int) $request->query('annee')) ?? AnneeJudiciaire::courante() ?? $annees->first();

        $lignes = Service::query()->with('agents.agent')
            ->when($annee, fn ($q) => $q->where('annee_judiciaire_id', $annee->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('ordre')->orderBy('libelle')->get()
            ->sortBy(fn (Service $s) => array_search($s->categorie, array_keys(Service::CATEGORIES), true))->values();

        return view('organisation.services.index', [
            'annees' => $annees,
            'annee' => $annee,
            'lignes' => $lignes,
            'agents' => Agent::query()->where('actif', true)->orderBy('nom')->orderBy('prenoms')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('services.voir');
        $service = Service::with('agents')->findOrFail($id);

        return response()->json([
            'id' => $service->id, 'annee_judiciaire_id' => $service->annee_judiciaire_id, 'categorie' => $service->categorie,
            'libelle' => $service->libelle, 'ordre' => $service->ordre, 'remarque' => $service->remarque,
            'agents' => $service->agents->map(fn ($a) => ['agent_id' => $a->agent_id, 'fonction' => $a->fonction])->values(),
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $service = $request->filled('id_') ? Service::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($service ? 'services.modifier' : 'services.creer');

        $request->merge(['remarque' => TexteRiche::nettoyer($request->input('remarque'))]);

        $donnees = $request->validate([
            'annee_judiciaire_id' => ['required', 'integer', 'exists:annees_judiciaires,id'],
            'categorie' => ['required', Rule::in(array_keys(Service::CATEGORIES))],
            'libelle' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remarque' => ['nullable', 'string', 'max:10000'],
            'agents' => ['nullable', 'array', 'max:60'],
            'agents.*.agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
            'agents.*.fonction' => ['nullable', 'string', 'max:160'],
        ], [
            'annee_judiciaire_id.required' => "Choisissez l'année judiciaire.", 'categorie.required' => 'Choisissez la catégorie.', 'categorie.in' => 'Catégorie invalide.',
            'libelle.required' => 'Le libellé est obligatoire.', 'agents.*.agent_id.required' => 'Choisissez une personne ou retirez la ligne vide.',
            'agents.*.agent_id.exists' => 'Agent introuvable.', 'remarque.max' => 'La remarque est trop longue.',
        ]);

        $avant = $service ? $this->valeurs($service) : [];

        $service = DB::transaction(function () use ($service, $donnees) {
            $service ??= new Service;
            $service->forceFill([
                'annee_judiciaire_id' => $donnees['annee_judiciaire_id'], 'categorie' => $donnees['categorie'], 'libelle' => trim($donnees['libelle']),
                'ordre' => (int) ($donnees['ordre'] ?? 0), 'remarque' => filled($donnees['remarque'] ?? null) ? trim($donnees['remarque']) : null,
            ])->save();

            $service->agents()->delete();
            foreach (array_values($donnees['agents'] ?? []) as $i => $ligne) {
                $service->agents()->create(['agent_id' => $ligne['agent_id'], 'fonction' => filled($ligne['fonction'] ?? null) ? trim($ligne['fonction']) : null, 'ordre' => $i + 1]);
            }

            return $service;
        });

        Journal::audit($avant ? 'service_modifie' : 'service_cree', $service, $service->libelle, $avant, $this->valeurs($service->refresh()));

        return redirect()->route('organisation.services', ['annee' => $service->annee_judiciaire_id])->with('success', $avant ? 'Service modifié.' : 'Service créé.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('services.supprimer');
        $service = Service::with('agents')->findOrFail($id);

        Journal::audit('service_supprime', $service, $service->libelle, $this->valeurs($service));
        $service->delete();

        return response()->json(['status' => true, 'message' => 'Service supprimé.']);
    }

    private function valeurs(Service $s): array
    {
        return [
            'annee_judiciaire_id' => $s->annee_judiciaire_id, 'categorie' => $s->categorie, 'libelle' => $s->libelle, 'ordre' => $s->ordre, 'remarque' => $s->remarque,
            'agents' => $s->agents()->with('agent')->get()->map(fn ($a) => trim(($a->agent?->nomComplet() ?? '?').($a->fonction ? ' ('.$a->fonction.')' : '')))->implode(' ; '),
        ];
    }
}
