# Récapitulatif du cahier des charges, lot par lot

Application de gestion du **Tribunal de première instance de Bingerville** (Laravel 13, base `db_tpi`, interface en français, couleurs orange, blanc et vert).
État au **7 novembre 2026**, pour reprendre le travail dans une nouvelle session. Détail technique et décisions du client : `docs/PLAN_DES_LOTS.md`.

**Légende :** ✅ fait et testé · 🟡 fait en partie · ⏳ à faire · ⚠️ à confirmer par le tribunal.

---

## 1. Vue d'ensemble

| Mesure | Valeur |
|---|---|
| Fonctions du cahier réalisées (estimation) | **environ 80 à 85 %** (environ 70 % en comptant la mise en service) |
| Code applicatif | ≈ 24 000 lignes PHP, ≈ 19 000 lignes de vues, ≈ 12 000 lignes de tests |
| Tests automatiques | ≈ 560 tests, tous verts à la dernière passe complète |
| Routes | 327 (importées par groupes dans `routes/web.php`) |
| Estimation de valeur | 12 à 16 mois de travail d'un développeur senior : environ 20 à 45 millions de FCFA (tarif local) ou 45 à 75 millions (agence) ; maintenance 15 à 20 % par an |

---

## 2. Lots A à F : socle, organisation, référentiels, outils

| Lot | Contenu | Statut |
|---|---|---|
| **A** Socle technique | Projet, base MySQL, tests, intégration continue, réglages dans l'application, **jeu de données fictives** (`php artisan tpi:formation`) | ✅ |
| **B** Comptes et droits | Connexion, double authentification par e-mail, **codes de secours**, verrouillage après échecs et après inactivité, profils illimités avec droits par menu, page et niveau, droits spéciaux, **droits par chambre** et **familles protégées** (mineurs, instruction) | ✅ |
| **C** Journaux | Journal d'audit (avant et après) et de connexion, non modifiables, filtres, exports, alertes d'anomalies de connexion, consultations de dossiers journalisées | ✅ |
| **D** Organisation | Année judiciaire, salles, magistrats et greffiers, sections, chambres, formations, composition datée, services et responsabilités, calendrier d'audiences, jours fériés et vacances, dates limites d'enrôlement, **reconduction d'année**, archive des ordonnances | ✅ |
| **E** Référentiels | Types d'audience, modes de saisine, matières, natures d'affaire, objets du litige, infractions, délais de procédure | ✅ ; ⏳ **tiers réutilisables** : institutions et personnes morales (les commissaires de justice sont hors périmètre, décision du client) |
| **F** Outils de génération | Moteur de rédaction (nombres, dates, montants en lettres, accords, variables, blocs), police des actes réglable (Centaur 16 pour le corps, 12 pour le timbre) | ✅ |

---

## 3. Phase 1 : socle et matière civile

| Lot | Contenu | Statut |
|---|---|---|
| **G** Compléments du socle | Droits par chambre et type d'affaire, codes de secours, anomalies de connexion, données fictives, intégration continue | ✅ (sauf tiers du lot E) |
| **H** Enrôlement et numérotation | Numéro RG **saisi à la main** (Bingerville) ou attribué sans doublon ni trou ; parties (homme, femme, couple, personne morale, État, ministère public) avec avocat ; **objet libre rapproché par mots-clés** (nature, matière, chambre proposées) ; date de première audience contrôlée avec **dérogation** de la Présidente ; alerte de doublon, **jonction** ; correction tracée du numéro ; annulation avec motif ; réattribution de chambre ; registres RG, RP, RI, RE | ✅ |
| **I** Dossier numérique | Pièces PDF et images typées et horodatées, vérification du vrai contenu, retrait avec motif, consultations journalisées, emplacement du dossier papier | ✅ ; 🟡 archivage automatique à la clôture |
| **J** Rôles d'audience | Rôle composé tout seul (délibérés, anciennes, nouvelles), éditeur, composition datée, publication avec **versions**, export **Word** et **PDF**, **planning des salles** avec conflits, rôles pénaux (parquet) | ✅ ; ⏳ planning détaillé des salles hors audience |
| **K** Audiences, renvois, délibérés | Feuille d'audience (composition, empêchements, issues avec motif et date), report automatique des renvois, délibérés, page Renvois et délibérés, suites d'audience, **tableau de bord** et **Analyse et pilotage** | ✅ |
| **L** Délais, alertes et circuit | Moteur d'alertes quotidien, 15 types d'échéances, règles paramétrables (J-15 à J+30), destinataires par fonction, **justification d'un retard**, cloche et e-mail récapitulatif, rattrapage des jours manqués | ✅ |
| **M1** Modèles et blocs | Pages Modèles de décision (qualité et factum, versions, variables) et Blocs de texte ; premier modèle : **jugement d'hérédité** | ✅ ; ⚠️ à relire avec les juges |
| **M2** Décisions et clôture | Rédaction (formulaire déduit du modèle, aperçu A4 en direct, retouche), circuit **brouillon → rédigée → validée → signée → délivrée**, numéro **D-N/année**, délivrance (expédition, grosse, copie), registre des décisions avec exports | ✅ ; ⏳ **export Word** des décisions ; ⚠️ moment d'attribution du numéro (par défaut à la validation) |
| **N** Statistiques de base | Tuiles, graphiques, durées, indicateurs de célérité, vue sans données personnelles | ✅ |

