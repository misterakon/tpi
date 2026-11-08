<?php

namespace App\Support\Redaction;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\RoleLigne;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Choix de l'affaire dans la page Rédaction : on cherche dans le registre (numéro ou partie) au lieu de taper une référence à la main.
 | Sans recherche, on propose d'abord les affaires jugées dont la décision reste « à rédiger » (aucune décision validée),
 | puis la chambre et la date d'audience se remplissent d'après le dernier passage de l'affaire à l'audience.
 */
class ChoixAffaire
{
    private const LIMITE = 20;

    /** @return list<array<string, mixed>> */
    public static function rechercher(User $user, string $terme): array
    {
        $terme = mb_substr(trim($terme), 0, 80);
        $base = Affaire::visiblesPour($user)->with(['parties', 'chambre:id,libelle'])->where('statut', '!=', 'annulee');

        if ($terme === '') {
            // D'abord les jugées à rédiger, puis les affaires les plus récemment enrôlées pour compléter la liste.
            $affaires = self::enAttente($user, clone $base);
            $reste = self::LIMITE - $affaires->count();
            if ($reste > 0) {
                $affaires = $affaires->concat((clone $base)->whereNotIn('id', $affaires->pluck('id'))->orderByDesc('id')->limit($reste)->get())->values();
            }
        } else {
            $mot = '%'.addcslashes($terme, '%_\\').'%';
            $affaires = $base->where(fn ($q) => $q->where('numero_complet', 'like', $mot)->orWhere('objet', 'like', $mot)
                ->orWhereHas('parties', fn ($p) => $p->where('nom', 'like', $mot)->orWhere('prenoms', 'like', $mot)->orWhere('denomination', 'like', $mot)))
                ->orderByDesc('annee')->orderByDesc('numero')->limit(self::LIMITE)->get();
        }

        $ids = $affaires->pluck('id')->all();
        $derniers = self::derniers($ids);
        $aRediger = self::aRediger($derniers);

        return $affaires->map(fn (Affaire $a) => self::fiche($a, $derniers[$a->id] ?? null, isset($aRediger[$a->id])))
            ->sortByDesc('a_rediger')->values()->all();
    }

    /** Les affaires jugées sans décision validée, les plus récentes d'abord. */
    private static function enAttente(User $user, $base): Collection
    {
        $jugees = RoleLigne::query()->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')
            ->where('roles_audience.statut', 'publie')->where('role_lignes.issue', 'juge')->whereNotNull('role_lignes.affaire_id')
            ->orderByDesc('roles_audience.date_audience')->limit(80)->pluck('role_lignes.affaire_id')->unique()->values()->all();
        if (! $jugees) {
            return collect();
        }
        $affaires = $base->whereIn('id', $jugees)->get()->sortBy(fn ($a) => array_search($a->id, $jugees, true))->values();
        $derniers = self::derniers($affaires->pluck('id')->all());
        $aRediger = self::aRediger($derniers);

        return $affaires->filter(fn ($a) => isset($aRediger[$a->id]))->take(self::LIMITE)->values();
    }

    /** Dernier passage noté de chaque affaire. @param  list<int>  $ids @return array<int, array{issue: ?string, date: string, role_id: int}> */
    public static function derniers(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $lignes = RoleLigne::query()->select('role_lignes.affaire_id', 'role_lignes.issue', 'roles_audience.date_audience as date_role', 'roles_audience.id as id_role')
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')->where('roles_audience.statut', 'publie')
            ->whereIn('role_lignes.affaire_id', $ids)->orderBy('roles_audience.date_audience')->orderBy('role_lignes.id')->get()->groupBy('affaire_id');

        $derniers = [];
        foreach ($lignes as $id => $suite) {
            $l = $suite->last();
            $derniers[(int) $id] = ['issue' => $l->issue, 'date' => substr((string) $l->date_role, 0, 10), 'role_id' => (int) $l->id_role];
        }

        return $derniers;
    }

    /** Parmi ces derniers passages, les affaires jugées sans décision validée. @return array<int, true> */
    public static function aRediger(array $derniers): array
    {
        $jugees = array_keys(array_filter($derniers, fn ($d) => $d['issue'] === 'juge'));
        if (! $jugees) {
            return [];
        }
        $validees = Decision::whereIn('affaire_id', $jugees)->whereIn('statut', Decision::STATUTS_VALIDES)->pluck('affaire_id')->flip();

        return array_fill_keys(array_values(array_filter($jugees, fn ($id) => ! isset($validees[$id]))), true);
    }

    /** @param  array{issue: ?string, date: string, role_id: int}|null  $dernier @return array<string, mixed> */
    public static function fiche(Affaire $a, ?array $dernier, bool $aRediger): array
    {
        $intitule = $a->intitule();
        $audience = $dernier['date'] ?? $a->date_premiere_audience?->toDateString();

        return [
            'id' => $a->id, 'numero' => $a->numeroAffichage(), 'intitule' => $intitule, 'text' => $a->numeroAffichage().' — '.$intitule,
            'chambre_id' => $a->chambre_id, 'chambre' => (string) $a->chambre?->libelle, 'audience' => $audience,
            'a_rediger' => $aRediger, 'suite' => $dernier && $dernier['issue'] ? (RoleLigne::ISSUES[$dernier['issue']] ?? null) : null,
        ];
    }

    /** La fiche d'une seule affaire (ouverture depuis la feuille d'audience). @return array<string, mixed> */
    public static function pour(Affaire $a): array
    {
        $a->loadMissing(['parties', 'chambre:id,libelle']);
        $derniers = self::derniers([$a->id]);

        return self::fiche($a, $derniers[$a->id] ?? null, isset(self::aRediger($derniers)[$a->id]));
    }
}
