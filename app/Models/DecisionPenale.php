<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Décision pénale du répertoire : une ligne par prévenu, avec la peine et la transmission de la minute. */
class DecisionPenale extends Model
{
    public const ISSUES = [
        'condamne' => 'Condamné', 'relaxe' => 'Relaxé', 'acquitte' => 'Acquitté', 'dispense_de_peine' => 'Dispense de peine',
        'irrecevable' => 'Action irrecevable', 'incompetent' => 'Incompétence', 'autre' => 'Autre',
    ];

    public const CONTRADICTIONS = ['contradictoire' => 'Contradictoire', 'defaut' => 'Par défaut', 'reputee_contradictoire' => 'Réputé contradictoire'];

    protected $table = 'decisions_penales';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_decision' => 'date', 'date_transmission_minute' => 'date'];
    }

    /** Les décisions des affaires que l'utilisateur peut voir (chambres de son périmètre, familles protégées comprises). */
    public function scopeVisiblesPour(Builder $q, ?User $user): Builder
    {
        return $q->whereIn('affaire_id', Affaire::visiblesPour($user)->select('affaires.id'));
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(Partie::class);
    }

    /** « 2 ans dont 6 mois avec sursis, 100 000 F d'amende » : la peine en une ligne. */
    public function peineEnClair(): string
    {
        $morceaux = [];
        if ($this->emprisonnement_mois) {
            $m = (int) $this->emprisonnement_mois;
            $duree = $m % 12 === 0 ? ($m / 12).' an'.($m / 12 > 1 ? 's' : '') : $m.' mois';
            $morceaux[] = 'Emprisonnement : '.$duree.($this->sursis_mois ? ' dont '.$this->sursis_mois.' mois avec sursis' : '');
        }
        if ($this->amende) {
            $morceaux[] = 'Amende : '.number_format((int) $this->amende, 0, ',', ' ').' F CFA';
        }
        if ($this->autres_peines) {
            $morceaux[] = $this->autres_peines;
        }

        return implode(' ; ', $morceaux);
    }

    public function minuteATransmettre(): bool
    {
        return $this->date_transmission_minute === null;
    }

    /** Jours écoulés depuis la décision sans transmission de la minute. */
    public function joursSansMinute(): ?int
    {
        return $this->minuteATransmettre() ? (int) $this->date_decision->diffInDays(today()) : null;
    }

    public function minuteEnRetard(): bool
    {
        return $this->minuteATransmettre() && ($this->joursSansMinute() ?? 0) > (int) config('tpi.penal.minute_vigilance_jours', 30);
    }
}
