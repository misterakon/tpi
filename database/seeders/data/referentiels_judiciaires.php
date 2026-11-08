<?php

/*
 | Données de départ du lot E, tirées du cahier des charges (§ 3.2, 6.2, 6.5, 6.6, 8 et annexe A) et de l'ordonnance n°600/2026.
 | Ce qui vient du droit courant et non des documents du tribunal est marqué « a_valider » : la Présidente le confirme.
 | Les objets du litige reprennent les attributions de chaque chambre ; le tribunal complète la liste.
 */
return [
    'types_audience' => [
        'Audience publique', 'Tentative de conciliation', 'Réception des requêtes', 'Audience de référé', 'Audience de mise en état',
        'Session criminelle', 'Signature des ordonnances',
    ],

    // [libellé, à valider]
    'caracteres_decision' => [['Contradictoire', false], ['Par défaut', false], ['Réputé contradictoire', true]],

    // [libellé, demande une date]  (cahier des charges, exigence AUD-02)
    'issues_audience' => [['Renvoi', true], ['Mise en délibéré', true], ['Décision rendue', false], ['Radiation', false], ['Jonction', false], ['Désistement', false]],

    'modes_comparution' => ['En personne', 'Assisté', 'Représenté'],

    'qualites_procedurales' => ['Demandeur', 'Défendeur', 'Partie jointe', 'Ministère public', 'Prévenu', 'Victime'],

    'natures_partie' => ['Homme', 'Femme', 'Couple', 'Personne morale', 'État ou collectivité'],

    // [libellé, registre, à valider]
    'modes_saisine' => [
        ['Assignation', 'civil', true], ['Requête', 'civil', false], ['Déclaration au greffe', 'civil', true],
        ['Flagrant délit', 'penal', false], ['Citation directe', 'penal', true], ['Information judiciaire', 'instruction', false],
        ['Procès-verbal de transaction (art. 17 du code de procédure pénale)', 'penal', false],
        ['Comparution sur reconnaissance préalable de culpabilité (art. 521 et suivants)', 'penal', false],
        ['Arrêt de renvoi de la chambre d\'instruction criminelle', 'penal', false],
        ['Réquisitoire', 'instruction', false],
    ],

    'matieres' => [
        'Civile', 'Commerciale', 'Administrative et fiscale', 'Sociale', 'Correctionnelle', 'Criminelle', 'Enfance délinquante', 'Instruction',
    ],

    // [libellé, registre, exclue des dates limites d'enrôlement]  (états statistiques, § 8 ; art. 7 de l'ordonnance)
    'natures_affaire' => [
        ['Civile ordinaire', 'RG', false], ['État des personnes', 'RG', false], ['Matrimoniale', 'RG', false],
        ['Référé ordinaire', 'RG', true], ['Référé-expulsion', 'RG', true], ['Juge de l\'exécution (voies d\'exécution)', 'RG', true],
        ['Commerciale', 'RG', false], ['Administrative', 'RG', false], ['Saisie-rémunération', 'RG', false], ['Autre (civil)', 'RG', false],
        ['Flagrants délits', 'RP', false], ['Délits divers', 'RP', false], ['Délits économiques', 'RP', false],
        ['Police de la circulation', 'RP', false], ['Simple police', 'RP', false], ['Tribunal pour enfants', 'RP', false],
        ['Instruction (adultes)', 'RI', false], ['Instruction (enfants)', 'RE', false],
    ],

    // [libellé, registre, nature d'affaire, valeur, unité, renvois au plus, point de départ]  (notes manuscrites de l'ordonnance, à confirmer)
    'delais_procedure' => [
        ['Civil', 'RG', null, 6, 'mois', null, 'ouverture_audience'],
        ['Correctionnel', 'RP', null, 3, 'mois', null, 'ouverture_audience'],
        ['Référé-expulsion', null, 'Référé-expulsion', 15, 'jours', 1, 'ouverture_audience'],
    ],

    // [chambre, matière, nature d'affaire (ou null), objets…]  d'après les attributions de l'ordonnance
    'objets_litige' => [
        ['1re chambre civile', 'Civile', 'Civile ordinaire', [
            'Responsabilité délictuelle et contractuelle', 'Contrats civils', 'Créances civiles', 'Action en garantie', 'Exequatur', 'Propriété intellectuelle',
        ]],
        ['1re chambre civile', 'Commerciale', 'Commerciale', ['Litiges commerciaux de plus de 100 000 000 FCFA']],
        ['1re chambre civile', 'Administrative et fiscale', 'Administrative', [
            'Résolution et résiliation de contrats publics', 'Contentieux administratif et fiscal', 'Dettes publiques', 'Expropriation pour cause d\'utilité publique',
            'Recours de plein contentieux contre l\'administration',
        ]],
        ['2e chambre civile', 'Civile', 'Civile ordinaire', [
            'Expropriation', 'Copropriété', 'Domaine foncier rural et urbain', 'Baux emphytéotiques', 'Revendication de propriété', 'Empiètements', 'Droits réels',
            'Baux à construction', 'Servitudes', 'Publicité foncière', 'Vente d\'immeubles', 'Indemnités d\'occupation', 'Déguerpissement', 'Sûretés immobilières',
            'Baux d\'habitation et professionnels', 'Validation de congé', 'Expulsion et paiement de loyers', 'Hypothèque conservatoire',
            'Paiement (intérêt pécuniaire inférieur ou égal à 100 000 000 FCFA)', 'Opposition à ordonnance d\'injonction de payer, de délivrer, de restituer et de taxe',
            'Prestation de serment',
        ]],
        ['2e chambre civile', 'Civile', 'Juge de l\'exécution (voies d\'exécution)', ['Saisies et ventes immobilières']],
        ['3e chambre civile', 'Civile', 'Matrimoniale', [
            'Divorce et séparation de corps', 'Contentieux après divorce ou hors mariage', 'Liquidation et partage des régimes matrimoniaux, des indivisions conjugales et des successions',
            'Annulation de mariage', 'Changement de régime matrimonial', 'Contribution aux charges du ménage', 'Autorisation entre époux',
        ]],
        ['3e chambre civile', 'Civile', 'État des personnes', [
            'Paternité', 'Maternité', 'Nom et prénom', 'Hérédité', 'Absence', 'Disparition', 'Adoption', 'Action relative à la filiation', 'État civil',
        ]],
        ['3e chambre civile', 'Civile', null, ['Pension alimentaire', 'Obligation d\'entretien', 'Administration provisoire et mesures conservatoires', 'Scellés']],
        ['4e chambre civile', 'Civile', 'État des personnes', ['Tutelle', 'Puissance paternelle', 'Émancipation', 'Administration légale', 'Garde juridique']],
        ['4e chambre civile', 'Civile', null, ['Pension alimentaire', 'Certificats relevant de la compétence du juge des tutelles', 'Procédures d\'urgence en matière de famille et d\'état des personnes']],
        ['5e chambre civile', 'Civile', 'Référé ordinaire', [
            'Référé d\'heure à heure', 'Référé ordinaire', 'Rétractation d\'ordonnance sur requête', 'Difficultés d\'exécution d\'une décision de justice',
        ]],
        ['5e chambre civile', 'Civile', 'Juge de l\'exécution (voies d\'exécution)', ['Mesure d\'exécution forcée ou saisie conservatoire']],
        ['5e chambre civile', 'Civile', 'Saisie-rémunération', ['Saisie-rémunération et cession des rémunérations']],
        ['5e chambre civile', 'Civile', 'Référé-expulsion', [
            'Référé-expulsion', 'Renouvellement des baux et révision des loyers', 'Occupation sans droit ni titre', 'Validation de congé avec expulsion',
        ]],
        ['5e chambre civile', 'Civile', null, ['Ordonnance sur requête', 'Ordonnance d\'injonction de payer']],
        ['Section sociale', 'Sociale', null, [
            'Différend individuel du contrat de travail ou d\'apprentissage', 'Accident du travail et maladie professionnelle', 'Convention collective',
            'Litige entre travailleurs ou apprentis', 'Référé social',
        ]],
        ['1re chambre correctionnelle', 'Correctionnelle', 'Délits économiques', [
            'Vol simple et aggravé', 'Recel', 'Escroquerie', 'Abus de confiance', 'Abus de blanc-seing', 'Faux et usage de faux', 'Contrefaçon', 'Fraude commerciale',
            'Concurrence déloyale', 'Infraction bancaire, douanière, fiscale et forestière', 'Blanchiment de capitaux', 'Usure', 'Cybercriminalité',
        ]],
        ['1re chambre correctionnelle', 'Correctionnelle', 'Délits divers', [
            'Attentat aux mœurs', 'Rébellion', 'Délit de presse', 'Diffamation et injures', 'Coups et blessures volontaires', 'Violences',
            'Homicide et blessures involontaires', 'Stupéfiants', 'Armes à feu', 'Débit de boisson',
        ]],
        ['2e chambre correctionnelle', 'Correctionnelle', 'Flagrants délits', ['Infraction jugée selon la procédure de flagrant délit']],
        ['3e chambre correctionnelle', 'Correctionnelle', 'Simple police', ['Contravention', 'Amende de composition']],
        ['3e chambre correctionnelle', 'Correctionnelle', 'Police de la circulation', ['Délit de police de la circulation']],
        ['4e chambre correctionnelle', 'Enfance délinquante', 'Tribunal pour enfants', ['Enfance délinquante']],
        ['Tribunal criminel', 'Criminelle', null, ['Infraction criminelle renvoyée par la chambre d\'instruction criminelle']],
        ['Tribunal criminel pour mineurs', 'Criminelle', null, ['Crime commis par un mineur âgé de 16 ans au moins']],
        ['Juridiction présidentielle', 'Correctionnelle', null, ['Homologation de transaction pénale', 'Comparution sur reconnaissance préalable de culpabilité']],
        ['1er cabinet d\'instruction', 'Instruction', 'Instruction (adultes)', ['Instruction d\'une affaire pénale (adultes)']],
        ['Cabinet du juge des enfants', 'Instruction', 'Instruction (enfants)', ['Instruction d\'une affaire concernant un mineur']],
    ],

    // [texte, article, objet]  textes cités par l'ordonnance
    'textes_loi' => [
        ['Code de procédure civile, commerciale et administrative', '231', 'Rétractation d\'ordonnance sur requête'],
        ['Acte uniforme OHADA', '49', 'Mesure d\'exécution forcée ou saisie conservatoire'],
        ['Code de procédure pénale', '17', 'Transaction : homologation du procès-verbal'],
        ['Code de procédure pénale', '521 et suivants', 'Comparution sur reconnaissance préalable de culpabilité'],
    ],
];
