<?php

/*
 | Mots-clés pénaux : les infractions courantes, rattachées à la chambre qui les juge d'après les attributions de l'ordonnance 600/2026.
 | Servent à proposer la chambre (ou le cabinet) d'après ce que le greffier tape dans l'objet. Le tribunal complète la liste dans Référentiels > Objets du litige.
 | Format : [chambre, matière, nature d'affaire (ou null), [libellés]]
 */
return [
    // Délits économiques et financiers : « vols simples et aggravés, recel, escroquerie, abus de confiance… »
    ['1re chambre correctionnelle', 'Correctionnelle', 'Délits économiques', [
        'Vol', 'Vol de véhicule ou de moto', 'Vol de bétail', 'Cambriolage', 'Vol à l\'arraché', 'Détournement de deniers publics', 'Détournement de biens',
        'Corruption et trafic d\'influence', 'Chèque sans provision', 'Abus de biens sociaux', 'Banqueroute', 'Fraude fiscale', 'Fraude douanière',
        'Faux en écriture', 'Falsification de documents', 'Usage de faux', 'Escroquerie par internet', 'Cyberescroquerie', 'Piratage informatique',
        'Détournement de fonds', 'Spoliation foncière', 'Double vente', 'Détournement', 'Corruption', 'Concussion', 'Faux',
    ]],
    // Délits divers : « attentats aux mœurs, rébellion, délits de presse, coups et blessures, violences, homicides involontaires, stupéfiants, armes… »
    ['1re chambre correctionnelle', 'Correctionnelle', 'Délits divers', [
        'Coups', 'Coups et blessures', 'Blessure', 'Bagarre', 'Rixe', 'Menaces', 'Menaces de mort', 'Chantage', 'Destruction de biens', 'Dégradation de biens',
        'Violation de domicile', 'Outrage à agent', 'Outrage à magistrat', 'Abandon de famille', 'Abandon de domicile conjugal', 'Harcèlement', 'Harcèlement sexuel',
        'Trafic de drogue', 'Détention de drogue', 'Consommation de stupéfiants', 'Port d\'arme', 'Détention illégale d\'arme', 'Trouble à l\'ordre public',
        'Association de malfaiteurs', 'Complicité', 'Proxénétisme', 'Détournement de mineur', 'Attentat à la pudeur', 'Agression', 'Séquestration',
        'Incendie involontaire', 'Mise en danger de la vie d\'autrui', 'Non-assistance à personne en danger', 'Homicide involontaire', 'Faux témoignage',
        'Diffamation', 'Injure', 'Atteinte à la vie privée', 'Tapage', 'Exercice illégal d\'une profession', 'Incendie', 'Violence', 'Agression physique',
    ]],
    // Circulation routière : « jugement des délits en matière de police de la circulation »
    ['3e chambre correctionnelle', 'Correctionnelle', 'Police de la circulation', [
        'Accident de la circulation', 'Ivresse au volant', 'Conduite en état d\'ivresse', 'Délit de fuite', 'Conduite sans permis', 'Défaut d\'assurance',
        'Accident', 'Accident de la route', 'Excès de vitesse', 'Blessures involontaires par accident', 'Homicide involontaire par accident de la route', 'Refus d\'obtempérer',
    ]],
    // Contraventions : « contraventions de toute nature, amende de composition »
    ['3e chambre correctionnelle', 'Correctionnelle', 'Simple police', [
        'Contravention de simple police', 'Tapage nocturne', 'Divagation d\'animaux', 'Voie de fait légère',
    ]],
    // Enfance délinquante
    ['4e chambre correctionnelle', 'Enfance délinquante', 'Tribunal pour enfants', [
        'Mineur auteur d\'un délit', 'Enfant en conflit avec la loi', 'Délinquance juvénile', 'Vol commis par un mineur',
    ]],
    // Crimes : tribunal criminel, sur renvoi de la chambre d'instruction
    ['Tribunal criminel', 'Criminelle', null, [
        'Meurtre', 'Assassinat', 'Tentative de meurtre', 'Viol', 'Viol sur mineur', 'Vol à main armée', 'Braquage', 'Incendie volontaire', 'Empoisonnement',
        'Enlèvement', 'Coups mortels', 'Trafic d\'enfants', 'Traite des personnes', 'Parricide', 'Infanticide', 'Meurtre sur ascendant', 'Crime de sang',
        'Fausse monnaie', 'Faux en écriture publique', 'Détournement de deniers publics aggravé', 'Homicide', 'Séquestration', 'Crime',
    ]],
    ['Tribunal criminel pour mineurs', 'Criminelle', null, [
        'Crime commis par un mineur', 'Meurtre commis par un mineur', 'Viol commis par un mineur',
    ]],
    // Instruction : mêmes mots-clés, pour les deux cabinets d'instruction (adultes) et le juge des enfants
    ['1er cabinet d\'instruction', 'Instruction', 'Instruction (adultes)', [
        'Meurtre', 'Assassinat', 'Viol', 'Vol à main armée', 'Braquage', 'Escroquerie', 'Détournement de deniers publics', 'Faux et usage de faux', 'Corruption',
        'Coups et blessures', 'Trafic de drogue', 'Association de malfaiteurs', 'Abus de confiance', 'Homicide', 'Incendie volontaire', 'Blanchiment',
        'Enlèvement', 'Séquestration', 'Information judiciaire', 'Mandat de dépôt', 'Détention préventive', 'Empoisonnement',
        'Vol', 'Menaces', 'Coups', 'Stupéfiants', 'Détournement', 'Incendie', 'Blessure', 'Faux',
    ]],
    ['2e cabinet d\'instruction', 'Instruction', 'Instruction (adultes)', [
        'Meurtre', 'Assassinat', 'Viol', 'Vol à main armée', 'Braquage', 'Escroquerie', 'Détournement de deniers publics', 'Faux et usage de faux', 'Corruption',
        'Coups et blessures', 'Trafic de drogue', 'Association de malfaiteurs', 'Abus de confiance', 'Homicide', 'Incendie volontaire', 'Blanchiment',
        'Enlèvement', 'Séquestration', 'Information judiciaire', 'Mandat de dépôt', 'Détention préventive', 'Empoisonnement',
        'Vol', 'Menaces', 'Coups', 'Stupéfiants', 'Détournement', 'Incendie', 'Blessure', 'Faux',
    ]],
    ['Cabinet du juge des enfants', 'Instruction', 'Instruction (enfants)', [
        'Mineur', 'Enfant en conflit avec la loi', 'Mineur auteur de vol', 'Mineur auteur de coups et blessures', 'Mineur auteur de viol', 'Mineur auteur de meurtre',
        'Assistance éducative', 'Enfant en danger', 'Délinquance juvénile',
    ]],
];
