<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Courriel envoyé par le bouton « Tester l'envoi » de la page Réglages. Ne contient aucun secret. */
class TestCourriel extends Mailable
{
    public function __construct(
        public readonly string $demandeur,
        public readonly string $mode,
        public readonly string $serveur,
        public readonly string $expediteur,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Test d'envoi — ".config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test-courriel');
    }
}
