# Plan des lots du projet TPI Bingerville

Document de référence du travail restant, à relire au début de chaque session.
Dernière mise à jour : 1er novembre 2026. Sources : cahier des charges v1.1 (exigences PAR, ENR, DOS, ROL, AUD, DEC, CLO, NUM, STA, PEN, INS, AUT, REP, SEC et phases P1 à P4), ordonnance n°600/2026, décisions du client en conversation.

Règles de travail demandées par le client : après chaque tâche, dire ce qui a été fait, les fichiers modifiés et les commandes lancées ; une page = ses propres fichiers (pas d'écran générique) ; `@can` et `@hasanyrole` dans les vues ; listes déroulantes avec recherche (Select2) ; tableaux sans défilement horizontal ; couleurs orange, blanc, vert ; police Inter ; application très sécurisée ; hébergement en dernier.

Statuts : **Fait** · **À faire** · **En attente** (dépend du client ou de la Présidente).

---

## 1. Lots A à F (socle, organisation, référentiels, outils)

| Lot | Contenu | Statut |
|---|---|---|
| **A** Socle technique | Projet Laravel 13, base MySQL `db_tpi`, tests (280), réglages SMTP dans l'application | Fait |
| | Intégration continue | **Fait (nov. 2026)** : `.github/workflows/tests.yml` lance tous les tests à chaque modification poussée ou demande de fusion |
| | Jeu de données fictives (le cahier impose des données fictives pour maquettes, tests, formations, démonstrations) | **Fait (nov. 2026)** : guide `docs/DONNEES_FICTIVES.md` ; 36 comptes de test (`database/sql/comptes_test.sql`, mot de passe `Tpi@2026`) ; `php artisan affaires:demo` ; `php artisan tpi:codes-test` |
| **B** Comptes et droits | Connexion, mot de passe 8 caractères minimum, verrouillage après échecs, déconnexion et écran de verrouillage après inactivité, double authentification par code e-mail pour tous | Fait |
| | Profils illimités créés dans l'application, droits par menu, page et niveau, droits spéciaux, administrateur principal non supprimable | Fait |
| | **Droits par chambre et par type d'affaire** (SEC-02) | **Fait (nov. 2026)** : visibilité par chambre d'affectation (le greffier voit toute sa chambre) + familles d'affaires protégées : **mineurs** et **instruction** exigent un droit spécial (`affaires.famille.mineurs`, `affaires.famille.instruction`), même pour qui voit toutes les chambres ; civil et pénal restent ouverts à qui a accès à la chambre |
| | Codes de secours | **Fait (nov. 2026)** : 8 codes à usage unique par utilisateur (empreintes seulement), générés dans « Mon compte » (mot de passe redemandé), saisis sur la page du code par e-mail ; l'administrateur peut les révoquer (jamais les voir) ; événements au journal de connexion |
| **C** Journaux | Journal d'audit non modifiable, journal de connexion, filtres, pagination, export | Fait |
| | Alertes sur anomalies de connexion ; durée de conservation à fixer ; journaliser les consultations de dossiers | **Fait (nov. 2026)** : alertes aux administrateurs (cloche) pour 5 échecs en 15 min, tentatives sur comptes inconnus, adresse jamais vue, connexion de nuit (20 h–6 h), code de secours ; ouverture d'une affaire inscrite au journal d'audit (« Affaire consultée », une ligne par quart d'heure). Conservation : les journaux sont inviolables (déclencheurs en base), donc **sans purge automatique** ; durée minimale 5 ans, archivage éventuel hors application |
| **D** Organisation | Année judiciaire, salles, personnel, sections, chambres, formations, attributions, composition datée, services | Fait |
| | Calendrier : jours et heures d'audience avec historique, audiences supplémentaires, jours fériés et vacances, dates limites d'enrôlement (art. 7), reconduction d'année | Fait |
| | Archive des ordonnances signées (lecture dans l'application, téléchargement), éditeur de texte riche | Fait |
| | Comptes reliés aux agents, page « Mon compte » | Fait |
| **E** Référentiels | Pages maintenues : types d'audience, modes de saisine, matières, natures d'affaire, délais de procédure (par étape), objets du litige | Fait, formulaires validés |
| | Listes gardées en base sans page, pour les formulaires futurs : caractères de décision, issues d'audience, modes de comparution, qualités procédurales, natures de partie, textes de loi | Fait |
| | **Tiers réutilisables : avocats et cabinets, commissaires de justice, institutions, personnes morales** (PAR-09) | À faire |
| **F** Outils de génération | Moteur de rédaction `app/Support/Redaction/` : nombres, montants, heures et dates en lettres, accords de genre et de nombre, gabarits à variables et blocs, variables manquantes signalées | Fait |
| | Réglages d'impression des actes : Centaur 16 (corps), timbre 12, modifiables | Fait |
| | Pas de page dédiée : le moteur sera appelé par la page Rédaction (page « Atelier » retirée sur demande) | Décidé |

---

## 2. Lots à construire

### Phase 1 du cahier : socle et civil

| Lot | Contenu | Exigences | Dépend de |
|---|---|---|---|
| **G** Compléments du socle | Droits par chambre et type d'affaire, codes de secours, anomalies de connexion, tiers (avocats et institutions ; **les commissaires de justice n'interviennent pas dans l'application**, information du client, nov. 2026), données fictives, intégration continue | SEC-01, SEC-02, PAR-09 | Rien |
| **H** Enrôlement et numérotation | Numéros RG sans doublon ni trou en saisie simultanée, non modifiables, jamais réutilisés. Parties (homme, femme, couple, personne morale, État). Avocat par partie. **Objet du litige saisi librement, rattaché par mots-clés** (voir § 3). Nature d'affaire en liste fermée. Chambre proposée, réattribution par la Présidente. Contrôle de la date limite d'enrôlement avec dérogation tracée. Alerte de doublon. Jonction des causes | ENR-01 à 09, NUM-01, NUM-03, PAR-11 | G ; format du numéro à confirmer |
| **I** Dossier numérique | Pièces PDF ou images typées et horodatées, consultation journalisée, recherche multicritère, localisation du dossier physique, archivage à la clôture | DOS-01 à 05 | H |
| **J** Rôles d'audience | Rôle généré (délibérés, affaires anciennes, affaires nouvelles) sans ressaisie, en-tête automatique, cas particuliers (jonction, « et x autres »), export Word et PDF, versions, planning par salle avec détection des conflits | ROL-01 à 10 | H et D ; modèles de rôle de la greffière en chef | **(fait : rôle composé tout seul, éditeur « feuille », publication avec versions, Word et PDF fidèles aux modèles, planning par salle avec conflits ; reste : rôles pénaux (lot O), planning détaillé des salles hors audience)**
| **K** Audiences, renvois, délibérés | Feuille d'audience (composition pré-remplie, issues : renvoi avec motif, délibéré, décision, radiation, jonction, désistement), report automatique des renvois, délibérés et rappels, tableau de bord par magistrat et par chambre | AUD-01 à 06 | J | **(fait nov. 2026 : feuille d'audience dynamique sans rechargement (composition avec empêchements et remplaçants, issue de chaque affaire avec motif, date de renvoi proposée d'après le calendrier de la chambre, délibéré avec date, présence des parties, historique des passages), clôture et réouverture de l'audience ; page Renvois et délibérés (nombre de renvois, retards, changement de date, prorogation) ; tableau de bord par chambre et par magistrat actualisé sans rechargement ; un délibéré à date ne revient au rôle qu'à cette date ; désistement ajouté aux suites ; page « Suites d'audience » (consultation filtrée par suite, chambre, période, recherche ; export Excel et PDF) ; sous-menu « Analyse et pilotage » prévu (page et analyse par IA à construire, le tableau de bord actuel n'est plus dans le menu) ; reste : rappels par e-mail et alertes (lot L), limite de renvois en référé-expulsion (lot L), affaires « à rédiger » reliées au lot M2)** |
| **L** Délais, alertes et circuit | Délais par étape, **règles d'alerte paramétrables** (voir § 3), alerte à l'acteur qui doit agir avec l'action à mener, délais de procédure, limite de renvois en référé-expulsion | PAR-13, AUD-09 | H, K, M pour les événements | **(fait nov. 2026 : moteur d'alertes quotidien (04h30 et 12h30, rattrapage des jours manqués, jamais deux fois la même alerte), échéances calculées d'après le circuit (mise au rôle, publication du rôle, suites non notées par audience, renvois, délibérés, décisions à rédiger / valider / signer, délais de procédure, limite de renvois, détention, requêtes non réparties), règles paramétrables (J-15 à J+30, canaux, destinataires par rôle avec montée dans la hiérarchie), délais par étape en jours ouvrables, cloche et page « Mes notifications », suivi des alertes avec justification d'un retard, e-mail récapitulatif quotidien sans nom de partie, contrôle des agents sans compte ; reste : alertes de sécurité (lot G), alertes pour les modules à venir)** |
| **M1** Modèles de décision et blocs (**fait**) | Pages « Modèles de décision » (liste, édition en deux parties qualité et factum, éditeur avec alignement, insertion de variables et de blocs, variables propres à un modèle, rattachement aux objets du litige et aux chambres, versions avec retour à une ancienne, aperçu avec données fictives dans la police des actes) et « Blocs de texte ». Premier modèle chargé : jugement d'hérédité (d'après les 3 documents reçus), à relire avec les juges. Moteur étendu : données composées (`demandeur.nom`), transformations enchaînées, `personne`, `personnes`, `date_courte`, `date_chiffres`, mode HTML protégé. | DEC-03, 04, 06, PAR-10 | Fait ; aperçu A4 identique au Word (timbre à gauche, qualité en retrait, factum pleine largeur, pages numérotées), mise en page réglable dans « Impression des actes » |
| **M2** Décisions et clôture (reste) | Saisie après audience, **qualité et factum dans la page Rédaction**, modèles de décision (jugement d'hérédité en premier) avec variables et blocs, éditeur, statuts visibles (brouillon, rédigée, validée, signée, numérotée, délivrée), verrouillage et versions, impression avec la police des Réglages, PDF et Word, numéro de décision, clôture, délivrance (expédition, grosse), registre des décisions | DEC-01 à 12, CLO-01, CLO-02, NUM-02, NUM-05, PAR-10 | K, L ; **moment d'attribution du numéro à confirmer** | **(fait nov. 2026 : la décision se rédige pour une affaire du registre, choisie par recherche (numéro ou partie) avec les jugées « à rédiger » proposées d'abord ; chambre, date d'audience et parties remplies d'après l'affaire ; bouton « Rédiger la décision » sur la feuille d'audience ; référence non retapable ; fait aussi : cycle de vie brouillon, rédigée, validée, signée, numérotée, délivrée ; numéro de décision sans trou ni doublon dans l'année, attribué à la signature ou à la validation selon Réglages > Décisions (**moment à confirmer avec la greffière en chef**, réglage par défaut : signature) ; remises (expédition, grosse, copie) ; registre des décisions avec export Excel et PDF ; liste et accès aux décisions filtrés par chambre ; reste : clôture de l'affaire, recours et certificats de non-appel (lot P))** |
| **N** Statistiques de base | Tableaux de bord, stocks, entrées et sorties, durées, indicateurs de célérité, vue sans données personnelles | STA-01 à 04, STA-08 | H à M | **Fait (nov. 2026), avec Q pour le civil et le pénal : voir `docs/PLAN_STATISTIQUES.md`** |

### Phase 2 : pénal et compléments

| Lot | Contenu | Exigences |
|---|---|---|
| **O** Matière pénale | Enrôlement RP depuis le numéro du parquet, prévenus, mandat de dépôt et détenus prioritaires, rôles pénaux par type d'audience, décision pénale, scellés, infractions et types de procédure, date de transmission de la minute | PEN-01 à 07, PAR-07, PAR-08, AUD-10, ENR-01 (RP) | **(partie faite : détenus et échéances du mandat, scellés ; fait aussi : rôles pénaux du parquet (équipe d'audience, mandat de dépôt, signature du procureur, droit « roles.penal ») ; **lot O terminé (nov. 2026)** : numéro du parquet à l'enrôlement RP (doublon refusé sauf jonction), page « Infractions » (14 libellés installés, articles à renseigner par le tribunal) proposée dans le formulaire d'enrôlement, **répertoire pénal** (décision par prévenu : issue, peine, sursis, amende ; relaxe ou acquittement = remise en liberté dans le suivi des détenus), **date de transmission de la minute** avec alerte au-delà de 30 jours (valeur à confirmer) ; alertes `minute_a_transmettre` et `certificat_recours` ; la comparution immédiate et les flagrants relèvent des modes de saisine déjà gérés ; test pratique dans `docs/TEST_PRATIQUE_LOTS_O_P.md`)**
| **P** Requêtes, mise en état, recours | Requêtes et ordonnances sur requête, injonctions de payer, mise en état, séances hors audience publique, recours et certificats de non-appel | ENR-10, AUT-05, AUD-07, AUD-08, AUD-11, CLO-03 | **(partie faite : ENR-10 requêtes et hérédité avec répartition par le secrétariat de la Présidente ; **mise en état faite (nov. 2026, AUD-07 et AUD-08)** : page « Mise en état » (affaire civile RG confiée à un juge chargé de la chambre, dates de réception, de première audience, n° et date de l'ordonnance de clôture, durée calculée, vigilance au-delà de 90 jours), clôture qui verse l'affaire à la chambre de jugement et fixe l'audience de jugement (changement de chambre : droit `affaires.reattribuer`), exclusion des rôles et des alertes de mise au rôle pendant la mise en état ; **lot P terminé (nov. 2026)** : signature réservée des ordonnances sur requête, voies d'exécution, injonctions et bref délai (droit `requetes.signer`, signataire et date enregistrés, AUT-05) ; page « Recours et certificats » sous Décisions (signification, recours, transmission à la Cour d'appel, certificat de non-appel ou de non-opposition numéroté N/AAAA avec PDF, CLO-03) ; **les délais de recours installés sont provisoires : à faire confirmer par la personne habilitée (droit `recours.delais`), aucun certificat n'est délivré tant qu'ils ne le sont pas** ; séances hors audience publique (réception des requêtes, conciliation, tutelle, AUD-11)…)**
| **Q** Statistiques complètes | Récapitulatifs civils et pénaux, état civil et adoption, filtres et exports Excel, exports au gabarit du Ministère | STA-05 à 07, STA-09, STA-10 |

### Phases 3 et 4

| Lot | Contenu | Exigences |
|---|---|---|
| **R** Instruction et juge des enfants (P3) | Saisine, registres RI et RE, six côtes, détentions et échéances, ordres d'extraction, accès restreint aux mineurs et secret de l'instruction | INS-01 à 12, STA-11 |
| **S** Services annexes (P4) | Section sociale, assistance judiciaire, application des peines, personnel magistrat, tribunal criminel, autres actes du greffe, recherche plein texte, rôle du jour à l'écran | AUT-01 à 04, PEN-08, CLO-04, DOS-06, ROL-11, STA-12 |
| **T** Reprise de l'existant (P4) | Import des classeurs Excel avec rapport d'anomalies, conservation des anciens numéros | REP-01, REP-02, NUM-04 | **(fait : assistant d’import Excel sécurisé en cinq étapes avec progression, rapport des erreurs, annulation ; reste : autres classeurs du tribunal à adapter)**
| **Hébergement et mise en service** | Sauvegardes quotidiennes chiffrées et test de restauration, chiffrement, serveur de messagerie, formation, recette. **En dernier**, comme décidé | SEC-05, SEC-06 |

**Ordre recommandé :** G, H, I, J, K, M, L, N, puis la phase 2. Le lot L (alertes) vient après M car il a besoin des événements de dossier ; ses règles et délais par étape peuvent se saisir dès maintenant.

---

## 3. Décisions du client à respecter dans les lots

| Sujet | Décision | Lot |
|---|---|---|
| Objet du litige | Saisi librement ; l'application reconnaît des **mots-clés** dans le texte et propose nature d'affaire, matière, chambre et modèle de décision. Recherche sans majuscules, accents ni pluriels ; plusieurs correspondances proposées, la plus précise d'abord ; un mot-clé présent dans deux chambres oblige à choisir ; sans correspondance, le greffier choisit la nature d'affaire. Nature d'affaire et chambre restent des choix en liste fermée (statistiques du Ministère). La page « Objets du litige » (113 objets de départ) deviendra la page « Mots-clés ». | H |
| Circuit et délais | Après l'enrôlement, le dossier passe d'étape en étape (greffier de la chambre, juges…) jusqu'à la décision. Chaque étape a un délai ; le système **alerte l'acteur qui doit agir**, en indiquant l'action à mener, pour qu'un dossier ne tarde pas. Les délais déjà connus servent de base (civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours avec un seul renvoi, dates limites d'enrôlement). Le cahier décrit le circuit en 10 étapes (§ 5.1). | L |
| Règles d'alerte | Page paramétrable dans Paramètres, sur le modèle d'un autre projet du client : **code** (J-15, J-3, J-1, J+1, J+7, J+15, J+30), **libellé** (échéance proche, imminente, dépassée, retard persistant, grave, critique), **décalage** en jours, **canaux** (notification dans l'application, e-mail), **destinataires** (on monte dans la hiérarchie quand le retard grandit), **statut** actif ou inactif. Destinataires à traduire en rôles du tribunal : greffier de chambre, juge ou président de formation, Présidente et vice-présidents, coordonnateur du greffe. L'e-mail réutilise les Réglages SMTP. | L |
| Qualité et factum | Dans la page **Rédaction** du menu Décisions, sous deux onglets ou blocs : la qualité (en-tête, composition, parties, mode de comparution) se génère depuis l'affaire, le factum (visas, faits, motifs, dispositif) se rédige avec le modèle lié à l'objet. Les modèles se gèrent dans « Modèles de décision ». La délivrance est une page à part. | M |
| Police des actes | Centaur 16 pour le corps, 12 pour le timbre, paramétrables (Réglages, rubrique « Impression des actes »). Prévoir une police de repli à l'export. | M |
| Moteur de rédaction | Le lot F n'a pas d'écran : la page Rédaction l'appelle directement. | M |
| Sécurité de session | `SECURITE_INACTIVITE_MINUTES=20` dans `.env`. | Fait |
| Hébergement | Reporté à la fin ; seule la messagerie sera à brancher à ce moment. | Fin |

---

## 4. En attente du client ou de la Présidente

| Point | Lot concerné |
|---|---|
| Liste des matières et objets du litige, que le point focal doit remettre | H |
| ~~Délais de procédure~~ : confirmés (ordonnance p. 8) : civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours. Alertes de retard grave : Présidente, vice-présidents et coordonnateur du greffe (J+15 et J+30). **Délais par étape : confirmés par la Présidente (nov. 2026)**, dont la rédaction de la décision (30 jours ouvrables). Restent à confirmer par la Présidente : dates de l'année judiciaire (12/10/2026 au 11/10/2027), variantes de noms relevées dans l'ordonnance, durée de verrouillage | L, D |
| ~~Moment d'attribution et format du numéro de décision~~ : levé (nov. 2026, information du client) : **le numéro est généré automatiquement à la validation par le juge, après le factum**, au format **D-N/AAAA** (par exemple D-145/2026 ; compteur par année). L'option « le greffier de la chambre donne le numéro » existe aussi dans Réglages > Décisions | M, H |
| Modèles Word vierges de chaque rôle et de chaque décision, à demander à la greffière en chef | J, M |
| Phase du pénal (la Présidente peut l'avancer en phase 1) | O |
| Traitement du parquet (qui saisit les enrôlements RP) | O |
| ~~Contenu exact du « timbre »~~ : levé, c'est le bloc de la colonne de gauche de la première page (cour d'appel, tribunal, RG, chambre, type, numéro, affaire et parties) ; modèle de départ chargé | M |

## 5. Petites actions de nettoyage, côté projet ou client

- Supprimer la chambre en double « 1ere chambre civile » (la bonne est « 1re chambre civile »), dans la page Chambres.
- Accorder les nouveaux droits aux profils (archive des ordonnances, jours et heures d'audience, jours fériés et vacances, dates limites d'enrôlement, reconduction, référentiels) et vérifier les droits d'exportation.
- Archiver l'ordonnance signée dans le menu Organisation.
- Supprimer les anciens fichiers `public/assets/js/select-recherche.js` et `public/assets/css/select-recherche.css`, devenus inutiles.
- Hypothèses de l'organisation à confirmer : 5e chambre civile rattachée à la section civile, « section procédure d'urgence » créée sans chambre, noms dont l'orthographe varie dans le scan.

## 6. Repères techniques

- Laravel 13, PHP 8.3, MySQL `db_tpi`, Apache sous `/tpi`, thème « eLouange » (`public/assets/css/elouange-theme.css`), jQuery chargé en bas de page (les scripts de page installent leurs écouteurs après `DOMContentLoaded`).
- Droits : `app/Support/Droits.php` (menu, page, niveaux aucun, lecture, saisie, gestion, droits spéciaux), une `Gate` par droit.
- Données de départ : `database/seeders/data/ordonnance_600_2026.php` et `referentiels_judiciaires.php`, chargées par des seeders qui peuvent être relancés sans doublon.
- Tests : `php artisan test` (280 tests).

**M2 : avancement.** Page Rédaction construite et validée par le client (novembre 2026, RAS) : formulaire déduit du modèle en blocs repliables, aperçu A4 en direct (zoom 125 %), champs connus remplis d'avance (juridiction, cour d'appel, type de décision, numéro du RG tirés des réglages et de la référence ; composition tirée de l'ordonnance selon la chambre, assesseurs en liste), listes (matière, type d'audience, régime, fonction, comparution, nationalités), naissance en date ou année seule, retouche à la main du texte (gras, italique, lignes), exemples réversibles, validation qui fige le texte, correction par nouvelle décision, impression/PDF. Reste : export Word, lien avec l'affaire (Enrôlement : date du RG, chambre, demandeur), numéro de décision, clôture, délivrance.

**Lot H : avancement (novembre 2026, en attente de validation).** Menu Affaires construit : enrôlement civil (RG-0001/26, compteur verrouillé, sans doublon ni trou), parties de natures différentes avec avocat en texte libre, objet libre rapproché des 113 objets (propositions de nature, matière, chambre), alerte de doublon, date de première audience contrôlée (jours d'audience de la chambre, fermetures, date limite d'enrôlement, proposition de la plus proche date ouverte, dérogation de la Présidente tracée, référés exemptés), liste et fiche, correction tracée, annulation avec motif (numéro jamais réutilisé), réattribution de chambre tracée, jonction des causes, rédaction d'une décision à partir de l'affaire (numéro RG, date du RG, chambre, demandeur repris). Reste : référentiel des tiers (avocats, lot G), séries RP, RI, RE (lots O, P), alerte de doublon sur l'objet, reprise de l'existant (ancien numéro). Format du numéro à confirmer avec le client.

**Lot H : réglage par TPI (novembre 2026).** Réglage « Affaires > Numéro RG » : `manuel` (Bingerville : le greffe a son logiciel d'enrôlement, on reporte son numéro `1391/25` ; doublon bloqué sauf jonction par interrupteur ; aucune date limite contrôlée ; vocabulaire « Saisie des affaires » sans le mot enrôlement pour le greffier de chambre) ou `automatique` (autre TPI : numéro attribué, date limite et dérogation). Le format du cahier des charges (RG-1391/25) reste en base (`numero_complet`) sans être affiché. Nature « Ministère public » ajoutée aux parties. Nature de la décision : variable `nature_decision` (liste des caractères de décision) et transformation `adverbe`. Reste : droits par chambre pour limiter le greffier à sa chambre (lot G), correction du numéro saisi (droit dédié).

**Lot H : registres (novembre 2026).** Trois formulaires de saisie derrière un choix du registre : civil (RG), pénal (RP : prévenus, mandat de dépôt, nature de la prévention, articles, victimes) et instruction (RI adultes, RE enfants, la série suit la nature). Numéro saisi à la main (Bingerville) ou attribué par série (autres TPI), doublon bloqué sauf jonction, jonction limitée à la même série et à la même chambre. Liste filtrable par registre. Pas de menu « Registre » : filtres de la liste, et registre des décisions plus tard (lot M2). Reste : assistance judiciaire, JAP, tutelle (à trancher), modèles de décision pénale, droits par chambre (lot G).

## Lots R et S (5 novembre 2026)
**Lot R, instruction : fait.** Page « Instruction » (dossiers des cabinets d'instruction et du juge des enfants : réquisitoire, faits, ordonnance de clôture, inculpés avec détention et renouvellements, victimes avec auditions, commissions rogatoires), statistiques de l'instruction, trois registres Excel au modèle (INFO JI 1er et 2e cabinet, INFO JE) avec leurs tableaux dossiers, inculpés et victimes et leurs feuilles de commissions rogatoires.
**Lot S, services annexes : fait pour l'assistance judiciaire et les activités du juge de l'application des peines** (pages de saisie, statistiques, registres Excel au modèle ; le classeur du JAP garde ses macros), et le personnel magistrat. Restent à construire, non demandés pour l'instant : application des peines (autres actes), tribunal criminel, autres actes du greffe, recherche plein texte.

## Compléments du 5 novembre 2026
- **Recherche plein texte** (menu : champ « Rechercher un dossier » dans l'en-tête, page `/recherche`) : tous les mots saisis doivent se retrouver, dans les affaires (numéro, parquet, objet, articles, parties), décisions, requêtes, décisions pénales, pièces du dossier numérique et assistance judiciaire. Chacun ne trouve que ce que ses droits permettent (droit de chaque rubrique, chambres, familles protégées). Les jokers SQL sont neutralisés.
- **Rôle du jour à l'écran** (ROL-11, Audiences > Rôle du jour) : les rôles publiés de la journée avec salle, heure, composition, état de l'audience, affaires par rubrique et suite notée ; mise à jour automatique toutes les 30 secondes ; plein écran pour l'affichage en salle.
- **Délais de recours** : confirmés d'office d'après la norme usuelle (appel civil 1 mois, opposition 15 jours, référé 15 jours, appel correctionnel 10 jours), modifiables dans « Délais de recours ».
- **Chambre** : « 1re chambre civile » (le doublon avait déjà été fusionné ; le nom est corrigé dans la base et les données de référence). Anciens fichiers `select-recherche` supprimés.
- **Année judiciaire** : ses dates sont définies par le tribunal chaque année (page Année judiciaire et reconduction) ; aucune date n'est figée dans le code.
- **Registres Excel** : formules des colonnes « Année » rendues indépendantes de la langue d'Excel ; valeurs écrites en cache dans les récapitulatifs annuels.


## Listes paginées côté serveur (5 novembre 2026)
Les longues listes ne chargent plus toutes les lignes : une page de 20 lignes par défaut, puis les données arrivent quand on change de page, de tri, de filtre ou qu'on recherche (`app/Support/TableauServeur.php`, `RechercheTexte.php`, `public/assets/js/elouange-table.js` en mode `data-serveur`).
Listes concernées : affaires, dossier numérique, détenus, requêtes, mise en état, répertoire pénal, recours et certificats, instruction, assistance judiciaire, séances hors audience, rôles d'audience, rédaction des décisions, avocats, infractions. Déjà paginées avant : journaux, suivi des alertes, registre des décisions. Les petits tableaux de paramétrage (salles, sections, matières…) restent chargés d'un coup.
Chaque liste a sa route `…/donnees` (droit identique à la page, limitée à 240 appels par minute) et un fichier `_ligne.blade.php` pour la ligne. Les compteurs de l'en-tête sont calculés dans la base ; ceux des recours (calcul d'état complet) se chargent après la page.
Couleurs : textes secondaires en gris foncé (`--el-mute` #2F3B52) pour la lisibilité.

## Manuel d'utilisation intégré (5 novembre 2026)
Menu **Aide** (tout compte connecté) : sommaire en cartes, parcours d'un dossier, recherche plein texte instantanée, chapitres avec sommaire latéral et barre de lecture, « Je suis bloqué » (questions dépliables), manuel complet imprimable en PDF, et **bouton orange « ? »** de l'en-tête (touche F1) qui ouvre l'aide de la page courante dans un tiroir. Chacun ne voit que les chapitres de ses droits. Les chapitres sont des fichiers Markdown de `resources/aide/` (en-tête : titre, icône, couleur, résumé, droit, routes, ordre) : modifiables sans toucher au code. Code : `app/Support/Aide/ManuelAide.php`, `AideController`, vues `aide/*` et `layout/aide`, style `public/assets/css/aide.css`, test `AideTest`. À faire : captures d'écran dans les chapitres, relecture par le tribunal.
**Captures du manuel (7 nov. 2026)** : `php artisan aide:captures` refait toutes les captures avec Chrome sans fenêtre (outil `tools/captures/captures.mjs`, aucune dépendance) ; les chapitres posent des épingles numérotées et une légende par capture (`![légende](aide/x.png "1@x,y=texte | …")`). Voir `docs/CAPTURES_AIDE.md`.
**Transitions douces (7 nov. 2026, essai sur le tableau de bord)** : `public/assets/css/transitions.css` et `public/assets/js/transitions.js` (blocs et tuiles en cascade, chiffres qui comptent, désactivé pour « moins d'animations »). **Pas de fondu à la sortie** (demande du client : il laissait un vide). Feuille chargée partout ; la classe `tp-anime` est posée sur les pages les plus utilisées (tableau de bord, liste des affaires, requêtes, mise en état, rôles, rôle du jour, feuilles d'audience, rédaction, recours, accueil de l'aide). Captures du manuel supprimées (outil `aide:captures` conservé).
