<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/*
 | Alerte de sécurité pour les administrateurs : une connexion qui sort de l'ordinaire (échecs répétés, adresse jamais vue, nuit, code de secours).
 | Elle arrive dans la cloche et dans « Mes notifications », avec un lien vers le journal de connexion. Jamais de mot de passe ni de code.
 */
class AnomalieConnexionNotification extends Notification
{
    public function __construct(public readonly string $genre, public readonly string $titre, public readonly string $niveau = 'attention') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'securite', 'genre' => $this->genre, 'titre' => $this->titre, 'action' => 'Vérifier dans le journal de connexion, et prévenir la personne si besoin',
            'url' => route('journal.connexions', [], false), 'niveau' => $this->niveau, 'echeance' => now()->toDateString(), 'regle' => 'SEC', 'regle_libelle' => 'Sécurité',
            'sans_destinataire' => false,
        ];
    }
}
