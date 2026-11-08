<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Délai d'une étape du circuit d'un dossier (saisie des suites, rédaction de la décision…), modifiable dans Paramètres. */
class DelaiEtape extends Model
{
    public const UNITES = ['jours' => 'jours calendaires', 'jours_ouvrables' => 'jours ouvrables', 'mois' => 'mois'];

    protected $table = 'delais_etape';

    protected $guarded = [];

    /** @var array<string, self>|null */
    private static ?array $cache = null;

    /** @var list<array{0: string, 1: string}>|null fermetures : [début, fin] */
    private static ?array $fermetures = null;

    public static function oublier(): void
    {
        self::$cache = null;
        self::$fermetures = null;
    }

    public static function de(string $code): ?self
    {
        self::$cache ??= self::all()->keyBy('code')->all();

        return self::$cache[$code] ?? null;
    }

    /** La date d'échéance : « depuis » plus (ou moins, avec $sens = -1) le délai de l'étape. Sans délai connu, la date de départ elle-même. */
    public static function echeance(string $code, Carbon $depuis, int $sens = 1): Carbon
    {
        $d = self::de($code);
        if (! $d) {
            return $depuis->copy()->startOfDay();
        }

        return self::ajouter($depuis, $d->valeur * $sens, $d->unite);
    }

    public static function ajouter(Carbon $depuis, int $n, string $unite): Carbon
    {
        $date = $depuis->copy()->startOfDay();
        if ($unite === 'mois') {
            return $date->addMonthsNoOverflow($n);
        }
        if ($unite === 'jours') {
            return $date->addDays($n);
        }
        // Jours ouvrables : ni samedi, ni dimanche, ni jour de fermeture du tribunal (jours fériés, vacances judiciaires).
        $pas = $n < 0 ? -1 : 1;
        for ($reste = abs($n); $reste > 0;) {
            $date->addDays($pas);
            if (self::estOuvrable($date)) {
                $reste--;
            }
        }

        return $date;
    }

    public static function estOuvrable(Carbon $jour): bool
    {
        if ($jour->isWeekend()) {
            return false;
        }
        self::$fermetures ??= Fermeture::all()->map(fn ($f) => [$f->debut->toDateString(), $f->fin->toDateString()])->all();
        $j = $jour->toDateString();
        foreach (self::$fermetures as [$debut, $fin]) {
            if ($j >= $debut && $j <= $fin) {
                return false;
            }
        }

        return true;
    }

    public function libelleDelai(): string
    {
        return $this->valeur.' '.(self::UNITES[$this->unite] ?? $this->unite);
    }
}
