<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Services\Journal;
use App\Support\Audiences\DatesAudience;
use App\Support\PerimetreChambres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | La feuille d'audience (AUD-01, AUD-02, AUD-03) : on ouvre l'audience d'un rôle publié, on contrôle la composition (empêchements, remplaçants),
 | puis le greffier note l'issue de chaque affaire à mesure de l'appel : renvoi (motif, prochaine date proposée selon le calendrier de la chambre),
 | mise en délibéré (date), jugée, radiée, jointe, désistement, avec la présence des parties. La saisie se fait sans recharger la page.
 | Ce qui est noté ici alimente le rôle suivant (renvois, délibérés) et le tableau de bord.
 */
class FeuilleAudienceController extends Controller
{
    // ---------------------------------------------------------------------------------------------------- liste des audiences

    public function index(Request $request): View
    {
        $perimetre = PerimetreChambres::de($request->user());
        $debut = today()->subDays(45)->toDateString();
        $fin = today()->addDays(45)->toDateString();
        $base = RoleAudience::with(['chambre:id,libelle', 'salle:id,nom'])->withCount('lignes')
            ->when($perimetre !== null, fn ($q) => $q->whereIn('chambre_id', $perimetre ?: [0]));

        $roles = (clone $base)->where('statut', 'publie')->whereBetween('date_audience', [$debut, $fin])->orderBy('date_audience')->orderBy('heure')->get();
        $traitees = RoleLigne::whereIn('role_id', $roles->pluck('id'))->whereNotNull('issue')->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');

        $audiences = $roles->map(fn (RoleAudience $r) => [
            'id' => $r->id, 'chambre' => $r->chambre->libelle, 'type' => $r->typeLibelle(), 'jour' => ucfirst($r->date_audience->translatedFormat('l d F Y')), 'date' => $r->date_audience->toDateString(),
            'court' => ucfirst($r->date_audience->translatedFormat('D d/m')), 'heure' => $r->heureCourte(), 'salle' => $r->salle?->nom, 'etat' => $r->etatAudience(),
            'total' => (int) $r->lignes_count, 'traitees' => (int) ($traitees[$r->id] ?? 0), 'aujourdhui' => $r->date_audience->isToday(), 'passee' => $r->date_audience->lt(today()),
            'penal' => $r->estPenal(),
        ])->values()->all();

        return view('audiences.feuille.index', [
            'audiences' => $audiences,
            'abPublier' => (clone $base)->where('statut', 'brouillon')->whereBetween('date_audience', [today()->toDateString(), $fin])->count(),
        ]);
    }

    // ---------------------------------------------------------------------------------------------------- la feuille

    public function show(int $id, Request $request): View|RedirectResponse
    {
        $role = $this->role($id, $request)->load('chambre', 'salle', 'lignes');
        if (! $role->estPublie()) {
            return redirect()->route('audiences.feuille')->with('error', 'Publiez d\'abord le rôle de cette audience : la feuille se fait sur le rôle publié.');
        }

        $lignes = $role->lignes->values();
        $historique = $this->historique($role, $lignes->pluck('affaire_id')->filter()->all());
        $donnees = $lignes->map(fn (RoleLigne $l) => $this->ligne($l, $role, $historique))->values()->all();

        return view('audiences.feuille.show', [
            'role' => $role, 'lignes' => $donnees, 'composition' => $role->compositionTenue(), 'peutModifier' => $request->user()->can('feuille.modifier'),
            'resume' => $this->resume($role->lignes),
        ]);
    }

    /** Les prochaines audiences habituelles de la chambre et l'analyse d'une date (jour fermé, chambre qui ne siège pas). */
    public function dates(int $id, Request $request): JsonResponse
    {
        $role = $this->role($id, $request)->load('chambre');
        $date = $request->query('date');
        $analyse = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? DatesAudience::analyser($role->chambre, Carbon::parse($date), $role->date_audience) : null;

        return response()->json(['analyse' => $analyse, 'prochaines' => DatesAudience::prochaines($role->chambre, $role->date_audience, $role->date_audience, 4)]);
    }

    public function ouvrir(int $id, Request $request): JsonResponse
    {
        Gate::authorize('feuille.modifier');
        $role = $this->publie($id, $request);
        if (! $role->audience_ouverte_le) {
            $this->ouvrirSiBesoin($role, $request);
            Journal::audit('audience_ouverte', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['composition' => count($role->composition_tenue ?? [])]);
        }

        return response()->json(['etat' => $role->etatAudience(), 'composition' => $role->compositionTenue(), 'message' => 'Audience ouverte.']);
    }

