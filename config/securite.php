<?php

/*
 | Paramètres de sécurité de l'authentification : valeurs PAR DÉFAUT (.env ou ce fichier).
 | La plupart se modifient ensuite depuis la page « Réglages » de l'application, qui prime sur ces valeurs.
 */
return [
    // Déconnexion automatique après ce délai sans aucune action.
    'inactivite_minutes' => (int) env('SECURITE_INACTIVITE_MINUTES', 20),

    // Avertissement avec compte à rebours avant le verrouillage (de 10 à 300 secondes).
    'avertissement_secondes' => (int) env('SECURITE_AVERTISSEMENT_SECONDES', 60),

    // Après l'inactivité, la session est VERROUILLÉE (écran de verrouillage : mot de passe seul, sans code par e-mail).
    // Au-delà de cette durée de verrouillage, ou après trop d'essais de mot de passe, la session est fermée
    // et une connexion complète (mot de passe + code par e-mail) est exigée.
    'ecran_verrouille_max_minutes' => (int) env('SECURITE_VERROUILLAGE_ECRAN_MAX_MINUTES', 120),
    'deverrouillage_essais_max' => (int) env('SECURITE_DEVERROUILLAGE_ESSAIS_MAX', 5),

    // Mot de passe : longueur minimale (aucune autre obligation).
    'mot_de_passe_min' => 8,

    // Connexion : échecs successifs avant verrouillage du compte, et durée du verrouillage.
    'echecs_max' => (int) env('SECURITE_CONNEXION_ECHECS_MAX', 5),
    'verrouillage_minutes' => (int) env('SECURITE_VERROUILLAGE_MINUTES', 15),

    // Alertes de sécurité pour les administrateurs : échecs répétés, adresse jamais vue, connexion de nuit, code de secours.
    'anomalies' => [
        'actif' => (bool) env('SECURITE_ANOMALIES_ACTIF', true),
        'echecs' => (int) env('SECURITE_ANOMALIES_ECHECS', 5),
        'fenetre_minutes' => (int) env('SECURITE_ANOMALIES_FENETRE', 15),
        'nuit_debut' => (int) env('SECURITE_ANOMALIES_NUIT_DEBUT', 20),
        'nuit_fin' => (int) env('SECURITE_ANOMALIES_NUIT_FIN', 6),
        'fuseau' => env('SECURITE_ANOMALIES_FUSEAU', 'Africa/Abidjan'),
    ],

    // Double authentification par code envoyé par e-mail.
    'double_auth' => [
        'validite_minutes' => (int) env('SECURITE_2FA_VALIDITE_MINUTES', 10),
        'tentatives_max' => (int) env('SECURITE_2FA_TENTATIVES', 5),
        'renvoi_secondes' => (int) env('SECURITE_2FA_RENVOI_SECONDES', 60),
        'renvois_max' => 5,
        'longueur' => 6,
    ],
];
