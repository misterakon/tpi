<?php

namespace App\Support;

use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Models\Fermeture;
use Illuminate\Support\Carbon;

/*
 | Dates d'audience d'une chambre et date limite d'enrôlement : d'après les créneaux d'audience de ses formations (calendrier de l'ordonnance),
 | les jours de fermeture (fériés, vacances) et la règle « pour une audience tel jour, enrôler avant tel jour à telle heure ».
 */
class CalendrierEnrolement
{
    private const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    /** Jours de la semaine (1 = lundi) où la chambre tient audience, d'après ses créneaux « Audience… ». @return list<int> */
    public static function joursDAudience(Chambre $chambre): array
    {
        return Creneau::whereIn('formation_id', $chambre->formations()->select('id'))
            ->whereRaw("LOWER(type) LIKE 'audience%'")->whereNotNull('jour')->where('frequence', 'hebdomadaire')
            ->pluck('jour')->map(fn ($j) => (int) $j)->unique()->sort()->values()->all();
    }

    /**
     * Créneaux d'audience hebdomadaires de la chambre : jour, heure (« 09:00 » ou null) et précision de l'ordonnance (« Après l'audience de la formation A »).
     *
     * @return list<array{jour: int, heure: ?string, precision: ?string}>
     */
    public static function creneauxDAudience(Chambre $chambre): array
    {
        return Creneau::whereIn('formation_id', $chambre->formations()->select('id'))
            ->whereRaw("LOWER(type) LIKE 'audience%'")->whereNotNull('jour')->where('frequence', 'hebdomadaire')
            ->get(['jour', 'heure', 'precision'])
            ->map(fn ($c) => ['jour' => (int) $c->jour, 'heure' => $c->heure ? substr((string) $c->heure, 0, 5) : null, 'precision' => $c->precision ?: null])
            ->unique(fn ($c) => $c['jour'].'|'.$c['heure'].'|'.$c['precision'])->sortBy(fn ($c) => $c['jour'].'|'.($c['heure'] ?? '99:99'))->values()->all();
    }

    /** « jeudi 12 novembre 2026 ». */
    public static function libelleDate(Carbon $date): string
    {
        return $date->copy()->locale('fr')->translatedFormat('l d F Y');
    }

    /** « mercredi 11 novembre à 16h00 ». */
    public static function libelleLimite(?Carbon $limite): ?string
    {
        return $limite?->copy()->locale('fr')->translatedFormat('l d F \à H\hi');
    }

    /**
     * Prochaines audiences de la chambre avec leur date limite d'enrôlement.
     *
     * @return list<array{date: string, libelle: string, limite: ?string, ouverte: bool}>
     */
    public static function prochaines(Chambre $chambre, int $nombre = 8, ?Carbon $maintenant = null, bool $avecLimite = true): array
    {
        $maintenant ??= now();
        $creneaux = self::creneauxDAudience($chambre);
        if ($creneaux === []) {
            return [];
        }

        // Une proposition par créneau : une chambre qui siège deux fois le même jour (9 h et 14 h 30) a deux lignes.
        $trouvees = [];
        for ($d = $maintenant->copy()->startOfDay()->addDay(); $d->lte($maintenant->copy()->addDays(120)) && count($trouvees) < $nombre; $d->addDay()) {
            if (Fermeture::couvre($d)) {
                continue;
            }
            foreach ($creneaux as $c) {
                if ($c['jour'] !== $d->isoWeekday()) {
                    continue;
                }
                $limite = $avecLimite ? DelaiEnrolement::limitePour($d) : null;   // la date limite d'enrôlement ne concerne que le civil
                $trouvees[] = [
                    'date' => $d->toDateString(), 'heure' => $c['heure'], 'precision' => $c['precision'],
                    'libelle' => self::libelleDate($d).($c['heure'] ? ' à '.str_replace(':', 'h', $c['heure']) : '').($c['precision'] && ! $c['heure'] ? ' ('.mb_strtolower($c['precision']).')' : ''),
                    'limite' => self::libelleLimite($limite), 'ouverte' => ! $limite || $maintenant->lte($limite),
                ];
            }
        }

        return $trouvees;
    }

    /** La plus proche audience encore ouverte à l'enrôlement, ou null. */
    public static function proposer(Chambre $chambre, ?Carbon $maintenant = null): ?string
    {
        foreach (self::prochaines($chambre, 12, $maintenant) as $a) {
            if ($a['ouverte']) {
                return $a['date'];
            }
        }

        return null;
    }

    /**
     * Contrôle d'une date d'audience choisie.
     *
     * @return array{ferme: ?string, jour_siege: ?bool, jours: string, limite: ?Carbon, depassee: bool}
     */
    public static function controler(Chambre $chambre, Carbon $date, ?Carbon $maintenant = null): array
    {
        $maintenant ??= now();
        $jours = self::joursDAudience($chambre);
        $limite = DelaiEnrolement::limitePour($date);

        return [
            'ferme' => Fermeture::couvre($date)?->libelle,
            'jour_siege' => $jours === [] ? null : in_array($date->isoWeekday(), $jours, true),
            'jours' => implode(', ', array_map(fn ($j) => self::JOURS[$j], $jours)),
            'limite' => $limite,
            'depassee' => $limite !== null && $maintenant->gt($limite),
        ];
    }
}
