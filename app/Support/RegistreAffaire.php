<?php

namespace App\Support;

/*
 | Les trois familles de saisie d'une affaire : civil (série RG), pénal (série RP) et instruction (séries RI pour les adultes, RE pour les
 | enfants, choisie par la nature d'affaire). Chaque famille a ses parties (demandeurs et défendeurs, prévenus, inculpés), ses modes de
 | saisine et son formulaire ; la liste, la fiche, la jonction, l'annulation et la réattribution sont communes.
 */
class RegistreAffaire
{
    private const DEFINITIONS = [
        'civil' => [
            'cle' => 'civil', 'libelle' => 'Affaire civile', 'serie' => 'RG', 'modes' => 'civil', 'natures' => ['RG'],
            'roles' => ['demandeur', 'defendeur'], 'principal' => 'demandeur',
            'natures_partie' => ['homme', 'femme', 'couple', 'morale', 'etat', 'mp'],
            'objet' => 'Objet du litige', 'icone' => 'fa-scale-balanced',
        ],
        'penal' => [
            'cle' => 'penal', 'libelle' => 'Affaire pénale', 'serie' => 'RP', 'modes' => 'penal', 'natures' => ['RP'],
            'roles' => ['prevenu', 'victime'], 'principal' => 'prevenu',
            'natures_partie' => ['homme', 'femme', 'morale'],
            'objet' => 'Nature de la prévention', 'icone' => 'fa-handcuffs',
        ],
        'instruction' => [
            'cle' => 'instruction', 'libelle' => "Instruction (cabinet d'instruction)", 'serie' => null, 'modes' => 'instruction', 'natures' => ['RI', 'RE'],
            'roles' => ['inculpe', 'victime'], 'principal' => 'inculpe',
            'natures_partie' => ['homme', 'femme', 'morale'],
            'objet' => 'Qualification des faits', 'icone' => 'fa-magnifying-glass',
        ],
    ];

    /** @return array<string, mixed> */
    public static function depuis(?string $cle): array
    {
        return self::DEFINITIONS[$cle ?? 'civil'] ?? self::DEFINITIONS['civil'];
    }

    /** La famille d'une série : RG civil, RP pénal, RI et RE instruction. */
    public static function depuisSerie(string $serie): array
    {
        return match ($serie) {
            'RP' => self::DEFINITIONS['penal'],
            'RI', 'RE' => self::DEFINITIONS['instruction'],
            default => self::DEFINITIONS['civil'],
        };
    }

    /** @return array<string, array<string, mixed>> */
    public static function toutes(): array
    {
        return self::DEFINITIONS;
    }
}
