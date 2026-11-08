<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\SynthesePilotage;
use App\Services\Journal;
use App\Support\PerimetreChambres;
use App\Support\Pilotage\AnalyseAuto;
use App\Support\Pilotage\DonneesPilotage;
use App\Support\Pilotage\NotePdf;
use App\Support\Pilotage\RedacteurIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/*
 | Analyse et pilotage : une vue de tout le tribunal pour la Présidente (ou des chambres de l'utilisateur) : indicateurs clés, entrées et sorties,
 | tableau par chambre, alertes, indice de santé, analyse et recommandations. L'analyse automatique est toujours là ; l'IA est facultative
 | et n'agit que sur demande (droit « tableau.ia »). Seuls des totaux sont transmis à l'IA.
 */
class PilotageController extends Controller
{
    public function index(Request $request): View
    {
        return view('audiences.pilotage.index', [
            'periodes' => DonneesPilotage::PERIODES,
            'iaDisponible' => RedacteurIA::disponible(), 'quota' => RedacteurIA::disponible() ? RedacteurIA::quotaRestant() : 0, 'peutIA' => Gate::allows('tableau.ia'),
            'historique' => $this->historique($request)->take(8)->map(fn (SynthesePilotage $s) => self::forme($s, false))->values()->all(),
        ]);
    }

    /** Les chiffres, l'analyse automatique et la dernière note de l'IA de la période. */
    public function donnees(Request $request): JsonResponse
    {
        $periode = DonneesPilotage::periode($request->query('periode'));
        $cle = 'pilotage:'.$request->user()->id.':'.$periode['type'];
        if ($request->boolean('frais')) {
            Cache::forget($cle);
        }
        // Mis en mémoire une minute : l'actualisation automatique ne recalcule pas tout à chaque passage.
        $donnees = Cache::remember($cle, 60, fn () => DonneesPilotage::calculer($request->user(), $periode));
        $analyse = AnalyseAuto::generer($donnees);

        $derniere = $this->historique($request)->where('source', 'ia')->where('periode_type', $periode['type'])->first();

        return response()->json([
            'donnees' => $donnees, 'analyse' => $analyse, 'ia' => $derniere ? self::forme($derniere, true) : null,
            'genere_le' => now()->format('H:i'),
        ]);
    }

    /** Demande l'analyse par l'IA : sur demande seulement, dans la limite quotidienne. En cas d'échec, l'analyse automatique reste affichée. */
    public function analyser(Request $request): JsonResponse
    {
        Gate::authorize('tableau.ia');
        $periode = DonneesPilotage::periode($request->input('periode'));
        $donnees = DonneesPilotage::calculer($request->user(), $periode);
        $analyse = AnalyseAuto::generer($donnees);

        try {
            $synthese = RedacteurIA::generer($donnees, $analyse, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
        Journal::audit('pilotage_analyse_ia', $synthese, 'Analyse par l\'IA – '.$donnees['periode']['libelle'], [], ['periode' => $periode['type'], 'perimetre' => $donnees['perimetre']]);

        return response()->json(['status' => true, 'ia' => self::forme($synthese, true), 'quota' => RedacteurIA::quotaRestant()]);
    }

    /** La note en PDF, dessinée côté serveur : analyse automatique, ou note de l'IA (source=ia, éventuellement une ancienne : synthese). */
    public function pdf(Request $request)
    {
        $periode = DonneesPilotage::periode($request->query('periode'));
        $donnees = DonneesPilotage::calculer($request->user(), $periode);
        $analyse = AnalyseAuto::generer($donnees);

        $ia = null;
        if ($request->query('source') === 'ia') {
            $ia = $request->filled('synthese')
                ? $this->historique($request)->firstWhere('id', (int) $request->query('synthese'))
                : $this->historique($request)->where('source', 'ia')->where('periode_type', $periode['type'])->first();
        }
        $contenu = NotePdf::generer($donnees, $analyse, $ia, (string) $request->user()->name);
        Journal::audit('pilotage_note_pdf', null, "Note d'analyse et de pilotage en PDF – ".$donnees['periode']['libelle'], [], ['periode' => $periode['type'], 'source' => $ia ? 'ia' : 'auto']);

        return response($contenu, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="note-pilotage-'.now()->format('Y-m-d').'.pdf"']);
    }

    /** Relire une ancienne note. */
    public function synthese(Request $request, int $id): JsonResponse
    {
        $synthese = $this->historique($request)->firstWhere('id', $id);
        abort_unless($synthese, 404);

        return response()->json(['ia' => self::forme($synthese, true)]);
    }

    /** Les notes que l'utilisateur peut relire : les siennes, ou toutes s'il voit toutes les chambres. */
    private function historique(Request $request)
    {
        return SynthesePilotage::with('auteur:id,name')->when(PerimetreChambres::de($request->user()) !== null, fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('created_at')->limit(60)->get();
    }

    /** @return array<string, mixed> */
    private static function forme(SynthesePilotage $s, bool $avecTexte): array
    {
        return [
            'id' => $s->id, 'source' => $s->source, 'periode' => DonneesPilotage::PERIODES[$s->periode_type] ?? $s->periode_type, 'du' => $s->periode_debut->format('d/m/Y'), 'au' => $s->periode_fin->format('d/m/Y'),
            'cree_le' => $s->created_at->format('d/m/Y à H:i'), 'auteur' => $s->auteur?->name, 'perimetre' => $s->perimetre, 'modele' => $s->modele_ia,
        ] + ($avecTexte ? ['contenu' => $s->contenu] : []);
    }
}
