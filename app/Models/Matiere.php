<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Matière (lot E : référentiels judiciaires). */
class Matiere extends Model
{
    protected $table = 'matieres';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function objets(): HasMany
    {
        return $this->hasMany(ObjetLitige::class);
    }
}
