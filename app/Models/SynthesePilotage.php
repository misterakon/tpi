<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une note de synthèse du pilotage : automatique (modèles de phrases) ou rédigée par l'IA. */
class SynthesePilotage extends Model
{
    protected $table = 'syntheses_pilotage';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['periode_debut' => 'date', 'periode_fin' => 'date', 'chiffres' => 'array'];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estIA(): bool
    {
        return $this->source === 'ia';
    }
}
