<?php

namespace App\Support\Alertes;

use Illuminate\Support\Carbon;

/** Une échéance du circuit d'un dossier : quelque chose doit être fait avant (ou à) cette date, par quelqu'un de précis. */
final class Echeance
{
    public function __construct(
        public readonly string $type,
        public readonly int $objetId,
        public readonly ?int $affaireId,
        public readonly ?int $chambreId,
        public readonly Carbon $date,
        public readonly string $titre,
        public readonly string $action,
        public readonly ?string $url,
    ) {}

    /** Une situation, une clé : si l'échéance change de date (renvoi reporté), c'est une nouvelle situation. */
    public function cle(): string
    {
        return $this->type.':'.$this->objetId.':'.$this->date->toDateString();
    }
}
