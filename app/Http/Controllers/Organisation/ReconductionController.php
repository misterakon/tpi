<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Formation;
use App\Models\Service;
use App\Services\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Reconduction d'une année judiciaire vers la suivante : on repart de l'organisation de l'année précédente
 | (chambres et formations, puis au choix composition, jours et heures d'audience, services) et on ajuste.
 | Rien n'est écrasé : une chambre ou un service déjà présent dans l'année cible est laissé tel quel.
 | Droits : reconduction.voir / reconduction.executer.
 */
class ReconductionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('reconduction.voir');

        return view('organisation.reconduction.index', ['annees' => AnneeJudiciaire::orderByDesc('debut')->get()]);
    }

    public function executer(Request $request): RedirectResponse
    {
        Gate::authorize('reconduction.executer');

        $donnees = $request->validate([
            'source_id' => ['required', 'integer', 'exists:annees_judiciaires,id'],
            'cible_id' => ['required', 'integer', 'exists:annees_judiciaires,id', 'different:source_id'],
            'composition' => ['nullable', 'boolean'],
            'audiences' => ['nullable', 'boolean'],
            'services' => ['nullable', 'boolean'],
        ], [
            'source_id.required' => "Choisissez l'année à reconduire.", 'cible_id.required' => "Choisissez l'année qui reçoit la copie.",
            'cible_id.different' => 'Les deux années doivent être différentes.',
        ]);

        $source = AnneeJudiciaire::findOrFail($donnees['source_id']);
        $cible = AnneeJudiciaire::findOrFail($donnees['cible_id']);
        $options = ['composition' => $request->boolean('composition'), 'audiences' => $request->boolean('audiences'), 'services' => $request->boolean('services')];

        $bilan = DB::transaction(fn () => $this->reconduire($source, $cible, $options));

        Journal::audit('annee_reconduite', $cible, "De {$source->libelle} vers {$cible->libelle}", [], $bilan + $options);

        $texte = "{$bilan['chambres']} chambre(s), {$bilan['formations']} formation(s)";
        foreach (['affectations' => 'affectation(s)', 'creneaux' => 'audience(s)', 'services' => 'service(s)'] as $cle => $libelle) {
            if ($bilan[$cle]) {
                $texte .= ", {$bilan[$cle]} $libelle";
            }
        }

        return redirect()->route('organisation.reconduction')->with('success', "Année {$cible->libelle} préparée à partir de {$source->libelle} : $texte copié(s). Les chambres ou services déjà présents sont restés intacts ({$bilan['ignores']}).");
    }

    /** @return array<string, int> */
    private function reconduire(AnneeJudiciaire $source, AnneeJudiciaire $cible, array $options): array
    {
        $bilan = ['chambres' => 0, 'formations' => 0, 'affectations' => 0, 'creneaux' => 0, 'services' => 0, 'ignores' => 0];

        foreach (Chambre::where('annee_judiciaire_id', $source->id)->with('formations.affectations', 'formations.creneaux')->orderBy('ordre')->get() as $chambre) {
            if (Chambre::where('annee_judiciaire_id', $cible->id)->where('libelle', $chambre->libelle)->exists()) {
                $bilan['ignores']++;

                continue;
            }

            $copie = Chambre::create(['annee_judiciaire_id' => $cible->id] + $chambre->only(['section_id', 'groupe', 'libelle', 'intitule', 'attributions', 'remarque', 'ordre', 'actif']));
            $bilan['chambres']++;

            foreach ($chambre->formations as $formation) {
                $f = Formation::create(['chambre_id' => $copie->id] + $formation->only(['nom', 'intitule', 'attributions', 'ordre', 'actif']));
                $bilan['formations']++;

                if ($options['composition']) {
                    // Seules les personnes encore en poste à la fin de l'année source sont reconduites ; la nouvelle période commence avec la nouvelle année.
                    foreach ($formation->affectations->filter(fn (Affectation $a) => $a->fin === null || $a->fin->gte($source->fin)) as $a) {
                        Affectation::create(['formation_id' => $f->id, 'debut' => $cible->debut, 'fin' => null] + $a->only(['agent_id', 'role', 'suppleant', 'chef_section', 'remarque']));
                        $bilan['affectations']++;
                    }
                }

                if ($options['audiences']) {
                    // Les audiences ponctuelles et celles déjà terminées avant la fin de l'année ne sont pas reconduites.
                    foreach ($formation->creneaux->filter(fn (Creneau $c) => $c->frequence !== 'ponctuelle' && ($c->fin === null || $c->fin->gte($source->fin))) as $c) {
                        Creneau::create(['formation_id' => $f->id, 'debut' => null, 'fin' => null, 'ordonnance_id' => null] + $c->only(['type', 'jour', 'heure', 'frequence', 'salle_id', 'precision']));
                        $bilan['creneaux']++;
                    }
                }
            }
        }

        if ($options['services']) {
            foreach (Service::where('annee_judiciaire_id', $source->id)->with('agents')->orderBy('ordre')->get() as $service) {
                if (Service::where('annee_judiciaire_id', $cible->id)->where('categorie', $service->categorie)->where('libelle', $service->libelle)->exists()) {
                    $bilan['ignores']++;

                    continue;
                }
                $copie = Service::create(['annee_judiciaire_id' => $cible->id] + $service->only(['categorie', 'libelle', 'ordre', 'remarque']));
                foreach ($service->agents as $ligne) {
                    $copie->agents()->create($ligne->only(['agent_id', 'fonction', 'ordre']));
                }
                $bilan['services']++;
            }
        }

        return $bilan;
    }
}
