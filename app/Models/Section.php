<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Section du siège (civile, pénale, instruction, sociale…), fixée par l'ordonnance. Elle regroupe des chambres. */
class Section extends Model
{
    protected $table = 'sections';

    protected $guarded = [];

    public function chambres(): HasMany
    {
        return $this->hasMany(Chambre::class);
    }
}
