<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Agent rattaché à un service, avec sa fonction dans ce service. */
class ServiceAgent extends Model
{
    protected $table = 'service_agents';

    protected $guarded = [];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }
}