    /** La composition réellement présente : présence de chacun et remplaçant en cas d'empêchement. */
    public function composition(int $id, Request $request): JsonResponse
    {
        Gate::authorize('feuille.modifier');
        $role = $this->publie($id, $request);
        $this->refuserSiTerminee($role);
        $donnees = $request->validate([
            'membres' => ['required', 'array', 'max:20'],
            'membres.*.agent_id' => ['nullable', 'integer'], 'membres.*.nom' => ['required', 'string', 'max:120'], 'membres.*.role' => ['required', 'string', 'max:60'],
            'membres.*.present' => ['required', 'boolean'], 'membres.*.remplacant' => ['nullable', 'string', 'max:120'],
        ]);
        $role->forceFill([
            'composition_tenue' => collect($donnees['membres'])->map(fn ($m) => [
                'agent_id' => (int) ($m['agent_id'] ?? 0), 'nom' => $this->net($m['nom']), 'role' => $this->net($m['role']), 'present' => (bool) $m['present'],
                'remplacant' => ($m['present'] ?? true) ? null : (($r = $this->net((string) ($m['remplacant'] ?? ''))) !== '' ? $r : null),
            ])->all(),
        ])->save();
        Journal::audit('audience_composition', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['absents' => collect($role->composition_tenue)->where('present', false)->pluck('nom')->all()]);

        return response()->json(['message' => 'Composition enregistrée.', 'etat' => $role->etatAudience()]);
    }

    /** Note l'issue d'une affaire à l'audience. Les champs utiles dépendent de la suite : date et motif de renvoi, date de délibéré, motif. */
    public function suite(int $id, int $ligne, Request $request): JsonResponse
    {
        Gate::authorize('feuille.modifier');
        $role = $this->publie($id, $request);
        $this->refuserSiTerminee($role);
        $l = $role->lignes()->findOrFail($ligne);
        $jour = $role->date_audience->toDateString();
        $donnees = $request->validate([
            'issue' => ['nullable', Rule::in(array_keys(RoleLigne::ISSUES))],
            'renvoi_au' => ['nullable', 'date', 'required_if:issue,renvoi', 'after:'.$jour],
            'date_delibere' => ['nullable', 'date', 'after:'.$jour],
            'motif' => ['nullable', 'string', 'max:200'],
            'presence' => ['nullable', Rule::in(array_keys(RoleLigne::PRESENCES))],
            'note_audience' => ['nullable', 'string', 'max:500'],
        ], ['renvoi_au.required_if' => 'Indiquez la date de renvoi.', 'renvoi_au.after' => "La date de renvoi doit suivre celle de l'audience.", 'date_delibere.after' => "La date de délibéré doit suivre celle de l'audience."]);

        $issue = $donnees['issue'] ?? null;
        $avant = $l->only(['issue', 'renvoi_au', 'date_delibere', 'motif', 'presence']);
        $motif = isset($donnees['motif']) ? $this->net($donnees['motif']) : '';
        $l->update([
            'issue' => $issue,
            'renvoi_au' => $issue === 'renvoi' ? $donnees['renvoi_au'] : null,
            'date_delibere' => $issue === 'delibere' ? ($donnees['date_delibere'] ?? null) : null,
            'motif' => $issue && $motif !== '' ? $motif : null,
            'presence' => $donnees['presence'] ?? null,
            'note_audience' => ($n = $this->net((string) ($donnees['note_audience'] ?? ''))) !== '' ? $n : null,
            'proroge' => $issue === $avant['issue'] ? $l->proroge : 0,
            'suite_le' => $issue ? ($issue === $avant['issue'] && $l->suite_le ? $l->suite_le : now()) : null,
        ]);
        $this->ouvrirSiBesoin($role, $request);
        Journal::audit('suite_notee', $role, ($l->numero ? strtok($l->numero, "\n") : 'ligne libre').' – '.($issue ? RoleLigne::ISSUES[$issue] : 'suite effacée'), $avant, $l->only(['issue', 'renvoi_au', 'date_delibere', 'motif', 'presence']));

        $role->load('lignes');
        $historique = $this->historique($role, [$l->affaire_id]);

        return response()->json([
            'message' => $issue ? 'Suite enregistrée : '.RoleLigne::ISSUES[$issue].'.' : 'Suite effacée.',
            'ligne' => $this->ligne($l->refresh(), $role, $historique), 'resume' => $this->resume($role->lignes), 'etat' => $role->etatAudience(),
        ]);
    }

    public function terminer(int $id, Request $request): JsonResponse
    {
        Gate::authorize('feuille.modifier');
        $role = $this->publie($id, $request);
        $this->refuserSiTerminee($role);
        $donnees = $request->validate(['note_audience' => ['nullable', 'string', 'max:1000']]);
        $sans = $role->lignes()->whereNull('issue')->count();
        $role->forceFill(['audience_terminee_le' => now(), 'audience_ouverte_le' => $role->audience_ouverte_le ?? now(), 'note_audience' => ($n = $this->net((string) ($donnees['note_audience'] ?? ''))) !== '' ? $n : null])->save();
        Journal::audit('audience_terminee', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['sans_suite' => $sans]);

        return response()->json(['message' => 'Audience terminée.'.($sans ? ' '.$sans.' affaire'.($sans > 1 ? 's' : '').' sans suite : '.($sans > 1 ? 'elles reviendront' : 'elle reviendra').' parmi les anciennes du prochain rôle.' : ''), 'etat' => $role->etatAudience(), 'sans_suite' => $sans]);
    }

