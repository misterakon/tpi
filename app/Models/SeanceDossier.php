<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un dossier examiné pendant une séance hors audience, avec son issue. */
class SeanceDossier extends Model
{
    protected $table = 'seance_dossiers';

    protected $guarded = [];

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }
}
