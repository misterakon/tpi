<?php

namespace App\Support\Audiences;

use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Fermeture;
use Illuminate\Support\Carbon;

/*
 | Aide au choix d'une date de renvoi ou de délibéré (jamais bloquante) : le jour est-il fermé ? la chambre y siège-t-elle d'habitude ?
 | Propose les prochaines audiences habituelles de la chambre, hors jours fermés, après une date donnée.
 */
class DatesAudience
{
    /** Jours de la semaine (1 = lundi) où la chambre tient audience d'après son calendrier. @return list<int> */
    public static function joursHabituels(Chambre $chambre): array
    {
        return Creneau::whereIn('formation_id', $chambre->formations()->select('id'))->whereRaw("LOWER(type) LIKE 'audience%'")
            ->whereNotNull('jour')->where('frequence', 'hebdomadaire')->pluck('jour')->map(fn ($j) => (int) $j)->unique()->values()->all();
    }

    /**
     * @return array{jour: string, chambre: string, siege: bool, ferme: ?string, habituels: list<string>, suggestion: ?array{date: string, libelle: string}, suggestions: list<array{date: string, libelle: string}>}
     */
    public static function analyser(Chambre $chambre, Carbon $jour, Carbon $apres, int $combien = 3): array
    {
        $jours = collect(self::joursHabituels($chambre));
        $fermeture = Fermeture::couvre($jour);
        $siege = $jours->isEmpty() || $jours->contains($jour->isoWeekday());
        $suggestions = [];
        if ($jours->isNotEmpty() && ($fermeture || ! $siege)) {
            $suggestions = self::prochaines($chambre, $jour, $apres, $combien);
        }

        return [
            'jour' => $jour->translatedFormat('l d/m/Y'), 'chambre' => $chambre->libelle, 'siege' => $siege, 'ferme' => $fermeture?->libelle,
            'habituels' => $jours->map(fn ($j) => Carbon::now()->startOfWeek()->addDays($j - 1)->translatedFormat('l'))->all(), 'suggestion' => $suggestions[0] ?? null, 'suggestions' => $suggestions,
        ];
    }

    /**
     * Les prochaines audiences habituelles de la chambre après $depuis (et après $apres), hors jours fermés.
     *
     * @return list<array{date: string, libelle: string}>
     */
    public static function prochaines(Chambre $chambre, Carbon $depuis, Carbon $apres, int $combien = 3): array
    {
        $jours = collect(self::joursHabituels($chambre));
        $resultat = [];
        if ($jours->isEmpty()) {
            return $resultat;
        }
        for ($d = $depuis->copy()->addDay(), $n = 0; $n < 120 && count($resultat) < $combien; $d->addDay(), $n++) {
            if ($jours->contains($d->isoWeekday()) && ! Fermeture::couvre($d) && $d->gt($apres)) {
                $resultat[] = ['date' => $d->toDateString(), 'libelle' => $d->translatedFormat('l d/m/Y')];
            }
        }

        return $resultat;
    }
}
