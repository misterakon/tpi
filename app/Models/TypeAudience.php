<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Type d'audience (lot E : référentiels judiciaires). */
class TypeAudience extends Model
{
    protected $table = 'types_audience';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
