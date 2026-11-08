<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Support\Audiences\SuiviAffaires;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Rôle du jour à l'écran (ROL-11) : les rôles publiés d'une journée (aujourd'hui par défaut) pour les chambres de l'utilisateur, avec la salle, l'heure, la composition,
 | l'état de l'audience (à tenir, en cours, terminée), les affaires par rubrique et la suite donnée à chacune à mesure que le greffier la note.
 | La page se met à jour toute seule ; le mode plein écran sert à l'affichage dans la salle. Droit : roles.voir (aucun droit nouveau).
 */
class RoleDuJourController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.voir');
        $perimetre = PerimetreChambres::de(auth()->user());

        return view('audiences.jour.index', [
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('roles.voir');
        $jour = $this->jour($request);
        $chambre = $request->filled('chambre') ? (int) $request->query('chambre') : null;
        if ($chambre !== null && ! PerimetreChambres::voit($request->user(), $chambre)) {
            $chambre = null;
        }

        $roles = RoleAudience::visiblesPour($request->user())->where('statut', 'publie')->whereDate('date_audience', $jour->toDateString())->when($chambre, fn ($q) => $q->where('chambre_id', $chambre))
            ->with(['chambre', 'salle', 'lignes.affaire.parties'])->orderBy('heure')->orderBy('id')->get();

        return response()->json([
            'jour' => $jour->toDateString(), 'libelle' => $jour->copy()->locale('fr')->translatedFormat('l j F Y'), 'aujourdhui' => $jour->isToday(),
            'precedent' => $jour->copy()->subDay()->toDateString(), 'suivant' => $jour->copy()->addDay()->toDateString(), 'maj' => now()->format('H:i:s'),
            'roles' => $roles->map(fn (RoleAudience $r) => $this->role($r))->values()->all(),
            'totaux' => ['roles' => $roles->count(), 'affaires' => $roles->sum(fn ($r) => $r->lignes->whereNotNull('affaire_id')->count() ?: $r->lignes->count()), 'traitees' => $roles->sum(fn ($r) => $r->lignes->whereNotNull('issue')->count())],
        ]);
    }

    /** @return array<string, mixed> */
    private function role(RoleAudience $r): array
    {
        $lignes = $r->lignes;
        $traitees = $lignes->whereNotNull('issue')->count();

        return [
            'id' => $r->id, 'chambre' => $r->chambre?->libelle, 'intitule' => $r->intitule, 'type' => RoleAudience::TYPES[$r->type] ?? $r->type, 'salle' => $r->salle?->nom, 'heure' => $r->heureCourte(),
            'etat' => $r->etatAudience(), 'president' => $r->president, 'assesseurs' => $r->assesseurs, 'greffier' => $r->greffier, 'procureur' => $r->procureur,
            'total' => $lignes->count(), 'traitees' => $traitees, 'url' => Gate::allows('roles.voir') ? route('audiences.roles.show', $r->id) : null,
            'rubriques' => collect(RoleLigne::RUBRIQUES)->map(fn ($lib, $cle) => [
                'cle' => $cle, 'libelle' => $lib,
                'lignes' => $lignes->where('rubrique', $cle)->values()->map(fn (RoleLigne $l, $i) => [
                    'ordre' => $i + 1, 'numero' => $l->affaire?->numeroAffichage() ?? $l->numero, 'parties' => $l->affaire ? SuiviAffaires::parties($l->affaire) : trim($l->demandeurs.($l->defendeurs ? ' c/ '.$l->defendeurs : '')),
                    'objet' => $l->objet ?: $l->affaire?->objet, 'issue' => $l->issue, 'issue_libelle' => $l->issue ? (RoleLigne::ISSUES[$l->issue] ?? $l->issue) : null, 'renvoi_au' => $l->renvoi_au?->format('d/m/Y'),
                ])->all(),
            ])->filter(fn ($rub) => $rub['lignes'] !== [])->values()->all(),
        ];
    }

    private function jour(Request $request): Carbon
    {
        try {
            return $request->filled('jour') ? Carbon::parse($request->query('jour'))->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }
}
