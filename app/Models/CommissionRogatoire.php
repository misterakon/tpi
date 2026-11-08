<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Commissions rogatoires d'un cabinet pour un mois : stock initial, nouvelles, exécutées, selon le sens (reçues, émises) et la portée (nationale, internationale). */
class CommissionRogatoire extends Model
{
    public const SENS = ['recu' => 'Reçues', 'emis' => 'Émises'];

    public const PORTEES = ['nationale' => 'Nationales', 'internationale' => 'Internationales'];

    protected $table = 'commissions_rogatoires';

    protected $guarded = [];

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function stockRestant(): int
    {
        return max(0, $this->stock_initial + $this->nouvelles - $this->executees);
    }
}
