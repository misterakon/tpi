<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\AffaireAttribution;
use App\Models\Affectation;
use App\Models\Chambre;
use App\Models\MiseEnEtat;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use App\Support\Audiences\SuiviAffaires;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Mise en état (AUD-07, AUD-08) : le greffe confie une affaire civile à la mise en état d'un juge chargé, puis enregistre l'ordonnance de clôture ;
 | la clôture verse l'affaire à la chambre de jugement, qui la voit « à mettre au rôle » à la date d'audience de jugement indiquée.
 | Droits : mise_en_etat.voir / creer / modifier / annuler (retirer de la mise en état). Changer de chambre à la clôture exige aussi affaires.reattribuer.
 */
class MiseEnEtatController extends Controller
{
    public function index(): View
    {
        Gate::authorize('mise_en_etat.voir');
        $base = MiseEnEtat::visiblesPour(auth()->user());
        $ouvertes = (clone $base)->where('statut', 'en_cours')->get(['id', 'statut', 'date_premiere_audience', 'date_cloture']);
        $cloturees = (clone $base)->where('statut', 'cloturee')->get(['id', 'statut', 'date_premiere_audience', 'date_cloture']);
        $durees = $cloturees->map->dureeJours()->filter(fn ($d) => $d !== null);

        return view('affaires.mise_en_etat.index', [
            'total' => (clone $base)->count(),
            'compte' => [
                'en_cours' => $ouvertes->count(), 'longues' => $ouvertes->filter->estLongue()->count(),
                'cloturees_30j' => $cloturees->filter(fn ($m) => $m->date_cloture && $m->date_cloture->gte(today()->subDays(30)))->count(),
                'duree_moyenne' => $durees->isEmpty() ? null : (int) round($durees->avg()),
            ],
            'vigilance' => (int) config('tpi.mise_en_etat.vigilance_jours', 90),
            'eligibles' => Gate::allows('mise_en_etat.creer') ? $this->affairesEligibles() : collect(),
            'juges' => $this->jugesParChambre(),
            'chambres' => Gate::allows('affaires.reattribuer') ? Chambre::where('actif', true)->orderBy('id')->get(['id', 'libelle']) : collect(),
        ]);
    }

