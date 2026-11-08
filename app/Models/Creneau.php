<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Créneau du calendrier d'une formation : audience publique, conciliation, réception des requêtes, session… */
class Creneau extends Model
{
    public const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public const FREQUENCES = ['hebdomadaire' => 'Chaque semaine', 'trimestrielle' => 'Chaque trimestre', 'a_la_demande' => 'À la demande', 'ponctuelle' => 'Audience supplémentaire (une seule date)'];

    protected $table = 'creneaux';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debut' => 'date', 'fin' => 'date'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /** Libellé court du jour et de l'heure : « Lundi à 09:00 », « Chaque trimestre ». */
    public function quand(): string
    {
        $texte = $this->jour ? self::JOURS[$this->jour] : (self::FREQUENCES[$this->frequence] ?? '');
        if ($this->frequence === 'ponctuelle' && $this->debut) {
            $texte .= ' '.$this->debut->format('d/m/Y');
        }

        return $texte.($this->heure ? ' à '.substr($this->heure, 0, 5) : '');
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }
}