---

## 4. Phase 2 : pénal et compléments

| Lot | Contenu | Statut |
|---|---|---|
| **O** Matière pénale | Enrôlement RP avec numéro du parquet, prévenus, **mandat de dépôt et détenus** classés par échéance, scellés, infractions, **répertoire pénal** (règles peine, sursis, issue), transmission de la minute, rôles pénaux | ✅ ; ⚠️ articles des infractions et délai de minute (30 jours) à valider |
| **P** Requêtes, mise en état, recours | Requêtes (hérédité, ordonnances, injonctions, bref délai) avec répartition par la présidence et **signature réservée** ; **mise en état** (longue au-delà de 90 jours) ; **séances hors audience** (réception des requêtes, conciliation, tutelle) ; **recours et certificats** de non-appel avec délais confirmés | ✅ |
| **Q** Statistiques complètes | Civil, pénal, état civil, adoption, durées, exports Excel et PDF, **21 registres du tribunal** au modèle de leurs classeurs | ✅ ; ⚠️ vérifier à l'ouverture dans Excel que les récapitulatifs ne montrent pas de zéros (cause non prouvée) |

---

## 5. Phases 3 et 4

| Lot | Contenu | Statut |
|---|---|---|
| **R** Instruction et juge des enfants | Dossiers RI et RE, réquisitoire, inculpés et détention, victimes et auditions, commissions rogatoires, statistiques, trois registres au modèle | ✅ ; ⏳ ordres d'extraction |
| **S** Services annexes | Assistance judiciaire ✅, activités du juge de l'application des peines ✅ (classeur `.xlsm` avec macros), personnel magistrat ✅, **recherche plein texte** ✅, **rôle du jour à l'écran** ✅ | 🟡 ; ⏳ **tribunal criminel**, ⏳ **autres actes du greffe**, ⏳ section sociale détaillée |
| **T** Reprise de l'existant | Assistant d'import Excel en cinq étapes, rapport d'anomalies, annulation, anciens numéros conservés | ✅ ; ⏳ adapter les autres classeurs du tribunal |
| **Hébergement et mise en service** | Sauvegardes chiffrées quotidiennes et test de restauration, serveur de messagerie, formation, recette | ⏳ (volontairement en dernier) |

---

## 6. Ajouts hors cahier, faits depuis le début de novembre

- **Listes paginées côté serveur** (20 lignes, chargement à la demande) : affaires, dossier numérique, détenus, requêtes, mise en état, répertoire pénal, recours, instruction, assistance, séances, rôles, rédaction, avocats, infractions, objets du litige, blocs de texte, journaux, registre.
- **Manuel d'utilisation intégré** : menu Aide, 9 chapitres, recherche instantanée, bouton **« ? »** et touche **F1** (aide de la page courante), manuel complet imprimable en PDF, « Je suis bloqué ». Textes dans `resources/aide/*.md`.
- **Outil de captures** `php artisan aide:captures` (conservé, captures actuellement supprimées).
- **Transitions douces** à l'entrée des pages les plus utilisées (classe `tp-anime`), sans fondu à la sortie.
- **Sécurité** : attaque simulée de toutes les routes, correctifs, rapport dans `docs/RAPPORT_SECURITE.md`.
- **Textes plus lisibles** : gris foncé dans toute l'application.
- **Menu réorganisé** : 8 entrées, sous-menus imbriqués dans Administration, routes importées par groupes.
- **Délivrance** : l'ancienne entrée de menu a été retirée (la délivrance se fait dans Rédaction, les remises figurent au registre).

