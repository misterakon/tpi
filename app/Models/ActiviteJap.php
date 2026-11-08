<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 | Activités du juge de l'application des peines : un compteur par année, mois et rubrique. Les rubriques suivent le classeur du tribunal :
 | visites des établissements, puis, pour chaque type de demande, stock au début du mois, demandes reçues, initiatives du JAP, demandes traitées dont accord et rejet.
 */
class ActiviteJap extends Model
{
    /** Les rubriques de demandes : libellé, champs saisis, ligne du classeur de chaque champ (feuille « ACTIVITES DU JAP AAAA »). */
    public const RUBRIQUES = [
        'placement' => ['Placement de détenus à l\'extérieur', ['stock' => 'Stock au début du mois', 'recues' => 'Demandes reçues', 'traitees' => 'Demandes traitées', 'accord' => 'Dont accord', 'rejet' => 'Dont rejet'], ['stock' => 11, 'recues' => 12, 'traitees' => 14, 'accord' => 15, 'rejet' => 16]],
        'semi_liberte' => ['Semi-liberté', ['stock' => 'Stock au début du mois', 'recues' => 'Demandes reçues', 'traitees' => 'Demandes traitées', 'accord' => 'Dont accord', 'rejet' => 'Dont rejet'], ['stock' => 21, 'recues' => 22, 'traitees' => 24, 'accord' => 25, 'rejet' => 26]],
        'permission' => ['Permission de sortir', ['stock' => 'Stock au début du mois', 'recues' => 'Demandes reçues', 'traitees' => 'Demandes traitées', 'accord' => 'Dont accord', 'rejet' => 'Dont rejet'], ['stock' => 31, 'recues' => 32, 'traitees' => 34, 'accord' => 35, 'rejet' => 36]],
        'liberte_conditionnelle' => ['Liberté conditionnelle', ['stock' => 'Stock au début du mois', 'recues' => 'Demandes reçues', 'initiatives' => 'Initiatives du JAP', 'traitees' => 'Demandes traitées', 'accord' => 'Dont accord', 'rejet' => 'Dont rejet'], ['stock' => 41, 'recues' => 42, 'initiatives' => 43, 'traitees' => 45, 'accord' => 46, 'rejet' => 47]],
        'reclassement' => ['Reclassement de condamnés', ['stock' => 'Stock au début du mois', 'recues' => 'Demandes reçues des régisseurs', 'initiatives' => 'Initiatives du JAP', 'traitees' => 'Demandes traitées', 'accord' => 'Dont accord', 'rejet' => 'Dont rejet'], ['stock' => 52, 'recues' => 53, 'initiatives' => 54, 'traitees' => 56, 'accord' => 57, 'rejet' => 58]],
    ];

    public const VISITES = ['effectuees' => ['Nombre de visites effectuées', 5], 'pv' => ['Nombre de procès-verbaux établis', 6], 'pv_chancellerie' => ['Procès-verbaux adressés à la Chancellerie', 7]];

    protected $table = 'activites_jap';

    protected $guarded = [];
}
