<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 | Personne du tribunal (magistrat ou greffier) qui peut être affectée à une formation.
 | Distincte du compte utilisateur : un agent peut ne pas avoir de compte ; le lien est facultatif.
 */
class Agent extends Model
{
    use SoftDeletes;

    public const FONCTIONS = [
        'presidente' => 'Président(e) du tribunal',
        'vice_president' => 'Vice-président(e)',
        'juge' => 'Juge',
        'juge_instruction' => "Juge d'instruction",
        'juge_enfants' => 'Juge des enfants',
        'greffier_en_chef' => 'Coordonnateur du greffe',
        'greffier_en_chef_adjoint' => 'Coordonnateur adjoint du greffe',
        'greffier' => 'Greffier',
        'secretaire' => 'Secrétaire',
        'assesseur_spjej' => 'Assesseur SPJEJ',
        'assesseur_employeur' => 'Assesseur employeur',
        'assesseur_travailleur' => 'Assesseur travailleur',
        'service_social' => 'Service social (SPJEJ)',
        'autre' => 'Autre',
    ];

    /** Sert aux accords (Président / Présidente, greffier / greffière) dans les décisions. */
    public const SEXES = ['M' => 'Masculin', 'F' => 'Féminin'];

    protected $table = 'agents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function lignesServices(): HasMany
    {
        return $this->hasMany(ServiceAgent::class);
    }

    /** Affectations valables aujourd'hui, avec leur formation, leur chambre et leur section. */
    public function affectationsEnCours(): Collection
    {
        $jour = now()->toDateString();

        return $this->affectations()
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', $jour))
            ->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', $jour))
            ->with('formation.chambre.section', 'formation.chambre.annee')
            ->get()
            ->sortBy(fn (Affectation $a) => [$a->formation->chambre->ordre, $a->formation->ordre])
            ->values();
    }

    /** Services, comités et fonctions de l'année judiciaire en cours où l'agent figure. */
    public function servicesEnCours(): Collection
    {
        return $this->lignesServices()
            ->whereHas('service', fn ($q) => $q->whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id')))
            ->with('service')
            ->get()
            ->sortBy(fn (ServiceAgent $l) => $l->service->ordre)
            ->values();
    }

    public function fonctionLibelle(): string
    {
        return self::FONCTIONS[$this->fonction] ?? $this->fonction;
    }

    /** « KONE Awa » : nom en majuscules puis prénoms. */
    public function nomComplet(): string
    {
        return mb_strtoupper($this->nom).' '.$this->prenoms;
    }
}
