<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/*
 | Courriel de bienvenue envoyé à la création d'un compte.
 | Jamais de mot de passe dans le message : soit un lien pour le choisir, soit une mention
 | indiquant qu'il a été communiqué par l'administrateur.
 */
class CompteCree extends Mailable
{
    public function __construct(
        public readonly string $nom,
        public readonly string $email,
        public readonly string $profil,
        public readonly ?string $lien,
        public readonly int $validite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte a été créé — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.compte-cree');
    }
}
