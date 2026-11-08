<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/*
 | Récapitulatif quotidien des alertes d'une personne : un seul courriel, même s'il y a plusieurs alertes.
 | Il ne contient que des numéros d'affaire, des dates et l'action à mener, jamais le nom d'une partie.
 */
class RecapAlertes extends Mailable
{
    /** @param  list<array{niveau: string, titre: string, action: string, echeance: string, regle: string}>  $alertes */
    public function __construct(public readonly string $nom, public readonly array $alertes, public readonly string $lien) {}

    public function envelope(): Envelope
    {
        $n = count($this->alertes);

        return new Envelope(subject: $n.' alerte'.($n > 1 ? 's' : '').' à traiter — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.recap-alertes');
    }
}
