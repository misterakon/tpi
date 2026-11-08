<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une correction du numéro d'une affaire (numéro reporté du greffe). */
class NumeroCorrige extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'affaire_numeros_corriges';

    protected $guarded = [];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'par');
    }
}
