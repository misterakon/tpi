<?php

namespace App\Http\Controllers\Annexes;

use App\Http\Controllers\Controller;
use App\Models\ActiviteJap;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Activités du juge de l'application des peines : visites des établissements et, pour chaque type de demande (placement à l'extérieur, semi-liberté, permission de sortir,
 | liberté conditionnelle, reclassement), le stock, les demandes reçues, les initiatives du JAP, les demandes traitées dont accord et rejet, mois par mois.
 | Le total à traiter et le stock restant se calculent. Droits : jap.voir et jap.modifier.
 */
class JapController extends Controller
{
    public function index(): View
    {
        Gate::authorize('jap.voir');

        return view('annexes.jap.index', ['rubriques' => ActiviteJap::RUBRIQUES, 'visites' => ActiviteJap::VISITES, 'peutModifier' => Gate::allows('jap.modifier')]);
    }

    /** Les compteurs d'une année : cle => [valeur du mois 1 à 12]. */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('jap.voir');
        $annee = $this->annee($request);
        $sortie = [];
        foreach (ActiviteJap::where('annee', $annee)->get() as $a) {
            $sortie[$a->cle][$a->mois] = $a->valeur;
        }

        return response()->json(['annee' => $annee, 'valeurs' => $sortie]);
    }

    public function enregistrer(Request $request): JsonResponse
    {
        Gate::authorize('jap.modifier');
        $annee = $this->annee($request);
        $request->validate(['valeurs' => ['required', 'array']]);
        $valeurs = $request->input('valeurs');
        $cles = $this->clesAutorisees();

        // Cohérence : dans chaque rubrique et chaque mois, traitées ≤ stock + reçues + initiatives, et accord + rejet ≤ traitées.
        // Le stock du début d'un mois est le stock restant du mois précédent (comme dans le classeur) : seul celui de janvier se saisit.
        foreach (ActiviteJap::RUBRIQUES as $code => [$libelle]) {
            $reste = 0;
            for ($m = 1; $m <= 12; $m++) {
                $v = fn (string $c) => (int) ($valeurs[$code.'_'.$c][$m] ?? 0);
                $stock = $m === 1 ? $v('stock') : $reste;
                $total = $stock + $v('recues') + $v('initiatives');
                if ($v('traitees') > $total) {
                    return response()->json(['message' => $libelle.', mois '.$m.' : il y a plus de demandes traitées que de demandes à traiter.', 'errors' => ['valeurs' => [$libelle.' : traitées supérieures au total à traiter (mois '.$m.').']]], 422);
                }
                if ($v('accord') + $v('rejet') > $v('traitees')) {
                    return response()->json(['message' => $libelle.', mois '.$m.' : accords et rejets dépassent les demandes traitées.', 'errors' => ['valeurs' => [$libelle.' : accords et rejets supérieurs aux demandes traitées (mois '.$m.').']]], 422);
                }
                $reste = $total - $v('traitees');
            }
        }
        foreach ($valeurs as $cle => $mois) {
            if (! in_array($cle, $cles, true) || ! is_array($mois)) {
                continue;
            }
            foreach ($mois as $m => $valeur) {
                $m = (int) $m;
                if ($m < 1 || $m > 12 || ! is_numeric($valeur) || (str_ends_with($cle, '_stock') && $m > 1)) {
                    continue;
                }
                ActiviteJap::updateOrCreate(['annee' => $annee, 'mois' => $m, 'cle' => $cle], ['valeur' => min(max((int) $valeur, 0), 999999)]);
            }
        }
        Journal::audit('jap_activites_enregistrees', null, 'Activités du JAP '.$annee);

        return response()->json(['status' => true, 'message' => 'Activités du JAP '.$annee.' enregistrées.']);
    }

    /** @return list<string> */
    private function clesAutorisees(): array
    {
        $cles = array_map(fn ($c) => 'visites_'.$c, array_keys(ActiviteJap::VISITES));
        foreach (ActiviteJap::RUBRIQUES as $code => [, $champs]) {
            foreach (array_keys($champs) as $c) {
                $cles[] = $code.'_'.$c;
            }
        }

        return $cles;
    }

    private function annee(Request $request): int
    {
        return min(max((int) $request->input('annee', $request->query('annee', now()->year)), 2000), 2100);
    }
}
