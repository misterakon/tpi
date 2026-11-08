<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une expédition, une grosse ou une copie d'une décision remise à quelqu'un : à qui, quand, par qui. Ne se supprime pas. */
class Delivrance extends Model
{
    protected $table = 'delivrances';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['delivre_le' => 'date'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivre_par');
    }

    public function libelleType(): string
    {
        return Decision::TYPES_DELIVRANCE[$this->type] ?? $this->type;
    }
}
