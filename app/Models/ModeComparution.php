<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mode de comparution (lot E : référentiels judiciaires). */
class ModeComparution extends Model
{
    protected $table = 'modes_comparution';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
