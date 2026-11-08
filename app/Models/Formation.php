<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Formation d'une chambre (facultative) : sa composition et son calendrier d'audiences. Sans nom, elle est la composition de la chambre elle-même. */
class Formation extends Model
{
    protected $table = 'formations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(Creneau::class);
    }

    /** « Formation A – Tribunal de simple police », ou « Composition de la chambre » pour la formation unique. */
    public function libelle(): string
    {
        if (! $this->nom) {
            return $this->intitule ?: 'Composition de la chambre';
        }

        return $this->intitule ? $this->nom.' – '.$this->intitule : $this->nom;
    }

    /** « 2e chambre civile, Formation A » (pour le journal d'audit et les messages). */
    public function libelleComplet(): string
    {
        $chambre = $this->chambre?->libelle ?? 'Chambre n°'.$this->chambre_id;

        return $this->nom ? $chambre.', '.$this->libelle() : $chambre;
    }
}
