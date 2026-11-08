<?php

namespace App\Support\Roles;

use App\Models\Affaire;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use Illuminate\Support\Collection;

/*
 | Compose le rôle sans ressaisie, d'après les affaires de la chambre et ce qui s'est passé aux rôles précédents :
 |   - délibérés : la dernière suite donnée à l'affaire est « mise en délibéré » ;
 |   - anciennes : renvoyées (ou dont la suite n'a pas été saisie), dont la date de renvoi est atteinte ; les affaires jamais portées à un rôle y figurent aussi ;
 |   - nouvelles : jamais portées à un rôle, dont la première audience est cette date.
 | Les affaires jugées, radiées ou jointes sortent. Les affaires reprises d'un classeur n'entrent pas d'elles-mêmes (on ignore leur état) : on les ajoute à la main.
 */
class GenerateurRole
{
    /** @return array{deliberes: Collection, anciennes: Collection, nouvelles: Collection} */
    public static function proposer(RoleAudience $role): array
    {
        $deja = $role->lignes()->whereNotNull('affaire_id')->pluck('affaire_id')->all();
        $date = $role->date_audience->toDateString();
        $resultat = ['deliberes' => collect(), 'anciennes' => collect(), 'nouvelles' => collect()];

        $affaires = Affaire::with(['parties', 'jointes'])
            ->where('chambre_id', $role->chambre_id)->where('statut', 'enrolee')->whereNull('jointe_a_id')
            ->whereDoesntHave('miseEnEtatOuverte')   // une affaire en mise en état ne figure sur aucun rôle de jugement avant sa clôture
            ->whereNotNull('date_premiere_audience')->whereDate('date_premiere_audience', '<=', $date)
            ->whereNotIn('id', $deja ?: [0])->get();
        if ($affaires->isEmpty()) {
            return $resultat;
        }

        $dernieres = RoleLigne::query()->select('role_lignes.*', 'roles_audience.date_audience as date_role')
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')
            ->whereIn('role_lignes.affaire_id', $affaires->pluck('id'))->whereDate('roles_audience.date_audience', '<', $date)
            ->orderByDesc('roles_audience.date_audience')->orderByDesc('role_lignes.id')->get()->unique('affaire_id')->keyBy('affaire_id');

        foreach ($affaires as $a) {
            $d = $dernieres->get($a->id);
            if (! $d) {
                if ($a->reprise_import_id) {
                    continue;
                }
                $rubrique = $a->date_premiere_audience->toDateString() === $date ? 'nouvelles' : 'anciennes';
                $resultat[$rubrique]->push([$a, null]);

                continue;
            }
            if (in_array($d->issue, RoleLigne::ISSUES_FINALES, true)) {
                continue;
            }
            if ($d->issue === 'delibere') {
                // Un délibéré à date fixée ne revient au rôle qu'à cette date (ou après) ; sans date, il revient au rôle suivant.
                if ($d->date_delibere && $d->date_delibere->toDateString() > $date) {
                    continue;
                }
                $resultat['deliberes']->push([$a, $d->date_role]);
            } elseif (! ($d->issue === 'renvoi' && $d->renvoi_au && $d->renvoi_au->toDateString() > $date)) {
                $resultat['anciennes']->push([$a, null]);
            }
        }

        $ordre = fn (Collection $c) => $c->sortBy(fn ($x) => sprintf('%s|%04d|%08d', $x[1] ?? '', $x[0]->annee, $x[0]->numero))->map(fn ($x) => $x[0])->values();

        return ['deliberes' => $ordre($resultat['deliberes']), 'anciennes' => $ordre($resultat['anciennes']), 'nouvelles' => $ordre($resultat['nouvelles'])];
    }

    /** Ajoute au rôle les affaires proposées qui n'y sont pas encore ; rend le nombre de lignes ajoutées. */
    public static function completer(RoleAudience $role): int
    {
        $ajoutees = 0;
        foreach (self::proposer($role) as $rubrique => $affaires) {
            $ordre = (int) $role->lignes()->where('rubrique', $rubrique)->max('ordre');
            foreach ($affaires as $a) {
                $role->lignes()->create(self::attributs($a, $rubrique, ++$ordre, 'auto', $role->type));
                $ajoutees++;
            }
        }

        return $ajoutees;
    }

    /**
     * Les champs d'une ligne tirés de l'affaire. Civil et référé : numéro (avec les affaires jointes), parties et avocats de chaque côté, objet
     * (en référé, l'observation EXPUL. ou ORDIN. est proposée). Pénal : numéro RP, identité des prévenus (âge, nationalité, profession),
     * date du mandat de dépôt, nature de la prévention suivie des articles, et « Scellé » s'il y a des scellés.
     */
    public static function attributs(Affaire $a, string $rubrique, int $ordre, string $origine, string $type = 'civil'): array
    {
        $a->loadMissing('parties', 'jointes');
        if ($type === 'penal') {
            return self::attributsPenal($a, $rubrique, $ordre, $origine);
        }
        $cote = fn (array $roles) => $a->parties->filter(fn ($p) => in_array($p->role, $roles, true));
        $demande = $cote(['demandeur', 'prevenu', 'inculpe']);
        $defend = $cote(['defendeur', 'victime']);
        $avocats = fn ($parties) => $parties->pluck('avocat')->map(fn ($v) => trim((string) $v))->filter()->unique()->implode("\n");
        $numeros = collect([$a->numeroAffichage()])->merge($a->jointes->map(fn ($j) => 'Et '.$j->numeroAffichage()))->implode("\n");

        return [
            'rubrique' => $rubrique, 'ordre' => $ordre, 'affaire_id' => $a->id, 'numero' => $numeros,
            'demandeurs' => $demande->map(fn ($p) => $p->libelle())->implode("\n"),
            'defendeurs' => $defend->map(fn ($p) => $p->libelle())->implode("\n"),
            'avocats_demandeurs' => $avocats($demande), 'avocats_defendeurs' => $avocats($defend),
            'objet' => $a->objet, 'origine' => $origine,
            'observations' => $type === 'refere' ? (preg_match('/expuls|d[ée]guerpiss/iu', (string) $a->objet) ? 'EXPUL.' : 'ORDIN.') : null,
        ];
    }

    private static function attributsPenal(Affaire $a, string $rubrique, int $ordre, string $origine): array
    {
        $poursuivis = $a->parties->filter(fn ($p) => in_array($p->role, ['prevenu', 'inculpe'], true))->values();
        $plusieurs = $poursuivis->count() > 1;
        $identites = $poursuivis->map(fn ($p, $i) => ($plusieurs ? ($i + 1).'- ' : '').$p->libelle().($p->identite() !== '' ? "\n".$p->identite() : ''))->implode("\n");

        return [
            'rubrique' => $rubrique, 'ordre' => $ordre, 'affaire_id' => $a->id, 'numero' => $a->numeroAffichage(),
            'demandeurs' => $identites,
            'defendeurs' => $poursuivis->map(fn ($p) => $p->mandat_depot_date?->format('d-m-y'))->filter()->implode("\n"),
            'avocats_demandeurs' => null, 'avocats_defendeurs' => null,
            'objet' => trim($a->objet.($a->articles ? "\nArts : ".$a->articles : '')),
            'observations' => $a->scelles()->exists() ? 'Scellé' : null,
            'origine' => $origine,
        ];
    }
}
