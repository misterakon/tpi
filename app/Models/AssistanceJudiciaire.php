<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une demande d'assistance judiciaire examinée par le bureau local. */
class AssistanceJudiciaire extends Model
{
    public const DECISIONS = ['accord' => 'Accord', 'rejet' => 'Rejet'];

    public const NATURES = ['Assistance judiciaire', 'Sociale', 'Civile ordinaire', 'Etat civil', 'Affaire matrimoniale', 'Hérédité', 'Tutelle', 'Référé', 'Commerciale', 'Administrative', 'Pénale', 'Autre'];

    protected $table = 'assistance_judiciaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_rg' => 'date', 'date_audience' => 'date', 'date_decision' => 'date', 'date_recours' => 'date', 'date_transmission_recours' => 'date'];
    }

    /** Jours entre la réception de la demande et la décision. */
    public function dureeJours(): ?int
    {
        return $this->date_decision && $this->date_decision->gte($this->date_rg) ? (int) $this->date_rg->diffInDays($this->date_decision) : null;
    }
}
