<?php

namespace App\Support;

/*
 | Paramètres propres à chaque TPI pour les affaires. À Bingerville, le greffe gère déjà l'enrôlement avec son logiciel : le siège reporte
 | le numéro RG (mode « manuel ») et le greffier de chambre ne voit jamais le mot « enrôlement » (son métier : établir le rôle d'audience).
 | Un TPI qui enrôle dans l'application choisit le mode « automatique ».
 */
class ParametresAffaires
{
    /** Le numéro de cette série (RG, RP, RI, RE) est-il saisi à la main ? Bingerville : oui, partout. */
    public static function numeroManuel(string $serie = 'RG'): bool
    {
        return config('tpi.enrolement.numero.'.strtolower($serie), 'manuel') !== 'automatique';
    }

    /** Une série au moins reçoit ses numéros du greffe : l'écran parle de « saisie » et non d'« enrôlement ». */
    public static function auMoinsUnManuel(): bool
    {
        foreach (['RG', 'RP', 'RI', 'RE'] as $serie) {
            if (self::numeroManuel($serie)) {
                return true;
            }
        }

        return false;
    }

    /** Les mots de l'écran de saisie selon le TPI. @return array{menu: string, titre: string, sous_titre: string, ajout: string, bouton: string, recentes: string, tag: string} */
    public static function vocabulaire(): array
    {
        if (self::auMoinsUnManuel()) {
            return [
                'menu' => 'Saisie des affaires',
                'titre' => "Saisie d'une affaire",
                'sous_titre' => "Reportez ici les informations de l'affaire telles qu'elles figurent au greffe : le numéro RG est celui du greffe, saisi à la main (une alerte signale les doublons ; un même numéro n'est admis que pour une jonction).",
                'ajout' => 'Saisir une affaire',
                'bouton' => "Enregistrer l'affaire",
                'recentes' => 'Dernières affaires saisies',
                'tag' => 'Affaires',
            ];
        }

        return [
            'menu' => 'Enrôlement',
            'titre' => 'Enrôlement',
            'sous_titre' => "Enregistrer une nouvelle affaire civile : le numéro RG est attribué à l'enregistrement, sans doublon ni trou, et ne change plus.",
            'ajout' => 'Enrôler une affaire',
            'bouton' => 'Enregistrer et obtenir le numéro RG',
            'recentes' => 'Derniers enrôlements',
            'tag' => 'Affaires',
        ];
    }

    public static function libelleMenu(): string
    {
        return self::vocabulaire()['menu'];
    }
}
