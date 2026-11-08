<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/*
 | Un import de reprise de l'existant. L'adresse publique utilise la « reference » (ULID), jamais l'identifiant interne.
 | États : televerse > analyse > colonnes > controle > pret > importe ; annule et echec sont des sorties.
 */
class ImportReprise extends Model
{
    public const DOSSIER = 'imports';

    public const STATUTS = [
        'televerse' => 'Fichier reçu', 'analyse' => 'Fichier analysé', 'colonnes' => 'Colonnes choisies', 'controle' => 'Contrôle en cours',
        'pret' => 'Prêt à importer', 'importe' => 'Importé', 'annule' => 'Annulé', 'echec' => 'En échec',
    ];

    protected $table = 'imports_reprise';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    protected function casts(): array
    {
        return ['feuilles' => 'array', 'colonnes' => 'array', 'correspondance' => 'array', 'verrou_jusqu_a' => 'datetime', 'importe_le' => 'datetime', 'annule_le' => 'datetime'];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chambreDefaut(): BelongsTo
    {
        return $this->belongsTo(Chambre::class, 'chambre_defaut_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ImportRepriseLigne::class, 'import_id');
    }

    public function affaires(): HasMany
    {
        return $this->hasMany(Affaire::class, 'reprise_import_id');
    }

    public function cheminComplet(): ?string
    {
        return $this->chemin ? Storage::disk('local')->path(self::DOSSIER.'/'.$this->chemin) : null;
    }

    public function supprimerFichier(): void
    {
        if ($this->chemin) {
            Storage::disk('local')->delete(self::DOSSIER.'/'.$this->chemin);
            $this->forceFill(['chemin' => null])->save();
        }
    }

    /** Pourcentage d'avancement de l'étape en cours (contrôle ou importation). */
    public function pourcentage(int $fait, int $sur): int
    {
        return $sur <= 0 ? 100 : (int) min(100, floor($fait * 100 / $sur));
    }

    public function estOuvert(): bool
    {
        return ! in_array($this->statut, ['importe', 'annule', 'echec'], true);
    }
}
