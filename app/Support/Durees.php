<?php

namespace App\Support;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\RoleLigne;
use Illuminate\Support\Carbon;

/*
 | Durées affichées sur les affaires, les rôles et les décisions : le nombre de jours (calendaires) entre deux actions, d'après les dates
 | déjà enregistrées (enregistrement, inscription au rôle, audience, suite saisie, rédaction, validation, signature, transmission au greffe, délivrance).
 | Rien n'est saisi en plus : on ne fait qu'afficher. Les délais à respecter et les retards restent suivis par les alertes (« Délais par étape »).
 */
class Durees
{
    /** Nombre de jours entre deux dates (jamais négatif), ou null si l'une manque. */
    public static function entre(mixed $de, mixed $a): ?int
    {
        if (! $de || ! $a) {
            return null;
        }
        $debut = Carbon::parse($de)->startOfDay();
        $fin = Carbon::parse($a)->startOfDay();

        return max(0, (int) round(($fin->getTimestamp() - $debut->getTimestamp()) / 86400));
    }

    /** « le même jour », « 1 jour », « 12 jours », « 75 jours (≈ 3 mois) ». */
    public static function texte(?int $jours): string
    {
        return match (true) {
            $jours === null => '',
            $jours === 0 => 'le même jour',
            $jours === 1 => '1 jour',
            $jours >= 60 => $jours.' jours (≈ '.round($jours / 30).' mois)',
            default => $jours.' jours',
        };
    }

    /** « + 3 j » : la forme courte, pour une étiquette. */
    public static function court(?int $jours): string
    {
        return $jours === null ? '' : ($jours === 0 ? 'même jour' : '+ '.$jours.' j');
    }

    /**
     * La chronologie d'une affaire : chaque action avec sa date, la durée depuis l'action précédente et depuis l'enregistrement.
     *
     * @return array{evenements: list<array<string, mixed>>, total: ?int, ouverte: bool}
     */
    public static function chronologie(Affaire $affaire): array
    {
        $evenements = [];
        $ajouter = function (string $code, string $libelle, mixed $date, ?string $detail = null, string $icone = 'fa-circle', bool $prevu = false) use (&$evenements) {
            if ($date) {
                $evenements[] = ['code' => $code, 'libelle' => $libelle, 'date' => Carbon::parse($date)->startOfDay(), 'detail' => $detail, 'icone' => $icone, 'prevu' => $prevu, 'rang' => count($evenements)];
            }
        };

        $ajouter('enrolement', 'Affaire enregistrée', $affaire->date_rg, $affaire->chambre?->libelle, 'fa-folder-plus');

        $aujourdhui = now()->startOfDay();
        $lignes = RoleLigne::where('affaire_id', $affaire->id)->with('role.chambre')->orderBy('id')->get();
        foreach ($lignes as $l) {
            $role = $l->role;
            if (! $role) {
                continue;
            }
            $audience = $role->date_audience;
            // Une inscription saisie après coup (reprise de données) n'est pas une action de l'époque : on ne l'affiche pas.
            if ($l->created_at && $l->created_at->copy()->startOfDay()->lte($audience->copy()->startOfDay())) {
                $ajouter('role', 'Inscrite au rôle', $l->created_at, 'audience du '.$audience->format('d/m/Y'), 'fa-list-check');
            }
            $futur = $audience->copy()->startOfDay()->gt($aujourdhui);
            $issue = $l->issue ? (RoleLigne::ISSUES[$l->issue] ?? $l->issue) : null;
            $ajouter('audience', $futur ? 'Audience prévue' : 'Audience', $audience, trim(($role->chambre?->libelle ?? '').($issue ? ' · '.$issue : '')), 'fa-gavel', $futur);
            if ($l->suite_le) {
                $ajouter('suite', 'Suite saisie', $l->suite_le, $issue, 'fa-pen');
            }
        }

        foreach (Decision::where('affaire_id', $affaire->id)->orderBy('id')->get() as $d) {
            $n = $d->numeroAffichage() ? 'n° '.$d->numeroAffichage() : null;
            $ajouter('redigee', 'Décision rédigée', $d->redigee_le, $n, 'fa-pen-nib');
            $ajouter('validee', 'Décision validée', $d->validee_le, $n, 'fa-circle-check');
            $ajouter('signee', 'Décision signée', $d->date_signature, $n, 'fa-signature');
            $ajouter('transmise', 'Transmise au greffe (délivrée)', $d->transmise_le, $n, 'fa-share');
        }

        usort($evenements, fn ($a, $b) => [$a['date']->getTimestamp(), $a['rang']] <=> [$b['date']->getTimestamp(), $b['rang']]);

        $enrolement = $evenements[0]['date'] ?? null;
        $precedent = null;
        foreach ($evenements as $i => $e) {
            $evenements[$i]['depuis_precedent'] = $precedent ? self::entre($precedent, $e['date']) : null;
            $evenements[$i]['depuis_enrolement'] = $enrolement ? self::entre($enrolement, $e['date']) : null;
            $precedent = $e['date'];
        }

        $passes = array_filter($evenements, fn ($e) => ! $e['prevu']);
        $derniere = $passes ? end($passes)['date'] : $enrolement;
        $jugee = $lignes->contains(fn ($l) => in_array($l->issue, RoleLigne::ISSUES_FINALES, true));
        $livree = Decision::where('affaire_id', $affaire->id)->where('statut', 'transmise')->exists();
        $ouverte = ! $affaire->estAnnulee() && ! $livree && ! $jugee;

        return [
            'evenements' => $evenements,
            // Une affaire en cours : la durée va jusqu'à aujourd'hui ; terminée : jusqu'à sa dernière action.
            'total' => $enrolement ? self::entre($enrolement, $ouverte ? $aujourdhui : $derniere) : null,
            'ouverte' => $ouverte,
        ];
    }
}
