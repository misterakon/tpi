<?php

namespace App\Support\Audiences;

use App\Models\Affectation;
use App\Models\Chambre;
use App\Models\RoleAudience;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 | Les chiffres du tableau de bord des audiences (AUD-06), par chambre et par magistrat : affaires en cours, audiences à venir, délibérés en attente,
 | affaires à rédiger, affaires en retard. Un seul tableau de données sert à l'affichage et à l'actualisation sans rechargement.
 */
class TableauAudiences
{
    public const HORIZON_JOURS = 14;

    /**
     * Les magistrats (pas les greffiers) affectés aujourd'hui à une chambre du périmètre, avec leurs chambres.
     *
     * @param  list<int>|null  $perimetre
     * @return Collection<int, array{id: int, nom: string, fonction: string, chambres: list<int>}>
     */
    public static function magistrats(?array $perimetre): Collection
    {
        $jour = today()->toDateString();
        $affectations = Affectation::with(['agent', 'formation:id,chambre_id'])->where('suppleant', false)->where('role', '!=', 'greffier')
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', $jour))->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', $jour))->get()
            ->filter(fn ($x) => $x->agent && ! $x->agent->trashed() && $x->agent->actif && $x->formation);

        return $affectations->groupBy('agent_id')->map(function ($groupe) use ($perimetre) {
            $agent = $groupe->first()->agent;
            $chambres = $groupe->pluck('formation.chambre_id')->map(fn ($c) => (int) $c)->unique()->values()->all();
            if ($perimetre !== null) {
                $chambres = array_values(array_intersect($chambres, $perimetre));
            }

            return ['id' => (int) $agent->id, 'nom' => $agent->nomComplet(), 'fonction' => $agent->fonctionLibelle(), 'chambres' => $chambres];
        })->filter(fn ($m) => $m['chambres'])->sortBy('nom')->values();
    }

    /**
     * @param  list<int>|null  $perimetre  chambres que l'utilisateur voit (null : toutes)
     * @return array<string, mixed>
     */
    public static function donnees(?array $perimetre, ?int $chambreId = null, ?int $agentId = null): array
    {
        $magistrats = self::magistrats($perimetre);
        $portee = $perimetre;
        if ($agentId && ($m = $magistrats->firstWhere('id', $agentId))) {
            $portee = $m['chambres'];
        }
        if ($chambreId && ($portee === null || in_array($chambreId, $portee, true))) {
            $portee = [$chambreId];
        }

        $aujourdhui = today();
        $etats = SuiviAffaires::etats($portee, $aujourdhui);
        $enCours = $etats->where('en_cours', true);
        $deliberees = $etats->where('etat', 'deliberee');
        $retards = $etats->where('retard', true);
        $aRediger = $etats->where('a_rediger', true);

        $fin = $aujourdhui->copy()->addDays(self::HORIZON_JOURS - 1);
        $roles = RoleAudience::with(['chambre:id,libelle', 'salle:id,nom'])->withCount('lignes')
            ->whereBetween('date_audience', [$aujourdhui->toDateString(), $fin->toDateString()])
            ->when($portee !== null, fn ($q) => $q->whereIn('chambre_id', $portee ?: [0]))->orderBy('date_audience')->orderBy('heure')->get();

        $parJour = [];
        for ($i = 0; $i < self::HORIZON_JOURS; $i++) {
            $d = $aujourdhui->copy()->addDays($i);
            $duJour = $roles->filter(fn ($r) => $r->date_audience->isSameDay($d));
            $parJour[] = ['date' => $d->toDateString(), 'libelle' => ucfirst($d->translatedFormat('D d')), 'publie' => $duJour->where('statut', 'publie')->count(), 'brouillon' => $duJour->where('statut', 'brouillon')->count()];
        }

        $chambres = Chambre::whereIn('id', $etats->pluck('chambre_id')->unique())->orderBy('ordre')->get(['id', 'libelle']);
        $parChambre = $chambres->map(function ($c) use ($etats) {
            $e = $etats->where('chambre_id', $c->id);

            return ['id' => $c->id, 'libelle' => $c->libelle, 'a_venir' => $e->whereIn('etat', ['a_venir'])->count(), 'renvoyee' => $e->where('etat', 'renvoyee')->count(), 'deliberee' => $e->where('etat', 'deliberee')->count(),
                'retard' => $e->where('retard', true)->count(), 'a_rediger' => $e->where('a_rediger', true)->count(), 'en_cours' => $e->where('en_cours', true)->count()];
        })->filter(fn ($c) => $c['en_cours'] + $c['a_rediger'] > 0)->values()->all();

        $echeances = ['echus' => 0, 'semaine' => 0, 'mois' => 0, 'plus_tard' => 0, 'sans_date' => 0];
        foreach ($deliberees as $d) {
            if (! $d['echeance']) {
                $echeances['sans_date']++;
            } elseif ($d['echeance']->toDateString() <= $aujourdhui->toDateString()) {
                $echeances['echus']++;
            } elseif ($d['echeance']->diffInDays($aujourdhui) <= 7) {
                $echeances['semaine']++;
            } elseif ($d['echeance']->diffInDays($aujourdhui) <= 30) {
                $echeances['mois']++;
            } else {
                $echeances['plus_tard']++;
            }
        }

        $ligne = fn (array $a) => [
            'affaire_id' => $a['affaire_id'], 'numero' => $a['numero'], 'chambre' => $a['chambre'], 'parties' => $a['parties'], 'objet' => $a['objet'], 'etat' => $a['etat'],
            'echeance' => $a['echeance']?->format('d/m/Y'), 'jours' => $a['jours_retard'], 'nb_renvois' => $a['nb_renvois'], 'proroge' => $a['proroge'], 'motif' => $a['motif'], 'role_id' => $a['role_id'],
        ];
        $chambresDeLAgent = fn (array $m) => $m['chambres'];
        $parMagistrat = $magistrats->map(function ($m) use ($etats, $roles, $chambresDeLAgent, $aujourdhui) {
            $mien = $etats->whereIn('chambre_id', $chambresDeLAgent($m));
            $prochain = RoleAudience::whereIn('chambre_id', $m['chambres'])->whereDate('date_audience', '>=', $aujourdhui->toDateString())->orderBy('date_audience')->orderBy('heure')->first(['date_audience', 'heure']);

            return $m + ['en_cours' => $mien->where('en_cours', true)->count(), 'deliberee' => $mien->where('etat', 'deliberee')->count(), 'a_rediger' => $mien->where('a_rediger', true)->count(),
                'retard' => $mien->where('retard', true)->count(), 'prochaine' => $prochain ? ucfirst($prochain->date_audience->translatedFormat('D d/m')).($prochain->heure ? ' à '.str_replace(':', 'h', substr((string) $prochain->heure, 0, 5)) : '') : null,
                'chambres_libelles' => Chambre::whereIn('id', $m['chambres'])->orderBy('ordre')->pluck('libelle')->all()];
        })->values()->all();

        return [
            'genere_le' => now()->format('H:i'),
            'kpis' => [
                'en_cours' => $enCours->count(), 'renvoyees' => $etats->where('etat', 'renvoyee')->count(),
                'audiences' => $roles->count(), 'aujourdhui' => $roles->filter(fn ($r) => $r->date_audience->isSameDay($aujourdhui))->count(),
                'deliberes' => $deliberees->count(), 'deliberes_echus' => $echeances['echus'],
                'a_rediger' => $aRediger->count(),
                'retard' => $retards->count(), 'retard_renvois' => $retards->where('etat', 'renvoyee')->count(), 'retard_deliberes' => $retards->where('etat', 'deliberee')->count(),
            ],
            'repartition' => collect(SuiviAffaires::ETATS)->map(fn ($libelle, $cle) => ['cle' => $cle, 'libelle' => $libelle, 'n' => $etats->where('etat', $cle)->count()])->values()->all(),
            'par_chambre' => $parChambre,
            'echeances' => $echeances,
            'par_jour' => $parJour,
            'audiences' => $roles->take(8)->map(fn ($r) => [
                'id' => $r->id, 'chambre' => $r->chambre->libelle, 'date' => ucfirst($r->date_audience->translatedFormat('l d/m')), 'heure' => $r->heureCourte(), 'salle' => $r->salle?->nom,
                'statut' => $r->statut, 'etat' => $r->etatAudience(), 'affaires' => $r->lignes_count, 'aujourdhui' => $r->date_audience->isSameDay($aujourdhui),
            ])->values()->all(),
            'deliberes' => $deliberees->sortBy(fn ($a) => $a['echeance']?->toDateString() ?? '9999')->take(8)->map($ligne)->values()->all(),
            'retards' => $retards->sortByDesc('jours_retard')->take(8)->map($ligne)->values()->all(),
            'a_rediger' => $aRediger->take(6)->map($ligne)->values()->all(),
            'magistrats' => $parMagistrat,
            'filtre' => ['chambre' => $chambreId, 'agent' => $agentId],
        ];
    }
}
