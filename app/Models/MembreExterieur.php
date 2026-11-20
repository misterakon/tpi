<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membre d'un bureau local qui n'appartient pas au tribunal (trésorier, commissaire de justice, avocat, sous-préfet…). Ce n'est pas un agent : il n'a pas de compte. */
class MembreExterieur extends Model
{
    protected $table = 'membres_exterieurs';

    protected $guarded = [];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
