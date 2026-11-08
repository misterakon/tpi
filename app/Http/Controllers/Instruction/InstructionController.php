<?php

namespace App\Http\Controllers\Instruction;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\CommissionRogatoire;
use App\Models\DossierInstruction;
use App\Models\Partie;
use App\Models\SuiviInculpe;
use App\Models\SuiviVictime;
use App\Services\Journal;
use App\Support\FamillesAffaires;
use App\Support\PerimetreChambres;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use AppSupportRechercheTexte;
use AppSupportTableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | Instruction (lot R) : le suivi des dossiers des cabinets d'instruction et du cabinet du juge des enfants, comme dans les classeurs « INFO JI » et « INFO JE » :
 | dossier (réquisitoire, textes visés, faits, clôture), inculpés (statut, détention, renouvellements, liberté provisoire), victimes (auditions) et commissions rogatoires.
 | L'enrôlement (RI ou RE, parties, mandat de dépôt) se fait déjà dans « Affaires » ; ici on complète. Droits : instruction.voir et instruction.modifier ;
 | les dossiers d'instruction et de mineurs restent protégés (droits spéciaux de la famille d'affaires, voir FamillesAffaires).
 */
class InstructionController extends Controller
{
    public const STATUTS = ['libre' => 'Libre', 'garde_provisoire' => 'Garde provisoire', 'controle_judiciaire' => 'Contrôle judiciaire', 'detenu' => 'Détenu', 'autre' => 'Autre'];

    public const NATURES = ['crime' => 'Crime', 'delit' => 'Délit', 'contravention' => 'Contravention'];

    public function index(): View
    {
        Gate::authorize('instruction.voir');
        $user = auth()->user();
        $perimetre = PerimetreChambres::de($user);
        $ids = $this->affaires($user)->select('affaires.id');
        $total = $this->affaires($user)->count();
        $clos = DossierInstruction::whereIn('affaire_id', $ids)->whereNotNull('date_cloture')->count();
        $inculpes = Partie::where('role', 'inculpe')->whereIn('affaire_id', $ids)->get(['id', 'mandat_depot_date', 'libere_le']);
        $suivis = SuiviInculpe::whereIn('partie_id', $inculpes->pluck('id'))->pluck('statut', 'partie_id');
        $detenus = $inculpes->filter(fn ($p) => ($suivis[$p->id] ?? null) === 'detenu' || (! isset($suivis[$p->id]) && $p->estDetenu()))->count();

        return view('instruction.index', [
            'total' => $total, 'clos' => $clos, 'detenus' => $detenus,
            'peutModifier' => Gate::allows('instruction.modifier'),
            'statuts' => self::STATUTS, 'natures' => self::NATURES,
            'cabinets' => Chambre::where('actif', true)->whereIn('id', FamillesAffaires::chambresInstruction())->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle']),
        ]);
    }

    /** Le détail d'un dossier : sa fiche, ses inculpés et ses victimes avec leur suivi. */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('instruction.voir');
        $a = $this->affaires(auth()->user())->with('parties')->findOrFail($id);
        $d = DossierInstruction::where('affaire_id', $a->id)->first();
        $date = fn ($x) => $x?->format('Y-m-d');
        $suivis = SuiviInculpe::whereIn('partie_id', $a->parties->pluck('id'))->get()->keyBy('partie_id');
        $victimes = SuiviVictime::whereIn('partie_id', $a->parties->pluck('id'))->get()->keyBy('partie_id');

