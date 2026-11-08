<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une séance hors audience publique : réception de requêtes, conciliation, tutelle. */
class SeanceHorsAudience extends Model
{
    public const TYPES = ['reception_requetes' => 'Réception des requêtes', 'conciliation' => 'Conciliation', 'tutelle' => 'Tutelle'];

    public const STATUTS = ['prevue' => 'Prévue', 'tenue' => 'Tenue', 'annulee' => 'Annulée'];

    protected $table = 'seances_hors_audience';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_seance' => 'date'];
    }

    /** Une séance rattachée à une chambre se voit dans le périmètre de chambres de l'utilisateur ; sans chambre, elle est ouverte à tous. */
    public function scopeVisiblesPour(Builder $q, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);

        return $chambres === null ? $q : $q->where(fn ($w) => $w->whereNull('chambre_id')->orWhereIn('chambre_id', $chambres ?: [0]));
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function juge(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'juge_id');
    }

    public function greffier(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'greffier_id');
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(SeanceDossier::class, 'seance_id')->orderBy('id');
    }
}
