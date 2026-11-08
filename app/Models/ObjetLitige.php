<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Objet du litige (lot E : référentiels judiciaires). */
class ObjetLitige extends Model
{
    protected $table = 'objets_litige';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'matiere_id');
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureAffaire::class, 'nature_affaire_id');
    }
}
