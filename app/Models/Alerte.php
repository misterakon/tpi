<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Une situation qui demande une action et qui a déclenché au moins une règle. Elle se referme quand l'action est faite. */
class Alerte extends Model
{
    public const TYPES = [
        'mise_au_role' => 'Affaire à mettre au rôle', 'publication_role' => 'Rôle à publier', 'feuille_a_saisir' => "Suites d'audience à noter", 'renvoi' => 'Renvoi à échéance',
        'delibere' => 'Délibéré à rendre', 'redaction' => 'Décision à rédiger', 'jamais_portee' => 'Affaire jamais portée au rôle', 'delai_procedure' => 'Délai de procédure',
        'detention' => "Détention : échéance du mandat", 'limite_renvois' => 'Limite de renvois dépassée', 'repartition_requete' => 'Requête à répartir',
        'validation_decision' => 'Décision à valider', 'signature_decision' => 'Décision à signer',
        'minute_a_transmettre' => 'Minute pénale à transmettre', 'certificat_recours' => 'Certificat de non-appel à délivrer',
    ];

    protected $table = 'alertes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['echeance' => 'date', 'ouverte_le' => 'datetime', 'resolue_le' => 'datetime', 'justifiee_jusqu' => 'date', 'justifiee_le' => 'datetime'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function justifieePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justifiee_par');
    }

    public function scopeOuvertes(Builder $q): Builder
    {
        return $q->whereNull('resolue_le');
    }

    /** Les alertes des chambres que l'utilisateur voit ; une alerte sans chambre n'est visible que de qui voit toutes les chambres. */
    public function scopeVisiblesPour(Builder $q, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);

        return $chambres === null ? $q : $q->whereIn('chambre_id', $chambres ?: [0]);
    }

    public function estOuverte(): bool
    {
        return $this->resolue_le === null;
    }

    public function estJustifiee(?Carbon $jour = null): bool
    {
        return $this->justifiee_jusqu !== null && $this->justifiee_jusqu->toDateString() >= ($jour ?? today())->toDateString();
    }

    /** Jours de retard (0 tant que l'échéance n'est pas passée). */
    public function retard(?Carbon $jour = null): int
    {
        $jour = ($jour ?? today())->copy()->startOfDay();

        return $this->echeance->lt($jour) ? (int) $this->echeance->diffInDays($jour) : 0;
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
