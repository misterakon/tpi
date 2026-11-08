<?php

namespace App\Notifications;

use App\Models\Alerte;
use App\Models\RegleAlerte;
use Illuminate\Notifications\Notification;

/*
 | Notification dans l'application (cloche de l'en-tête et page « Mes notifications »).
 | Elle dit l'affaire ou l'audience concernée, ce qu'il faut faire, pour quand, et renvoie à l'écran où agir.
 | Aucune donnée sensible : pas de nom de partie, seulement des numéros et des dates.
 */
class AlerteNotification extends Notification
{
    /** @param  bool  $reorientee  aucun agent relié à un compte pour ce rôle : l'alerte arrive chez les administrateurs, qui doivent compléter les comptes */
    public function __construct(public readonly Alerte $alerte, public readonly RegleAlerte $regle, public readonly bool $reorientee = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'alerte_id' => $this->alerte->id, 'type' => $this->alerte->type, 'titre' => $this->alerte->titre, 'action' => $this->alerte->action,
            'url' => $this->alerte->url, 'niveau' => $this->regle->niveau, 'echeance' => $this->alerte->echeance->toDateString(),
            'regle' => $this->regle->code, 'regle_libelle' => $this->regle->libelle, 'affaire_id' => $this->alerte->affaire_id,
            'sans_destinataire' => $this->reorientee,
        ];
    }
}