    public function rouvrir(int $id, Request $request): JsonResponse
    {
        Gate::authorize('feuille.modifier');
        $role = $this->publie($id, $request);
        $role->forceFill(['audience_terminee_le' => null])->save();
        Journal::audit('audience_rouverte', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], []);

        return response()->json(['message' => 'Audience rouverte : vous pouvez corriger les suites.', 'etat' => $role->etatAudience()]);
    }

    // ---------------------------------------------------------------------------------------------------- aides

    private function role(int $id, Request $request): RoleAudience
    {
        $role = RoleAudience::with('chambre')->findOrFail($id);
        abort_unless(PerimetreChambres::voit($request->user(), (int) $role->chambre_id), 404);

        return $role;
    }

    private function publie(int $id, Request $request): RoleAudience
    {
        $role = $this->role($id, $request);
        abort_unless($role->estPublie(), 422, 'Le rôle n\'est pas publié.');

        return $role;
    }

    private function refuserSiTerminee(RoleAudience $role): void
    {
        abort_if($role->audience_terminee_le !== null, 422, 'L\'audience est terminée : rouvrez-la pour corriger.');
    }

    /** L'audience s'ouvre d'elle-même à la première saisie. */
    private function ouvrirSiBesoin(RoleAudience $role, Request $request): void
    {
        if (! $role->audience_ouverte_le) {
            $role->forceFill(['audience_ouverte_le' => now(), 'audience_ouverte_par' => $request->user()->id, 'composition_tenue' => $role->composition_tenue ?: $role->compositionTenue()])->save();
        }
    }

    private function net(string $texte): string
    {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texte));
    }

    /** Les passages antérieurs des affaires du rôle sur des rôles publiés : date, suite, motif. @return array<int, list<array<string, mixed>>> */
    private function historique(RoleAudience $role, array $affaires): array
    {
        if (! $affaires) {
            return [];
        }
        $lignes = RoleLigne::query()->select('role_lignes.*', 'roles_audience.date_audience as date_role')
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')
            ->whereIn('role_lignes.affaire_id', $affaires)->whereDate('roles_audience.date_audience', '<', $role->date_audience->toDateString())
            ->orderBy('roles_audience.date_audience')->orderBy('role_lignes.id')->get();

        return $lignes->groupBy('affaire_id')->map(fn ($g) => $g->map(fn ($x) => [
            'date' => Carbon::parse($x->date_role)->format('d/m/Y'), 'issue' => $x->issue, 'libelle' => $x->issue ? RoleLigne::ISSUES[$x->issue] : 'Suite non notée',
            'detail' => $x->issue === 'renvoi' && $x->renvoi_au ? 'au '.$x->renvoi_au->format('d/m/Y') : ($x->issue === 'delibere' && $x->date_delibere ? 'jusqu\'au '.$x->date_delibere->format('d/m/Y') : null), 'motif' => $x->motif,
        ])->values()->all())->all();
    }

    private function ligne(RoleLigne $l, RoleAudience $role, array $historique): array
    {
        $lignes = fn (?string $t) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $t))));
        $avant = $historique[$l->affaire_id] ?? [];
        $demande = $lignes($l->demandeurs)[0] ?? '';
        $contre = $lignes($l->defendeurs)[0] ?? '';

        return [
            'id' => $l->id, 'rubrique' => $l->rubrique, 'n' => $l->ordre, 'affaire_id' => $l->affaire_id, 'fiche' => $l->affaire_id && Gate::allows('affaires.voir') ? route('affaires.liste.fiche', $l->affaire_id) : null, 'numero' => $lignes($l->numero), 'penal' => $role->estPenal(),
            'parties' => $role->estPenal() ? $demande : trim($demande.($contre !== '' ? ' c/ '.$contre : '')), 'objet' => (string) $l->objet,
            'issue' => $l->issue, 'renvoi_au' => $l->renvoi_au?->format('Y-m-d'), 'renvoi_au_libelle' => $l->renvoi_au?->format('d/m/Y'),
            'date_delibere' => $l->date_delibere?->format('Y-m-d'), 'date_delibere_libelle' => $l->date_delibere?->format('d/m/Y'),
            'motif' => $l->motif, 'presence' => $l->presence, 'note' => $l->note_audience,
            'renvois_avant' => collect($avant)->where('issue', 'renvoi')->count(), 'historique' => $avant,
        ];
    }

    /** Compteurs par suite et avancement. @return array{total: int, traitees: int, parSuite: array<string, int>} */
    private function resume($lignes): array
    {
        $par = [];
        foreach (array_keys(RoleLigne::ISSUES) as $cle) {
            $par[$cle] = $lignes->where('issue', $cle)->count();
        }

        return ['total' => $lignes->count(), 'traitees' => $lignes->whereNotNull('issue')->count(), 'parSuite' => $par];
    }
}