    /** Les lignes de la page « Mise en état », une page à la fois (tri, recherche et filtre de statut dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('mise_en_etat.voir');
        $base = MiseEnEtat::visiblesPour($request->user())->with(['affaire.chambre', 'affaire.parties', 'juge', 'chambreJugement'])
            ->orderByRaw("CASE statut WHEN 'en_cours' THEN 0 WHEN 'cloturee' THEN 1 ELSE 2 END")->orderByDesc('date_reception')->orderByDesc('id');
        $f = (string) $request->input('filtre.5', '');
        if (in_array($f, ['en_cours', 'cloturee', 'retiree'], true)) {
            $base->where('statut', $f);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['affaire_id'], 3 => ['date_reception'], 4 => ['date_premiere_audience']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['numero_ordonnance_cloture', 'motif_retrait'], 'affaire', ['numero_complet', 'objet']),
            fn (MiseEnEtat $m) => view('affaires.mise_en_etat._ligne', ['l' => $m])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('mise_en_etat.voir');
        $m = MiseEnEtat::visiblesPour(auth()->user())->with('affaire')->findOrFail($id);

        return response()->json([
            'id' => $m->id, 'affaire_id' => $m->affaire_id, 'numero' => $m->affaire->numeroAffichage(), 'chambre_id' => $m->affaire->chambre_id, 'juge_id' => $m->juge_id, 'observations' => $m->observations,
            'date_reception' => $m->date_reception->format('Y-m-d'), 'date_premiere_audience' => $m->date_premiere_audience?->format('Y-m-d'),
        ]);
    }

    /** Ouverture (id_ vide) ou correction (id_ renseigné) d'une mise en état encore ouverte. */
    public function store(Request $request): RedirectResponse
    {
        $m = $request->filled('id_') ? MiseEnEtat::visiblesPour($request->user())->with('affaire')->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($m ? 'mise_en_etat.modifier' : 'mise_en_etat.creer');
        abort_if($m && ! $m->estOuverte(), 403, 'Une mise en état clôturée ou retirée ne se modifie plus.');

        $regles = [
            'date_reception' => ['required', 'date', 'before_or_equal:today'],
            'juge_id' => ['nullable', 'integer', 'exists:agents,id'],
            'date_premiere_audience' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
        if (! $m) {
            $regles['affaire_id'] = ['required', 'integer'];
        }
        $d = $request->validate($regles, [
            'affaire_id.required' => 'Choisissez l\'affaire.', 'date_reception.required' => 'Indiquez la date de réception du dossier.',
            'date_reception.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
        ]);

        $affaire = $m ? $m->affaire : Affaire::visiblesPour($request->user())->find((int) $d['affaire_id']);
        if (! $affaire) {
            throw ValidationException::withMessages(['affaire_id' => 'Cette affaire est introuvable ou hors de votre périmètre.']);
        }
        if (! $m) {
            $this->verifierEligible($affaire);
        }
        if (! empty($d['juge_id']) && ! $this->jugeDeLaChambre((int) $d['juge_id'], (int) $affaire->chambre_id)) {
            throw ValidationException::withMessages(['juge_id' => 'Le juge chargé doit être un magistrat de la chambre de l\'affaire.']);
        }
        if (! empty($d['date_premiere_audience']) && $d['date_premiere_audience'] < $d['date_reception']) {
            throw ValidationException::withMessages(['date_premiere_audience' => 'La première audience ne peut pas précéder la réception du dossier.']);
        }

        $champs = ['date_reception' => $d['date_reception'], 'juge_id' => $d['juge_id'] ?? null, 'date_premiere_audience' => $d['date_premiere_audience'] ?? null, 'observations' => $d['observations'] ?? null];
        if ($m) {
            $avant = $m->only(array_keys($champs));
            $m->update($champs);
            Journal::audit('mise_en_etat_corrigee', $affaire, $affaire->numeroAffichage(), $avant, $champs);

            return redirect()->route('affaires.mise_en_etat')->with('success', 'Mise en état corrigée.');
        }

        $nouvelle = MiseEnEtat::create($champs + ['affaire_id' => $affaire->id, 'statut' => 'en_cours', 'cree_par' => $request->user()?->id]);
        Journal::audit('mise_en_etat_ouverte', $affaire, $affaire->numeroAffichage(), [], ['reception' => $d['date_reception'], 'juge_id' => $nouvelle->juge_id]);

        return redirect()->route('affaires.mise_en_etat')->with('success', 'Affaire '.$affaire->numeroAffichage().' confiée à la mise en état.');
    }

    /** Ordonnance de clôture : numéro, date, puis versement à la chambre de jugement et date de l'audience de jugement. */
    public function cloturer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('mise_en_etat.modifier');
        $m = MiseEnEtat::visiblesPour($request->user())->with('affaire')->findOrFail($id);
        abort_if(! $m->estOuverte(), 403, 'Cette mise en état n\'est plus ouverte.');

        $d = $request->validate([
            'numero_ordonnance_cloture' => ['required', 'string', 'max:60'],
            'date_cloture' => ['required', 'date', 'before_or_equal:today'],
            'date_audience_jugement' => ['required', 'date'],
            'chambre_jugement_id' => ['nullable', 'integer', 'exists:chambres,id'],
        ], [
            'numero_ordonnance_cloture.required' => 'Indiquez le numéro de l\'ordonnance de clôture.', 'date_cloture.required' => 'Indiquez la date de l\'ordonnance de clôture.',
            'date_cloture.before_or_equal' => 'La date de clôture ne peut pas être dans le futur.', 'date_audience_jugement.required' => 'Indiquez la date de l\'audience de jugement.',
        ]);

        $affaire = $m->affaire;
        if ($d['date_cloture'] < $m->date_reception->toDateString() || ($m->date_premiere_audience && $d['date_cloture'] < $m->date_premiere_audience->toDateString())) {
            throw ValidationException::withMessages(['date_cloture' => 'La clôture ne peut pas précéder la réception du dossier ni la première audience.']);
        }
        if ($d['date_audience_jugement'] < $d['date_cloture']) {
            throw ValidationException::withMessages(['date_audience_jugement' => 'L\'audience de jugement ne peut pas précéder la clôture.']);
        }

        // La chambre de jugement : la même, sauf décision contraire de qui a le droit de réattribuer.
        $chambre = (int) $affaire->chambre_id;
        if (! empty($d['chambre_jugement_id']) && (int) $d['chambre_jugement_id'] !== $chambre) {
            Gate::authorize('affaires.reattribuer');
            $chambre = (int) $d['chambre_jugement_id'];
        }

        DB::transaction(function () use ($m, $affaire, $d, $chambre, $request) {
            if ($chambre !== (int) $affaire->chambre_id) {
                AffaireAttribution::create(['affaire_id' => $affaire->id, 'chambre_avant_id' => $affaire->chambre_id, 'chambre_apres_id' => $chambre, 'motif' => 'Versement à la chambre de jugement après clôture de la mise en état', 'par' => $request->user()?->id]);
                Affaire::where('jointe_a_id', $affaire->id)->update(['jointe_a_id' => null]);
                $affaire->forceFill(['chambre_id' => $chambre, 'jointe_a_id' => null]);
            }
            // La date de l'audience de jugement devient la date à laquelle l'affaire figure « à mettre au rôle » de la chambre de jugement.
            $affaire->forceFill(['date_premiere_audience' => $d['date_audience_jugement']])->save();
            $m->update([
                'numero_ordonnance_cloture' => trim($d['numero_ordonnance_cloture']), 'date_cloture' => $d['date_cloture'], 'chambre_jugement_id' => $chambre, 'date_audience_jugement' => $d['date_audience_jugement'],
                'statut' => 'cloturee', 'versee_le' => now(), 'cloture_par' => $request->user()?->id,
            ]);
        });
        Journal::audit('mise_en_etat_cloturee', $affaire, $affaire->numeroAffichage(), [], ['ordonnance' => trim($d['numero_ordonnance_cloture']), 'date_cloture' => $d['date_cloture'], 'audience_jugement' => $d['date_audience_jugement'], 'duree_jours' => $m->fresh()->dureeJours()]);

        return redirect()->route('affaires.mise_en_etat')->with('success', 'Mise en état clôturée : l\'affaire '.$affaire->numeroAffichage().' est versée à la chambre de jugement pour l\'audience du '.\Illuminate\Support\Carbon::parse($d['date_audience_jugement'])->format('d/m/Y').'.');
    }

