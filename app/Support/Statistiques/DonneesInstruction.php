<?php

namespace App\Support\Statistiques;

use App\Models\Affaire;
use App\Models\CommissionRogatoire;
use App\Models\DossierInstruction;
use App\Models\Partie;
use App\Models\SuiviInculpe;
use App\Models\SuiviVictime;
use App\Models\User;
use App\Support\FamillesAffaires;
use App\Support\PerimetreChambres;

/*
 | Statistiques de l'instruction (cabinets d'instruction et juge des enfants), d'après les rubriques des classeurs « INFO JI » et « INFO JE » :
 | dossiers ouverts et clôturés par année, stock, durée entre la saisine et l'ordonnance de clôture, inculpés (sexe, mineurs, détenus, traite), victimes, commissions rogatoires.
 */
class DonneesInstruction
{
    /** @return array<string, mixed> */
    public static function calculer(User $user, int $depuis, int $jusqua, ?int $chambre = null): array
    {
        $affaires = Affaire::visiblesPour($user)->whereIn('serie', ['RI', 'RE'])->where('statut', 'enrolee')->when($chambre, fn ($q) => $q->where('chambre_id', $chambre))->with('parties')->get();
        $dossiers = DossierInstruction::whereIn('affaire_id', $affaires->pluck('id'))->get()->keyBy('affaire_id');
        $parties = $affaires->flatMap->parties;
        $suivisI = SuiviInculpe::whereIn('partie_id', $parties->pluck('id'))->get()->keyBy('partie_id');
        $suivisV = SuiviVictime::whereIn('partie_id', $parties->pluck('id'))->get()->keyBy('partie_id');

        $annees = range($depuis, $jusqua);
        $stock = $affaires->filter(fn ($a) => $a->date_rg && $a->date_rg->year < $depuis && ! ($dossiers->get($a->id)?->date_cloture && $dossiers->get($a->id)->date_cloture->year < $depuis))->count();
        $parAn = [];
        foreach ($annees as $y) {
            $ouverts = $affaires->filter(fn ($a) => $a->date_rg?->year === $y)->count();
            $clos = $affaires->filter(fn ($a) => $dossiers->get($a->id)?->date_cloture?->year === $y)->count();
            $stock += $ouverts - $clos;
            $parAn[$y] = ['ouverts' => $ouverts, 'clos' => $clos, 'stock_fin' => $stock];
        }

        $durees = $affaires->map(fn ($a) => ($c = $dossiers->get($a->id)?->date_cloture) && $a->date_rg && $c->gte($a->date_rg) && $c->year >= $depuis && $c->year <= $jusqua ? (int) $a->date_rg->diffInDays($c) : null)->filter(fn ($d) => $d !== null)->sort()->values();
        $n = $durees->count();
        $clotures = $affaires->filter(fn ($a) => ($c = $dossiers->get($a->id)?->date_cloture) && $c->year >= $depuis && $c->year <= $jusqua)->count();

        $age = fn (Partie $p) => $p->age();
        $inculpes = $parties->where('role', 'inculpe');
        $victimes = $parties->where('role', 'victime');
        $detenus = $inculpes->filter(fn (Partie $p) => ($suivisI->get($p->id)?->statut === 'detenu') || ($suivisI->get($p->id)?->statut === null && $p->estDetenu()));

        $cr = CommissionRogatoire::whereBetween('annee', [$depuis, $jusqua])->when($chambre, fn ($q) => $q->where('chambre_id', $chambre), function ($q) use ($user) {
            $visibles = PerimetreChambres::de($user);
            $cabinets = FamillesAffaires::chambresInstruction();

            return $q->whereIn('chambre_id', $visibles === null ? $cabinets : (array_values(array_intersect($cabinets, $visibles)) ?: [0]));
        })->get();
        $crParAn = [];
        foreach ($annees as $y) {
            $l = $cr->where('annee', $y);
            $crParAn[$y] = ['recues' => (int) $l->where('sens', 'recu')->sum('nouvelles'), 'emises' => (int) $l->where('sens', 'emis')->sum('nouvelles'), 'executees' => (int) $l->sum('executees'), 'internationales' => (int) $l->where('portee', 'internationale')->sum('nouvelles')];
        }

        return [
            'annees' => $annees, 'par_an' => $parAn, 'total_dossiers' => $affaires->count(),
            'durees' => ['clotures' => $clotures, 'mesurees' => $n, 'manquantes' => $clotures - $n, 'moyenne' => $n ? (int) round($durees->avg()) : null, 'mediane' => $n ? ($n % 2 ? $durees[intdiv($n, 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2)) : null],
            'inculpes' => [
                'total' => $inculpes->count(), 'hommes' => $inculpes->where('nature', 'homme')->count(), 'femmes' => $inculpes->where('nature', 'femme')->count(), 'mineurs' => $inculpes->filter(fn ($p) => $age($p) !== null && $age($p) < 18)->count(),
                'detenus' => $detenus->count(), 'traite' => $inculpes->filter(fn ($p) => $suivisI->get($p->id)?->traite)->count(),
                'statuts' => collect(\App\Http\Controllers\Instruction\InstructionController::STATUTS)->map(fn ($lib, $k) => ['libelle' => $lib, 'n' => $inculpes->filter(fn ($p) => ($suivisI->get($p->id)?->statut ?? ($p->estDetenu() ? 'detenu' : null)) === $k)->count()])->filter(fn ($x) => $x['n'] > 0)->values()->all(),
            ],
            'victimes' => ['total' => $victimes->count(), 'hommes' => $victimes->where('nature', 'homme')->count(), 'femmes' => $victimes->where('nature', 'femme')->count(), 'mineurs' => $victimes->filter(fn ($p) => $age($p) !== null && $age($p) < 18)->count(), 'traite' => $victimes->filter(fn ($p) => $suivisV->get($p->id)?->traite)->count()],
            'commissions' => $crParAn,
        ];
    }
}
