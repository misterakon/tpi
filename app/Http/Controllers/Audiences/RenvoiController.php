<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\RoleLigne;
use App\Services\Journal;
use App\Support\Audiences\DatesAudience;
use App\Support\Audiences\SuiviAffaires;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Renvois et délibérés (AUD-03, AUD-04, AUD-05) : toutes les affaires renvoyées avec leur nombre de renvois, leurs dates et leurs motifs ;
 | tous les délibérés avec leur date, les échus à rendre et les prorogations. On peut changer une date de renvoi ou proroger un délibéré
 | sans quitter la page ; l'affaire revient d'elle-même sur le rôle à cette date.
 */
class RenvoiController extends Controller
{
    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());
        $etats = SuiviAffaires::etats($perimetre)->whereIn('etat', ['renvoyee', 'deliberee'])->values();
        $historiques = $this->historiques($etats->pluck('affaire_id')->all());
        $lignes = $etats->map(function (array $a) use ($historiques) {
            return [
                'ligne' => $a['ligne_id'], 'affaire_id' => $a['affaire_id'], 'role_id' => $a['role_id'], 'etat' => $a['etat'], 'numero' => $a['numero'], 'chambre_id' => $a['chambre_id'], 'chambre' => $a['chambre'],
                'parties' => $a['parties'], 'objet' => $a['objet'], 'premiere' => $a['premiere']?->format('d/m/Y'), 'derniere' => $a['derniere_audience']?->format('d/m/Y'),
                'echeance' => $a['echeance']?->toDateString(), 'echeance_libelle' => $a['echeance']?->format('d/m/Y'), 'jours' => $a['echeance'] ? (int) today()->diffInDays($a['echeance'], false) : null,
                'retard' => $a['retard'], 'nb_renvois' => $a['nb_renvois'], 'proroge' => $a['proroge'], 'motif' => $a['motif'], 'historique' => $historiques[$a['affaire_id']] ?? [],
            ];
        })->sortBy(fn ($x) => ($x['echeance'] ?? '9999-12-31').'|'.$x['numero'])->values()->all();

        return view('audiences.renvois.index', [
            'lignes' => $lignes,
            'chambres' => Chambre::whereIn('id', collect($lignes)->pluck('chambre_id')->unique())->orderBy('ordre')->get(['id', 'libelle']),
            'peutModifier' => $request->user()->can('renvois.modifier'),
        ]);
    }

    /** Change la date d'un renvoi (la dernière suite de l'affaire) : elle reviendra au rôle de cette nouvelle date. */
    public function changerDate(int $ligne, Request $request): JsonResponse
    {
        Gate::authorize('renvois.modifier');
        $l = $this->derniere($ligne, $request, 'renvoi');
        $role = $l->role;
        $donnees = $request->validate(['renvoi_au' => ['required', 'date', 'after:'.$role->date_audience->toDateString()], 'motif' => ['nullable', 'string', 'max:200']],
            ['renvoi_au.required' => 'Indiquez la nouvelle date.', 'renvoi_au.after' => "La date de renvoi doit suivre celle de l'audience du ".$role->date_audience->format('d/m/Y').'.']);
        $avant = $l->only(['renvoi_au', 'motif']);
        $l->update(['renvoi_au' => $donnees['renvoi_au'], 'motif' => ($m = trim((string) ($donnees['motif'] ?? ''))) !== '' ? $m : $l->motif]);
        Journal::audit('renvoi_date_modifiee', $role, strtok((string) $l->numero, "\n").' – renvoi', $avant, $l->only(['renvoi_au', 'motif']));

        return response()->json(['message' => 'Date de renvoi changée : '.Carbon::parse($donnees['renvoi_au'])->format('d/m/Y').'.']);
    }

    /** Fixe ou proroge la date d'un délibéré : l'affaire reviendra au rôle à cette date. */
    public function proroger(int $ligne, Request $request): JsonResponse
    {
        Gate::authorize('renvois.modifier');
        $l = $this->derniere($ligne, $request, 'delibere');
        $role = $l->role;
        $minimum = ($l->date_delibere && $l->date_delibere->gt($role->date_audience) ? $l->date_delibere : $role->date_audience)->toDateString();
        $donnees = $request->validate(['date_delibere' => ['required', 'date', 'after:'.$minimum], 'motif' => ['nullable', 'string', 'max:200']],
            ['date_delibere.required' => 'Indiquez la date.', 'date_delibere.after' => 'La nouvelle date doit être postérieure au '.Carbon::parse($minimum)->format('d/m/Y').'.']);
        $avant = $l->only(['date_delibere', 'proroge']);
        $l->update(['date_delibere' => $donnees['date_delibere'], 'proroge' => $l->date_delibere ? $l->proroge + 1 : $l->proroge, 'motif' => ($m = trim((string) ($donnees['motif'] ?? ''))) !== '' ? $m : $l->motif]);
        Journal::audit('delibere_proroge', $role, strtok((string) $l->numero, "\n").' – délibéré', $avant, $l->only(['date_delibere', 'proroge']));

        return response()->json(['message' => ($avant['date_delibere'] ? 'Délibéré prorogé' : 'Date de délibéré fixée').' au '.Carbon::parse($donnees['date_delibere'])->format('d/m/Y').'.']);
    }

    /** Les prochaines audiences de la chambre de l'affaire, pour proposer une date. */
    public function dates(int $ligne, Request $request): JsonResponse
    {
        $l = $this->derniere($ligne, $request);
        $role = $l->role->load('chambre');
        $date = $request->query('date');
        $analyse = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? DatesAudience::analyser($role->chambre, Carbon::parse($date), $role->date_audience) : null;
        $depuis = today()->gt($role->date_audience) ? today()->subDay() : $role->date_audience;

        return response()->json(['analyse' => $analyse, 'prochaines' => DatesAudience::prochaines($role->chambre, $depuis, $role->date_audience, 4)]);
    }

    /** La ligne, si c'est bien le dernier passage de l'affaire sur un rôle publié (et avec la suite attendue si précisée). */
    private function derniere(int $ligne, Request $request, ?string $issue = null): RoleLigne
    {
        $l = RoleLigne::with('role.chambre')->findOrFail($ligne);
        $role = $l->role;
        abort_unless($role->estPublie() && $l->affaire_id && PerimetreChambres::voit($request->user(), (int) $role->chambre_id), 404);
        $plusRecent = RoleLigne::join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')->where('role_lignes.affaire_id', $l->affaire_id)
            ->where(fn ($q) => $q->whereDate('roles_audience.date_audience', '>', $role->date_audience->toDateString())->orWhere(fn ($q2) => $q2->whereDate('roles_audience.date_audience', $role->date_audience->toDateString())->where('role_lignes.id', '>', $l->id)))->exists();
        abort_if($plusRecent, 422, 'Cette affaire a déjà un passage plus récent sur un rôle : modifiez-y la suite.');
        abort_if($issue !== null && $l->issue !== $issue, 422, 'Cette suite a changé : actualisez la page.');

        return $l;
    }

    /** Tous les passages de chaque affaire sur des rôles publiés. @return array<int, list<array<string, mixed>>> */
    private function historiques(array $affaires): array
    {
        if (! $affaires) {
            return [];
        }
        $lignes = RoleLigne::query()->select('role_lignes.*', 'roles_audience.date_audience as date_role', 'roles_audience.id as id_role')
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')->whereIn('role_lignes.affaire_id', $affaires)
            ->orderBy('roles_audience.date_audience')->orderBy('role_lignes.id')->get();

        return $lignes->groupBy('affaire_id')->map(fn ($g) => $g->map(fn ($x) => [
            'date' => Carbon::parse($x->date_role)->format('d/m/Y'), 'role_id' => (int) $x->id_role, 'issue' => $x->issue, 'libelle' => $x->issue ? RoleLigne::ISSUES[$x->issue] : 'Suite non notée',
            'detail' => $x->issue === 'renvoi' && $x->renvoi_au ? 'au '.$x->renvoi_au->format('d/m/Y') : ($x->issue === 'delibere' && $x->date_delibere ? 'jusqu\'au '.$x->date_delibere->format('d/m/Y') : null), 'motif' => $x->motif,
        ])->values()->all())->all();
    }
}
