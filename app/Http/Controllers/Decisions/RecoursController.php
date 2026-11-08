<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\CertificatRecours;
use App\Models\Decision;
use App\Models\DelaiRecours;
use App\Models\Recours;
use App\Models\SuiviRecours;
use App\Models\User;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use App\Services\Journal;
use App\Support\Recours\DelaisRecours;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Recours et certificats (CLO-03) : pour chaque décision signée, la signification (début du délai de recours), les recours formés et leur
 | transmission à la Cour d'appel, puis le certificat de non-appel ou de non-opposition une fois le délai expiré sans recours.
 | Droits : recours.voir / creer / modifier / annuler ; recours.certifier (délivrer un certificat) ; recours.delais (modifier et confirmer les délais).
 | Les délais installés sont provisoires : tant qu'ils ne sont pas confirmés, aucun certificat ne peut être délivré.
 */
class RecoursController extends Controller
{
    public function index(): View
    {
        Gate::authorize('recours.voir');
        return view('decisions.recours.index', [
            'delais' => DelaiRecours::with('natureAffaire:id,libelle')->orderBy('nature')->orderBy('id')->get(),
            'peutCertifier' => Gate::allows('recours.certifier'),
            'peutDelais' => Gate::allows('recours.delais'),
        ]);
    }

    /** Les décisions signées visibles de l'utilisateur. */
    private function signees(?User $u): \Illuminate\Database\Eloquent\Builder
    {
        return Decision::visiblesPour($u)->whereIn('statut', ['signee', 'delivree']);
    }

    /** L'état de recours de chaque décision signée (calcul complet : sert au filtre par état et aux compteurs seulement). @return array<int, array<string, mixed>> */
    private function etats(?User $u): array
    {
        $sortie = [];
        $this->signees($u)->with('affaire')->orderBy('id')->chunkById(300, function ($lot) use (&$sortie) {
            foreach ($lot as $d) {
                $sortie[$d->id] = DelaisRecours::etat($d);
            }
        });

        return $sortie;
    }

    /** Les compteurs de l'en-tête, chargés après la page : leur calcul passe en revue toutes les décisions signées. */
    public function compteurs(Request $request): JsonResponse
    {
        Gate::authorize('recours.voir');
        $e = collect($this->etats($request->user()));

        return response()->json([
            'a_signifier' => $e->where('etat', 'a_signifier')->count(), 'en_cours' => $e->where('etat', 'en_cours')->count(),
            'a_certifier' => $e->filter(fn ($x) => $x['etat'] === 'expire' && $x['certifiable'])->count(), 'recours' => $e->where('etat', 'recours')->count(),
        ]);
    }

