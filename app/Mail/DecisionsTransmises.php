<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Copie, en Word, des décisions que le juge vient de transmettre au greffe : destinée au greffier en chef. Aucun nom de partie dans le texte du message. */
class DecisionsTransmises extends Mailable
{
    /** @param  list<array{nom: string, octets: string, libelle: string, numero: string, affaire: string, chambre: string, type: string, signee: ?string}>  $fichiers */
    public function __construct(public readonly array $fichiers, public readonly string $expediteur, public readonly string $dateTransmission, public readonly string $lien) {}

    public function envelope(): Envelope
    {
        $n = count($this->fichiers);

        return new Envelope(subject: $n.' décision'.($n > 1 ? 's transmises' : ' transmise').' au greffe — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.decisions-transmises');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn ($f) => Attachment::fromData(fn () => $f['octets'], $f['nom'])->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            $this->fichiers
        );
    }
}
