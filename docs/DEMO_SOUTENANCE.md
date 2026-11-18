# Démonstration à l'équipe du point focal : guide de préparation et de déroulé

Séance en ligne d'environ deux heures. Trois affaires fictives servent de fil rouge, de l'enrôlement jusqu'à la décision délivrée, puis aux statistiques. Une fiche par module dit **quoi montrer, avec quel compte, avec quelles données**.
Le PowerPoint de présentation (`presentation_tpi.pptx`) suit le même ordre ; ses notes d'orateur reprennent ce guide.

> Toutes les données sont fictives. Les comptes de test et les affaires de démonstration seront retirés avant la mise en service.

---

## 1. Préparer (la veille, une seule fois)

| Étape | Commande ou action |
|---|---|
| 1. Jeu de formation complet (statistiques, pénal, instruction, requêtes, recours…) | `php artisan tpi:formation` (≈ 5 minutes) |
| 2. Modèles de jugement civil (paiement, foncier, bail) | `php artisan db:seed --class=ModelesJugementsCivilsSeeder` |
| 3. Les trois affaires de démonstration | `php artisan demo:soutenance` |
| 4. Comptes de test | importer `database/sql/comptes_test.sql` (36 comptes, mot de passe `Tpi@2026`) |
| 5. Codes de secours des comptes | `php artisan tpi:codes-test` (fichier `storage/app/codes_secours_test.txt`) |
| 6. Sexe des agents de la 1re chambre civile | Administration > Organisation du tribunal > Magistrats et greffiers : renseigner le greffier (il sert aux accords « Assisté » ou « Assistée ») |
| 7. Heure du serveur et connexion Internet | vérifier que le courriel n'est pas nécessaire (la connexion se fait avec un code de secours) |

> **Ne pas relancer** `demo:soutenance` pendant la séance sans nécessité : à chaque passage elle recrée la décision de l'affaire 3 et consomme un numéro de décision (D-N/année, sans trou). Après la séance : `php artisan tpi:formation --retirer`.

**Ordre à respecter :** `tpi:formation` d'abord (il efface les affaires d'essai, donc aussi les trois affaires), puis `demo:soutenance`.

### Comptes à ouvrir (une fenêtre ou un navigateur par compte : un navigateur ne garde qu'un compte connecté)

| Rôle | Compte (mot de passe `Tpi@2026`, puis un code de secours) | Sert à |
|---|---|---|
| Administrateur | le compte administrateur | sécurité, profils, organisation, réglages, journaux |
| Greffier, 1re chambre civile | `guedji.kouadio@tpi.test` | feuille d'audience, rôle, enrôlement, rédaction |
| Juge, 1re chambre civile | `myriam.diako@tpi.test` | valider et signer les décisions |
| Présidente | `appa.nguessan@tpi.test` | requêtes à répartir, dérogations, toutes les chambres |
| Coordonnateur du greffe | `prisca.toure@tpi.test` | certificat de non-appel, statistiques |
| Greffier, 2e chambre civile | `yaba.assamoi@tpi.test` | montrer qu'il ne voit pas la 1re chambre |

---

## 2. Les trois affaires

| Affaire | Objet | Modèle proposé | Où elle en est |
|---|---|---|---|
| **RG 2101/26** | Déguerpissement et démolition de constructions | Jugement civil : foncier | Inscrite au rôle **d'aujourd'hui** (1re chambre civile), pas encore tenue. Premier renvoi déjà noté |
| **RG 2102/26** | Paiement de créances civiles | Jugement civil : paiement et contrats | **Jugée il y a deux jours** : la décision est « à rédiger » |
| **RG 2103/26** | Validation de congé avec expulsion | Jugement civil : bail d'habitation | Décision **signée et délivrée** il y a une semaine |

