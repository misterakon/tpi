<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\AnneeJudiciaire;
use App\Models\Requete;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use App\Services\NumerotationRegistre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Requêtes et requêtes d'hérédité (ENR-10). Le greffe les enregistre, le secrétariat de la Présidente les répartit entre les formations
 | (droit spécial « requetes.repartir »), la formation les traite. Droits : requetes.voir / creer / modifier / annuler (classer sans suite).
 */
class RequeteController extends Controller
{
    private const CHAMPS = ['type', 'date_depot', 'objet', 'requerants', 'defunt_nom', 'defunt_date_deces', 'defunt_lieu_deces'];

    public function index(): View
    {
        Gate::authorize('requetes.voir');
        $base = Requete::visiblesPour(auth()->user());

        return view('affaires.requetes.index', [
            'total' => (clone $base)->count(),
            'chambres' => $this->chambresDestinataires(),
            'compte' => [
                'recues' => (clone $base)->where('statut', 'recue')->count(),
                'retard' => (clone $base)->where('statut', 'recue')->get()->filter->enRetard()->count(),
                'reparties' => (clone $base)->where('statut', 'repartie')->count(),
            ],
        ]);
    }

    /** Les lignes de la page « Requêtes », une page à la fois (tri, recherche et filtre de statut dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('requetes.voir');
        $base = Requete::visiblesPour($request->user())->with('chambre')->orderByRaw("CASE statut WHEN 'recue' THEN 0 WHEN 'repartie' THEN 1 ELSE 2 END")->orderBy('date_depot')->orderBy('id');
        $f = (string) $request->input('filtre.4', '');
        if ($f === 'close') {
            $base->whereIn('statut', ['traitee', 'classee']);
        } elseif (in_array($f, ['recue', 'repartie'], true)) {
            $base->where('statut', $f);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['annee', 'numero'], 3 => ['date_depot'], 4 => ['statut']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['numero_complet', 'objet', 'requerants', 'defunt_nom', 'reference_decision'], 'chambre', ['libelle']),
            fn (Requete $r) => view('affaires.requetes._ligne', ['l' => $r])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('requetes.voir');
        $r = Requete::visiblesPour(auth()->user())->findOrFail($id);

        return response()->json(['id' => $r->id, 'date_depot' => $r->date_depot->format('Y-m-d'), 'defunt_date_deces' => $r->defunt_date_deces?->format('Y-m-d')] + $r->only(['type', 'objet', 'requerants', 'defunt_nom', 'defunt_lieu_deces', 'statut']));
    }

    /** Dépôt (id_ vide) ou correction (id_ renseigné) d'une requête encore ouverte. */
    public function store(Request $request): RedirectResponse
    {
        $r = $request->filled('id_') ? Requete::visiblesPour($request->user())->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($r ? 'requetes.modifier' : 'requetes.creer');
        abort_if($r && ! $r->estOuverte(), 403, 'Une requête traitée ou classée ne se modifie plus.');

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Requete::TYPES))],
            'date_depot' => ['required', 'date', 'before_or_equal:today'],
            'objet' => ['required', 'string', 'max:1000'],
            'requerants' => ['required', 'string', 'max:1000'],
            'defunt_nom' => ['nullable', 'string', 'max:200', 'required_if:type,heredite'],
            'defunt_date_deces' => ['nullable', 'date', 'before_or_equal:today'],
            'defunt_lieu_deces' => ['nullable', 'string', 'max:160'],
        ], [
            'type.required' => 'Choisissez le type de requête.', 'date_depot.required' => 'La date de dépôt est obligatoire.', 'objet.required' => "L'objet de la requête est obligatoire.",
            'requerants.required' => 'Indiquez au moins un requérant.', 'defunt_nom.required_if' => "Pour une requête d'hérédité, indiquez le défunt.",
        ]);
        foreach (['objet', 'requerants', 'defunt_nom', 'defunt_lieu_deces'] as $c) {
            $donnees[$c] = filled($donnees[$c] ?? null) ? trim(strip_tags($donnees[$c])) : null;
        }
        if ($donnees['type'] !== 'heredite') {
            $donnees['defunt_nom'] = $donnees['defunt_date_deces'] = $donnees['defunt_lieu_deces'] = null;
        }

        $avant = $r ? $r->only(self::CHAMPS) : [];
        if ($r) {
            $r->forceFill($donnees)->save();
        } else {
            $r = DB::transaction(function () use ($donnees, $request) {
                $n = NumerotationRegistre::attribuer('RQ', (int) date('Y'));

                return Requete::create($donnees + ['annee' => $n['annee'], 'numero' => $n['numero'], 'numero_complet' => $n['libelle'], 'statut' => 'recue', 'cree_par' => $request->user()?->id]);
            });
        }
        Journal::audit($avant ? 'requete_modifiee' : 'requete_enregistree', $r, $r->numeroAffichage(), $avant, $r->only(self::CHAMPS));

        return redirect()->route('affaires.requetes')->with('success', $avant ? 'Requête modifiée.' : 'Requête enregistrée sous le numéro '.$r->numeroAffichage().' : elle attend sa répartition.');
    }

    /** Le secrétariat de la Présidente répartit la requête vers la formation compétente (ou la répartit à nouveau). */
    public function repartir(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('requetes.repartir');
        $r = Requete::visiblesPour($request->user())->findOrFail($id);
        abort_unless($r->estOuverte(), 403, 'Une requête traitée ou classée ne se répartit plus.');

        $donnees = $request->validate(['chambre_id' => ['required', 'integer', Rule::in($this->chambresDestinataires()->pluck('id')->all())], 'note' => ['nullable', 'string', 'max:300']], ['chambre_id.required' => 'Choisissez la formation (chambre) destinataire.', 'chambre_id.in' => 'Cette chambre ne peut pas recevoir la requête.']);
        $avant = ['statut' => $r->statut, 'chambre' => $r->chambre?->libelle];
        $r->forceFill(['chambre_id' => $donnees['chambre_id'], 'statut' => 'repartie', 'reparti_par' => $request->user()?->id, 'reparti_le' => now(), 'note_repartition' => filled($donnees['note'] ?? null) ? trim($donnees['note']) : null])->save();
        Journal::audit('requete_repartie', $r, $r->numeroAffichage(), $avant, ['statut' => 'repartie', 'chambre' => $r->fresh()->chambre?->libelle, 'note' => $r->note_repartition]);

        return redirect()->route('affaires.requetes')->with('success', 'Requête '.$r->numeroAffichage().' répartie : '.$r->fresh()->chambre?->libelle.'.');
    }

    /** La formation clôt la requête : traitée (avec la référence de la décision) ou classée sans suite (avec le motif). */
    public function cloturer(int $id, Request $request): RedirectResponse
    {
        $r = Requete::visiblesPour($request->user())->findOrFail($id);
        abort_unless($r->estOuverte(), 403, 'Cette requête est déjà clôturée.');
        $traitee = $request->input('issue') !== 'classee';
        Gate::authorize($traitee ? 'requetes.modifier' : 'requetes.annuler');
        abort_if($traitee && $r->statut !== 'repartie', 403, "Une requête doit être répartie avant d'être traitée.");
        // Une ordonnance sur requête ou une injonction de payer se signe : réservé à ceux qui ont le droit de signer (la formation, pas le greffe).
        if ($traitee && $r->signatureReservee()) {
            Gate::authorize('requetes.signer');
        }

        $donnees = $request->validate([
            'reference' => ['required', 'string', 'min:3', 'max:120'],
            'date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$r->date_depot->format('Y-m-d')],
        ], ['reference.required' => $traitee ? 'Indiquez la décision rendue (ex. Ordonnance n° 12/26).' : 'Indiquez le motif du classement.', 'reference.min' => 'La précision est trop courte.', 'date.required' => 'La date est obligatoire.', 'date.after_or_equal' => 'La date ne peut pas précéder le dépôt.']);
        $r->forceFill(['statut' => $traitee ? 'traitee' : 'classee', 'reference_decision' => trim($donnees['reference']), 'traitee_le' => $donnees['date']]
            + ($traitee && $r->signatureReservee() ? ['signee_par' => $request->user()?->id, 'signee_le' => now()] : []))->save();
        Journal::audit($traitee ? 'requete_traitee' : 'requete_classee', $r, $r->numeroAffichage(), [], ['reference' => $r->reference_decision, 'date' => $donnees['date']]);

        return redirect()->route('affaires.requetes')->with('success', 'Requête '.$r->numeroAffichage().($traitee ? ' traitée.' : ' classée sans suite.'));
    }

    /** Les chambres civiles, d'urgence ou sociales de l'année en cours : celles qui reçoivent des requêtes. */
    private function chambresDestinataires()
    {
        return Chambre::with('section')->whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)->orderBy('ordre')->get(['id', 'libelle', 'intitule', 'section_id'])
            ->filter(fn (Chambre $c) => in_array($c->section?->code, ['civile', 'urgence', 'sociale'], true))->values();
    }
}
