<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trace d'une réattribution d'affaire à une autre chambre (par la Présidente). */
class AffaireAttribution extends Model
{
    public $timestamps = false;

    protected $table = 'affaire_attributions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function avant(): BelongsTo
    {
        return $this->belongsTo(Chambre::class, 'chambre_avant_id');
    }

    public function apres(): BelongsTo
    {
        return $this->belongsTo(Chambre::class, 'chambre_apres_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'par');
    }
}
