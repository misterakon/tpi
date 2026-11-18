<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 | Personne du tribunal (magistrat, greffier, assesseur, personnel administratif et technique ou membre extérieur).
 | Les magistrats, greffiers et assesseurs peuvent être affectés à une formation.
 | Distincte du compte utilisateur : un agent peut ne pas avoir de compte ; le lien est facultatif.
 */
class Agent extends Model
{
    use SoftDeletes;

    /**
     * Catégories du personnel. Les agents inter-ministériels (agents de bureau, informaticiens…) forment une catégorie à part :
     * l'ordonnance ne les cite pas, ils ne sont donc affectés ni aux chambres ni aux formations.
     */
    public const CATEGORIES = [
        'magistrat' => [
            'libelle' => 'Magistrats', 'singulier' => 'Magistrat', 'icone' => 'fa-solid fa-gavel', 'ton' => 'blue', 'chip' => 'info',
            'fonctions' => ['presidente', 'vice_president', 'juge', 'juge_instruction', 'juge_enfants'],
        ],
        'greffier' => [
            'libelle' => 'Greffiers', 'singulier' => 'Greffier', 'icone' => 'fa-solid fa-file-signature', 'ton' => 'green', 'chip' => 'success',
            'fonctions' => ['greffier_en_chef', 'greffier_en_chef_adjoint', 'greffier', 'secretaire'],
        ],
        'assesseur' => [
            'libelle' => 'Assesseurs', 'singulier' => 'Assesseur', 'icone' => 'fa-solid fa-scale-balanced', 'ton' => 'amber', 'chip' => 'warning',
            'fonctions' => ['assesseur_spjej', 'assesseur_employeur', 'assesseur_travailleur'],
        ],
        'administratif' => [
            'libelle' => 'Personnel administratif et technique', 'singulier' => 'Administratif / technique', 'icone' => 'fa-solid fa-laptop-code', 'ton' => 'purple', 'chip' => 'purple',
            'fonctions' => ['agent_de_bureau', 'agent_administratif', 'informaticien', 'agent_technique', 'service_social', 'autre'],
        ],
        'exterieur' => [
            'libelle' => 'Membres extérieurs', 'singulier' => 'Membre extérieur', 'icone' => 'fa-solid fa-handshake', 'ton' => 'red', 'chip' => 'neutral',
            'fonctions' => ['membre_exterieur'],
        ],
    ];

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
        'agent_de_bureau' => 'Agent de bureau',
        'agent_administratif' => 'Agent administratif',
        'informaticien' => 'Informaticien',
        'agent_technique' => 'Agent technique (entretien, sécurité…)',
        'service_social' => 'Service social (SPJEJ)',
        'membre_exterieur' => 'Membre extérieur (bureau, commission)',
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

    /** Un agent créé sans catégorie (reprise Excel, seeder) est classé d'après sa fonction. */
    protected static function booted(): void
    {
        static::saving(function (Agent $agent) {
            if (blank($agent->categorie)) {
                $agent->categorie = self::categorieDe((string) $agent->fonction);
            }
        });
    }

    /** Catégorie à laquelle appartient une fonction. */
    public static function categorieDe(string $fonction): string
    {
        foreach (self::CATEGORIES as $cle => $categorie) {
            if (in_array($fonction, $categorie['fonctions'], true)) {
                return $cle;
            }
        }

        return 'administratif';
    }

    public function categorieLibelle(): string
    {
        return self::CATEGORIES[$this->categorie]['libelle'] ?? 'Autre';
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
