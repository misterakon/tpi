<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salle ou lieu d'audience : salles n°3 et n°4, cabinets de juges, salle de réunion… */
class Salle extends Model
{
    public const TYPES = [
        'audience' => "Salle d'audience",
        'cabinet' => 'Cabinet de juge',
        'reunion' => 'Salle de réunion',
        'autre' => 'Autre lieu',
    ];

    protected $table = 'salles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function typeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
