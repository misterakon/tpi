<?php

namespace App\Support\Recours;

use App\Models\Affaire;
use App\Models\Decision;
use App\Models\DelaiRecours;
use App\Models\Fermeture;
use App\Models\Recours;
use App\Models\SuiviRecours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 | Les délais de recours d'une décision : quel délai s'applique, quand il expire, où en est la décision.
 |  - le délai court à partir de la SIGNIFICATION (saisie par le greffe) ; sans signification, il n'a pas commencé ;
 |  - le délai applicable se choisit d'après la voie de recours surveillée (appel, opposition), la nature de l'affaire puis le registre (RG, RP) ;
 |  - le dernier jour tombant un samedi, un dimanche ou une fermeture du tribunal, l'échéance est reportée au premier jour ouvré suivant ;
 |  - un certificat de non-appel ou de non-opposition se délivre APRÈS l'échéance, s'il n'y a eu aucun recours de cette nature, et SEULEMENT si le délai a été confirmé.
 | Les délais installés sont provisoires : la personne habilitée les confirme (table delais_recours, colonne valide).
 */
class DelaisRecours
{
    public const ETATS = [
        'a_signifier' => 'Signification à enregistrer', 'en_cours' => 'Délai de recours en cours', 'expire' => 'Délai expiré',
        'recours' => 'Recours formé', 'certifie' => 'Certificat délivré', 'sans_delai' => 'Aucun délai défini',
    ];

    /** Le délai qui s'applique à cette décision pour cette voie de recours, ou null. */
    public static function pour(Decision $decision, string $nature = 'appel'): ?DelaiRecours
    {
        $affaire = $decision->affaire;
        $candidats = DelaiRecours::where('actif', true)->where('nature', $nature)->get();

        return $candidats->first(fn (DelaiRecours $d) => $affaire && $d->nature_affaire_id && (int) $d->nature_affaire_id === (int) $affaire->nature_affaire_id)
            ?? $candidats->first(fn (DelaiRecours $d) => ! $d->nature_affaire_id && $affaire && $d->registre === $affaire->serie)
            ?? $candidats->first(fn (DelaiRecours $d) => ! $d->nature_affaire_id && ! $d->registre);
    }

    /** Dernier jour utile du recours, prorogé au premier jour ouvré. */
    public static function echeance(Carbon $signification, DelaiRecours $delai): Carbon
    {
        $fin = $signification->copy()->startOfDay();
        $delai->unite === 'mois' ? $fin->addMonthsNoOverflow($delai->valeur) : $fin->addDays($delai->valeur);
        while ($fin->isWeekend() || Fermeture::couvre($fin)) {
            $fin->addDay();
        }

        return $fin;
    }

    /**
     * L'état de la décision. @return array{etat: string, nature: string, delai: ?DelaiRecours, signification: ?Carbon, echeance: ?Carbon, jours_restants: ?int, recours: int, certificat: ?\App\Models\CertificatRecours, certifiable: bool, obstacle: ?string}
     */
    public static function etat(Decision $decision, ?Carbon $jour = null): array
    {
        $jour = ($jour ?? today())->copy()->startOfDay();
        $suivi = SuiviRecours::where('decision_id', $decision->id)->first();
        $nature = $suivi?->nature ?? 'appel';
        $delai = self::pour($decision, $nature);
        $signification = $suivi?->date_signification;
        $echeance = $signification && $delai ? self::echeance($signification, $delai) : null;
        $recours = Recours::where('decision_id', $decision->id)->whereIn('nature', [$nature, 'pourvoi', 'autre'])->where('statut', '!=', 'abandonne')->count();
        $certificat = $decision->certificats()->where('type', $nature === 'opposition' ? 'non_opposition' : 'non_appel')->first();

        $etat = match (true) {
            $certificat !== null => 'certifie',
            $recours > 0 => 'recours',
            ! $delai => 'sans_delai',
            ! $signification => 'a_signifier',
            $echeance->lt($jour) => 'expire',
            default => 'en_cours',
        };
        $obstacle = match (true) {
            $certificat !== null => 'Un certificat a déjà été délivré.',
            $recours > 0 => 'Un recours a été formé : on ne délivre pas de certificat de non-'.($nature === 'opposition' ? 'opposition' : 'appel').'.',
            ! $delai => 'Aucun délai de recours n\'est défini pour cette décision.',
            ! $signification => 'La signification de la décision n\'est pas enregistrée : le délai de recours n\'a pas commencé.',
            ! $delai->valide => 'Le délai de recours applicable (« '.$delai->libelle.' ») n\'est pas encore confirmé : faites-le confirmer dans « Délais de recours ».',
            ! $echeance->lt($jour) => 'Le délai de recours court jusqu\'au '.$echeance->format('d/m/Y').'.',
            default => null,
        };

        return [
            'etat' => $etat, 'nature' => $nature, 'delai' => $delai, 'signification' => $signification, 'echeance' => $echeance,
            'jours_restants' => $echeance ? (int) $jour->diffInDays($echeance, false) : null, 'recours' => $recours, 'certificat' => $certificat, 'certifiable' => $obstacle === null, 'obstacle' => $obstacle,
        ];
    }

    /** Numéro suivant du certificat pour l'année : 1, 2, 3… sans trou ni doublon, compteur verrouillé le temps de l'attribution. */
    public static function numeroSuivant(int $annee): int
    {
        DB::table('numeros_certificat')->insertOrIgnore(['annee' => $annee, 'dernier' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $compteur = DB::table('numeros_certificat')->where('annee', $annee)->lockForUpdate()->first();
        $n = (int) $compteur->dernier + 1;
        DB::table('numeros_certificat')->where('id', $compteur->id)->update(['dernier' => $n, 'updated_at' => now()]);

        return $n;
    }

    /** Les décisions dont le délai de recours est expiré sans recours ni certificat (pour l'alerte « certificat à délivrer »). @return \Illuminate\Support\Collection<int, array{decision: Decision, etat: array<string, mixed>}> */
    public static function certificatsAPreparer(?Carbon $jour = null): \Illuminate\Support\Collection
    {
        $sortie = collect();
        foreach (SuiviRecours::whereNotNull('date_signification')->with('decision.affaire')->get() as $s) {
            $d = $s->decision;
            if (! $d || ! $d->estSignee()) {
                continue;
            }
            $etat = self::etat($d, $jour);
            if ($etat['etat'] === 'expire' && $etat['certifiable']) {
                $sortie->push(['decision' => $d, 'etat' => $etat]);
            }
        }

        return $sortie;
    }
}