    /** Retire l'affaire de la mise en état sans clôture (saisie par erreur, ou affaire reprise par la formation) : elle redevient une affaire ordinaire. */
    public function retirer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('mise_en_etat.annuler');
        $m = MiseEnEtat::visiblesPour($request->user())->with('affaire')->findOrFail($id);
        abort_if(! $m->estOuverte(), 403, 'Cette mise en état n\'est plus ouverte.');
        $d = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:300']], ['motif.required' => 'Indiquez le motif.', 'motif.min' => 'Le motif est trop court.']);

        $m->update(['statut' => 'retiree', 'motif_retrait' => trim($d['motif'])]);
        Journal::audit('mise_en_etat_retiree', $m->affaire, $m->affaire->numeroAffichage(), [], ['motif' => trim($d['motif'])]);

        return redirect()->route('affaires.mise_en_etat')->with('success', 'Affaire retirée de la mise en état.');
    }

    /** Seules les affaires civiles (registre RG), enrôlées, non jointes, pas déjà en mise en état. */
    private function verifierEligible(Affaire $a): void
    {
        $motif = match (true) {
            $a->estAnnulee() || $a->statut !== 'enrolee' => 'Cette affaire n\'est pas en cours.',
            $a->serie !== 'RG' => 'Seules les affaires civiles (registre RG) passent en mise en état.',
            $a->jointe_a_id !== null => 'Cette affaire est jointe à une autre.',
            $a->miseEnEtatOuverte()->exists() => 'Cette affaire est déjà en mise en état.',
            default => null,
        };
        if ($motif) {
            throw ValidationException::withMessages(['affaire_id' => $motif]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, Affaire> */
    private function affairesEligibles()
    {
        return Affaire::visiblesPour(auth()->user())->with(['chambre:id,libelle', 'parties'])->where('statut', 'enrolee')->where('serie', 'RG')->whereNull('jointe_a_id')
            ->whereDoesntHave('miseEnEtatOuverte')->orderByDesc('date_rg')->orderByDesc('id')->limit(400)->get();
    }

    /** Les magistrats de chaque chambre, d'après les affectations en cours (le greffier n'est pas juge chargé). @return array<int, list<array{id: int, nom: string}>> */
    private function jugesParChambre(): array
    {
        $sortie = [];
        $affectations = Affectation::with(['agent', 'formation'])->where('suppleant', false)->whereNotIn('role', ['greffier'])
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', today()->toDateString()))
            ->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()->toDateString()))->get();
        foreach ($affectations as $a) {
            if (! $a->agent || ! $a->formation) {
                continue;
            }
            $sortie[(int) $a->formation->chambre_id][$a->agent->id] = ['id' => $a->agent->id, 'nom' => $a->agent->nomComplet()];
        }

        return array_map(fn ($l) => array_values($l), $sortie);
    }

    private function jugeDeLaChambre(int $agentId, int $chambreId): bool
    {
        return in_array($agentId, array_column($this->jugesParChambre()[$chambreId] ?? [], 'id'), true);
    }
}
