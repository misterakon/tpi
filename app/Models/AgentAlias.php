<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une écriture d'un nom rattachée à un agent par un utilisateur (au chargement d'une ordonnance) : reconnue automatiquement ensuite. */
class AgentAlias extends Model
{
    protected $table = 'agents_alias';

    protected $guarded = [];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }
}
