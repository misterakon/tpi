<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Règle de déclenchement d'une alerte : à tel nombre de jours avant ou après l'échéance (J-3, J+1…), on prévient tels destinataires par tels canaux.
 * Plus le retard grandit, plus on monte dans la hiérarchie.
 */
class RegleAlerte extends Model
{
    public const DESTINATAIRES = [
        'greffier_chambre' => 'Greffier de la chambre',
        'president_formation' => 'Président de la formation',
        'magistrats_chambre' => 'Magistrats de la chambre',
        'vice_presidents' => 'Vice-présidents',
        'presidente' => 'Présidente du tribunal',
        'greffier_en_chef' => 'Coordonnateur du greffe',
        'secretariat_presidence' => 'Secrétariat de la Présidence (répartition des requêtes)',
        'administrateur' => 'Administrateurs',
    ];

    public const CANAUX = ['application' => "Notification dans l'application", 'email' => 'E-mail (récapitulatif quotidien)'];

    public const NIVEAUX = ['info' => 'Information', 'attention' => 'Attention', 'critique' => 'Critique'];

    protected $table = 'regles_alerte';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decalage_jours' => 'integer', 'canaux' => 'array', 'destinataires' => 'array', 'types' => 'array', 'actif' => 'boolean'];
    }

    /** « 3 jours avant l'échéance », « Le jour de l'échéance », « 7 jours après l'échéance ». */
    public function quand(): string
    {
        $n = abs($this->decalage_jours);

        return match (true) {
            $this->decalage_jours === 0 => "Le jour de l'échéance",
            $this->decalage_jours < 0 => $n.' jour'.($n > 1 ? 's' : '')." avant l'échéance",
            default => $n.' jour'.($n > 1 ? 's' : '')." après l'échéance",
        };
    }

    /** @return list<string> */
    public function libellesDestinataires(): array
    {
        return collect($this->destinataires ?? [])->map(fn ($c) => self::DESTINATAIRES[$c] ?? $c)->values()->all();
    }

    /** @return list<string> */
    public function libellesCanaux(): array
    {
        return collect($this->canaux ?? [])->map(fn ($c) => self::CANAUX[$c] ?? $c)->values()->all();
    }

    /** La règle vise-t-elle ce type d'échéance ? (aucun type indiqué : tous) */
    public function vise(string $type): bool
    {
        return empty($this->types) || in_array($type, $this->types, true);
    }
}
