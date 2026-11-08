<?php

namespace App\Support;

use App\Models\Chambre;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Les quatre familles d'affaires, pour les droits par type d'affaire : civil (civil, commercial, administratif, référés, social), pénal (correctionnel,
 | criminel, application des peines), mineurs (juge des enfants, tribunaux pour mineurs et pour enfants), instruction (cabinets d'instruction).
 | La famille d'une chambre se déduit de sa section dans l'ordonnance et de son nom : aucune saisie à faire quand l'ordonnance change.
 | Le civil et le pénal sont ouverts à qui a accès à la chambre (visibilité par affectation, voir PerimetreChambres). Les mineurs et l'instruction sont
 | PROTÉGÉS : il faut un droit spécial du profil, jamais donné par défaut, même à qui a accès à toutes les chambres.
 */
class FamillesAffaires
{
    public const FAMILLES = [
        'civil' => 'Affaires civiles, commerciales, administratives, de référé et sociales',
        'penal' => 'Affaires pénales (correctionnel, criminel, application des peines)',
        'mineurs' => 'Affaires de mineurs (juge des enfants, tribunaux pour mineurs et pour enfants)',
        'instruction' => "Dossiers d'instruction (cabinets d'instruction)",
    ];

    /** Les familles dont l'accès doit être donné expressément. */
    public const PROTEGEES = ['mineurs', 'instruction'];

    public static function droit(string $famille): string
    {
        return 'affaires.famille.'.$famille;
    }

    public static function deChambre(Chambre $chambre): string
    {
        $texte = mb_strtolower($chambre->libelle.' '.$chambre->intitule);
        if (preg_match('/mineur|enfant/u', $texte)) {
            return 'mineurs';
        }

        return match ($chambre->section?->code) {
            'instruction' => 'instruction',
            'penale' => 'penal',
            default => 'civil',
        };
    }

    /** Les familles que cet utilisateur peut voir : le civil et le pénal, plus les familles protégées dont il a le droit (administrateurs du système : toutes). @return list<string> */
    public static function autorisees(?User $user): array
    {
        if (! $user || ! $user->actif) {
            return [];
        }

        return array_values(array_filter(array_keys(self::FAMILLES), fn ($f) => ! in_array($f, self::PROTEGEES, true) || $user->peut(self::droit($f))));
    }

    /** Les cabinets d'instruction et le cabinet du juge des enfants (les classeurs « INFO JI » et « INFO JE »). @return list<int> */
    public static function chambresInstruction(): array
    {
        return self::chambresDe(['instruction'])->merge(Chambre::where('libelle', 'like', '%juge des enfants%')->pluck('id')->map(fn ($i) => (int) $i))->unique()->values()->all();
    }

    /** Les chambres de ces familles. @param  list<string>  $familles  @return Collection<int, int> identifiants */
    public static function chambresDe(array $familles): Collection
    {
        return Chambre::with('section')->get()->filter(fn (Chambre $c) => in_array(self::deChambre($c), $familles, true))->pluck('id')->map(fn ($i) => (int) $i);
    }
}
