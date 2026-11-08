<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Qualité procédurale (lot E : référentiels judiciaires). */
class QualiteProcedurale extends Model
{
    protected $table = 'qualites_procedurales';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