    /** Les lignes de la page, une page à la fois. L'état de recours n'est calculé que pour les lignes affichées (et pour tout le monde seulement si l'on filtre par état). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('recours.voir');
        $base = $this->signees($request->user())->with(['affaire.chambre', 'affaire.parties'])->orderByDesc('date_signature')->orderByDesc('id');
        $filtre = (string) $request->input('filtre.4', '');
        if (isset(DelaisRecours::ETATS[$filtre])) {
            $ids = array_keys(array_filter($this->etats($request->user()), fn ($x) => $x['etat'] === $filtre));
            $base->whereIn('decisions.id', $ids ?: [0]);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['date_signature', 'id']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['numero'], 'affaire', ['numero_complet', 'objet', 'articles']),
            fn (Decision $d) => view('decisions.recours._ligne', ['peutCertifier' => Gate::allows('recours.certifier'), 'l' => ['d' => $d, 'e' => DelaisRecours::etat($d), 'recours' => Recours::where('decision_id', $d->id)->orderBy('date_recours')->get()]])->render(),
        );
    }


    public function signifier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('recours.modifier');
        $d = $this->decision($id, $request);
        $v = $request->validate([
            'date_signification' => ['required', 'date', 'before_or_equal:today'], 'nature' => ['required', 'in:appel,opposition'], 'signification_a' => ['nullable', 'string', 'max:200'],
        ], ['date_signification.required' => 'Indiquez la date de signification.', 'date_signification.before_or_equal' => 'La signification ne peut pas être dans le futur.']);
        if ($d->date_signature && $v['date_signification'] < $d->date_signature->toDateString()) {
            throw ValidationException::withMessages(['date_signification' => 'La signification ne peut pas précéder la signature de la décision.']);
        }
        if ($d->certificats()->exists()) {
            throw ValidationException::withMessages(['date_signification' => 'Un certificat a déjà été délivré : la signification ne se modifie plus.']);
        }
        $avant = SuiviRecours::where('decision_id', $d->id)->first()?->only(['date_signification', 'nature']) ?? [];
        SuiviRecours::updateOrCreate(['decision_id' => $d->id], ['affaire_id' => $d->affaire_id, 'nature' => $v['nature'], 'date_signification' => $v['date_signification'], 'signification_a' => $v['signification_a'] ?? null, 'saisi_par' => $request->user()?->id]);
        Journal::audit('signification_enregistree', $d, $d->numeroAffichage() ?? 'Décision '.$d->id, $avant, ['date_signification' => $v['date_signification'], 'nature' => $v['nature']]);

        return redirect()->route('decisions.recours')->with('success', 'Signification enregistrée.');
    }

    public function enregistrer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('recours.creer');
        $d = $this->decision($id, $request);
        $v = $request->validate([
            'nature' => ['required', 'in:appel,opposition,pourvoi,autre'], 'auteur' => ['required', 'string', 'max:200'], 'date_recours' => ['required', 'date', 'before_or_equal:today'], 'observations' => ['nullable', 'string', 'max:500'],
        ], ['auteur.required' => 'Indiquez l\'auteur du recours.', 'date_recours.required' => 'Indiquez la date du recours.', 'date_recours.before_or_equal' => 'La date du recours ne peut pas être dans le futur.']);
        if ($d->date_signature && $v['date_recours'] < $d->date_signature->toDateString()) {
            throw ValidationException::withMessages(['date_recours' => 'Le recours ne peut pas précéder la signature de la décision.']);
        }
        if ($d->certificats()->exists()) {
            throw ValidationException::withMessages(['nature' => 'Un certificat a déjà été délivré pour cette décision.']);
        }
        $r = Recours::create($v + ['affaire_id' => $d->affaire_id, 'decision_id' => $d->id, 'statut' => 'forme', 'cree_par' => $request->user()?->id]);
        Journal::audit('recours_enregistre', $d, $d->numeroAffichage() ?? 'Décision '.$d->id, [], ['nature' => $r->nature, 'auteur' => $r->auteur, 'date' => $v['date_recours']]);

        return redirect()->route('decisions.recours')->with('success', 'Recours enregistré : aucun certificat ne pourra être délivré pour cette voie de recours.');
    }

    public function transmettre(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('recours.modifier');
        $r = Recours::findOrFail($id);
        $d = $this->decision($r->decision_id, $request);
        abort_if($r->statut !== 'forme', 403, 'Ce recours n\'est plus à transmettre.');
        $v = $request->validate(['date_transmission' => ['required', 'date', 'before_or_equal:today'], 'reference_cour' => ['nullable', 'string', 'max:100']], ['date_transmission.required' => 'Indiquez la date de transmission.']);
        if ($v['date_transmission'] < $r->date_recours->toDateString()) {
            throw ValidationException::withMessages(['date_transmission' => 'La transmission ne peut pas précéder le recours.']);
        }
        $r->update(['statut' => 'transmis', 'date_transmission' => $v['date_transmission'], 'reference_cour' => $v['reference_cour'] ?? null]);
        Journal::audit('recours_transmis', $d, $d->numeroAffichage() ?? 'Décision '.$d->id, [], ['date' => $v['date_transmission'], 'reference' => $r->reference_cour]);

        return redirect()->route('decisions.recours')->with('success', 'Dossier transmis à la Cour d\'appel.');
    }

    public function abandonner(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('recours.annuler');
        $r = Recours::findOrFail($id);
        $d = $this->decision($r->decision_id, $request);
        abort_if($r->statut === 'abandonne', 403);
        $r->update(['statut' => 'abandonne', 'observations' => trim(($r->observations ?? '').' [Abandonné le '.now()->format('d/m/Y').']')]);
        Journal::audit('recours_abandonne', $d, $d->numeroAffichage() ?? 'Décision '.$d->id, [], ['nature' => $r->nature]);

        return redirect()->route('decisions.recours')->with('success', 'Recours marqué comme abandonné.');
    }

    /** Délivre le certificat de non-appel ou de non-opposition : seulement si l'état de la décision le permet (délai confirmé et expiré, aucun recours). */
    public function certifier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('recours.certifier');
        $d = $this->decision($id, $request);
        $v = $request->validate(['delivre_a' => ['required', 'string', 'max:200'], 'qualite' => ['nullable', 'string', 'max:100']], ['delivre_a.required' => 'Indiquez à qui le certificat est délivré.']);