Parties : 2101/26 KOUASSI Yao Hyacinthe c/ TIEHI Gbalé Roger (avocat Maître KONE Catherine) · 2102/26 Société IVOIRE MATERIAUX SA (Cabinet ABI & Associés) c/ BAKAYOKO Ibrahim · 2103/26 DIOMANDE Salimata (Maître N'GUESSAN Paul) c/ ZADI Marie-Claire.

### Affaire 1 : RG 2101/26, l'audience en direct (greffier puis juge)
1. **Recherche** : taper `KOUASSI déguerpissement` dans l'en-tête → l'affaire apparaît.
2. **Fiche** (Affaires > Liste des affaires > 2101/26) : parties, avocat, objet, historique (renvoi du … au …).
3. **Rôle d'audience** : Audiences > Rôles d'audience : montrer le rôle d'aujourd'hui de la 1re chambre civile (composé, publié, version 1) et le planning des salles.
4. **Feuille d'audience** : Audiences > Feuille d'audience > ouvrir l'audience d'aujourd'hui. Noter pour 2101/26 « **Mise en délibéré** » avec une date (par exemple dans 8 jours ouvrés). Refus à montrer : un renvoi sans motif, une date de renvoi un dimanche.
5. Terminer l'audience (la feuille est figée), puis **la rouvrir** et noter « **Jugée** » : le bouton « Rédiger la décision » apparaît.
6. **Rédaction** (compte juge) : l'affaire est présélectionnée, le **modèle foncier est proposé**. Dans le formulaire : choisir déguerpissement « ordonné » (les champs du motif apparaissent), démolition « ordonnée », dommages-intérêts « accordés en partie », exécution provisoire « ordonnée ». Regarder l'aperçu A4 à droite.

### Affaire 2 : RG 2102/26, le circuit de signature
1. Décisions > Rédaction : l'affaire est en tête avec l'étiquette **« À rédiger »** ; le **modèle paiement** est proposé à la sélection.
2. Remplir : demandes, exposé, somme réclamée, raisonnement. Montrer « À compléter » quand des champs manquent.
3. **Marquer rédigée** → **Valider** (compte juge : le texte est figé, le numéro D-N/année est attribué) → **Signer** (date de signature jamais dans le futur) → **Délivrer** (compte greffier : expédition, grosse ou copie).
4. Refus à montrer : signer une décision non validée ; délivrer une seconde grosse à la même personne.
5. **Registre des décisions** : la décision y est, avec sa remise.

### Affaire 3 : RG 2103/26, après la décision
1. **Registre des décisions** : décision D-N/année signée et délivrée.
2. **Recours et certificats** : enregistrer la signification, voir l'échéance calculée ; état « délai en cours ». Avec le coordonnateur : certificat de non-appel (une décision du jeu de formation au délai expiré permet de le délivrer, voir plus bas).
3. **Statistiques civiles** : cliquer sur un chiffre pour ouvrir la liste des affaires ; onglet « À compléter ».
4. **Registres du tribunal** : télécharger un registre Excel au modèle du greffe.
5. **Journal d'audit** (compte administrateur) : toutes les actions de la séance, avec leur auteur.

---

## 3. Un geste pour chaque module

| Module | Quoi montrer | Donnée prête |
|---|---|---|
| **Connexion et sécurité** | Mot de passe, code de secours, 5 essais faux → blocage 15 minutes ; verrouillage après inactivité | n'importe quel compte de test |
| **Profils et droits** | Créer un profil « lecteur », régler les niveaux ; se connecter avec le greffier de la 2e chambre : il ne voit pas la 1re | Administration > Utilisateurs |
| **Journaux** | Journal de connexion (échecs, code de secours) ; journal d'audit (avant et après) | filtrer sur aujourd'hui |
| **Enrôlement** | Enrôler une 4e affaire : taper l'objet « paiement de loyers impayés » → propositions ; essayer un numéro déjà utilisé (`1391/26`) → refusé sauf jonction | `1391/26`, `1402/26` existent |
| **Dossier numérique** | Déposer un PDF, essayer un faux PDF (renommé) → refusé ; retirer une pièce avec motif | n'importe quelle affaire |
| **Avocats** | Ajouter un avocat, tenter un doublon | Cabinet ABI & Associés |
| **Mise en état** | Confier 2101/26 à un juge, clôturer avec ordonnance ; marque « longue » au-delà de 90 jours | `1391/26` : mise en état longue |
| **Requêtes** | Répartir une requête d'hérédité ; voir celle « en retard » | RQ-0003 (en retard), RQ-0001 |
| **Détenus** | Liste par échéance : dépassée, proche | RP-1334/26 (JOHNSON, KOUADIO) |
| **Rôles et planning** | Créer un rôle, provoquer un conflit de salle | rôles J+20 du jeu de formation |
| **Rôle du jour** | Plein écran, mise à jour toutes les 30 secondes | rôle d'aujourd'hui, 1re chambre |
| **Renvois et délibérés** | Changer la date d'un renvoi ; délibéré en retard en rouge | affaires 1621/26 à 1627/26 (3e chambre) |
| **Séances hors audience** | Planifier une séance, la déclarer tenue | séances du jeu de formation |
| **Analyse et pilotage** | Indicateurs, note en langage clair, export PDF | — |
| **Modèles et blocs** | Ouvrir « Jugement civil : foncier », voir les variables, l'aperçu A4, les versions ; ouvrir un bloc (`civ_depens`) | 4 modèles installés |
| **Recours et certificats** | Certificat de non-appel, refus si recours formé | 1512/26 (délai expiré), 1513/26 (recours) |
| **Répertoire pénal** | Relaxe d'un détenu → il sort de la liste ; peine incohérente refusée | RP-1334/26 |
| **Instruction** | Dossier RI 45/26, inculpé détenu ; secret : compte du 2e cabinet ne voit pas le 1er | RI-0045/26, RE-0045/26 |
| **Assistance judiciaire** | Décider une demande, saisir un recours | AJ 4/26, AJ 5/26 à traiter |
| **JAP** | Saisir les compteurs d'un mois, télécharger le classeur `.xlsm` | compte du greffier JAP |
| **Statistiques** | Tuiles, onglets, clic sur un chiffre ; pénales ; instruction | ≈ 330 affaires civiles fictives |
| **Alertes** | Suivi des alertes, justifier un retard ; règles ; délais par étape | alertes calculées au démarrage |
| **Organisation** | Année judiciaire, chambres, formations, composition, calendrier, fermetures, reconduction | ordonnance n°600/2026 |
| **Référentiels** | Objets du litige (mots-clés), infractions | 297 objets |
| **Réglages** | Sécurité : avertissement avant verrouillage ; décisions : moment du numéro ; impression des actes | rubrique par onglet |
| **Aide** | Bouton « ? », touche F1, recherche, chapitre « Je suis bloqué » | — |

---

## 4. Minutage conseillé (≈ 2 h)

| Partie | Durée |
|---|---|
| Ouverture : enjeux, parcours d'un dossier, chiffres | 10 min |
| Sécurité et accès | 15 min |
| Premiers pas et Affaires (affaire 1 : fiche) | 20 min |
| Audiences (affaire 1 : feuille d'audience en direct) | 20 min |
| Décisions (affaires 1 et 2 : rédaction, circuit, registre) | 30 min |
| Affaire 3, statistiques, registres, alertes | 15 min |
| Administration, instruction et annexes (survol) | 10 min |
| Bilan, points à valider, questions | 10 min |

## 5. Si quelque chose ne va pas

- **« Interdit »** sur une page : le compte n'a pas le droit ; se reconnecter avec le bon compte (Aide > « Je suis bloqué »).
- **Le formulaire demande des champs en trop** : choisir « non demandé » pour les motifs qui ne s'appliquent pas.
- **Le greffier ou le juge ne voit pas l'affaire** : vérifier la chambre du compte (1re chambre civile pour les trois affaires).
- **Une pastille orange dans l'aperçu** : un champ manque (par exemple le sexe d'un agent) ; le compléter dans le formulaire.
- **Recommencer** : `php artisan tpi:formation` puis `php artisan demo:soutenance`.
