<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Texte de loi (lot E : référentiels judiciaires). */
class TexteLoi extends Model
{
    protected $table = 'textes_loi';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
