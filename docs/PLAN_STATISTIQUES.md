# Module Statistiques (lots N et Q) : réalisé le 5 novembre 2026

**État : statistiques civiles et pénales construites** (pages dynamiques, exports Excel et PDF au modèle des classeurs, droits, tests). Restent : instruction et enfants (lot R) et services annexes (lot S), qui s'ajouteront à ce module.
Pages : Statistiques > Statistiques civiles (`/statistiques/civil`) et Statistiques pénales (`/statistiques/penal`). Jeu d'essai : `php artisan statistiques:demo` (retrait : `--retirer`).

# Plan initial

Sources lues : les 23 classeurs Excel du dossier TPI, plus les exigences STA-01 à STA-12 du cahier des charges.
Principe du cahier : **aucune ressaisie** (STA-01). Les chiffres sortent des affaires, audiences et décisions déjà saisies.

## 1. Ce que disent les classeurs

| Famille | Classeurs | Contenu statistique | Lot |
|---|---|---|---|
| **Civil** | Audience civile présidentielle, référé (ordinaire, expulsion, voies d'exécution), matrimoniale, état des personnes, civile diverse, hérédité, tutelle | Onglets « RECAP ENROL. CIV PAR AN » et « PAR MOIS » : stock en début, affaires nouvelles, à juger, vidées, stock en fin, **par catégorie** : civile ordinaire, état civil, matrimoniale, hérédité, référé, juge de l'exécution, commerciale, administrative, saisie-rémunération, autre. Sous-états : **état civil** (JSN, JSD, modification de nom ou prénom, reprise d'identité, rectification, reconnaissance de paternité) et **matrimonial** (divorce pour faute, DCM, séparation de corps, adoption simple ou plénière), chacun en reçues, jugées, vidées, **accordées, rejetées**. | N, Q |
| **Mise en état** | Audience mise en état | Même structure, avec une catégorie « Sociale » en plus. | N |
| **Pénal** | Flagrants délits, délits divers, délits économiques, police de la circulation, simple police, tribunal pour enfants | Une ligne par décision : n° RP, prévenus, 1re audience, décision, caractère, peines, réparations, **infraction retenue (liste de 38)**, traite des personnes, nature (crime, délit, contravention), condamnés (hommes, femmes, nationalités, professions), victimes (hommes, femmes, garçons, filles, nationalités, professions), signature, **transmission de la minute**, durées. | Q |
| **Instruction** | Cabinets JI 1 et 2, enfants | Affaires, inculpés (traite, sexe, âge, majeur ou mineur, statut), victimes, commissions rogatoires reçues et émises, mensuelles. | R |
| **Services annexes** | Juge de l'application des peines, assistance judiciaire, personnel magistrat | Activités mensuelles, demandes accordées ou rejetées, effectif par sexe et par an. | S |

Les classeurs sont les **gabarits à reproduire** pour la hiérarchie (STA-10). Presque toutes les valeurs y sont à zéro : ce sont des modèles vides.

## 2. Ce qu'on a déjà
Tableau de bord `/accueil` et « Analyse et pilotage » : stock, entrées, sorties, délai moyen, renvois, détenus proches de l'échéance, alertes. Il manque : le découpage par **catégorie officielle**, le **stock en début d'année**, les **sorties par type**, les exports au gabarit.

## 3. Proposition

### Étape 1 : socle des données (STA-01, STA-08)
- **Catégorie statistique** d'une affaire : table de correspondance entre nature d'affaire, objet du litige et catégories des classeurs (hérédité, état civil, sociale…). Visible et modifiable dans une page de réglage.
- **Issue d'une affaire civile** saisie une fois, à la feuille d'audience ou à la décision : au fond, radiation, désistement, jonction… ; caractère (contradictoire, défaut) ; **accordée ou rejetée** ; pour l'adoption, nombre de filles et de garçons.
- **Stock d'ouverture** par catégorie et par année pour la reprise (le classeur prévoit « reçu avant 2020 », « vidé avant 2020 »).
- Durées fiables avec dates manquantes signalées : 1re audience à décision, décision à minute.

### Étape 2 : statistiques civiles (STA-02, 03, 05, 07)
Page **Statistiques > Civil** : stock début, entrées, sorties, stock fin (début + entrées − sorties) par catégorie, par mois et par année. Sous-pages **État civil et matrimonial** (dont adoption) avec accordées et rejetées. Mise en état. Filtres période, chambre, magistrat, nature (STA-09), comparaison d'une période à l'autre. Ancienneté du stock, audiences du jour, délibérés en retard.

### Étape 3 : pénal (STA-06)
Compléter le **répertoire pénal** : infraction retenue (liste de 38 du classeur), traite des personnes, nature, victimes (sexe, mineur), nationalité et profession des condamnés. Page **Statistiques > Pénal** : condamnés par sexe, nationalité, profession, infractions, peines, victimes, traite, violences basées sur le genre, durées et délai de transmission de la minute.

### Étape 4 : exports et accès restreint (STA-04, 09, 10)
- Export **Excel au gabarit des classeurs** (onglets RECAP par an et par mois, états civil et matrimonial, pénal) et export PDF.
- Droit `stats.agrege` : chiffres seulement, aucun nom ni numéro de dossier, pour les profils non habilités au détail.
- Contrôle : chaque total doit pouvoir être **recoupé** avec la liste des affaires qui le composent.

### Hors de ce module (plus tard)
Instruction et enfants (STA-11, lot R), juge de l'application des peines, assistance judiciaire, personnel magistrat (STA-12, lot S).

## 4. Questions à trancher avant de commencer
1. **Catégories** : retenir les 10 des classeurs (civile ordinaire, état civil, matrimoniale, hérédité, référé, juge de l'exécution, commerciale, administrative, saisie-rémunération, autre) plus « Sociale » en mise en état ? Le référé comprend-il ordinaire et expulsion ?
2. **Sorties** : une affaire est « vidée » par quoi ? Décision au fond, radiation, désistement, jonction, dessaisissement ou renvoi à une autre chambre ?
3. **Dates de référence** : entrée = date d'enrôlement (RG) ; sortie = date de la décision. D'accord ?
4. **Historique** : reprendre les chiffres depuis 2020 (stock d'ouverture saisi à la main, ou import des anciens classeurs) ?
5. **Pénal** : ajouter maintenant les champs manquants au répertoire (infraction retenue, traite, victimes, nationalités, professions), ou seulement les statistiques de ce qui existe ?
6. **Gabarit** : les classeurs du dossier sont-ils bien le gabarit exact attendu par la hiérarchie pour l'export Excel ?
7. **Ordre** : étapes 1 et 2 d'abord, puis pénal, puis exports ?

## 5. Décisions retenues : déduites des formules des classeurs du TPI
Le point focal n'a pas à répondre : les classeurs portent déjà les règles du tribunal (lues dans leurs formules). On les reprend telles quelles.

| Question | Règle du TPI (formule du classeur) |
|---|---|
| Catégories | Les 10 listes déroulantes du classeur : civile ordinaire, état civil, matrimoniale, hérédité, référé, juge de l'exécution, commerciale, administrative, saisie-rémunération, autre (« sociale » en plus pour la mise en état) |
| Affaire nouvelle de l'année N | Affaire dont **l'année de la 1re audience** est N (et non la date d'enrôlement) |
| Affaire vidée de l'année N | Affaire dont **l'année de la décision** est N |
| Issues qui vident | Liste du classeur : **radiation, décision au fond, PV de conciliation totale, désistement** |
| Stock fin N | (stock début N + nouvelles N) − vidées N ; stock début N = stock fin N−1 |
| À juger N | (à juger N−1 − vidées N−1) + nouvelles N |
| Historique | Colonnes « reçu avant 2020 » et « vidé avant 2020 » : le stock d'ouverture vient des affaires antérieures à la date de départ |
| Accordée ou rejetée | Colonne « Accord ou Rejet » de la décision au fond, par année de décision |
| État civil | JSN, JSD, modification de nom, modification de prénom, reprise d'identité, rectification judiciaire, reconnaissance de paternité, autre |
| Matrimonial | Divorce pour faute, DCM, séparation de corps, adoption simple, adoption plénière, autre |
| Durée | Date de décision − date de la 1re audience (jours) |
| Gabarit d'export | Les classeurs eux-mêmes |

Conséquence pour l'application : l'issue d'une affaire civile (radiation, décision au fond, PV de conciliation totale, désistement) et accord ou rejet se saisissent à la feuille d'audience ; la sous-catégorie état civil ou matrimonial se déduit de l'objet du litige.

## 6. Ordre avec les lots R et S
Les statistiques civiles et pénales d'abord (priorité essentielle P1 et P2 du cahier). Les statistiques de l'instruction (STA-11) et des services annexes (STA-12) s'ajoutent ensuite, à la fin des lots R et S, car leurs données n'existent pas encore.

## 7. Registres au modèle du tribunal (5 novembre 2026)
Relecture des classeurs : chacun est un **tableau de saisie** (colonnes saisies et colonnes calculées par formule) suivi d'onglets « RECAP » protégés qui se calculent à partir du tableau. L'application remplit donc **les classeurs eux-mêmes** : page Statistiques > Registres du tribunal, un bouton par classeur.
- Modèles : `resources/modeles_excel/` (les classeurs d'origine réduits à l'en-tête et à une ligne type ; script `tools/preparer_modeles_excel.py`).
- Remplissage : `App\Support\Statistiques\ModeleTribunal` (écrit les lignes, recopie styles et formules, Excel recalcule à l'ouverture) et `RegistresTribunal` (colonnes, lettre par lettre, et répartition des affaires entre registres).
- 16 registres : audience civile présidentielle, référé et voies d'exécution, référé-expulsion, référé ordinaire, matrimoniale, état des personnes, civile diverse, hérédité, tutelle, mise en état, et six registres pénaux.
- Colonnes saisies en plus à la page « À compléter » : caractère de la décision, statut des adoptants ivoirien et étranger, observations.
- Reste à faire avec les lots R et S : instruction (cabinets JI et JE), assistance judiciaire, application des peines, personnel magistrat.
- **Personnel magistrat (STA-12)** : 17e registre, classeur « RH Magistrat » rempli avec les magistrats du siège (matricule, nom, fonction, genre, service) dans la feuille de l'année en cours. Dates, grade et notation à compléter à la main (non tenus par l'application).
- **Reste, qui demande de nouvelles pages de saisie** : instruction JI et JE (dates de réquisitoire, ordonnances de clôture, renouvellements de détention, victimes, commissions rogatoires : lot R), assistance judiciaire et activités du juge de l'application des peines (lot S). Leurs classeurs sont prêts à servir de modèle d'export.

