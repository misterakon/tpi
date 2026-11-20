<?php

/*
 | Articles de l'ordonnance n°600/2026 (texte tel qu'il figure dans le scan) et membres extérieurs des bureaux locaux.
 | « rattache_a » : la feuille du classeur Excel que l'article pilote (voir docs/FORMAT_EXCEL_ORDONNANCE.md) ; null = texte seul.
 | Chargés par OrdonnanceBingerville2026Seeder.
 */
return [

    'articles' => [
        ['Article premier', 'Les sections', "Le siège du Tribunal de Première Instance de Bingerville pour l'année judiciaire 2026-2027, sera divisé en six (06) sections :\n- SECTION CIVILE, COMMERCIALE ET ADMINISTRATIVE\n- SECTION PROCEDURE D'URGENCE\n- SECTION PENALE\n- SECTION INSTRUCTION\n- SECTION SOCIALE\n- SECTION ADMINISTRATION GENERALE", 'sections'],

        ['Article deuxième', 'Organisation des sections', "Le service intérieur des sections est organisé comme suit :\n(La composition de chaque chambre et de chaque formation, leurs attributions et leurs audiences figurent dans les feuilles Chambres, Formations, Composition et Audiences.)", 'composition'],

        ['Article 3', 'Service social et juges taxateurs', "Sont chargés d'exercer\nService social (SPJEJ) : M. KOFFI Salia\nLes fonctions de :\n- Juge taxateur en matière civile, commerciale et administrative : M. N'DRAMAN Kablan Fidèle Amilcar, 1er Vice-Président\n- Juge taxateur en matière correctionnelle : Mme TOURE Biba épouse OLAYE, 2ème Vice-Président", 'services'],

        ['Article 4', 'Testaments', "L'authentification des testaments par acte public, les procès-verbaux constatant le dépôt d'un testament mystique reçu par les notaires, l'ouverture des testaments et toutes les autres formalités auxquelles sont assujettis les testaments sont de la compétence du Président du Tribunal et des Juges délégués ci-après : Mmes ZEREGBA Adeline épouse RABE et BOKA Carine.\nLes Magistrats seront assistés dans leurs tâches par Me COULIBALY Dramane Thomas, Greffier chef adjoint ;\nLes audiences seront tenues en fonction des nécessités, aux jours et heures convenus par le Juge et les parties.", 'services'],

        ['Article 5', 'Certificats de nationalité ivoirienne', "La signature des certificats de nationalité ivoirienne est de la compétence du Président du Tribunal et de tous les Magistrats du siège.", 'services'],

        ['Article 6', 'Procurations et actes d\'individualité', "La signature des procurations spéciales et des actes d'individualité est de la compétence du Président du Tribunal, des Vice-Présidents et des Magistrats délégués ci-après :\nM. N'GUESSAN Bodo Joan Cyrille\nM. KIPRE Aubin Stéphane\nMme YOUGONE Lou Nazié Ruth Urchulla épouse KOUACOU\nMme DIAKO Myriam épouse KOUAKOU", 'services'],

        ['Article 7', "Fin de l'enregistrement des dossiers", "La fin de l'enrôlement des dossiers devant les chambres civiles, commerciales administratives est, sauf dérogation accordée par le Président du Tribunal, impérativement fixée ainsi qu'il suit :\n- pour l'audience du lundi, le mercredi précédant l'audience à 16 heures ;\n- pour l'audience du mardi, le jeudi précédant l'audience à 16 heures ;\n- pour l'audience du mercredi, le vendredi précédant l'audience à 16 heures ;\n- pour l'audience du jeudi, le lundi précédant l'audience à 16 heures ;\n- pour l'audience du vendredi, le mardi précédant l'audience à 16 heures ;\nLes procédures des référés et des voies d'exécution ne sont pas concernées par ces délais.", 'dates_limites'],

        ['Article 8', 'Juges hors de leur chambre', "Les juges peuvent, en cas de nécessité, être appelés à siéger dans les chambres autres que celles dans lesquelles ils sont affectés dans la présente ordonnance.", null],

        ['Article 9', 'Le Président préside toute chambre', "Le Président du Tribunal a la faculté de présider toute chambre de la Juridiction, chaque fois qu'il le jugera nécessaire. En outre, le Président du Tribunal peut demander le renvoi à la première chambre de toutes les affaires pendantes devant les autres formations de jugement.", null],

        ['Article 10', 'Représentation du tribunal', "La représentation du Tribunal de Première Instance de Bingerville et la communication externe sont assurées par le Président du Tribunal et, en cas d'empêchement, par les Vice-Présidents.", null],

        ['Article 11', 'Prise d\'effet', "La présente ordonnance prend effet à compter de sa date de signature. Toutefois, elle pourra, en cas de besoin, être modifiée, pour tenir compte des nécessités du service.\nDisons qu'il nous en sera référé en cas de difficulté sur simple requête verbale ou écrite.", null],
    ],

    /* libellé du service => [[nom, qualité, institution], ...] */
    'membres_exterieurs' => [
        "Bureau local de l'assistance judiciaire" => [
            ['Mme TIENDE Jocelyne', 'Représentant du trésorier de Bingerville', 'Trésor de Bingerville'],
            ['Maître KABORE Issa', 'Commissaire de justice désigné', null],
            ['Maître OUPOH Somala Carine Prudence', 'Avocat désigné', null],
        ],
        "Bureau local de suivi du travail d'intérêt général (BLS)" => [
            ['Mme OUATTARA Moussoh Kandana', 'Assistante du sous-préfet de Bingerville, représentant le sous-préfet', 'Sous-préfecture de Bingerville'],
            ['M. AMARA Amadou', 'Commandant, administrateur des services pénitentiaires, délégué du Régisseur du Pôle Pénal et Pénitentiaire d\'Abidjan', 'Pôle Pénal et Pénitentiaire d\'Abidjan'],
            ['Mme YAVO Yahoubié Rosine', 'Assistante sociale au centre social de Cocody centre', 'Centre social de Cocody centre'],
            ['M. KOUADIO Baba Alexandre', 'Conseiller d\'éducation permanente au SPJEJ', 'SPJEJ'],
        ],
    ],
];