---

## 7. Ce qui reste à faire

### Fonctions non construites
1. Tiers réutilisables : institutions et personnes morales (lot E).
2. Tribunal criminel (lot S).
3. Autres actes du greffe (lot S).
4. Décision pénale rédigée avec son modèle (jugement pénal).
5. Répertoire manuscrit.
6. Export Word des décisions (les rôles l'ont déjà).
7. Ordres d'extraction (lot R).
8. Profil « Gestionnaire ».
9. Reprise des autres classeurs Excel du tribunal.

### Mise en service (en dernier)
10. Sauvegardes quotidiennes chiffrées et test de restauration.
11. Serveur de messagerie réel (mode « Test » actuellement), hébergement, HTTPS.
12. Avant production : `APP_DEBUG=false`, cookies sécurisés, politique de mots de passe renforcée, **supprimer les comptes de test et le fichier des codes de secours**, retirer les données fictives (`php artisan tpi:formation --retirer`).
13. Formation des agents (supports : manuel d'aide, `docs/TEST_PRATIQUE_COMPLET`) et recette.

### À confirmer ou valider par le tribunal
14. Délais de recours installés (appel civil 1 mois, opposition 15 jours, référé 15 jours, appel correctionnel 10 jours), délai de transmission de la minute (30 jours), articles de chaque infraction, texte et signataire du certificat.
15. Moment d'attribution du numéro de décision et format D-N/année.
16. Modèles Word vierges des rôles et des décisions (à demander à la greffière en chef).
17. Relecture des textes du manuel d'aide par une greffière ou un magistrat (imprimer « Manuel complet », annoter, renvoyer les corrections).
18. Zéros éventuels dans les récapitulatifs Excel (si cela persiste : capture après « Activer la modification »).

### Chantiers en cours ou prévus
19. **Test pratique de bout en bout** : prévu le soir du 7 novembre 2026.
20. **Transitions** : valider l'essai, puis décider de l'extension à d'autres pages.
21. **Captures d'écran du manuel** : à remettre écran par écran, une fois les images vérifiées.
22. Compléter `docs/PLAN_DES_LOTS.md` avec l'état des lots P, O, R, S (une partie est déjà notée).

---

## 8. Pour reprendre le travail

- **Documents** (dossier `docs/`) : `PLAN_DES_LOTS.md` (plan détaillé), `TEST_PRATIQUE_COMPLET.md` et `.pdf` (déroulé du test), `DONNEES_TEST_COMPLET.md` et `.pdf` (données et champs à saisir), `PLAN_STATISTIQUES.md`, `RAPPORT_SECURITE.md`, `DONNEES_FICTIVES.md`, `CAPTURES_AIDE.md`, `GUIDE_ROLES_AUDIENCE.md`.
- **Commandes utiles** :
  - `php artisan tpi:formation` : efface les données d'essai puis recrée le jeu complet (jamais les vraies données).
  - `php artisan tpi:codes-test` : codes de secours des comptes de test.
  - `php artisan tpi:droits-test-lots-o-p` : droits de test.
  - `php artisan alertes:evaluer [--simuler] [--date=AAAA-MM-JJ]` : moteur d'alertes.
  - `php artisan aide:captures` : captures d'écran du manuel.
  - `php artisan test` : tous les tests (les lancer fichier par fichier pour économiser le temps).
- **Comptes de test** : `database/sql/comptes_test.sql` (36 comptes `@tpi.test`, mot de passe `Tpi@2026`, connexion avec un code de secours). Jamais en production.
- **Règles de travail demandées par le client** : réponses en français ; après chaque tâche, dire ce qui a été fait, les fichiers modifiés et les commandes lancées ; une page = ses propres fichiers (pas d'écran générique) ; `@can` et `@hasanyrole` dans les vues ; listes déroulantes avec recherche (Select2) ; fenêtres nommées `form_xxx` ; tableaux sans défilement horizontal ; application très sécurisée ; hébergement en dernier.
- **Mémoire du projet** : les décisions et conventions sont enregistrées dans le dossier de mémoire de la session (`MEMORY.md` et fichiers liés).
