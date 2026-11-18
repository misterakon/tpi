<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Ordonnance;
use App\Models\Salle;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Composition des chambres, pour l'année judiciaire choisie : qui siège dans chaque chambre aujourd'hui, ses jours d'audience
 | et ce qui manque (président, greffier). Le bouton « Composer » ouvre, dans un panneau de cette même page, la composition
 | détaillée de la chambre (AffectationController@index renvoie ce fragment) avec les formulaires d'ajout, de modification,
 | de remplacement et d'audience.
 | Droit : affectations.voir (la saisie demande affectations.creer / modifier / supprimer et creneaux.*).
 */
class CompositionController extends Controller
{
    /** Catégories de personnel qui peuvent siéger dans une chambre (les autres sont gérées dans « Personnel du tribunal »). */
    private const CATEGORIES_DES_CHAMBRES = ['magistrat', 'greffier', 'assesseur'];

    public function index(Request $request): View
    {
        Gate::authorize('affectations.voir');

        $annees = AnneeJudiciaire::orderByDesc('debut')->get();
        $annee = $annees->firstWhere('id', (int) $request->query('annee')) ?? AnneeJudiciaire::courante() ?? $annees->first();
        $aujourdhui = now()->startOfDay();

        $chambres = Chambre::query()
            ->with(['section', 'formations.creneaux.salle', 'formations.affectations.agent'])
            ->when($annee, fn ($q) => $q->where('annee_judiciaire_id', $annee->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('ordre')->get();

        $fiches = $chambres->map(fn (Chambre $chambre) => $this->fiche($chambre, $aujourdhui));

        $dejaPresents = $chambres->flatMap->formations->flatMap->affectations->pluck('agent_id')->unique()->all();

        return view('organisation.composition.index', [
            'annees' => $annees,
            'annee' => $annee,
            'sections' => Section::orderBy('ordre')->orderBy('libelle')->get(),
            'fiches' => $fiches,
            'ouvrir' => $chambres->contains('id', (int) $request->query('ouvrir')) ? (int) $request->query('ouvrir') : null,
            'resume' => [
                'chambres' => $fiches->count(),
                'completes' => $fiches->where('manques', [])->count(),
                'a_completer' => $fiches->filter(fn ($f) => $f['manques'] !== [])->count(),
                'affectations' => $fiches->sum('nb_titulaires') + $fiches->sum('nb_suppleants') + $fiches->sum('nb_greffiers'),
            ],
            // Pour les fenêtres de saisie (affectation, remplacement, audience).
            'agents' => Agent::query()
                ->where(fn ($q) => $q->where('actif', true)->whereIn('categorie', self::CATEGORIES_DES_CHAMBRES)->orWhereIn('id', $dejaPresents))
                ->orderBy('nom')->orderBy('prenoms')->get(),
            'salles' => Salle::where('actif', true)->orderBy('nom')->get(),
            'ordonnances' => Ordonnance::orderByDesc('date_signature')->get(),
        ]);
    }

    /** Ce qui est valable aujourd'hui dans une chambre. */
    private function fiche(Chambre $chambre, $aujourdhui): array
    {
        $enCours = $chambre->formations->flatMap->affectations->filter(
            fn (Affectation $a) => (! $a->debut || $a->debut->lte($aujourdhui)) && (! $a->fin || $a->fin->gte($aujourdhui))
        );
        $titulaires = $enCours->where('suppleant', false);

        // À la tête d'une chambre : son président ; un cabinet (instruction, juge des enfants, application des peines) n'a qu'un juge.
        $tete = $titulaires->where('role', 'president');
        $roleTete = 'Président(e)';
        if ($tete->isEmpty()) {
            $tete = $titulaires->whereIn('role', ['juge', 'juge_delegue']);
            $roleTete = 'Juge';
        }
        $presidents = $tete->filter(fn ($a) => $a->agent)->unique('agent_id')
            ->map(fn ($a) => ['id' => $a->agent_id, 'nom' => $a->agent->nomComplet()])->values();
        $greffiers = $titulaires->where('role', 'greffier');
        $membres = $titulaires->whereNotIn('role', ['president', 'greffier'])->reject(fn ($a) => $tete->contains($a));

        $audiences = $chambre->formations->flatMap->creneaux
            ->filter(fn (Creneau $c) => ! $c->fin || $c->fin->gte($aujourdhui))
            ->sortBy([fn ($a, $b) => ($a->jour ?? 9) <=> ($b->jour ?? 9), fn ($a, $b) => strcmp((string) $a->heure, (string) $b->heure)])
            // Un horaire = une pastille (heure et jour, ou date pour une audience supplémentaire), un type et une salle.
            ->map(function (Creneau $c) {
                $ponctuelle = $c->frequence === 'ponctuelle' && $c->debut;

                return [
                    'grand' => $ponctuelle ? $c->debut->format('d') : ($c->heure ? substr($c->heure, 0, 5) : '—'),
                    'petit' => $ponctuelle
                        ? mb_strtoupper($c->debut->locale('fr')->translatedFormat('M'))
                        : ($c->jour ? mb_strtoupper(mb_substr(Creneau::JOURS[$c->jour], 0, 3)) : ($c->frequence === 'trimestrielle' ? 'TRIM.' : 'DEM.')),
                    // « Jeu 09:00 », « 12 nov. 09:00 » (audience supplémentaire) ou la fréquence quand il n'y a pas de jour fixe
                    'court' => $ponctuelle
                        ? trim($c->debut->format('d').' '.mb_strtolower($c->debut->locale('fr')->translatedFormat('M')).($c->heure ? ' '.substr($c->heure, 0, 5) : ''))
                        : ($c->jour
                            ? mb_substr(Creneau::JOURS[$c->jour], 0, 3).($c->heure ? ' '.substr($c->heure, 0, 5) : '')
                            : (Creneau::FREQUENCES[$c->frequence] ?? 'Sur demande')),
                    'type' => $c->type ?: 'Audience',
                    'salle' => $c->salle?->nom,
                    'quand' => $c->quand(),
                ];
            })
            ->unique(fn (array $h) => implode('|', $h))
            ->values();

        $manques = [];
        if ($presidents->isEmpty()) {
            $manques[] = 'pas de président ni de juge';
        }
        if ($greffiers->isEmpty()) {
            $manques[] = 'pas de greffier';
        }
        // Une chambre sans audience n'est pas « incomplète » : les cabinets d'instruction n'en tiennent pas. Elle est seulement signalée dans la carte.

        return [
            'chambre' => $chambre,
            'presidents' => $presidents,
            'role_tete' => $roleTete,
            'nb_titulaires' => $membres->count() + $tete->count(),
            'nb_suppleants' => $enCours->where('suppleant', true)->count(),
            'nb_greffiers' => $greffiers->count(),
            'audiences' => $audiences,
            'manques' => $manques,
            'a_venir' => $chambre->formations->flatMap->affectations->filter(fn (Affectation $a) => $a->debut && $a->debut->gt($aujourdhui))->count(),
        ];
    }
}
