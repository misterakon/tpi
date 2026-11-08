<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Alerte;
use App\Models\Chambre;
use App\Models\Formation;
use App\Models\RegleAlerte;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | Règles d'alerte (Paramètres) : à quel moment (J-3, J+1…), à qui, par quel canal. Plus le retard grandit, plus on monte dans la hiérarchie.
 | La page montre aussi, chambre par chambre, qui recevrait les alertes et si cette personne a un compte : sans compte, personne n'est prévenu.
 */
class ReglesAlerteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('regles_alerte.voir');

        return view('alertes.regles', [
            'regles' => RegleAlerte::orderBy('decalage_jours')->get(),
            'destinataires' => RegleAlerte::DESTINATAIRES, 'canaux' => RegleAlerte::CANAUX, 'niveaux' => RegleAlerte::NIVEAUX, 'types' => Alerte::TYPES,
            'peutCreer' => Gate::allows('regles_alerte.creer'), 'peutModifier' => Gate::allows('regles_alerte.modifier'), 'peutSupprimer' => Gate::allows('regles_alerte.annuler'),
        ]);
    }

    /** Qui recevrait les alertes aujourd'hui, chambre par chambre, et s'il a un compte. */
    public function destinataires(): View
    {
        Gate::authorize('regles_alerte.voir');

        return view('alertes.destinataires', ['controle' => self::controleDestinataires()]);
    }

    public function enregistrer(Request $request, ?int $id = null): JsonResponse
    {
        Gate::authorize($id ? 'regles_alerte.modifier' : 'regles_alerte.creer');
        $regle = $id ? RegleAlerte::findOrFail($id) : new RegleAlerte;

        $donnees = $request->validate([
            'decalage_jours' => ['required', 'integer', 'between:-365,365'],
            'libelle' => ['required', 'string', 'max:80'],
            'canaux' => ['required', 'array', 'min:1'], 'canaux.*' => [Rule::in(array_keys(RegleAlerte::CANAUX))],
            'destinataires' => ['required', 'array', 'min:1'], 'destinataires.*' => [Rule::in(array_keys(RegleAlerte::DESTINATAIRES))],
            'types' => ['nullable', 'array'], 'types.*' => [Rule::in(array_keys(Alerte::TYPES))],
            'niveau' => ['required', Rule::in(array_keys(RegleAlerte::NIVEAUX))],
            'actif' => ['nullable', 'boolean'],
        ], [
            'decalage_jours.required' => "Indiquez le décalage en jours (négatif avant l'échéance, positif après).", 'canaux.required' => 'Choisissez au moins un canal.', 'canaux.min' => 'Choisissez au moins un canal.',
            'destinataires.required' => 'Choisissez au moins un destinataire.', 'destinataires.min' => 'Choisissez au moins un destinataire.', 'libelle.required' => 'Donnez un libellé à la règle.',
        ]);

        // Le code se déduit du décalage (J-3, J0, J+15) : deux règles ne peuvent pas avoir le même moment.
        $n = (int) $donnees['decalage_jours'];
        $code = $n === 0 ? 'J0' : ($n < 0 ? 'J'.$n : 'J+'.$n);
        if (RegleAlerte::where('code', $code)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            return response()->json(['status' => false, 'message' => "Une règle $code existe déjà : modifiez-la plutôt que d'en créer une seconde au même moment."], 422);
        }

        $avant = $regle->exists ? $regle->only(['code', 'libelle', 'decalage_jours', 'canaux', 'destinataires', 'types', 'niveau', 'actif']) : [];
        $regle->forceFill([
            'code' => $code, 'libelle' => trim($donnees['libelle']), 'decalage_jours' => $n, 'canaux' => array_values($donnees['canaux']), 'destinataires' => array_values($donnees['destinataires']),
            'types' => ! empty($donnees['types']) ? array_values($donnees['types']) : null, 'niveau' => $donnees['niveau'], 'actif' => (bool) ($donnees['actif'] ?? false),
        ])->save();
        Journal::audit($id ? 'regle_alerte_modifiee' : 'regle_alerte_creee', $regle, $regle->code.' – '.$regle->libelle, $avant, $regle->only(['code', 'libelle', 'decalage_jours', 'canaux', 'destinataires', 'types', 'niveau', 'actif']));

        return response()->json(['status' => true, 'message' => $id ? 'Règle modifiée.' : 'Règle créée.']);
    }

    public function activer(Request $request, int $id): JsonResponse
    {
        Gate::authorize('regles_alerte.modifier');
        $regle = RegleAlerte::findOrFail($id);
        $regle->forceFill(['actif' => ! $regle->actif])->save();
        Journal::audit('regle_alerte_modifiee', $regle, $regle->code.' – '.($regle->actif ? 'activée' : 'désactivée'), ['actif' => ! $regle->actif], ['actif' => $regle->actif]);

        return response()->json(['status' => true, 'actif' => $regle->actif, 'message' => 'Règle '.$regle->code.($regle->actif ? ' activée.' : ' désactivée.')]);
    }

    public function supprimer(int $id): JsonResponse
    {
        Gate::authorize('regles_alerte.annuler');
        $regle = RegleAlerte::findOrFail($id);
        Journal::audit('regle_alerte_supprimee', $regle, $regle->code.' – '.$regle->libelle, $regle->only(['code', 'libelle', 'decalage_jours', 'canaux', 'destinataires']));
        $regle->delete();

        return response()->json(['status' => true, 'message' => 'Règle supprimée.']);
    }

    /**
     * Qui recevrait les alertes ? Par chambre : greffier(s) et président de la formation ; puis les fonctions de la hiérarchie.
     * Chacun est marqué « avec compte » ou « sans compte » : sans compte, la personne ne reçoit rien.
     *
     * @return array{chambres: list<array<string, mixed>>, fonctions: list<array<string, mixed>>}
     */
    public static function controleDestinataires(): array
    {
        $comptes = fn ($agents) => $agents->map(fn ($a) => ['nom' => $a->nomComplet(), 'compte' => (bool) $a->user_id])->values()->all();
        $chambres = Chambre::whereIn('annee_judiciaire_id', \App\Models\AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)->orderBy('ordre')->get(['id', 'libelle']);
        $formations = Formation::where('actif', true)->get(['id', 'chambre_id'])->groupBy('chambre_id');
        $affectations = Affectation::with('agent')->where('suppleant', false)
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', today()->toDateString()))->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()->toDateString()))->get();

        $lignes = $chambres->map(function ($c) use ($formations, $affectations, $comptes) {
            $ids = ($formations->get($c->id) ?? collect())->pluck('id');
            $a = $affectations->whereIn('formation_id', $ids)->filter(fn ($x) => $x->agent && ! $x->agent->trashed());

            return ['chambre' => $c->libelle, 'greffiers' => $comptes($a->where('role', 'greffier')->pluck('agent')->unique('id')), 'presidents' => $comptes($a->where('role', 'president')->pluck('agent')->unique('id'))];
        })->values()->all();

        $fonctions = [];
        foreach (['presidente' => 'Présidente du tribunal', 'vice_president' => 'Vice-présidents', 'greffier_en_chef' => 'Coordonnateur du greffe'] as $cle => $libelle) {
            $fonctions[] = ['fonction' => $libelle, 'agents' => $comptes(Agent::where('fonction', $cle)->where('actif', true)->get())];
        }

        return ['chambres' => $lignes, 'fonctions' => $fonctions];
    }
}
