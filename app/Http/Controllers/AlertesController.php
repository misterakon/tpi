<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Models\AlerteEvaluation;
use App\Models\Chambre;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Suivi des alertes : toutes les situations qui demandent une action (par chambre, par gravité), avec la possibilité de justifier un retard.
 | Une alerte justifiée est suspendue (plus de relance) jusqu'à la date indiquée ; le motif et l'auteur restent visibles. Chacun ne voit
 | que les alertes de ses chambres.
 */
class AlertesController extends Controller
{
    private const PAR_PAGE = 12;

    /** Durées de suspension proposées (jours). */
    public const PAUSES = [3, 7, 15, 30];

    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());
        $derniere = AlerteEvaluation::orderByDesc('jour')->first();

        return view('alertes.suivi', [
            'chambres' => Chambre::where('actif', true)->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
            'types' => Alerte::TYPES,
            'peutJustifier' => Gate::allows('alertes.justifier'),
            'pauses' => self::PAUSES,
            'derniere' => $derniere ? ['jour' => $derniere->jour->format('d/m/Y'), 'envoyees' => $derniere->envoyees, 'declenchees' => $derniere->declenchees, 'echecs' => $derniere->echecs, 'retard_jours' => (int) $derniere->jour->diffInDays(today())] : null,
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        $f = [
            'niveau' => in_array($request->query('niveau'), ['info', 'attention', 'critique'], true) ? $request->query('niveau') : null,
            'type' => array_key_exists((string) $request->query('type'), Alerte::TYPES) ? $request->query('type') : null,
            'chambre' => ctype_digit((string) $request->query('chambre')) ? (int) $request->query('chambre') : null,
            'etat' => in_array($request->query('etat'), ['ouvertes', 'justifiees', 'en_retard', 'refermees'], true) ? $request->query('etat') : 'ouvertes',
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
        ];
        $aujourdhui = today()->toDateString();

        $base = Alerte::visiblesPour($request->user())
            ->when($f['niveau'], fn ($q, $v) => $q->where('niveau', $v))
            ->when($f['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($f['chambre'], fn ($q, $v) => $q->where('chambre_id', $v))
            ->when($f['q'] !== '', fn ($q) => $q->where('titre', 'like', '%'.addcslashes($f['q'], '%_\\').'%'));

        $compteurs = [
            'ouvertes' => (clone $base)->ouvertes()->count(),
            'en_retard' => (clone $base)->ouvertes()->whereDate('echeance', '<', $aujourdhui)->count(),
            'critiques' => (clone $base)->ouvertes()->where('niveau', 'critique')->count(),
            'justifiees' => (clone $base)->ouvertes()->whereDate('justifiee_jusqu', '>=', $aujourdhui)->count(),
            'refermees' => (clone $base)->whereNotNull('resolue_le')->where('resolue_le', '>=', now()->subDays(30))->count(),
        ];

        $liste = match ($f['etat']) {
            'justifiees' => (clone $base)->ouvertes()->whereDate('justifiee_jusqu', '>=', $aujourdhui),
            'en_retard' => (clone $base)->ouvertes()->whereDate('echeance', '<', $aujourdhui),
            'refermees' => (clone $base)->whereNotNull('resolue_le')->where('resolue_le', '>=', now()->subDays(30)),
            default => (clone $base)->ouvertes(),
        };
        $page = $liste->with(['chambre:id,libelle', 'justifieePar:id,name'])
            ->orderByRaw("case niveau when 'critique' then 3 when 'attention' then 2 else 1 end desc")->orderBy('echeance')->orderBy('id')
            ->paginate(self::PAR_PAGE, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'lignes' => $page->getCollection()->map(fn (Alerte $a) => self::forme($a))->values()->all(),
            'total' => $page->total(), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'par_page' => self::PAR_PAGE, 'compteurs' => $compteurs,
        ]);
    }

    /** Justifie un retard : l'alerte est suspendue jusqu'à la date choisie, le motif est conservé et tracé. */
    public function justifier(Request $request, int $id): JsonResponse
    {
        Gate::authorize('alertes.justifier');
        $alerte = Alerte::visiblesPour($request->user())->findOrFail($id);
        abort_unless($alerte->estOuverte(), 422, 'Cette alerte est déjà refermée : il n\'y a plus rien à justifier.');

        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:10', 'max:500'],
            'jours' => ['required', 'integer', 'in:'.implode(',', self::PAUSES)],
        ], ['motif.required' => 'Expliquez le retard (au moins 10 caractères).', 'motif.min' => 'Expliquez le retard (au moins 10 caractères).']);

        $avant = ['justifiee_jusqu' => $alerte->justifiee_jusqu?->toDateString()];
        $alerte->forceFill([
            'justification' => trim(preg_replace('/\s+/u', ' ', $donnees['motif'])), 'justifiee_jusqu' => today()->addDays((int) $donnees['jours'])->toDateString(),
            'justifiee_par' => $request->user()->id, 'justifiee_le' => now(),
        ])->save();
        Journal::audit('alerte_justifiee', $alerte, $alerte->titre, $avant, ['justifiee_jusqu' => $alerte->justifiee_jusqu->toDateString(), 'jours' => (int) $donnees['jours']]);

        return response()->json(['status' => true, 'message' => 'Retard justifié : les relances sont suspendues jusqu\'au '.$alerte->justifiee_jusqu->format('d/m/Y').'.', 'alerte' => self::forme($alerte->load(['chambre:id,libelle', 'justifieePar:id,name']))]);
    }

    /** @return array<string, mixed> */
    public static function forme(Alerte $a): array
    {
        $statut = ! $a->estOuverte() ? 'resolue' : ($a->estJustifiee() ? 'justifiee' : 'ouverte');

        return [
            'id' => $a->id, 'type' => $a->type, 'type_libelle' => $a->libelleType(), 'niveau' => $a->niveau, 'titre' => $a->titre, 'action' => $a->action, 'url' => $a->url ? url($a->url) : null,
            'chambre' => (string) $a->chambre?->libelle, 'echeance' => $a->echeance->format('d/m/Y'), 'retard' => $a->estOuverte() ? $a->retard() : 0, 'statut' => $statut,
            'justifiee_jusqu' => $a->justifiee_jusqu?->format('d/m/Y'), 'justification' => $a->justification, 'justifiee_par' => $a->justifieePar?->name, 'ouverte_le' => $a->ouverte_le?->format('d/m/Y'),
            'resolue_le' => $a->resolue_le?->format('d/m/Y'),
        ];
    }
}
