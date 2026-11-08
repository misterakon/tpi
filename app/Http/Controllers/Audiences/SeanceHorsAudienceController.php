<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Agent;
use App\Models\Chambre;
use App\Models\SeanceDossier;
use App\Models\SeanceHorsAudience;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Séances hors audience publique (AUD-11) : réception des requêtes au cabinet du juge, conciliation, tutelle. On planifie la séance, on y inscrit les
 | dossiers examinés et leur issue, puis on la déclare tenue. Droits : seances.voir / creer / modifier / annuler.
 */
class SeanceHorsAudienceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('seances.voir');
        $user = auth()->user();
        $perimetre = PerimetreChambres::de($user);

        return view('audiences.seances.index', [
            'tenues' => SeanceHorsAudience::visiblesPour($user)->where('statut', 'tenue')->count(),
            'prochaines' => SeanceHorsAudience::visiblesPour($user)->where('statut', 'prevue')->whereDate('date_seance', '>=', today())->count(),
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'magistrats' => Agent::where('actif', true)->whereIn('fonction', ['presidente', 'vice_president', 'juge', 'juge_instruction', 'juge_enfants'])->orderBy('nom')->get(),
            'greffiers' => Agent::where('actif', true)->whereIn('fonction', ['greffier', 'greffier_en_chef', 'greffier_en_chef_adjoint', 'secretaire'])->orderBy('nom')->get(),
            'affaires' => Gate::allows('seances.modifier') ? Affaire::visiblesPour($user)->where('statut', 'enrolee')->orderByDesc('date_rg')->limit(400)->get(['id', 'numero_complet', 'serie', 'annee', 'numero', 'objet']) : collect(),
        ]);
    }

    /** Les lignes de la page, une page à la fois (tri, recherche et filtre d'état dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('seances.voir');
        $base = SeanceHorsAudience::visiblesPour($request->user())->with(['chambre:id,libelle', 'juge', 'greffier', 'dossiers.affaire'])->orderByDesc('date_seance')->orderByDesc('id');
        $f = (string) $request->input('filtre.4', '');
        if (isset(SeanceHorsAudience::STATUTS[$f])) {
            $base->where('statut', $f);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['date_seance', 'id'], 4 => ['statut']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['lieu', 'observations'], 'dossiers.affaire', ['numero_complet', 'objet']),
            fn (SeanceHorsAudience $s) => view('audiences.seances._ligne', ['s' => $s])->render(),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $s = $request->filled('id_') ? SeanceHorsAudience::visiblesPour($request->user())->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($s ? 'seances.modifier' : 'seances.creer');
        abort_if($s && $s->statut !== 'prevue', 403, 'Une séance tenue ou annulée ne se modifie plus.');
        $d = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(SeanceHorsAudience::TYPES))], 'chambre_id' => ['nullable', 'integer', 'exists:chambres,id'], 'date_seance' => ['required', 'date'],
            'heure' => ['nullable', 'date_format:H:i'], 'lieu' => ['nullable', 'string', 'max:120'], 'juge_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('agents', 'id')->whereIn('fonction', ['presidente', 'vice_president', 'juge', 'juge_instruction', 'juge_enfants'])],
            'greffier_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('agents', 'id')->whereIn('fonction', ['greffier', 'greffier_en_chef', 'greffier_en_chef_adjoint', 'secretaire'])],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], ['date_seance.required' => 'Indiquez la date de la séance.']);
        $perimetre = PerimetreChambres::de($request->user());
        if (! empty($d['chambre_id']) && $perimetre !== null && ! in_array((int) $d['chambre_id'], $perimetre, true)) {
            throw ValidationException::withMessages(['chambre_id' => 'Cette chambre est hors de votre périmètre.']);
        }
        if ($s) {
            $s->update($d);
        } else {
            $s = SeanceHorsAudience::create($d + ['statut' => 'prevue', 'cree_par' => $request->user()?->id]);
        }
        Journal::audit($request->filled('id_') ? 'seance_corrigee' : 'seance_planifiee', $s, SeanceHorsAudience::TYPES[$s->type].' du '.$s->date_seance->format('d/m/Y'), [], ['date' => $d['date_seance']]);

        return redirect()->route('audiences.seances')->with('success', 'Séance enregistrée.');
    }

    public function dossier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('seances.modifier');
        $s = SeanceHorsAudience::visiblesPour($request->user())->findOrFail($id);
        abort_if($s->statut === 'annulee', 403, 'Cette séance est annulée.');
        $d = $request->validate(['affaire_id' => ['nullable', 'integer'], 'objet' => ['required', 'string', 'max:250'], 'issue' => ['nullable', 'string', 'max:250']], ['objet.required' => 'Indiquez l\'objet du dossier examiné.']);
        if (! empty($d['affaire_id']) && ! Affaire::visiblesPour($request->user())->whereKey($d['affaire_id'])->exists()) {
            throw ValidationException::withMessages(['affaire_id' => 'Cette affaire est introuvable ou hors de votre périmètre.']);
        }
        SeanceDossier::create($d + ['seance_id' => $s->id]);

        return redirect()->route('audiences.seances')->with('success', 'Dossier ajouté à la séance.');
    }

    public function retirerDossier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('seances.modifier');
        $l = SeanceDossier::findOrFail($id);
        $s = SeanceHorsAudience::visiblesPour($request->user())->findOrFail($l->seance_id);
        abort_if($s->statut === 'annulee', 403);
        $l->delete();

        return redirect()->route('audiences.seances')->with('success', 'Dossier retiré de la séance.');
    }

    public function statut(int $id, Request $request): RedirectResponse
    {
        $s = SeanceHorsAudience::visiblesPour($request->user())->findOrFail($id);
        $v = $request->validate(['statut' => ['required', 'in:tenue,annulee']]);
        Gate::authorize($v['statut'] === 'annulee' ? 'seances.annuler' : 'seances.modifier');
        abort_if($s->statut !== 'prevue', 403, 'Cette séance n\'est plus à venir.');
        if ($v['statut'] === 'tenue' && $s->date_seance->gt(today())) {
            throw ValidationException::withMessages(['statut' => 'Une séance à venir ne peut pas être déclarée tenue.']);
        }
        $s->update(['statut' => $v['statut']]);
        Journal::audit($v['statut'] === 'tenue' ? 'seance_tenue' : 'seance_annulee', $s, SeanceHorsAudience::TYPES[$s->type].' du '.$s->date_seance->format('d/m/Y'));

        return redirect()->route('audiences.seances')->with('success', $v['statut'] === 'tenue' ? 'Séance déclarée tenue.' : 'Séance annulée.');
    }
}
