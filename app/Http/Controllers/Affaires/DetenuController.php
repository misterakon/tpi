<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Partie;
use App\Models\User;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Détenus prioritaires : les prévenus et inculpés sous mandat de dépôt, classés par échéance (les plus pressants d'abord).
 | Uniquement les affaires que l'utilisateur a le droit de voir (périmètre par chambre) ; une affaire annulée en sort. Droit : detenus.voir.
 | La liste est paginée côté serveur (une page à la fois) : tri, recherche et filtre d'échéance se font dans la base.
 */
class DetenuController extends Controller
{
    /** Les détenus en cours : une ligne par personne (mandat de dépôt sans libération), dans le périmètre de l'utilisateur. */
    private function base(?User $u): Builder
    {
        return Partie::query()->whereNotNull('mandat_depot_date')->whereNull('libere_le')
            ->whereHas('affaire', fn ($q) => $q->visiblesPour($u)->where('statut', '!=', 'annulee')->whereIn('serie', ['RP', 'RI', 'RE']));
    }

    /** L'échéance en SQL : la date saisie ou, à défaut, le mandat plus la durée réglée. */
    private static function echeanceSql(): string
    {
        $jours = (int) config('tpi.penal.detention_jours', 180);

        return DB::getDriverName() === 'sqlite'
            ? "coalesce(echeance_detention, date(mandat_depot_date, '+{$jours} days'))"
            : "coalesce(echeance_detention, date_add(mandat_depot_date, interval {$jours} day))";
    }

    public function index(): View
    {
        Gate::authorize('detenus.voir');

        $e = self::echeanceSql();
        $auj = now()->toDateString();
        $limite = now()->addDays((int) config('tpi.penal.alerte_jours', 30))->toDateString();

        return view('affaires.detenus.index', [
            'compte' => [
                'total' => $this->base(auth()->user())->count(),
                'depassees' => $this->base(auth()->user())->whereRaw("$e < ?", [$auj])->count(),
                'proches' => $this->base(auth()->user())->whereRaw("$e >= ? and $e <= ?", [$auj, $limite])->count(),
            ],
        ]);
    }

    /** Les lignes de la page, une page à la fois. */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('detenus.voir');
        $e = self::echeanceSql();
        $base = $this->base($request->user())->with(['affaire.chambre'])->orderByRaw("$e asc")->orderBy('id');
        $niveau = (string) $request->input('filtre.5', '');
        $auj = now()->toDateString();
        $limite = now()->addDays((int) config('tpi.penal.alerte_jours', 30))->toDateString();
        if ($niveau === 'depassee') {
            $base->whereRaw("$e < ?", [$auj]);
        } elseif ($niveau === 'proche') {
            $base->whereRaw("$e >= ? and $e <= ?", [$auj, $limite]);
        } elseif ($niveau === 'normal') {
            $base->whereRaw("$e > ?", [$limite]);
        }

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['affaire_id'], 3 => ['mandat_depot_date'], 5 => [DB::raw($e)]],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['nom', 'prenoms', 'denomination', 'lieu_detention'], 'affaire', ['numero_complet', 'objet', 'articles']),
            fn (Partie $p) => view('affaires.detenus._ligne', ['l' => $p])->render(),
        );
    }
}
