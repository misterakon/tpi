<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modèle de décision : un type de décision écrit en deux parties (qualité et factum), versionné. */
class ModeleDecision extends Model
{
    public const ETATS = ['brouillon' => 'Brouillon', 'actif' => 'Actif', 'archive' => 'Archivé'];

    protected $table = 'modeles_decision';

    protected $guarded = [];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ModeleDecisionVersion::class)->orderByDesc('numero');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ModeleDecisionVariable::class)->orderBy('code');
    }

    public function objets(): BelongsToMany
    {
        return $this->belongsToMany(ObjetLitige::class, 'modeles_decision_objets', 'modele_decision_id', 'objet_litige_id');
    }

    /** Libellés des chambres rattachées. @return list<string> */
    public function chambres(): array
    {
        return \DB::table('modeles_decision_chambres')->where('modele_decision_id', $this->id)->orderBy('chambre_libelle')->pluck('chambre_libelle')->all();
    }

    /** La version la plus récente (celle qui s'utilise). */
    public function versionCourante(): ?ModeleDecisionVersion
    {
        return $this->versions->first();
    }
}
