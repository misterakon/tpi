<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Support\Decisions\TransmissionDecision;
use App\Support\PerimetreChambres;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Transmission des décisions au greffe, en lot : le juge choisit les décisions signées (une sélection, ou toutes celles d'une période),
 | fixe la date de transmission (elle vaut date de délivrance au greffe) et coche, s'il le veut, l'envoi d'une copie Word au greffier en chef.
 | Droit : redaction.transmettre (le même que la transmission d'une seule décision, depuis sa fiche).
 */
class TransmissionController extends Controller
{
    private const LIMITE = 300;

    public function index(Request $request): View
    {
        Gate::authorize('redaction.transmettre');
        $f = $request->validate(['du' => ['nullable', 'date'], 'au' => ['nullable', 'date'], 'chambre' => ['nullable', 'integer']]);

        $liste = Decision::visiblesPour($request->user())->with(['chambre:id,libelle', 'affaire'])->where('statut', 'signee')->whereNull('transmise_le')
            ->when($f['du'] ?? null, fn ($q, $v) => $q->whereDate('date_signature', '>=', $v))
            ->when($f['au'] ?? null, fn ($q, $v) => $q->whereDate('date_signature', '<=', $v))
            ->when($f['chambre'] ?? null, fn ($q, $v) => $q->where('chambre_id', $v))
            ->orderBy('date_signature')->orderBy('id');
        $total = (clone $liste)->count();

        return view('decisions.transmission.index', [
            'decisions' => $liste->limit(self::LIMITE)->get(), 'total' => $total, 'limite' => self::LIMITE, 'f' => $f,
            'chambres' => Chambre::whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)
                ->when(PerimetreChambres::de($request->user()) !== null, fn ($q) => $q->whereIn('id', PerimetreChambres::de($request->user()) ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'adresse' => TransmissionDecision::adresseGreffierEnChef(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('redaction.transmettre');
        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::LIMITE], 'ids.*' => ['integer'],
            'date_transmission' => ['required', 'date', 'before_or_equal:today'], 'envoyer_copie' => ['nullable', 'boolean'],
        ], [
            'ids.required' => 'Cochez au moins une décision à transmettre.', 'ids.min' => 'Cochez au moins une décision à transmettre.',
            'date_transmission.required' => 'Indiquez la date de transmission au greffe.', 'date_transmission.before_or_equal' => 'La date de transmission ne peut pas être dans le futur.',
        ]);

        $decisions = Decision::visiblesPour($request->user())->with('affaire')->whereIn('id', $donnees['ids'])->orderBy('date_signature')->orderBy('id')->get();
        $date = Carbon::parse($donnees['date_transmission']);
        $res = TransmissionDecision::transmettre($decisions, $request->user(), $date, (bool) ($donnees['envoyer_copie'] ?? false));

        $n = count($res['transmises']);
        $retour = redirect()->route('decisions.transmission');
        if ($n > 0) {
            $message = $n.' décision'.($n > 1 ? 's transmises' : ' transmise').' au greffe le '.$date->format('d/m/Y').' : cette date vaut date de délivrance.';
            if ($res['courriel'] === 'envoyé') {
                $message .= ' Une copie en Word a été envoyée au greffier en chef.';
            }
            $retour->with('success', $message);
        }
        $erreurs = $res['refusees'];
        if ($res['courriel'] !== null && $res['courriel'] !== 'envoyé') {
            $erreurs[] = $res['courriel'];
        }
        if ($erreurs) {
            $retour->with('error', implode(' ', array_slice($erreurs, 0, 4)).(count($erreurs) > 4 ? ' (et '.(count($erreurs) - 4).' autre(s))' : ''));
        }

        return $retour;
    }
}
