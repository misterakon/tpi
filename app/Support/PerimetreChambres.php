<?php

namespace App\Support;

use App\Models\User;

/*
 | Les chambres dont un utilisateur voit les affaires. Le droit spécial « affaires.toutes_chambres » (la Présidente, la greffière en chef,
 | les administrateurs) donne accès à toutes. Sans lui, on ne voit que les affaires des chambres où l'agent relié au compte est affecté
 | aujourd'hui, d'après l'ordonnance : le greffier de la 2e chambre civile ne voit pas celles de la 3e. Un compte sans agent ne voit rien.
 | Par-dessus, les familles PROTÉGÉES (mineurs, instruction : voir FamillesAffaires) exigent un droit spécial du profil : sans lui, ces chambres sont
 | retirées du périmètre, même pour la Présidente ou un administrateur délégué.
 */
class PerimetreChambres
{
    /** @return list<int>|null  null : toutes les chambres */
    public static function de(?User $user): ?array
    {
        if (! $user || ! $user->actif) {
            return [];
        }
        $familles = FamillesAffaires::autorisees($user);
        $toutes = count($familles) === count(FamillesAffaires::FAMILLES);

        if ($user->peut('affaires.toutes_chambres')) {
            // Toutes les chambres, mais seulement des familles d'affaires que le profil a le droit de voir.
            return $toutes ? null : FamillesAffaires::chambresDe($familles)->values()->all();
        }

        $agent = $user->agent;
        $siennes = $agent ? $agent->affectationsEnCours()->map(fn ($a) => (int) $a->formation->chambre_id)->unique()->values()->all() : [];
        if ($toutes || ! $siennes) {
            return $siennes;
        }

        // Ses chambres, limitées aux familles autorisées : un greffier du civil ne voit pas une chambre de mineurs où il aurait été affecté.
        return array_values(array_intersect($siennes, FamillesAffaires::chambresDe($familles)->all()));
    }

    public static function voit(?User $user, ?int $chambreId): bool
    {
        $chambres = self::de($user);

        return $chambres === null || ($chambreId !== null && in_array($chambreId, $chambres, true));
    }
}
