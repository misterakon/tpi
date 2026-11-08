<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | La mise en état d'une affaire civile (AUD-07, AUD-08). Une affaire peut passer plusieurs fois en mise en état (une ligne « retirée » ou « clôturée » reste
 | au dossier), mais une seule ligne « en cours » à la fois.
 */
class MiseEnEtat extends Model
{
    public const STATUTS = ['en_cours' => 'En cours', 'cloturee' => 'Clôturée', 'retiree' => 'Retirée'];

    protected $table = 'mises_en_etat';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_reception' => 'date', 'date_premiere_audience' => 'date', 'date_cloture' => 'date', 'date_audience_jugement' => 'date', 'versee_le' => 'datetime'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function juge(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'juge_id')->withTrashed();
    }

    public function chambreJugement(): BelongsTo
    {
        return $this->belongsTo(Chambre::class, 'chambre_jugement_id');
    }

    /** Les mises en état des affaires que l'utilisateur a le droit de voir (voir PerimetreChambres). */
    public function scopeVisiblesPour(Builder $requete, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);

        return $chambres === null ? $requete : $requete->whereHas('affaire', fn ($q) => $q->whereIn('chambre_id', $chambres ?: [0]));
    }

    public function estOuverte(): bool
    {
        return $this->statut === 'en_cours';
    }

    /**
     * Jours entre la première audience de mise en état et la clôture ; pour une mise en état encore ouverte, jours écoulés depuis cette première audience.
     * Null tant que la première audience n'a pas eu lieu.
     */
    public function dureeJours(): ?int
    {
        if (! $this->date_premiere_audience) {
            return null;
        }
        $fin = $this->date_cloture ?? ($this->estOuverte() ? today() : null);

        return $fin ? max(0, (int) $this->date_premiere_audience->diffInDays($fin)) : null;
    }

    /** Ouverte depuis plus longtemps que le seuil de vigilance (réglable, 90 jours par défaut). */
    public function estLongue(): bool
    {
        return $this->estOuverte() && ($this->dureeJours() ?? 0) > (int) config('tpi.mise_en_etat.vigilance_jours', 90);
    }
}