        return response()->json([
            'id' => $a->id, 'numero' => $a->numeroAffichage(), 'objet' => $a->objet, 'articles' => $a->articles, 'date_ri' => $date($a->date_rg),
            'dossier' => $d ? array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $d->only(['date_requisitoire', 'textes_vises', 'date_faits', 'lieu_faits', 'nature_infraction', 'infraction_specifique', 'autre_specificite', 'date_cloture', 'nature_cloture', 'autres_ordonnances', 'infraction_retenue', 'date_transmission_parquet', 'observations'])) : (object) [],
            'inculpes' => $a->parties->where('role', 'inculpe')->map(fn (Partie $p) => [
                'id' => $p->id, 'nom' => $p->libelle(), 'sexe' => $p->nature === 'femme' ? 'F' : 'M', 'age' => $p->age(), 'mandat' => $date($p->mandat_depot_date),
                'suivi' => ($s = $suivis->get($p->id)) ? array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $s->only(['traite', 'statut', 'precisions_statut', 'date_ogp', 'date_titre_detention', 'duree_detention_mois', 'date_renouvellement', 'duree_renouvellement_mois', 'date_liberte_provisoire', 'date_mainlevee_cj', 'date_cloture', 'nature_cloture', 'observations'])) : (object) [],
            ])->values(),
            'victimes' => $a->parties->where('role', 'victime')->map(fn (Partie $p) => [
                'id' => $p->id, 'nom' => $p->libelle(),
                'suivi' => ($s = $victimes->get($p->id)) ? array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $s->only(['traite', 'infraction_subie', 'audition_1', 'audition_2', 'audition_3', 'date_cloture', 'date_notification_cloture', 'observations'])) : (object) [],
            ])->values(),
        ]);
    }

    public function enregistrerDossier(int $id, Request $request): JsonResponse
    {
        Gate::authorize('instruction.modifier');
        $a = $this->affaires($request->user())->findOrFail($id);
        $v = $request->validate([
            'date_requisitoire' => ['nullable', 'date', 'before_or_equal:today'], 'textes_vises' => ['nullable', 'string', 'max:300'], 'date_faits' => ['nullable', 'date', 'before_or_equal:today'], 'lieu_faits' => ['nullable', 'string', 'max:160'],
            'nature_infraction' => ['nullable', Rule::in(array_keys(self::NATURES))], 'infraction_specifique' => ['nullable', 'string', 'max:80'], 'autre_specificite' => ['nullable', 'string', 'max:80'],
            'date_cloture' => ['nullable', 'date', 'before_or_equal:today'], 'nature_cloture' => ['nullable', 'string', 'max:120'], 'autres_ordonnances' => ['nullable', 'string', 'max:200'],
            'infraction_retenue' => ['nullable', 'string', 'max:200'], 'date_transmission_parquet' => ['nullable', 'date', 'before_or_equal:today'], 'observations' => ['nullable', 'string', 'max:500'],
        ]);
        $erreurs = $this->coherenceDates($a->date_rg?->toDateString(), $v);
        if ($erreurs) {
            return response()->json(['message' => 'Dates incohérentes.', 'errors' => $erreurs], 422);
        }
        $d = DossierInstruction::updateOrCreate(['affaire_id' => $a->id], $v + ['cree_par' => $request->user()?->id]);
        Journal::audit('instruction_dossier_enregistre', $a, $a->numeroAffichage(), [], array_filter($d->only(array_keys($v)), fn ($x) => $x !== null));

        return response()->json(['status' => true, 'message' => 'Dossier enregistré.']);
    }

    public function enregistrerInculpe(int $partie, Request $request): JsonResponse
    {
        Gate::authorize('instruction.modifier');
        $p = $this->partieDeLaFamille($partie, 'inculpe', $request);
        $v = $request->validate([
            'statut' => ['nullable', Rule::in(array_keys(self::STATUTS))], 'precisions_statut' => ['nullable', 'string', 'max:200'], 'date_ogp' => ['nullable', 'date', 'before_or_equal:today'],
            'date_titre_detention' => ['nullable', 'date', 'before_or_equal:today'], 'duree_detention_mois' => ['nullable', 'integer', 'between:1,120'], 'date_renouvellement' => ['nullable', 'date', 'before_or_equal:today'],
            'duree_renouvellement_mois' => ['nullable', 'integer', 'between:1,120'], 'date_liberte_provisoire' => ['nullable', 'date', 'before_or_equal:today'], 'date_mainlevee_cj' => ['nullable', 'date', 'before_or_equal:today'],
            'date_cloture' => ['nullable', 'date', 'before_or_equal:today'], 'nature_cloture' => ['nullable', 'string', 'max:120'], 'observations' => ['nullable', 'string', 'max:500'],
        ]);
        $ordre = ['date_titre_detention', 'date_renouvellement', 'date_liberte_provisoire', 'date_cloture'];
        $prec = null;
        foreach ($ordre as $champ) {
            if (! empty($v[$champ]) && $prec && $v[$champ] < $v[$prec]) {
                return response()->json(['message' => 'Dates incohérentes.', 'errors' => [$champ => ['Cette date ne peut pas précéder '.str_replace('_', ' ', $prec).'.']]], 422);
            }
            $prec = ! empty($v[$champ]) ? $champ : $prec;
        }
        $s = SuiviInculpe::updateOrCreate(['partie_id' => $p->id], $v + ['traite' => $request->boolean('traite')]);
        Journal::audit('instruction_inculpe_suivi', $p->affaire, $p->libelle(), [], array_filter($s->only(array_keys($v)), fn ($x) => $x !== null));

        return response()->json(['status' => true, 'message' => 'Suivi de l\'inculpé enregistré.']);
    }

    public function enregistrerVictime(int $partie, Request $request): JsonResponse
    {
        Gate::authorize('instruction.modifier');
        $p = $this->partieDeLaFamille($partie, 'victime', $request);
        $v = $request->validate([
            'infraction_subie' => ['nullable', 'string', 'max:200'], 'audition_1' => ['nullable', 'date', 'before_or_equal:today'], 'audition_2' => ['nullable', 'date', 'before_or_equal:today'], 'audition_3' => ['nullable', 'date', 'before_or_equal:today'],
            'date_cloture' => ['nullable', 'date', 'before_or_equal:today'], 'date_notification_cloture' => ['nullable', 'date', 'before_or_equal:today'], 'observations' => ['nullable', 'string', 'max:500'],
        ]);
        if (! empty($v['audition_2']) && (empty($v['audition_1']) || $v['audition_2'] < $v['audition_1']) || ! empty($v['audition_3']) && (empty($v['audition_2']) || $v['audition_3'] < $v['audition_2'])) {
            return response()->json(['message' => 'Les auditions se suivent dans l\'ordre.', 'errors' => ['audition_2' => ['Les auditions doivent se suivre dans l\'ordre (1re, 2e, 3e).']]], 422);
        }
        $s = SuiviVictime::updateOrCreate(['partie_id' => $p->id], $v + ['traite' => $request->boolean('traite')]);
        Journal::audit('instruction_victime_suivi', $p->affaire, $p->libelle(), [], array_filter($s->only(array_keys($v)), fn ($x) => $x !== null));

        return response()->json(['status' => true, 'message' => 'Suivi de la victime enregistré.']);
    }

    /** Les commissions rogatoires d'un cabinet pour une année, par mois. */
    public function commissions(Request $request): JsonResponse
    {
        Gate::authorize('instruction.voir');
        [$chambre, $annee] = $this->cabinetEtAnnee($request);
        $lignes = CommissionRogatoire::where('chambre_id', $chambre)->where('annee', $annee)->get()->groupBy(fn ($c) => $c->sens.'|'.$c->portee);
        $sortie = [];
        foreach (CommissionRogatoire::SENS as $sens => $_) {
            foreach (CommissionRogatoire::PORTEES as $portee => $__) {
                $par = ($lignes->get($sens.'|'.$portee) ?? collect())->keyBy('mois');
                $sortie[$sens.'|'.$portee] = collect(range(1, 12))->map(fn ($m) => ['mois' => $m, 'stock_initial' => (int) ($par[$m]->stock_initial ?? 0), 'nouvelles' => (int) ($par[$m]->nouvelles ?? 0), 'executees' => (int) ($par[$m]->executees ?? 0)])->all();
            }
        }

        return response()->json(['chambre_id' => $chambre, 'annee' => $annee, 'lignes' => $sortie]);
    }

    public function enregistrerCommissions(Request $request): JsonResponse
    {
        Gate::authorize('instruction.modifier');
        [$chambre, $annee] = $this->cabinetEtAnnee($request);
        $request->validate([
            'sens' => ['required', Rule::in(array_keys(CommissionRogatoire::SENS))], 'portee' => ['required', Rule::in(array_keys(CommissionRogatoire::PORTEES))],
            'mois' => ['required', 'array'], 'mois.*.stock_initial' => ['nullable', 'integer', 'between:0,9999'], 'mois.*.nouvelles' => ['nullable', 'integer', 'between:0,9999'], 'mois.*.executees' => ['nullable', 'integer', 'between:0,9999'],
        ]);
        foreach ($request->input('mois') as $m => $c) {
            $m = (int) $m;
            if ($m < 1 || $m > 12) {
                continue;
            }
            $stock = (int) ($c['stock_initial'] ?? 0);
            $nouvelles = (int) ($c['nouvelles'] ?? 0);
            $executees = (int) ($c['executees'] ?? 0);
            if ($executees > $stock + $nouvelles) {
                return response()->json(['message' => 'Mois '.$m.' : il y a plus de commissions exécutées que de commissions à exécuter.', 'errors' => ['mois' => ['Exécutées supérieures au total à exécuter (mois '.$m.').']]], 422);
            }
            CommissionRogatoire::updateOrCreate(['chambre_id' => $chambre, 'annee' => $annee, 'mois' => $m, 'sens' => $request->input('sens'), 'portee' => $request->input('portee')], ['stock_initial' => $stock, 'nouvelles' => $nouvelles, 'executees' => $executees]);
        }
        Journal::audit('instruction_commissions_enregistrees', null, Chambre::whereKey($chambre)->value('libelle').' '.$annee, [], ['sens' => $request->input('sens'), 'portee' => $request->input('portee')]);

        return response()->json(['status' => true, 'message' => 'Commissions rogatoires enregistrées.']);
    }

    /** Les affaires d'instruction (RI) et du juge des enfants (RE) que l'utilisateur peut voir. */
    /** Les lignes de la page, une page à la fois (tri, recherche et filtre d'état dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('instruction.voir');
        $base = $this->affaires($request->user())->with(['parties', 'chambre:id,libelle', 'modeSaisine:id,libelle'])->orderByDesc('date_rg')->orderByDesc('id');
        $f = (string) $request->input('filtre.5', '');
        if ($f === 'clos') {
            $base->whereIn('affaires.id', DossierInstruction::whereNotNull('date_cloture')->select('affaire_id'));
        } elseif ($f === 'en_cours') {
            $base->whereNotIn('affaires.id', DossierInstruction::whereNotNull('date_cloture')->select('affaire_id'));
        }
        $peutModifier = Gate::allows('instruction.modifier');

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['date_rg', 'id']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['numero_complet', 'objet', 'articles'], 'parties', ['nom', 'prenoms', 'denomination']),
            function (Affaire $a) use ($peutModifier) {
                $inculpes = $a->parties->where('role', 'inculpe');
                $suivis = SuiviInculpe::whereIn('partie_id', $inculpes->pluck('id'))->pluck('statut', 'partie_id');

                return view('instruction._ligne', [
                    'peutModifier' => $peutModifier,
                    'l' => ['a' => $a, 'dossier' => DossierInstruction::where('affaire_id', $a->id)->first(), 'inculpes' => $inculpes,
                        'detenus' => $inculpes->filter(fn ($p) => ($suivis[$p->id] ?? null) === 'detenu' || (! isset($suivis[$p->id]) && $p->estDetenu()))->count()],
                ])->render();
            },
        );
    }

    private function affaires($user)
    {
        return Affaire::visiblesPour($user)->whereIn('serie', ['RI', 'RE'])->where('statut', 'enrolee');
    }

    private function partieDeLaFamille(int $id, string $role, Request $request): Partie
    {
        $p = Partie::with('affaire')->where('role', $role)->findOrFail($id);
        abort_unless($this->affaires($request->user())->whereKey($p->affaire_id)->exists(), 404);

        return $p;
    }

    /** @return array{0: int, 1: int} */
    private function cabinetEtAnnee(Request $request): array
    {
        $chambre = (int) $request->input('chambre_id', $request->query('chambre_id'));
        abort_unless($chambre && in_array($chambre, FamillesAffaires::chambresInstruction(), true) && PerimetreChambres::voit($request->user(), $chambre), 404);

        return [$chambre, min(max((int) $request->input('annee', $request->query('annee', now()->year)), 2000), 2100)];
    }

    /** @return array<string, list<string>> */
    private function coherenceDates(?string $ri, array $v): array
    {
        $e = [];
        foreach (['date_requisitoire' => 'Le réquisitoire', 'date_cloture' => 'La clôture', 'date_transmission_parquet' => 'La transmission au parquet'] as $c => $lib) {
            if ($ri && ! empty($v[$c]) && $v[$c] < $ri && $c !== 'date_requisitoire') {
                $e[$c] = [$lib.' ne peut pas précéder l\'enregistrement du dossier ('.date('d/m/Y', strtotime($ri)).').'];
            }
        }
        if (! empty($v['date_cloture']) && ! empty($v['date_requisitoire']) && $v['date_cloture'] < $v['date_requisitoire']) {
            $e['date_cloture'] = ['La clôture ne peut pas précéder le réquisitoire.'];
        }
        if (! empty($v['date_transmission_parquet']) && ! empty($v['date_cloture']) && $v['date_transmission_parquet'] < $v['date_cloture']) {
            $e['date_transmission_parquet'] = ['La transmission au parquet suit l\'ordonnance de clôture.'];
        }
        if (! empty($v['date_faits']) && ! empty($v['date_requisitoire']) && $v['date_faits'] > $v['date_requisitoire']) {
            $e['date_faits'] = ['Les faits ne peuvent pas être postérieurs au réquisitoire.'];
        }

        return $e;
    }
}
