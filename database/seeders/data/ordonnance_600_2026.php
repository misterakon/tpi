<?php

/*
 | Données de l'ordonnance n°600/2026 du 12 octobre 2026 (Tribunal de Première Instance de Bingerville, année judiciaire 2026-2027),
 | relevées page par page sur « Ordonnance.pdf » (30 pages numérisées). Chargées par OrdonnanceBingerville2026Seeder.
 |
 | Conventions : les clés d'agents sont des abréviations propres à ce fichier ; « _s » = suppléant ; jour : 1 = lundi … 7 = dimanche.
 | Points à confirmer (la source est un scan, avec quelques variantes d'orthographe) : voir « notes » en fin de fichier.
 */

return [

    'annee' => [
        'libelle' => '2026-2027',
        'ordonnance' => 'Ordonnance n°600/2026 du 12 octobre 2026',
        // L'ordonnance ne donne pas les dates de l'année : début = date de signature (art. 11), fin = à confirmer.
        'debut' => '2026-10-12',
        'fin' => '2027-10-11',
    ],

    'sections' => [
        'civile' => ['Section civile, commerciale et administrative', 1],
        'urgence' => ["Section procédure d'urgence", 2],
        'penale' => ['Section pénale', 3],
        'instruction' => ['Section instruction', 4],
        'sociale' => ['Section sociale', 5],
        'administration' => ['Section administration générale', 6],
    ],

    'salles' => [
        'kone_mamadou' => ["Salle d'audience KONE Mamadou (n°4)", 'audience', null],
        'kambile' => ["Salle d'audience Jean Sansan Kambile (n°3)", 'audience', null],
        'cabinet_juge' => ['Cabinet du juge', 'cabinet', null],
        'cabinet_president' => ['Cabinet du Président', 'cabinet', null],
        'salle_reunion' => ['Grande salle de réunion', 'reunion', "1er étage, à côté du bureau du deuxième Vice-Président"],
    ],

    /* clé => [nom, prénoms, sexe (M, F ou null si l'ordonnance ne l'indique pas), fonction] */
    'agents' => [
        // Magistrats
        'nguessan_ab' => ["N'GUESSAN", 'Appa Brigitte épouse LEPRY', 'F', 'presidente'],
        'ndraman' => ["N'DRAMAN", 'Kablan Fidèle Amilcar', 'M', 'vice_president'],
        'toure_biba' => ['TOURE', 'Biba épouse OLAYE', 'F', 'vice_president'],
        'nguessan_bjc' => ["N'GUESSAN", 'Bodo Joan Cyrille', 'M', 'juge'],
        'kipre' => ['KIPRE', 'Aubin Stéphane', 'M', 'juge'],
        'yougone' => ['YOUGONE', 'Lou Nazié Ruth Urchulla épouse KOUACOU', 'F', 'juge'],
        'diako' => ['DIAKO', 'Myriam épouse KOUAKOU', 'F', 'juge'],
        'kangah' => ['KANGAH', 'Larissa Laurelle', 'F', 'juge'],
        'kouao' => ['KOUAO', 'Effoua Ange Marie-Benson', 'F', 'juge'],
        'kone_yvan' => ['KONE', 'Yvan Gneheri François', 'M', 'juge'],
        'gnabro' => ['GNABRO', 'Gadji Olivia épouse ASSIE', 'F', 'juge'],
        'nango' => ['NANGO', 'Kouakou Affoua Angèle Vanessa', 'F', 'juge'],
        'zeregba' => ['ZEREGBA', 'Adeline épouse RABE', 'F', 'juge'],
        'boka' => ['BOKA', 'Carine', 'F', 'juge'],
        'cauphy' => ['CAUPHY', 'Péka Serge', 'M', 'juge_enfants'],
        'lathro' => ['LATHRO', 'Lath Homère Enoc', 'M', 'juge'],
        'kone_wm' => ['KONE', 'Wagnimin Marie-Christelle épouse COULIBALY', 'F', 'juge_instruction'],
        'konan' => ['KONAN', 'Elisabeth Anne Epiphanie épouse YEBOUA', 'F', 'juge_instruction'],

        // Greffe
        'toure_prisca' => ['TOURE', 'Prisca Amandine Y. épouse COULIBALY', 'F', 'greffier_en_chef'],
        'koutou' => ['KOUTOU', "Moillé Jean de l'Emmanuel", null, 'greffier_en_chef_adjoint'],
        'coulibaly_dt' => ['COULIBALY', 'Dramane Thomas', null, 'greffier_en_chef_adjoint'],
        'kouadio_ge' => ['KOUADIO', 'Guedji Emile', null, 'greffier'],
        'bleu' => ['BLEU', 'Madé Yves Julien', null, 'greffier'],
        'assamoi' => ['ASSAMOI', 'Yaba Anne Armel N.', null, 'greffier'],
        'soro_ts' => ['SORO', 'Tiélourougou Sandrine épouse TUO', 'F', 'greffier'],
        'kouadio_ml' => ['KOUADIO', 'Marie-Laurette', null, 'greffier'],
        'kedja' => ['KEDJA', 'Tamon Marie Jeanne', null, 'greffier'],
        'bah' => ['BAH', 'Stéphanie', null, 'greffier'],
        'zouhou' => ['ZOUHOU', 'Joniack Alix', null, 'greffier'],
        'tape' => ['TAPE', 'Igor Sidoine', null, 'greffier'],
        'coulibaly_ms' => ['COULIBALY', 'Magnigui Safiatou', null, 'greffier'],
        'djole_nb' => ['DJOLE', "N'Guessan Benoît", null, 'greffier'],
        'djole_ng' => ['DJOLE', "N'Dey Gwladys", null, 'greffier'],
        'nsoua' => ["N'SOUA", 'Leyo Martine E. épouse YAPO', 'F', 'greffier'],
        'coulibaly_em' => ['COULIBALY', 'El Massogona épouse COULIBALY', 'F', 'greffier'],
        'soro_kl' => ['SORO', 'Kamogo Lindsey Fatou', null, 'greffier'],

        // Service social (SPJEJ) et assesseurs du tribunal pour enfants
        'koffi_sj' => ['KOFFI', 'Salia Joseph', 'M', 'service_social'],
        'koffie' => ['KOFFIE', 'Adja Françoise Fabienne épouse Komenan', 'F', 'assesseur_spjej'],
        'zohore' => ['ZOHORE', 'Toniako Pascal', 'M', 'assesseur_spjej'],
        'guei' => ['GUEI', 'Sahaké Appoline épouse BEUGRE', 'F', 'assesseur_spjej'],
        'kouakou_ap' => ['KOUAKOU', 'Adai Polycarpe GIACOMO', 'M', 'assesseur_spjej'],
        'angbandji' => ['ANGBANDJI', 'Name Louise', 'F', 'assesseur_spjej'],
        'attoungbre' => ['ATTOUNGBRE', 'Yao Jonas', 'M', 'assesseur_spjej'],

        // Assesseurs de la section sociale
        'djakaridja' => ['DJAKARIDJA', 'Traoré', 'M', 'assesseur_employeur'],
        'dape' => ['DAPE', 'Hilaire Gbo', 'M', 'assesseur_employeur'],
        'zoundi' => ['ZOUNDI', 'Patricia Yao', 'F', 'assesseur_employeur'],
        'mylonoyannis' => ['MYLONOYANNIS', 'Aicha', 'F', 'assesseur_employeur'],
        'macguil' => ['MACGUIL', 'Jean Jacques C.', 'M', 'assesseur_employeur'],
        'yapi' => ['YAPI', 'Marcel', 'M', 'assesseur_employeur'],
        'achy' => ['ACHY', "N'Gbichy", 'M', 'assesseur_travailleur'],
        'aka' => ['AKA', 'Yao Bernard', 'M', 'assesseur_travailleur'],
        'bouali' => ['BOUALI', 'Stéphane', 'M', 'assesseur_travailleur'],
        'koffi_yi' => ['KOFFI', 'Yao Isidore', 'M', 'assesseur_travailleur'],
        'koffi_yh' => ['KOFFI', 'Yao Hilaire', 'M', 'assesseur_travailleur'],
        'kaneu' => ['KANEU', 'Kado Ghislain', 'M', 'assesseur_travailleur'],
        'ndepo' => ['NDEPO', 'Gustave', 'M', 'assesseur_travailleur'],
    ],

    /*
     | Chambres, par section : section > chambre (attributions) > formations (facultatives, avec composition et audiences).
     | Une chambre sans formation nommée a une formation unique (nom null) qui porte sa composition.
     | « equipe » : rôle => liste de clés d'agents ; suffixe « _s » = suppléants. « chef » = greffier chef de section.
     | « creneaux » : [type, jour, heure, fréquence, salle, précision].
     */
    'chambres' => [
        [
            'section' => 'civile',
            'groupe' => null,
            'libelle' => '1re chambre civile',
            'intitule' => 'Présidentielle et administrative',
            'attributions' => 'Toutes instances en matière civile et commerciale dont l\'intérêt pécuniaire excède 100 000 000 FCFA (responsabilité délictuelle et contractuelle, contrats civils, créances civiles, action en garantie, résolution et résiliation de contrats publics…) ; contentieux administratifs et fiscaux, dettes publiques, expropriation pour cause d\'utilité publique, exequatur, propriété intellectuelle, recours de plein contentieux contre l\'administration ; toutes les procédures que le Président du Tribunal juge nécessaire de connaître.',
            'remarque' => 'Juges chargés de la mise en état : tous les membres de la chambre.',
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'president_s' => ['ndraman'],
                        'assesseur' => ['nguessan_bjc', 'kipre'],
                        'assesseur_s' => ['diako', 'kangah', 'kone_yvan', 'nango'],
                        'greffier' => ['kouadio_ge', 'kedja', 'kouadio_ml'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience', 4, '09:00', 'hebdomadaire', 'kone_mamadou', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'civile',
            'groupe' => null,
            'libelle' => '2e chambre civile',
            'intitule' => 'Immobilière et des paiements',
            'attributions' => 'Expropriation, copropriété, domaine foncier rural et urbain, baux emphytéotiques, revendication de propriété, empiètements, droits réels, baux à construction, servitudes, publicité foncière ; vente d\'immeubles, indemnités d\'occupation, déguerpissement, sûretés immobilières, baux d\'habitation et professionnels, validation de congé, expulsion et paiement de loyers, hypothèque conservatoire, saisies et ventes immobilières ; toutes instances en paiement dont l\'intérêt pécuniaire est inférieur ou égal à 100 000 000 FCFA, oppositions à ordonnance d\'injonction de payer, de délivrer, de restituer et de taxe, prestation de serment.',
            'remarque' => 'Juges chargés de la mise en état : tous les membres de la chambre.',
            'formations' => [
                [
                    'nom' => 'Formation A',
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'president_s' => ['ndraman'],
                        'assesseur' => ['nguessan_bjc', 'kipre', 'diako', 'kone_yvan', 'nango'],
                        'assesseur_s' => ['kangah', 'kouao'],
                        'greffier' => ['kouadio_ge', 'bleu', 'assamoi', 'soro_ts'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience', 1, '09:00', 'hebdomadaire', 'kone_mamadou', null],
                    ],
                ],
                [
                    'nom' => 'Formation B',
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['ndraman'],
                        'president_s' => ['toure_biba'],
                        'assesseur' => ['nguessan_bjc', 'kipre', 'diako', 'kone_yvan', 'nango'],
                        'assesseur_s' => ['kangah', 'kouao', 'yougone'],
                        'greffier' => ['kouadio_ge', 'bleu', 'assamoi', 'soro_ts'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience', 1, null, 'hebdomadaire', 'kone_mamadou', 'Après l\'audience de la formation A'],
                    ],
                ],
            ],
        ],
        [
            'section' => 'civile',
            'groupe' => null,
            'libelle' => '3e chambre civile',
            'intitule' => 'Affaires familiales et état des personnes',
            'attributions' => null,
            'remarque' => null,
            'formations' => [
                [
                    'nom' => '1re formation',
                    'intitule' => null,
                    'attributions' => 'Divorce et séparation de corps, paternité, maternité, pension alimentaire, contentieux après divorce ou hors mariage, obligation d\'entretien, nom et prénom, liquidation et partage des régimes matrimoniaux, des indivisions conjugales et des successions, administration provisoire et mesures conservatoires, annulation de mariage, changement de régimes matrimoniaux, scellés, contribution aux charges du ménage, autorisation entre époux.',
                    'equipe' => [
                        'president' => ['zeregba'],
                        'president_s' => ['boka'],
                        'assesseur' => ['yougone', 'diako', 'kangah', 'kouao', 'gnabro', 'kone_yvan', 'nango'],
                        'greffier' => ['kouadio_ge', 'bleu', 'soro_ts', 'assamoi', 'zouhou'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        [
                            'Réception des requêtes (divorce, séparation de corps, régimes matrimoniaux, charges du ménage)',
                            1,
                            '14:00',
                            'hebdomadaire',
                            'cabinet_juge',
                            null,
                        ],
                        [
                            'Tentative de conciliation (chambre du conseil)',
                            3,
                            '09:00',
                            'hebdomadaire',
                            'cabinet_juge',
                            null,
                        ],
                        ['Audience publique', 5, '09:00', 'hebdomadaire', 'kambile', null],
                    ],
                ],
                [
                    'nom' => '2e formation',
                    'intitule' => null,
                    'attributions' => 'Hérédité, absence, disparition, adoption, action relative à la filiation, état civil.',
                    'equipe' => [
                        'president' => ['boka'],
                        'president_s' => ['zeregba'],
                        'assesseur' => ['yougone', 'diako', 'kangah', 'kouao', 'gnabro'],
                        'greffier' => ['kouadio_ge', 'bleu', 'assamoi', 'soro_ts', 'zouhou'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience publique', 5, '09:00', 'hebdomadaire', 'kone_mamadou', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'civile',
            'groupe' => null,
            'libelle' => '4e chambre civile',
            'intitule' => 'Tutelles',
            'attributions' => 'Tutelle, puissance paternelle, émancipation, pension alimentaire, administration légale, garde juridique, tous certificats relevant de la compétence du juge des tutelles, procédures d\'urgence en matière de famille et d\'état des personnes.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['cauphy'],
                        'greffier' => ['tape', 'zouhou', 'coulibaly_ms', 'djole_nb'],
                        'representant_spjej' => ['koffi_sj'],
                    ],
                    'chef' => 'tape',
                    'creneaux' => [
                        ['Réception', 4, '09:00', 'hebdomadaire', 'cabinet_juge', 'À partir de 9 heures'],
                        ['Audience', 2, '09:00', 'hebdomadaire', 'cabinet_juge', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'civile',
            'groupe' => null,
            'libelle' => '5e chambre civile',
            'intitule' => 'Urgence',
            'attributions' => null,
            'remarque' => null,
            'formations' => [
                [
                    'nom' => '1re formation',
                    'intitule' => null,
                    'attributions' => 'Référés d\'heure à heure et ordinaires, rétractations d\'ordonnances sur requête (art. 231 du code de procédure civile, commerciale et administrative), difficultés d\'exécution d\'une décision de justice ; litiges relatifs à une mesure d\'exécution forcée ou à une saisie conservatoire (art. 49 de l\'Acte uniforme OHADA), saisies-rémunération et cessions des rémunérations.',
                    'equipe' => [
                        'president' => ['nguessan_ab', 'ndraman'],
                        'president_s' => ['nguessan_bjc', 'kipre'],
                        'greffier' => ['kouadio_ge', 'bleu', 'assamoi', 'soro_ts', 'kedja', 'bah'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience (Présidente du Tribunal)', 5, '09:00', 'hebdomadaire', 'kambile', null],
                        ['Audience (1er Vice-Président)', 3, '09:00', 'hebdomadaire', 'kambile', null],
                    ],
                ],
                [
                    'nom' => '2e formation',
                    'intitule' => null,
                    'attributions' => 'Référés-expulsion et renouvellement des baux, révision des loyers ; baux à usage professionnel et d\'habitation, occupation sans droit ni titre et occupation relevant de la voie de fait, validation de congé avec expulsion lorsque la réalité des motifs légitimes n\'est pas sérieusement contestée.',
                    'equipe' => [
                        'president' => ['kangah', 'kouao', 'nango', 'gnabro'],
                        'greffier' => ['kouadio_ge', 'bleu', 'assamoi', 'soro_ts', 'kedja', 'bah'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience', 2, '09:00', 'hebdomadaire', 'kambile', null],
                    ],
                ],
                [
                    'nom' => '3e formation',
                    'intitule' => null,
                    'attributions' => 'Ordonnances sur requête en matière civile, ordonnances sur requête en matière de voies d\'exécution, ordonnances d\'injonction de payer. Les requêtes sont déposées au greffe (sauf divorce et séparation de corps) qui les transmet au secrétariat du Président ; en cas d\'urgence hors heures ouvrables, elles sont présentées au Président. Les requêtes à bref délai, rétractations, saisies conservatoires, certificats d\'individualité, constats d\'adultère et ouvertures de portes relèvent du Président ou des juges délégués.',
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'juge_delegue' => [
                            'ndraman',
                            'toure_biba',
                            'nguessan_bjc',
                            'kipre',
                            'yougone',
                            'diako',
                            'kangah',
                            'kouao',
                            'kone_yvan',
                            'gnabro',
                            'nango',
                        ],
                        'greffier' => ['nsoua'],
                    ],
                    'chef' => 'nsoua',
                    'creneaux' => [
                        [
                            'Signature des ordonnances (pas d\'audience)',
                            null,
                            null,
                            'a_la_demande',
                            null,
                            'Signature par le Président et les juges délégués',
                        ],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions criminelles',
            'libelle' => 'Tribunal criminel',
            'intitule' => null,
            'attributions' => 'Infractions commises par des individus renvoyés devant lui par l\'arrêt de renvoi de la chambre d\'instruction criminelle.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'president_s' => ['ndraman', 'toure_biba'],
                        'assesseur' => ['nguessan_bjc', 'kipre', 'yougone', 'diako', 'kangah', 'kouao', 'kone_yvan'],
                        'greffier' => [
                            'toure_prisca',
                            'koutou',
                            'coulibaly_dt',
                            'kouadio_ge',
                            'tape',
                            'soro_ts',
                            'bleu',
                            'kouadio_ml',
                            'assamoi',
                            'bah',
                        ],
                    ],
                    'chef' => null,
                    'creneaux' => [
                        [
                            'Session (mardis)',
                            2,
                            null,
                            'trimestrielle',
                            'kone_mamadou',
                            'Tous les trois mois, sauf application de l\'alinéa 2 de l\'article 266 du code de procédure pénale',
                        ],
                        [
                            'Session (jeudis)',
                            4,
                            null,
                            'trimestrielle',
                            'kone_mamadou',
                            'Tous les trois mois, sauf application de l\'alinéa 2 de l\'article 266 du code de procédure pénale',
                        ],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions criminelles',
            'libelle' => 'Tribunal criminel pour mineurs',
            'intitule' => null,
            'attributions' => 'Crimes commis par les mineurs âgés de 16 ans au moins.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['ndraman'],
                        'president_s' => ['toure_biba', 'nguessan_bjc'],
                        'assesseur' => ['kipre', 'yougone', 'diako'],
                        'assesseur_s' => ['kangah', 'kouao', 'gnabro', 'nango'],
                        'greffier' => [
                            'toure_prisca',
                            'koutou',
                            'coulibaly_dt',
                            'kouadio_ge',
                            'tape',
                            'soro_ts',
                            'bleu',
                            'kouadio_ml',
                            'assamoi',
                            'zouhou',
                            'bah',
                        ],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions correctionnelles',
            'libelle' => '1re chambre correctionnelle',
            'intitule' => 'Délits économiques et financiers et délits divers',
            'attributions' => 'Délits économiques et financiers : vols simples et aggravés, recel, escroquerie, abus de confiance, abus de blanc-seing, faux et usage de faux, contrefaçon, fraude commerciale, concurrence déloyale, infractions bancaires, douanières, fiscales et forestières, blanchiment de capitaux, usure, cybercriminalité, code de l\'électricité… Délits divers : attentats aux mœurs, rébellion, délits de presse, diffamation et injures, coups et blessures volontaires, violences, homicides et blessures involontaires, stupéfiants, armes à feu, débits de boisson, contraventions de simple police connexes, réparations dues aux victimes après relaxe.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['boka', 'nguessan_bjc'],
                        'assesseur' => ['yougone', 'kipre', 'diako', 'kangah', 'kouao', 'kone_yvan', 'nango'],
                        'greffier' => ['kouadio_ge'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience', 2, '10:00', 'hebdomadaire', 'kone_mamadou', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions correctionnelles',
            'libelle' => '2e chambre correctionnelle',
            'intitule' => 'Flagrants délits',
            'attributions' => 'Infractions de toute nature, poursuivies suivant la procédure de flagrant délit.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['lathro', 'nguessan_bjc', 'kipre', 'yougone', 'diako'],
                        'assesseur' => ['kangah', 'kouao', 'kone_yvan', 'gnabro', 'nango'],
                        'greffier' => ['kouadio_ge', 'kouadio_ml', 'bah'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        ['Audience (mercredi)', 3, '10:00', 'hebdomadaire', 'kone_mamadou', null],
                        ['Audience (vendredi)', 5, '10:00', 'hebdomadaire', 'kone_mamadou', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions correctionnelles',
            'libelle' => '3e chambre correctionnelle',
            'intitule' => null,
            'attributions' => null,
            'remarque' => null,
            'formations' => [
                [
                    'nom' => 'Formation A',
                    'intitule' => 'Tribunal de simple police',
                    'attributions' => 'Contraventions de toute nature, amende de composition.',
                    'equipe' => [
                        'president' => ['lathro', 'diako'],
                        'president_s' => ['kipre', 'yougone', 'kouao'],
                        'greffier' => ['kouadio_ge', 'kouadio_ml', 'bah'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        [
                            'Audience',
                            3,
                            '09:00',
                            'hebdomadaire',
                            'kone_mamadou',
                            'Avant l\'audience des flagrants délits',
                        ],
                    ],
                ],
                [
                    'nom' => 'Formation B',
                    'intitule' => 'Police de la circulation',
                    'attributions' => 'Jugement des délits en matière de police de la circulation.',
                    'equipe' => [
                        'president' => ['lathro', 'diako'],
                        'president_s' => ['kipre', 'yougone', 'kouao'],
                        'greffier' => ['kouadio_ge', 'kouadio_ml', 'bleu', 'bah'],
                    ],
                    'chef' => 'kouadio_ge',
                    'creneaux' => [
                        [
                            'Audience',
                            3,
                            null,
                            'hebdomadaire',
                            'kone_mamadou',
                            'Après l\'audience du tribunal de simple police',
                        ],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => 'Les juridictions correctionnelles',
            'libelle' => '4e chambre correctionnelle',
            'intitule' => 'Tribunal pour enfants',
            'attributions' => null,
            'remarque' => null,
            'formations' => [
                [
                    'nom' => 'Tribunal pour enfants',
                    'intitule' => null,
                    'attributions' => 'Toutes les affaires relatives à l\'enfance délinquante.',
                    'equipe' => [
                        'president' => ['cauphy'],
                        'assesseur_spjej' => ['koffie', 'zohore'],
                        'assesseur_spjej_s' => ['guei', 'kouakou_ap', 'angbandji', 'attoungbre'],
                        'greffier' => ['tape', 'zouhou'],
                        'greffier_s' => ['coulibaly_ms', 'djole_nb'],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
                [
                    'nom' => 'Juge des enfants',
                    'intitule' => 'Juridiction unique',
                    'attributions' => 'Juge des enfants, juridiction unique.',
                    'equipe' => [
                        'president' => ['cauphy'],
                        'greffier' => ['tape', 'zouhou'],
                        'greffier_s' => ['coulibaly_ms', 'djole_nb'],
                    ],
                    'chef' => null,
                    'creneaux' => [
                        ['Audience', 4, null, 'hebdomadaire', 'cabinet_juge', null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => null,
            'libelle' => 'Juridiction présidentielle',
            'intitule' => null,
            'attributions' => 'Procédures correctionnelles à juge unique : homologation des procès-verbaux de transaction (art. 17 du code de procédure pénale) et des peines proposées dans le cadre de la comparution sur reconnaissance préalable de culpabilité (art. 521 et suivants).',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'president_s' => ['ndraman', 'toure_biba'],
                        'juge_delegue' => ['nguessan_bjc', 'kipre', 'kone_yvan'],
                        'greffier' => ['toure_prisca'],
                    ],
                    'chef' => null,
                    'creneaux' => [
                        ['Audience', null, null, 'a_la_demande', null, null],
                    ],
                ],
            ],
        ],
        [
            'section' => 'penale',
            'groupe' => null,
            'libelle' => 'Juridiction de l\'application des peines',
            'intitule' => null,
            'attributions' => 'Juge de l\'application des peines.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'juge' => ['kipre'],
                        'greffier' => ['djole_ng'],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
            ],
        ],
        [
            'section' => 'instruction',
            'groupe' => null,
            'libelle' => '1er cabinet d\'instruction',
            'intitule' => null,
            'attributions' => 'Instruction des affaires pénales (adultes).',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'juge' => ['kone_wm'],
                        'greffier' => ['coulibaly_ms'],
                        'greffier_s' => ['tape', 'djole_nb'],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
            ],
        ],
        [
            'section' => 'instruction',
            'groupe' => null,
            'libelle' => '2e cabinet d\'instruction',
            'intitule' => null,
            'attributions' => 'Instruction des affaires pénales (adultes).',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => null,
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'juge' => ['konan'],
                        'greffier' => ['djole_nb'],
                        'greffier_s' => ['tape', 'coulibaly_ms'],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
            ],
        ],
        [
            'section' => 'instruction',
            'groupe' => null,
            'libelle' => 'Cabinet du juge des enfants',
            'intitule' => null,
            'attributions' => 'Instruction des affaires concernant les mineurs.',
            'remarque' => null,
            'formations' => [
                [
                    'nom' => '3e cabinet',
                    'intitule' => null,
                    'attributions' => null,
                    'equipe' => [
                        'juge' => ['cauphy'],
                        'greffier' => ['tape'],
                        'greffier_s' => ['coulibaly_ms', 'djole_nb'],
                    ],
                    'chef' => null,
                    'creneaux' => [],
                ],
            ],
        ],
        [
            'section' => 'sociale',
            'groupe' => null,
            'libelle' => 'Section sociale',
            'intitule' => null,
            'attributions' => null,
            'remarque' => null,
            'formations' => [
                [
                    'nom' => '1re formation',
                    'intitule' => 'Affaires présidentielles',
                    'attributions' => 'Toutes instances en matière sociale dont l\'intérêt pécuniaire excède 100 000 000 FCFA. Attributions propres au Président du Tribunal du Travail : référé social, visa des requêtes, signature d\'ordonnances (taxe d\'assurances, contraintes CNPS…).',
                    'equipe' => [
                        'president' => ['nguessan_ab'],
                        'president_s' => ['toure_biba'],
                        'assesseur_employeur' => ['djakaridja', 'dape', 'zoundi'],
                        'assesseur_employeur_s' => ['mylonoyannis', 'macguil', 'yapi'],
                        'assesseur_travailleur' => ['achy', 'aka', 'bouali'],
                        'assesseur_travailleur_s' => ['koffi_yi', 'kaneu', 'ndepo'],
                        'greffier' => ['nsoua', 'kedja'],
                    ],
                    'chef' => 'nsoua',
                    'creneaux' => [
                        ['Tentative de conciliation', 3, '11:00', 'hebdomadaire', 'salle_reunion', null],
                        [
                            'Audience publique ordinaire',
                            3,
                            '14:30',
                            'hebdomadaire',
                            'kambile',
                            'Avant l\'audience de la deuxième formation (s\'il y a des dossiers)',
                        ],
                        ['Audience de référé', 4, '11:00', 'hebdomadaire', 'cabinet_president', null],
                    ],
                ],
                [
                    'nom' => '2e formation',
                    'intitule' => 'Contentieux social ordinaire',
                    'attributions' => 'Différends individuels à l\'occasion du contrat de travail ou d\'apprentissage, y compris accidents du travail et maladies professionnelles ; différends relatifs à la validité et à l\'exécution des conventions collectives ; litiges entre travailleurs ou apprentis.',
                    'equipe' => [
                        'president' => ['toure_biba', 'yougone', 'kangah', 'kone_yvan'],
                        'assesseur_employeur' => ['dape'],
                        'assesseur_employeur_s' => ['mylonoyannis', 'macguil', 'zoundi'],
                        'assesseur_travailleur' => ['bouali'],
                        'assesseur_travailleur_s' => ['aka', 'koffi_yi', 'koffi_yh', 'kaneu'],
                        'greffier' => ['nsoua', 'kedja'],
                    ],
                    'chef' => 'nsoua',
                    'creneaux' => [
                        ['Tentative de conciliation', 3, '11:00', 'hebdomadaire', 'salle_reunion', null],
                        ['Audience publique', 3, '14:30', 'hebdomadaire', 'kambile', null],
                    ],
                ],
            ],
        ],
    ],

    /*
     | Services, comités, délégations et fonctions (ordonnance, art. 2 « Section administration générale » et art. 3 à 6).
     | [catégorie, libellé, [[clé d'agent, fonction ou null], …], remarque ou null]
     */
    'services' => [
        // Coordination, contrôle et supervision
        ['coordination', 'Coordonnateur général', [['nguessan_ab', 'Président du Tribunal']], null],
        ['coordination', 'Coordonnateurs généraux adjoints', [['ndraman', '1er Vice-Président'], ['toure_biba', '2e Vice-Président']], null],
        ['coordination', 'Coordonnateur du greffe', [['toure_prisca', null]], null],
        ['coordination', 'Coordonnateurs adjoints du greffe', [['koutou', '1er greffier en chef adjoint'], ['coulibaly_dt', '2e greffier en chef adjoint']], null],

        // Comités et commissions
        ['comite', 'Comité de direction', [['nguessan_ab', 'Responsable du comité'], ['nguessan_bjc', 'Secrétaire général'], ['diako', 'Secrétaire général adjoint']], null],
        ['comite', "Comité chargé des statistiques et de l'évaluation", [['nguessan_ab', 'Responsable']], null],
        ['comite', "Comité de pilotage de l'informatisation", [['nguessan_ab', 'Responsable']], null],

        // Délégations (membres du comité de direction)
        ['delegation', 'Délégué aux rapports avec les Avocats et les Auxiliaires de Justice', [['ndraman', null]], null],
        ['delegation', 'Délégué à la veille juridique et stratégique', [['nguessan_bjc', null]], null],
        ['delegation', "Délégués aux manifestations professionnelles, sportives et culturelles, à la vie sociale, à l'intégration des nouveaux juges, aux prestations de serment et aux installations", [['kone_wm', null], ['konan', null]], null],
        ['delegation', "Délégué à l'éthique et à la déontologie", [['ndraman', null]], null],
        ['delegation', 'Délégué à la formation et à la documentation', [['kipre', null]], null],
        ['delegation', 'Délégués à la communication interne', [['yougone', null], ['kone_yvan', null]], null],
        ['delegation', "Délégué au suivi des expertises et autres mesures d'instruction en matière civile", [['nguessan_bjc', null]], null],
        ['delegation', 'Délégué à la santé et à la sécurité', [['konan', null]], null],

        // Services annexes
        ['service', 'Service Statistiques', [['coulibaly_dt', '2e greffier en chef adjoint']], null],
        ['service', 'Service Enrôlement', [['coulibaly_em', null]], 'Sauf en matière pénale.'],
        ['service', 'Service des recours civils', [['soro_kl', null]], null],
        ['service', 'Service des recours correctionnels', [['soro_kl', null]], null],
        ['service', 'Service des certificats de nationalité ivoirienne', [['koutou', '1er greffier en chef adjoint']], null],
        ['service', 'Service des actes du greffe', [['koutou', null]], null],
        ['service', 'Service du casier judiciaire', [['koutou', null]], null],
        ['service', 'Service social (SPJEJ)', [['koffi_sj', null]], null],

        // Fonctions particulières
        ['fonction', 'Juge taxateur en matière civile, commerciale et administrative', [['ndraman', '1er Vice-Président']], null],
        ['fonction', 'Juge taxateur en matière correctionnelle', [['toure_biba', '2e Vice-Président']], null],
        ['fonction', 'Testaments : authentification, procès-verbaux de dépôt, ouverture (art. 4)', [['zeregba', 'Juge délégué'], ['boka', 'Juge déléguée'], ['coulibaly_dt', 'Greffier chef adjoint, assiste les magistrats']], 'Compétence du Président du Tribunal et des juges délégués. Les audiences sont tenues en fonction des nécessités, aux jours et heures convenus par le juge et les parties.'],
        ['fonction', 'Signature des certificats de nationalité ivoirienne (art. 5)', [], 'Compétence du Président du Tribunal et de tous les magistrats du siège.'],
        ['fonction', "Signature des procurations spéciales et des actes d'individualité (art. 6)", [['nguessan_bjc', 'Juge délégué'], ['kipre', 'Juge délégué'], ['yougone', 'Juge déléguée'], ['diako', 'Juge déléguée']], "Compétence du Président du Tribunal, des Vice-Présidents et des magistrats délégués."],

        // Bureaux locaux
        ['bureau', "Bureau local de l'assistance judiciaire", [
            ['nguessan_ab', 'Président de la juridiction'], ['kipre', 'Juge délégué'], ['koffi_sj', 'Représentant du service social'], ['kouadio_ge', 'Secrétaire du bureau local'],
        ], "Autres membres (extérieurs au tribunal) : représentant du trésorier de Bingerville, Mme TIENDE Jocelyne ; commissaire de justice désigné, Maître KABORE Issa ; avocat désigné, Maître OUPOH Somala Carine Prudence."],
        ['bureau', "Bureau local de suivi du travail d'intérêt général (BLS)", [
            ['nguessan_ab', "Présidente du Tribunal (en cas d'empêchement, le 1er Vice-Président N'DRAMAN Kablan Fidèle Amilcar)"],
        ], "Autres membres : Mme OUATTARA Moussoh Kandana, assistante du sous-préfet de Bingerville, représentant le sous-préfet ; M. AMARA Amadou, commandant, administrateur des services pénitentiaires, délégué du Régisseur du Pôle Pénal et Pénitentiaire d'Abidjan ; Mme YAVO Yahoubié Rosine, assistante sociale au centre social de Cocody centre ; M. KOUADIO Baba Alexandre, conseiller d'éducation permanente au SPJEJ. Mission : organe consultatif qui assiste le juge de l'application des peines (et le juge des enfants pour les mineurs) dans l'exécution des peines de travail d'intérêt général ; se réunit en cas de besoin."],
    ],

    /*
     | Éléments de l'ordonnance qui seront chargés avec le calendrier (étape D6) ; conservés ici pour mémoire.
     */
    'a_charger_plus_tard' => [
        'fin_d_enrolement' => [
            // Article 7 : enrôlement des chambres civiles, commerciales et administratives, sauf dérogation du Président ; référés et voies d'exécution exclus.
            'lundi' => ['mercredi précédent', '16:00'],
            'mardi' => ['jeudi précédent', '16:00'],
            'mercredi' => ['vendredi précédent', '16:00'],
            'jeudi' => ['lundi précédent', '16:00'],
            'vendredi' => ['mardi précédent', '16:00'],
        ],
        'delais_de_procedure' => [
            // Notes manuscrites en marge de la page 8 de l'ordonnance (à confirmer).
            'civil' => '6 mois', 'correctionnel' => '3 mois', 'refere_expulsion' => '15 jours, avec un seul renvoi',
        ],
        'regles_generales' => [
            'Art. 8' => 'Les juges peuvent, en cas de nécessité, siéger dans des chambres autres que celles où ils sont affectés.',
            'Art. 9' => 'Le Président peut présider toute chambre et demander le renvoi à la première chambre de toutes les affaires pendantes devant les autres formations.',
            'Art. 10' => 'La représentation du tribunal et la communication externe sont assurées par le Président et, en cas d\'empêchement, par les Vice-Présidents.',
            'Art. 11' => 'L\'ordonnance prend effet à la date de sa signature (12 octobre 2026) ; elle peut être modifiée pour tenir compte des nécessités du service.',
        ],
    ],

    /*
     | Variantes d'orthographe relevées dans la source (le scan n'est pas homogène) ; la forme retenue est la plus fréquente.
     */
    'notes' => [
        'NANGO : « Kouakou Affoua Angèle Vanessa » (plusieurs pages) ; « Larissa » à la page 12.',
        'KOUADIO / KOUADJO Marie-Laurette : même personne (pages 5, 14, 17, 18, 19).',
        'ZOUHOU Joniack / Joniak Alix ; BLEU Madé Yves Julien / Julie ; COULIBALY Dramane Thomas / « COULIALY » (page 14).',
        'La 5e chambre civile figure dans la section civile (article 2) alors que l\'article premier cite aussi une « section procédure d\'urgence » : celle-ci est créée sans formation.',
        'Section sociale, 2e formation : les assesseurs suppléants ZOUNDI (employeur) et AKA (travailleur) sont rattachés d\'après leur catégorie de la 1re formation.',
        'Les dates de début et de fin de l\'année judiciaire ne figurent pas dans l\'ordonnance : à confirmer.',
    ],
];
