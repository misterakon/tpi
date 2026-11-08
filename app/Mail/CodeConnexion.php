<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/*
 | Courriel du code de connexion. Volontairement envoyé tout de suite (pas de file d'attente) :
 | le code ne doit pas rester stocké en clair dans une table de tâches.
 */
class CodeConnexion extends Mailable
{
    public function __construct(
        public readonly string $nom,
        public readonly string $code,
        public readonly int $validite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.code-connexion');
    }
}
