<?php

namespace App\Support\Audiences;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\RoleLigne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 | Où en est chaque affaire enrôlée, d'après ce qui a été noté aux audiences (lignes des rôles publiés) :
 |   - a_venir     : jamais jugée, son prochain passage est à venir (première audience ou rôle publié à venir) ;
 |   - non_portee  : sa première audience est passée mais elle n'a jamais figuré sur un rôle publié (en retard) ;
 |   - sans_suite  : elle a figuré sur un rôle passé sans qu'on ait noté la suite (en retard) ;
 |   - renvoyee    : renvoyée à une date ; en retard si cette date est passée sans nouveau passage ;
 |   - deliberee   : en délibéré (avec ou sans date) ; échue quand la date de délibéré est atteinte ;
 |   - jugee       : jugée ; la décision reste « à rédiger » tant qu'aucune décision n'est validée ;
 |   - terminee    : radiée, jointe à une autre ou désistement.
 | Les affaires reprises d'un classeur sans aucune ligne de rôle sont ignorées (leur état est inconnu).
 */
class SuiviAffaires
{
    public const ETATS = [
        'a_venir' => 'À venir', 'renvoyee' => 'Renvoyées', 'deliberee' => 'En délibéré', 'sans_suite' => 'Suite non notée', 'non_portee' => 'Jamais portées au rôle',
        'jugee' => 'Jugées', 'terminee' => 'Terminées', 'mise_en_etat' => 'En mise en état',
    ];

    /**
     * @param  list<int>|null  $chambres  null : toutes les chambres
     * @return Collection<int, array<string, mixed>>
     */
    public static function etats(?array $chambres, ?Carbon $aujourdhui = null): Collection
    {
        $jour = ($aujourdhui ?? today())->toDateString();
        $affaires = Affaire::with(['chambre:id,libelle', 'parties'])
            ->withExists(['miseEnEtatOuverte as en_mise_en_etat'])
            ->where('statut', 'enrolee')->whereNull('jointe_a_id')->whereNotNull('chambre_id')
            ->when($chambres !== null, fn ($q) => $q->whereIn('chambre_id', $chambres ?: [0]))->get();
        if ($affaires->isEmpty()) {
            return collect();
        }

        $lignes = RoleLigne::query()->select('role_lignes.*', 'roles_audience.date_audience as date_role', 'roles_audience.id as id_role')
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')
            ->whereIn('role_lignes.affaire_id', $affaires->pluck('id'))
            ->orderBy('roles_audience.date_audience')->orderBy('role_lignes.id')->get()->groupBy('affaire_id');

        $jugees = $lignes->filter(fn ($l) => $l->last()->issue === 'juge')->keys();
        $decidees = $jugees->isEmpty() ? collect() : Decision::whereIn('affaire_id', $jugees)->whereIn('statut', Decision::STATUTS_VALIDES)->pluck('affaire_id')->flip();

        $resultat = collect();
        foreach ($affaires as $a) {
            $suite = $lignes->get($a->id);
            $derniere = $suite?->last();
            $premiere = $a->date_premiere_audience;
            $base = [
                'affaire_id' => $a->id, 'numero' => $a->numeroAffichage(), 'chambre_id' => $a->chambre_id, 'chambre' => $a->chambre?->libelle, 'parties' => self::parties($a),
                'objet' => (string) $a->objet, 'enrolee_le' => $a->date_rg?->format('d/m/Y'), 'premiere' => $premiere, 'nb_renvois' => (int) $suite?->where('issue', 'renvoi')->count(), 'derniere_audience' => $derniere?->date_role ? Carbon::parse($derniere->date_role) : null,
                'motif' => $derniere?->motif, 'role_id' => $derniere?->id_role, 'ligne_id' => $derniere?->id, 'proroge' => (int) $derniere?->proroge, 'echeance' => null, 'retard' => false, 'jours_retard' => 0, 'a_rediger' => false,
            ];

            if ($a->en_mise_en_etat) {
                // En mise en état : le dossier est instruit par le juge chargé ; il n'est ni « à mettre au rôle » ni en retard de rôle.
                $etat = 'mise_en_etat';
                $echeance = null;
            } elseif (! $derniere) {
                if ($a->reprise_import_id || ! $premiere) {
                    continue;
                }
                $etat = $premiere->toDateString() >= $jour ? 'a_venir' : 'non_portee';
                $echeance = $premiere;
            } elseif (! $derniere->issue) {
                $echeance = Carbon::parse($derniere->date_role);
                $etat = $echeance->toDateString() >= $jour ? 'a_venir' : 'sans_suite';
            } elseif ($derniere->issue === 'renvoi') {
                $etat = 'renvoyee';
                $echeance = $derniere->renvoi_au;
            } elseif ($derniere->issue === 'delibere') {
                $etat = 'deliberee';
                $echeance = $derniere->date_delibere;
            } elseif ($derniere->issue === 'juge') {
                $etat = 'jugee';
                $echeance = null;
            } else {
                $etat = 'terminee';
                $echeance = null;
            }

            $retard = match ($etat) {
                'non_portee', 'sans_suite' => true,
                'renvoyee' => $echeance && $echeance->toDateString() < $jour,
                'deliberee' => $echeance && $echeance->toDateString() <= $jour,
                default => false,
            };
            $resultat->push(array_merge($base, ['etat' => $etat, 'echeance' => $echeance, 'retard' => (bool) $retard, 'jours_retard' => $retard && $echeance ? (int) $echeance->diffInDays(Carbon::parse($jour)) : 0,
                'a_rediger' => $etat === 'jugee' && ! $decidees->has($a->id), 'en_cours' => ! in_array($etat, ['jugee', 'terminee'], true)]));
        }

        return $resultat->values();
    }

    /** « KOFFI Adjoua c/ Société BATIMAT SARL » (et d'autres s'il y en a plusieurs). */
    public static function parties(Affaire $a): string
    {
        $cote = fn (array $roles) => $a->parties->filter(fn ($p) => in_array($p->role, $roles, true))->values();
        $format = function ($liste) {
            if ($liste->isEmpty()) {
                return '';
            }

            return $liste->first()->libelle().($liste->count() > 1 ? ' et '.($liste->count() - 1).' autre'.($liste->count() > 2 ? 's' : '') : '');
        };
        $contre = $cote(['defendeur', 'victime']);
        $demande = $cote(['demandeur', 'prevenu', 'inculpe']);

        return trim($format($demande).($contre->isNotEmpty() ? ' c/ '.$format($contre) : ''));
    }
}
