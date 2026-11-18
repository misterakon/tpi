<?php

namespace App\Support\Decisions;

use App\Mail\DecisionsTransmises;
use App\Models\Decision;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/*
 | Transmission d'une décision signée au greffe. Le travail du juge s'arrête là : la date de transmission vaut date de délivrance
 | au greffe (les deux champs prennent la même date). Option : une copie en Word est envoyée par e-mail au greffier en chef, dont
 | l'adresse se règle dans Réglages > Décisions. Une transmission peut porter sur une décision (fiche) ou sur un lot (page
 | « Transmission au greffe ») : dans les deux cas, un seul e-mail rassemble les fichiers Word du lot.
 */
class TransmissionDecision
{
    public static function adresseGreffierEnChef(): ?string
    {
        $a = trim((string) config('tpi.decisions.email_greffier_en_chef', ''));

        return $a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : null;
    }

    /** Ce qui empêche de transmettre cette décision, ou null. */
    public static function obstacle(Decision $d, ?Carbon $date = null): ?string
    {
        if ($d->statut === 'transmise' || $d->estTransmise()) {
            return 'Déjà transmise au greffe le '.$d->transmise_le?->format('d/m/Y').'.';
        }
        if ($d->statut !== 'signee') {
            return 'Seule une décision signée se transmet au greffe.';
        }
        if ($date && $d->date_signature && $date->lt($d->date_signature->copy()->startOfDay())) {
            return 'La date de transmission ne peut pas précéder la signature ('.$d->date_signature->format('d/m/Y').').';
        }

        return null;
    }

    /**
     * Transmet les décisions (celles qui ne le peuvent pas sont ignorées et expliquées) et envoie la copie si demandée.
     *
     * @param  iterable<Decision>  $decisions
     * @return array{transmises: list<Decision>, refusees: array<int, string>, courriel: ?string}  « courriel » : null (non demandé), « envoyé » ou le motif de l'échec
     */
    public static function transmettre(iterable $decisions, ?User $par, Carbon $date, bool $copie): array
    {
        $quand = $date->copy()->setTimeFrom(now());
        $transmises = [];
        $refusees = [];
        foreach ($decisions as $d) {
            if ($motif = self::obstacle($d, $date)) {
                $refusees[$d->id] = ($d->numeroAffichage() ?? $d->reference).' : '.$motif;

                continue;
            }
            $d->forceFill(['statut' => 'transmise', 'transmise_le' => $quand, 'transmise_par' => $par?->id, 'delivree_le' => $quand])->save();
            Journal::audit('decision_transmise', $d, $d->libelle(), ['statut' => 'signee'], ['statut' => 'transmise', 'date' => $date->toDateString()]);
            $transmises[] = $d;
        }

        $courriel = null;
        if ($copie && $transmises) {
            $courriel = self::envoyerCopie($transmises, $par);
        }

        return ['transmises' => $transmises, 'refusees' => $refusees, 'courriel' => $courriel];
    }

    /** Envoie les décisions en Word au greffier en chef. Retourne « envoyé » ou le motif de l'échec (la transmission, elle, est faite). @param list<Decision> $decisions */
    public static function envoyerCopie(array $decisions, ?User $par): string
    {
        $adresse = self::adresseGreffierEnChef();
        if (! $adresse) {
            return "Copie non envoyée : l'adresse e-mail du greffier en chef n'est pas réglée (Réglages > Décisions).";
        }
        try {
            $fichiers = [];
            foreach ($decisions as $d) {
                $fichiers[] = [
                    'nom' => self::nomFichier($d), 'octets' => self::word($d), 'libelle' => ($d->numeroAffichage() ?? $d->reference).' : '.($d->affaire?->numeroAffichage() ?? 'sans affaire'),
                    'numero' => $d->numeroAffichage() ?? (string) $d->reference, 'affaire' => $d->affaire?->numeroAffichage() ?? (string) $d->reference, 'chambre' => (string) $d->chambre?->libelle,
                    'type' => (string) $d->modele?->nom, 'signee' => $d->date_signature?->format('d/m/Y'),
                ];
            }
            $lien = count($decisions) === 1 ? route('decisions.redaction.edition', $decisions[0]->id) : route('decisions.registre');
            Mail::to($adresse)->send(new DecisionsTransmises($fichiers, $par?->name ?? 'Un magistrat', $decisions[0]->transmise_le?->format('d/m/Y') ?? today()->format('d/m/Y'), $lien));
            foreach ($decisions as $d) {
                Journal::audit('decision_copie_envoyee', $d, $d->libelle(), [], ['destinataire' => $adresse]);
            }

            return 'envoyé';
        } catch (Throwable $e) {
            report($e);

            return "Copie non envoyée : le courriel n'a pas pu partir.";
        }
    }

    public static function nomFichier(Decision $d): string
    {
        return (\Illuminate\Support\Str::slug(str_replace('/', '-', $d->numeroAffichage() ? 'decision '.$d->numeroAffichage() : (string) $d->reference), '-') ?: 'decision').'.docx';
    }

    /** Le Word d'une décision validée : le texte figé à la validation. */
    public static function word(Decision $d): string
    {
        $rendu = (array) $d->rendu;

        return \App\Support\Redaction\DecisionDocx::generer(
            ['entete' => (string) ($rendu['entete'] ?? ''), 'qualite' => (string) ($rendu['qualite'] ?? ''), 'factum' => (string) ($rendu['factum'] ?? '')],
            \App\Http\Controllers\Decisions\ModeleDecisionController::miseEnPage(),
            false,
        );
    }
}
