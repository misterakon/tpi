<?php

/*
 | Identité de la juridiction et préférences d'affichage.
 | Ce sont les valeurs PAR DÉFAUT : elles se modifient ensuite depuis la page « Réglages » de l'application.
 */
return [
    // Nom complet (courriels, écrans) ; sigle et ville composent la marque de l'en-tête.
    'juridiction' => env('TPI_JURIDICTION', 'Tribunal de Première Instance de Bingerville'),
    // Listes proposées dans le formulaire de rédaction pour certaines variables (le texte choisi s'insère tel quel dans la décision).
    // Elles seront remplacées par les données de l'enrôlement ; en attendant, on peut les adapter ici.
    'listes_decision' => [
        'matiere' => ["civile d'état des personnes", 'civile', 'commerciale', 'sociale', 'correctionnelle', 'criminelle', 'administrative et fiscale', "de l'enfance délinquante"],
        'regime_matrimonial' => ['la communauté de biens', 'la séparation de biens'],
        'type_audience' => ['ordinaire', 'extraordinaire', 'foraine'],
    ],
    // Numéro RG des affaires : « manuel » (le greffe a son propre logiciel d'enrôlement, on reporte son numéro, ex. Bingerville)
    // ou « automatique » (l'application attribue le numéro et contrôle la date limite d'enrôlement).
    // Un mode par série (RG civil, RP pénal, RI et RE instruction). Réglable dans Réglages > Affaires.
    'enrolement' => ['numero' => [
        'rg' => env('TPI_NUMERO_RG', 'manuel'), 'rp' => env('TPI_NUMERO_RP', 'manuel'),
        'ri' => env('TPI_NUMERO_RI', 'manuel'), 're' => env('TPI_NUMERO_RE', 'manuel'),
    ]],
    // Dossier numérique : taille maximale d'une pièce en Mo (le serveur PHP peut en accepter moins : php.ini, upload_max_filesize).
    'dossier' => ['taille_max_mo' => env('TPI_DOSSIER_MAX_MO', 20)],
    // Pénal : durée du mandat de dépôt avant échéance (jours) et délai d'alerte avant cette échéance. Valeurs à valider par le tribunal.
    'penal' => ['detention_jours' => env('TPI_DETENTION_JOURS', 180), 'alerte_jours' => env('TPI_DETENTION_ALERTE', 30), 'minute_vigilance_jours' => (int) env('TPI_MINUTE_VIGILANCE', 30)],   // minute_vigilance_jours : au-delà, une minute non transmise est signalée (valeur à confirmer)
    // Requêtes : au-delà de ce délai sans répartition, la requête est signalée en retard (secrétariat de la Présidente).
    'requetes' => ['delai_repartition_jours' => env('TPI_REQUETES_DELAI', 2)],
    // Décisions : moment où le numéro est attribué, sans trou ni doublon dans l'année ('validation' ou 'signature'). À confirmer avec la greffière en chef.
    'decisions' => ['numero_moment' => env('TPI_DECISION_NUMERO', 'validation')],
    // Reprise de l'existant : taille maximale d'un classeur Excel en Mo (le serveur PHP peut en accepter moins : php.ini).
    'reprise' => ['taille_max_mo' => env('TPI_REPRISE_MAX_MO', 10)],
    'cour_appel' => env('TPI_COUR_APPEL', "Cour d'appel d'Abidjan"),
    'sigle' => env('TPI_SIGLE', 'TPI'),
    'ville' => env('TPI_VILLE', 'BINGERVILLE'),

    // Impression des actes de décision : police et tailles (modifiables dans Réglages).
    'actes' => [
        'police_corps' => env('TPI_ACTES_POLICE_CORPS', 'Centaur'),
        'taille_corps' => (int) env('TPI_ACTES_TAILLE_CORPS', 16),
        'police_timbre' => env('TPI_ACTES_POLICE_TIMBRE', 'Centaur'),
        'taille_timbre' => (int) env('TPI_ACTES_TAILLE_TIMBRE', 12),
        // Mise en page (millimètres, comme dans les modèles Word du tribunal : A4, marges de 25 mm, timbre à gauche de la qualité).
        'marge_haut_mm' => (int) env('TPI_ACTES_MARGE_HAUT_MM', 25),
        'marge_bas_mm' => (int) env('TPI_ACTES_MARGE_BAS_MM', 14),
        'marge_gauche_mm' => (int) env('TPI_ACTES_MARGE_GAUCHE_MM', 25),
        'marge_droite_mm' => (int) env('TPI_ACTES_MARGE_DROITE_MM', 25),
        'timbre_largeur_mm' => (int) env('TPI_ACTES_TIMBRE_LARGEUR_MM', 57),
        'timbre_hauteur_mm' => (int) env('TPI_ACTES_TIMBRE_HAUTEUR_MM', 118),
        'timbre_gauche_mm' => (int) env('TPI_ACTES_TIMBRE_GAUCHE_MM', 8),
        'timbre_haut_mm' => (int) env('TPI_ACTES_TIMBRE_HAUT_MM', 18),
        'retrait_qualite_mm' => (int) env('TPI_ACTES_RETRAIT_QUALITE_MM', 45),
        'interligne_qualite_pct' => (int) env('TPI_ACTES_INTERLIGNE_QUALITE_PCT', 100),
        'interligne_factum_pct' => (int) env('TPI_ACTES_INTERLIGNE_FACTUM_PCT', 110),
    ],

    // Nombre de lignes par page dans les tableaux.
    // Mise en état : au-delà de ce nombre de jours depuis la première audience, une mise en état ouverte est signalée « longue ».
    'mise_en_etat' => ['vigilance_jours' => (int) env('TPI_MISE_EN_ETAT_VIGILANCE', 90)],

    // Délais de jugement de l'ordonnance (page 8) : civil 6 mois, correctionnel 3 mois. Servent à mesurer la part des affaires jugées dans le délai.
    'delais_jugement' => ['civil_jours' => 183, 'correctionnel_jours' => 91],

    'lignes_par_page' => (int) env('TPI_LIGNES_PAR_PAGE', 10),
];
