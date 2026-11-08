<?php

namespace App\Support\Alertes;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Qui doit agir : une clé de rôle d'une règle d'alerte (greffier de la chambre, président de formation, Présidente…) devient des personnes,
 | d'après les affectations de l'ordonnance (aujourd'hui) et la fonction des agents. Seuls les comptes actifs reliés à un agent sont prévenus.
 */
class Destinataires
{
    /** @var array<int, Collection<int, Affectation>> affectations en cours par chambre (le moteur les demande souvent) */
    private array $affectations = [];

    /** @var array<string, Collection<int, User>> */
    private array $fonctions = [];

    /**
     * @param  list<string>  $roles  clés de RegleAlerte::DESTINATAIRES
     * @return Collection<int, User> comptes actifs, sans doublon
     */
    public function pour(?int $chambreId, array $roles): Collection
    {
        $users = collect();
        foreach ($roles as $role) {
            $users = $users->concat(match ($role) {
                'greffier_chambre' => $this->deLaChambre($chambreId, fn ($a) => $a->role === 'greffier'),
                'president_formation' => $this->deLaChambre($chambreId, fn ($a) => $a->role === 'president'),
                'magistrats_chambre' => $this->deLaChambre($chambreId, fn ($a) => ! in_array($a->role, ['president', 'greffier'], true)),
                'vice_presidents' => $this->deLaFonction('vice_president'),
                'presidente' => $this->deLaFonction('presidente'),
                'greffier_en_chef' => $this->deLaFonction('greffier_en_chef'),
                'secretariat_presidence' => User::where('actif', true)->whereIn('profil_id', Profil::all()->filter(fn ($p) => in_array('requetes.repartir', $p->droits ?? [], true))->pluck('id'))->get(),
                'administrateur' => User::where('actif', true)->whereIn('profil_id', Profil::where('systeme', true)->select('id'))->get(),
                default => collect(),
            });
        }

        return $users->filter(fn (User $u) => $u->actif)->unique('id')->values();
    }

    /** @return Collection<int, User> */
    private function deLaChambre(?int $chambreId, callable $filtre): Collection
    {
        if (! $chambreId) {
            return collect();
        }
        $this->affectations[$chambreId] ??= Affectation::with('agent')->whereIn('formation_id', \App\Models\Formation::where('chambre_id', $chambreId)->where('actif', true)->select('id'))
            ->where('suppleant', false)
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', today()->toDateString()))
            ->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()->toDateString()))
            ->get();

        return $this->comptes($this->affectations[$chambreId]->filter($filtre)->pluck('agent')->filter()->unique('id'));
    }

    /** @return Collection<int, User> */
    private function deLaFonction(string $fonction): Collection
    {
        return $this->fonctions[$fonction] ??= $this->comptes(Agent::where('fonction', $fonction)->where('actif', true)->get());
    }

    /** Les comptes actifs reliés à ces agents. @param  Collection<int, Agent>  $agents @return Collection<int, User> */
    private function comptes(Collection $agents): Collection
    {
        $ids = $agents->filter(fn ($a) => ! $a->trashed())->pluck('user_id')->filter()->all();

        return $ids ? User::where('actif', true)->whereIn('id', $ids)->get() : collect();
    }
}