        $certificat = DB::transaction(function () use ($d, $v, $request) {
            $e = DelaisRecours::etat($d);
            if (! $e['certifiable']) {
                throw ValidationException::withMessages(['delivre_a' => $e['obstacle']]);
            }
            $annee = (int) now()->format('Y');

            return CertificatRecours::create([
                'decision_id' => $d->id, 'affaire_id' => $d->affaire_id, 'type' => $e['nature'] === 'opposition' ? 'non_opposition' : 'non_appel', 'annee' => $annee, 'numero' => DelaisRecours::numeroSuivant($annee),
                'date_signification' => $e['signification'], 'echeance' => $e['echeance'], 'date_delivrance' => today(), 'delivre_a' => trim($v['delivre_a']), 'qualite' => $v['qualite'] ?? null, 'delivre_par' => $request->user()?->id,
            ]);
        });
        Journal::audit('certificat_delivre', $d, $certificat->numeroAffichage(), [], ['type' => $certificat->type, 'decision' => $d->numeroAffichage(), 'delivre_a' => $certificat->delivre_a]);

        return redirect()->route('decisions.recours')->with(['success' => 'Certificat n° '.$certificat->numeroAffichage().' délivré.', 'certificat' => $certificat->id]);
    }

    public function imprimer(int $id, Request $request)
    {
        $c = CertificatRecours::with(['decision.affaire.parties', 'decision.chambre'])->findOrFail($id);
        $this->decision($c->decision_id, $request); // le périmètre de chambres s'applique aussi à l'impression
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('decisions.recours.certificat_pdf', [
            'c' => $c, 'd' => $c->decision, 'a' => $c->decision->affaire, 'juridiction' => config('tpi.juridiction'),
            'logo' => \App\Support\Roles\RoleExport::filigraneDonnees(), 'armoiries' => \App\Support\Roles\RoleExport::armoiriesDonnees(),
            'gauche' => \App\Support\Roles\RoleDocument::enteteGauche(), 'droite' => \App\Support\Roles\RoleDocument::enteteDroite(),
        ])->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="certificat-'.$c->annee.'-'.$c->numero.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Modification et confirmation des délais de recours (droit recours.delais). Une valeur modifiée perd sa confirmation. */
    public function delais(Request $request): RedirectResponse
    {
        Gate::authorize('recours.delais');
        $request->validate(['delais' => ['required', 'array'], 'delais.*.valeur' => ['required', 'integer', 'min:1', 'max:120'], 'delais.*.unite' => ['required', 'in:jours,mois']]);
        foreach ($request->input('delais') as $id => $l) {
            $delai = DelaiRecours::find((int) $id);
            if (! $delai) {
                continue;
            }
            $change = (int) $l['valeur'] !== (int) $delai->valeur || $l['unite'] !== $delai->unite;
            $veut = ! empty($l['valide']);
            if ($change) {
                $delai->update(['valeur' => (int) $l['valeur'], 'unite' => $l['unite'], 'valide' => $veut, 'valide_par' => $veut ? $request->user()?->id : null, 'valide_le' => $veut ? now() : null]);
                Journal::audit('delai_recours_modifie', null, $delai->libelle, [], ['delai' => $delai->libelleDelai(), 'confirme' => $veut]);
            } elseif ($veut !== (bool) $delai->valide) {
                $delai->update(['valide' => $veut, 'valide_par' => $veut ? $request->user()?->id : null, 'valide_le' => $veut ? now() : null]);
                Journal::audit($veut ? 'delai_recours_confirme' : 'delai_recours_modifie', null, $delai->libelle, [], ['delai' => $delai->libelleDelai(), 'confirme' => $veut]);
            }
        }

        return redirect()->route('decisions.recours')->with('success', 'Délais de recours enregistrés.');
    }

    private function decision(int $id, Request $request): Decision
    {
        $d = Decision::visiblesPour($request->user())->with('affaire')->findOrFail($id);
        abort_if(! $d->estSignee(), 403, 'Seule une décision signée peut faire l\'objet d\'un suivi de recours.');

        return $d;
    }
}
