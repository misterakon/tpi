# Test pratique complet de l'application TPI de Bingerville

**Du premier compte créé jusqu'aux alertes : tous les menus, tous les cas, tous les contrôles.**

Ce document sert à trois choses : **éprouver** l'application avant la mise en service, **former** les agents (chaque parcours est un exercice), et **maîtriser** les règles cachées (refus, blocages, droits) que l'on ne découvre que lorsqu'on essaie de mal faire.

> **À faire uniquement sur la base de test.** Jamais en production. Toutes les données sont fictives.

## Comment lire ce document

- Chaque étape donne : **Faire** (la manipulation) puis **Attendu** (ce que l'application doit répondre).
- Les lignes marquées **⛔ Refus attendu** sont des essais « à l'envers » : l'application doit refuser. Un refus qui ne vient pas est une anomalie.
- Les lignes marquées **🔎 Caché** décrivent une règle ou une fonction que rien n'annonce à l'écran.
- Case à cocher `[ ]` : cochez quand l'étape est réussie. Notez en marge **OK / Anomalie / Question**.
- Durée totale : une journée complète. Chaque partie se fait seule (≈ 20 à 40 minutes).

## 0. Préparation

### 0.1 Préparer les données (une seule commande)

```bash
php artisan migrate --seed       # tables, ordonnance, référentiels, modèles de décision (si ce n'est pas fait)
php artisan tpi:formation        # EFFACE les données d'essai existantes puis recrée tout le jeu du test (≈ 5 minutes)
```

La commande ne touche **jamais** aux vraies données (organisation, référentiels, modèles, réglages, comptes, journaux) et **refuse** de s'exécuter s'il existe une affaire qui n'est pas d'essai. Relancer la commande remet le jeu à l'état initial : pratique pour recommencer.
Avant : importer `database/sql/comptes_test.sql` (36 comptes `@tpi.test`, mot de passe `Tpi@2026`), puis `php artisan tpi:droits-test-lots-o-p` et `php artisan tpi:codes-test` (codes de secours dans `storage/app/codes_secours_test.txt`).

> **Les données exactes** (quelle affaire, quelle décision, quel compte, **quel champ remplir avec quelle valeur**, **quelles valeurs fausses essayer**) sont dans **DONNEES_TEST_COMPLET** : chaque partie ci-dessous renvoie à sa **fiche** (F1.1, F3.1…).

### 0.2 Règle d'or des navigateurs

**Un seul compte par navigateur.** Utiliser une fenêtre privée par compte (ou trois navigateurs : Chrome, Edge, Firefox). Sinon la session d'un compte écrase l'autre et les tests de droits n'ont aucun sens.

### 0.3 Comptes déjà prêts (la liste complète est en A.2 de DONNEES_TEST_COMPLET)

| Rôle | Compte | Sert à |
|---|---|---|
| Présidente | `appa.nguessan@tpi.test` | tout voir, répartir et signer les ordonnances sur requête, dérogations |
| Magistrat | `carine.boka@tpi.test` | rédiger et valider, séances, mise en état |
| Greffier de chambre | `yaba.assamoi@tpi.test` | enrôler, rôles, feuille d'audience, signification |
| Coordonnateur du greffe | `prisca.toure@tpi.test` | délais, certificats, référentiels, statistiques |

> Les autres comptes (juges d'instruction, juge des enfants, JAP, parquet…) sont dans `comptes_test.sql`, un par magistrat ou greffier de l'ordonnance.

### 0.4 Fiche de suivi

Notez au fil de l'eau, pour chaque numéro d'étape : **OK**, **Anomalie** (décrire, joindre une capture) ou **Question** (règle à confirmer). Une fiche vierge est en **annexe C**.

---

## 1. Comptes, profils, droits et sécurité

### 1.1 Créer les profils (Administration > Utilisateurs > Profils et droits)

> **Données à utiliser :** F1.1 de DONNEES_TEST_COMPLET.

Compte : **administrateur** (le profil Admin du système).

1. **Faire** : créer **six profils** avec ces niveaux. Pour chaque page : *aucun*, *lecture*, *saisie*, *gestion*, plus des **droits spéciaux** (cases à cocher à part).

| Profil à créer | Droits à donner (idée générale) |
|---|---|
| **Greffier de chambre (essai)** | Affaires (voir, enrôler, modifier), Dossier numérique (saisie), Rôles (voir, créer, modifier, imprimer), Feuille d'audience (saisie), Renvois (saisie), Requêtes (saisie), Mise en état (saisie), Recours (saisie), Rédaction (voir, numéroter) |
| **Magistrat (essai)** | Affaires (voir), Rôles (voir), Feuille (voir), Rédaction (saisie + valider + signer), Modèles (voir), Séances (saisie), Mise en état (saisie) |
| **Coordonnateur (essai)** | Tout le greffe + Recours (gestion, certifier, délais), Référentiels (gestion), Statistiques (voir, exporter), Alertes (voir, règles) |
| **Parquet (essai)** | Rôles (voir) + droit spécial **Rôles pénaux**, Répertoire pénal (saisie), Détenus (voir) |
| **Lecteur (essai)** | Uniquement *lecture* sur Liste des affaires et Rôles |
| **Sans droit (essai)** | Aucun droit |

   **Attendu** : chaque profil s'enregistre ; l'écran affiche le **nombre de pages accessibles**.
2. ⛔ **Refus attendu** : donner un niveau invalide à une page (en modifiant la requête, ou un niveau « gestion » sur une page qui n'a que « lecture ») → message « Niveau d'accès invalide ».
3. 🔎 **Caché** : le profil **Admin** a tous les droits, **ne peut pas être modifié ni supprimé**. Essayer : refusé.
4. 🔎 **Caché** : un profil **utilisé par des comptes** ne se supprime pas (message). Un profil vide se supprime.

### 1.2 Créer les utilisateurs (Administration > Utilisateurs > Liste des utilisateurs)

> **Données à utiliser :** F1.2 de DONNEES_TEST_COMPLET.

1. **Faire** : créer **un compte par profil** ci-dessus, avec des adresses `@tpi.test` fictives (`essai.greffier@tpi.test`, etc.) et le mot de passe provisoire demandé. Relier chaque compte à son **agent** (fiche de « Magistrats et greffiers ») quand il en existe un : c'est ce lien qui donne le **périmètre de chambre**.
   **Attendu** : le compte apparaît dans la liste (20 lignes par page, recherche en haut), actif.
2. ⛔ **Refus attendu** :
   - e-mail déjà utilisé : « Cette adresse e-mail est déjà utilisée par un autre compte » ;
   - mot de passe de moins de 8 caractères ;
   - se changer **soi-même** de profil ou se désactiver : « Vous ne pouvez pas modifier votre propre profil ni votre statut » ;
   - supprimer le **dernier administrateur** ou le compte principal : refusé.
3. **Faire** : désactiver un compte, puis essayer de s'y connecter. **Attendu** : connexion refusée.

### 1.3 Connexion et double authentification

> **Données à utiliser :** F1.3 de DONNEES_TEST_COMPLET.

Avec chaque nouveau compte, dans une fenêtre privée :

1. **Faire** : se connecter avec le mot de passe provisoire. **Attendu** : demande du **code reçu par e-mail** (boîte fictive : utiliser un **code de secours** — lien « Je ne reçois pas le code »).
2. **Faire** : saisir un code de secours ; **Attendu** : accès au tableau de bord. Le **même code ne marche plus** une seconde fois.
3. ⛔ **Refus attendu** : mauvais mot de passe 5 fois → **verrouillage temporaire** du compte. Les échecs apparaissent dans le **Journal de connexion** et déclenchent une **alerte aux administrateurs** (cloche) : « 5 échecs en 15 minutes ».
4. ⛔ **Refus attendu** : un mauvais code de double authentification (essayer 3 fois) → refus, journalisé.
5. **Mon compte** : changer son mot de passe (l'ancien est redemandé) ; générer **8 nouveaux codes de secours** (le mot de passe est redemandé) ; les anciens codes ne marchent plus.
6. 🔎 **Caché** : **inactivité** — laisser la page ouverte sans toucher pendant la durée réglée (Réglages) : un **écran de verrouillage** apparaît ; il faut ressaisir le mot de passe. Avec un mauvais mot de passe : refus. Le bouton « Verrouiller » du menu fait la même chose à la demande.
7. 🔎 **Caché** : se déconnecter depuis un onglet, puis cliquer dans un autre onglet : redirection vers la connexion (jamais d'erreur technique).

### 1.4 Vérifier que chaque profil voit ce qu'il doit voir

Pour **chaque compte créé** : ouvrir le menu et comparer.

| Compte | Doit voir | Ne doit pas voir |
|---|---|---|
| Sans droit | Tableau de bord, Mes notifications | Tout le reste |
| Lecteur | Liste des affaires, Rôles (sans boutons d'action) | Statistiques, Administration |
| Greffier | Affaires, Audiences, Rédaction, Recours | Administration, Profils |
| Parquet | Rôles (bouton « Rôle pénal »), Répertoire pénal | Rédaction |

1. **Faire** : avec le compte **Sans droit**, taper à la main l'adresse d'une page protégée (`/affaires/liste`, `/statistiques/civil`…). **Attendu** : **« interdit » (403)**, jamais la page, jamais une erreur technique.
2. **Faire** : avec le compte **Lecteur**, ouvrir la Liste des affaires. **Attendu** : pas de bouton « Enrôler », pas de crayon, pas de corbeille. Essayer d'appeler à la main une action d'écriture : refusé.
3. 🔎 **Caché** : **périmètre de chambre** — un greffier ne voit que les affaires, décisions, séances et requêtes **de sa chambre** (ordonnance). Le coordonnateur et la Présidente voient **toutes** les chambres. Vérifier avec un greffier de la 1re chambre civile : les affaires de la 2e chambre n'apparaissent **ni dans la liste, ni dans la recherche, ni par l'adresse directe** (introuvable, 404).
4. 🔎 **Caché** : **familles protégées** — les affaires **de mineurs** et d'**instruction** ne sont visibles que de ceux qui ont le droit spécial correspondant (juge des enfants, juge d'instruction), même pour un greffier de la chambre voisine.
5. **Journal d'audit** (Administration > Paramètres et journaux) : chaque création de profil, de compte, de connexion y figure, avec l'auteur, l'adresse IP, et pour une modification **les valeurs avant / après**. 🔎 Les mots de passe n'y figurent jamais. Le journal **ne peut ni être modifié ni effacé**.

---

## 2. Organisation du tribunal

Compte : administrateur ou coordonnateur. Menu **Administration > Organisation du tribunal**.

### 2.1 Année judiciaire

> **Données à utiliser :** F2.1 de DONNEES_TEST_COMPLET.

1. **Faire** : ouvrir **Année judiciaire** ; noter l'année courante (ses dates sont **définies par le tribunal** et changent chaque année). Créer l'année suivante avec ses dates de début et de fin.
   **Attendu** : une seule année est « courante » à la fois.
2. ⛔ **Refus attendu** : date de fin avant ou égale à la date de début ; libellé d'année déjà pris.

### 2.2 Salles, magistrats et greffiers, sections, chambres

> **Données à utiliser :** F2.2 (salle), F2.3 (agent), F2.4 (section et chambre), F2.5 (formation) de DONNEES_TEST_COMPLET.

1. **Salles** : ajouter une salle (nom, type, emplacement), la désactiver. 🔎 Une salle utilisée par un rôle ne se supprime pas (on la désactive).
2. **Magistrats et greffiers** : ajouter un agent (nom, prénoms, fonction : présidente, vice-président, juge, juge d'instruction, juge des enfants, greffier, greffier en chef…). Le lier à un compte. **Attendu** : les listes déroulantes « Magistrat » ne proposent que des magistrats, « Greffier » que des greffiers.
3. **Sections** : créer une section ; **Chambres** : créer une chambre dans la section, avec son intitulé (« civile », « correctionnelle »…), la nature de sa famille (civil, pénal, instruction, mineurs). Le nom affiché doit être **« 1re chambre civile »** (et non « 1ere »).
4. ⛔ **Refus attendu** : deux chambres de même libellé dans la même année.
5. **Formations et affectations** : dans une chambre, composer la **formation** (président, assesseurs, greffier). Essayer d'affecter un greffier comme président : refusé. Affecter le même agent deux fois dans la même formation : refusé.
   🔎 **Caché** : c'est cette composition qui donne ensuite **la composition automatique des rôles**, **le périmètre de chambre** des comptes et **les destinataires des alertes**.

### 2.3 Calendrier des audiences (jours et heures)

> **Données à utiliser :** F2.6 de DONNEES_TEST_COMPLET.

1. Dans une chambre : définir le **jour et l'heure d'audience** (par exemple jeudi 08 h 30, salle 2), avec une date de début d'application. Modifier plus tard : l'**historique** garde l'ancien créneau pour les rôles déjà établis.
2. Ajouter une **audience supplémentaire** (date exceptionnelle).
3. ⛔ **Refus attendu** : deux chambres au même jour, à la même heure, dans la **même salle** → conflit signalé dans le **Planning des salles**.

### 2.4 Jours fériés et vacances

> **Données à utiliser :** F2.7 de DONNEES_TEST_COMPLET.

1. **Jours fériés et vacances** : ajouter un jour férié et une période de vacances judiciaires.
   **Attendu** : aucun rôle n'est proposé ce jour-là ; les **délais de recours** qui tombent un samedi, un dimanche ou une fermeture sont **prorogés au premier jour ouvré** ; le calcul des échéances d'alerte en tient compte.

### 2.5 Dates limites d'enrôlement (art. 7 de l'ordonnance)

1. **Dates limites d'enrôlement** : régler le nombre de jours avant l'audience (ou la règle de l'ordonnance).
   **Attendu** : à l'enrôlement, une date de première audience **trop proche** est refusée, **sauf dérogation de la Présidente** (case spéciale, voir 3.1).

### 2.6 Services et responsabilités

> **Données à utiliser :** F2.8 de DONNEES_TEST_COMPLET.

1. **Services et responsabilités** : créer un service (par exemple « Greffe civil », « Service de l'enrôlement »), y rattacher des agents avec leur **responsabilité** (chef de service, adjoint, membre).
2. **Attendu** : l'agent apparaît avec sa responsabilité dans le service.
3. 🔎 **Caché** : ces responsabilités servent à choisir les **destinataires des alertes** (page Alertes > Destinataires).

### 2.7 Reconduction de l'année

> **Données à utiliser :** F2.9 de DONNEES_TEST_COMPLET.

1. **Faire** : **Reconduire une année** : choisir l'année source (en cours) et la nouvelle année ; cocher ce qu'il faut reprendre **en plus** : la **composition** (affectations), les **audiences** (calendrier) et les **services**.
   **Attendu** : les chambres et leurs formations sont recopiées dans la nouvelle année ; un **récapitulatif** indique ce qui a été copié.
2. ⛔ **Refus attendu** : reconduire deux fois la même année vers la même cible : refusé (pas de doublons) ; reconduire vers une année inexistante : refusé.
3. **Vérifier** : ouvrir Chambres sur la nouvelle année (sélecteur d'année) : tout est là ; ajuster ensuite selon la **nouvelle ordonnance**.

### 2.8 Archive des ordonnances

> **Données à utiliser :** F2.10 de DONNEES_TEST_COMPLET.

1. **Archive des ordonnances** : téléverser l'ordonnance signée (PDF). **Attendu** : lecture dans l'application et téléchargement. ⛔ Un fichier qui n'est pas un vrai PDF (renommé) est refusé.

### 2.9 Référentiels (Administration > Référentiels)

> **Données à utiliser :** F2.11 de DONNEES_TEST_COMPLET.

Pour **chacune** des six pages — Types d'audience, Modes de saisine, Matières, Infractions, Natures d'affaire, Objets du litige :

1. **Ajouter** un élément, **le modifier**, **le désactiver**, **le supprimer** (s'il n'est pas utilisé).
2. ⛔ **Refus attendu** : doublon (même libellé) ; suppression d'un élément déjà utilisé par une affaire (message d'explication).
3. 🔎 **Caché** : **Infractions** — une liste de 14 infractions est installée **sans articles** : compléter les articles du code pénal (à valider par le tribunal). **Objets du litige** — chacun porte des **mots-clés** qui servent à proposer nature, matière et chambre à l'enrôlement (essayer la recherche sans majuscules ni accents).
4. 🔎 **Caché** : les longues listes (Objets du litige, Infractions) se chargent **20 lignes par page** ; la recherche, le tri et la pagination se font sans recharger la page.

### 2.10 Module Réglages (Administration > Paramètres et journaux > Réglages)

> **Données à utiliser :** Partie 12 (valeurs à essayer, onglet par onglet) de DONNEES_TEST_COMPLET.

Le module **Réglages** regroupe, **par onglets (rubriques)**, tout ce que le tribunal peut ajuster **sans toucher au code** : nom de la juridiction, délais de sécurité, messagerie, numéros, mise en page des actes, numérotation des décisions, intelligence artificielle, confort d'affichage. Chaque onglet a ses champs ; chaque champ a un texte d'aide à l'écran.

**Comment ça marche (valable pour tous les onglets)**

- Chaque onglet s'**enregistre séparément** (bouton « Enregistrer » de l'onglet) ; les valeurs sont **contrôlées** avant d'être acceptées (minimum et maximum de chaque champ).
- Un champ changé par rapport à la valeur d'origine est **signalé « modifié »** ; l'onglet affiche le **nombre de champs modifiés**. Le bouton **Rétablir les valeurs par défaut** remet **tout l'onglet** à sa valeur d'origine.
- L'écran indique **qui a modifié l'onglet en dernier et quand**.
- Chaque enregistrement est écrit au **Journal d'audit** (ancienne et nouvelle valeur). Les **mots de passe** n'y figurent jamais.
- Les valeurs sont conservées **chiffrées** lorsqu'elles sont secrètes ; un champ secret **n'est jamais réaffiché** (l'écran indique seulement « [défini] »).
- Un onglet n'apparaît que si le compte y a droit ; **Courriels** et **Pilotage et IA** exigent le droit spécial **« Réglages : secrets »**.

#### Onglet 1 · Organisation — l'identité de la juridiction

| Champ | Ce que ça fait |
|---|---|
| Nom de l'application | titre des onglets du navigateur et des messages |
| Nom complet de la juridiction | affiché dans les courriels envoyés par l'application |
| Sigle | affiché **en orange** dans l'en-tête (ex. TPI) |
| Ville | affichée **en vert** dans l'en-tête (ex. BINGERVILLE) |

*Essai* : changer la ville, enregistrer, recharger : l'en-tête change aussitôt.

#### Onglet 2 · Sécurité — délais et limites de connexion

| Champ | Ce que ça fait | Limites |
|---|---|---|
| Verrouillage après inactivité | sans action pendant ce délai, la session est **verrouillée** (mot de passe seul pour la rouvrir) | 1 à 240 min |
| Durée maximale du verrouillage | au-delà, la session est **fermée** : connexion complète (mot de passe **et** code par e-mail) | 5 à 1 440 min |
| Essais de déverrouillage | mots de passe erronés admis sur l'écran de verrouillage avant fermeture de la session | 3 à 10 |
| Validité du code par e-mail | temps pendant lequel le code à 6 chiffres peut être saisi | 2 à 30 min |
| Essais du code par e-mail | codes erronés admis avant d'**invalider** le code envoyé | 3 à 10 |
| Délai avant un nouvel envoi du code | temps à attendre entre deux envois | 30 à 600 s |
| Échecs de connexion avant verrouillage du compte | mots de passe erronés successifs avant le **verrouillage temporaire** du compte | 3 à 10 |
| Durée du verrouillage du compte | temps pendant lequel le compte reste bloqué | 1 à 1 440 min |
| Longueur minimale du mot de passe | s'applique aux **nouveaux** mots de passe (jamais en dessous de 8) | 8 à 64 |

*Essais* : mettre l'inactivité à 1 minute, attendre : l'écran de verrouillage apparaît ; mettre « échecs avant verrouillage » à 3 et se tromper 3 fois : compte bloqué (et alerte aux administrateurs) ; mettre la longueur minimale à 12 et créer un compte avec un mot de passe de 10 : refusé. **Remettre ensuite les valeurs par défaut.**
⛔ **Refus attendu** : une valeur hors limites (par exemple 500 pour « essais de déverrouillage », ou 6 pour la longueur du mot de passe) est rejetée avec un message.

#### Onglet 3 · Courriels (droit « secrets ») — la messagerie

| Champ | Ce que ça fait |
|---|---|
| Nom de l'expéditeur | nom affiché dans la boîte de réception |
| Adresse de l'expéditeur | adresse qui apparaît comme expéditeur (en général celle du compte d'envoi) |
| Mode d'envoi | **Test** : rien n'est envoyé, les courriels sont seulement écrits dans le journal du serveur (codes de connexion compris) ; **SMTP** : envoi réel |
| Serveur SMTP | adresse du serveur de messagerie |
| Port | 587 (chiffrement automatique) ou 465 (SSL/TLS direct) |
| Chiffrement | « Automatique (STARTTLS, port 587) » ou « SSL/TLS direct (port 465) », selon le port |
| Adresse e-mail du compte (identifiant) | identifiant de connexion au serveur |
| Mot de passe du compte | enregistré **chiffré**, jamais réaffiché (avec Gmail ou Microsoft 365 : un « mot de passe d'application ») |

- **Tester l'envoi** : saisir une adresse, **Envoyer un test**. **Attendu** : message « envoyé » ou **erreur expliquée en clair** (serveur injoignable, identifiants refusés, port incorrect), sans jamais afficher le mot de passe.
- ⛔ **Refus attendu** : adresse d'expéditeur invalide ; port hors 1–65 535.
- 🔎 **Caché** : tant que le mode est « Test », **personne ne reçoit** de code de connexion : c'est pourquoi les comptes d'essai utilisent les **codes de secours**. En production, passer en **SMTP**.

#### Onglet 4 · Affaires — numéros et délais du circuit

| Champ | Ce que ça fait |
|---|---|
| Numéro RG (civil) | **Saisi à la main** (numéro du greffe, sans contrôle de la date d'audience) ou **Attribué par l'application** (sans doublon ni trou, date limite d'enrôlement contrôlée) |
| Numéro RP (pénal) | normalement le numéro du **parquet**, saisi à la main ; ou numéro généré |
| Numéro RI (instruction adultes) | registre des cabinets d'instruction pour les adultes |
| Numéro RE (instruction enfants) | registre des cabinets des juges des enfants |
| Durée du mandat de dépôt avant échéance | sert à **calculer l'échéance** d'un détenu quand elle n'est pas saisie (1 à 1 500 jours) |
| Alerte avant l'échéance d'un détenu | un détenu dont l'échéance tombe dans ce délai passe **en orange** (1 à 365 jours) |
| Délai de répartition d'une requête | une requête non répartie après ce délai est **« en retard »** et alerte le secrétariat (1 à 30 jours) |
| Taille maximale d'un classeur de reprise (Excel) | limite de l'importation d'affaires (1 à 50 Mo ; le serveur PHP peut en accepter moins) |
| Taille maximale d'une pièce du dossier numérique | limite de chaque PDF ou image (1 à 100 Mo) |

*Essais* : passer **RG en « Attribué par l'application »** : l'enrôlement propose un numéro automatique et contrôle la date limite ; revenir en « Saisi à la main ». Mettre « durée du mandat » à 100 : l'échéance d'un détenu sans date saisie passe à mandat + 100 jours. Mettre la taille maximale d'une pièce à 1 Mo : un PDF de 3 Mo est refusé avec un message de taille.
🔎 **Caché** : changer le mode d'un registre **n'efface rien** ; seuls les **prochains** enrôlements suivent la nouvelle règle.

#### Onglet 5 · Impression des actes — mise en page des décisions

| Champ | Ce que ça fait |
|---|---|
| Police du corps / taille du corps | texte de la décision (Centaur 16 dans le modèle du tribunal ; si la police n'est pas installée, une police de remplacement est utilisée) |
| Police du timbre / taille du timbre | en-tête de l'acte (timbre), 12 par défaut |
| Interligne de la qualité | 100 % = interligne simple, comme le modèle Word |
| Interligne du factum | 110 % par défaut (moyenne des 100, 107 et 115 des documents Word) |
| Marges haut / bas / gauche / droite | marges de la page A4 (5 à 80 mm) |
| Largeur / hauteur du timbre | dimensions de la colonne de gauche de la première page (57 mm de large dans le modèle) |
| Distance du timbre au bord gauche / haut | position du timbre depuis les bords de la feuille |
| Retrait du texte de la qualité | la qualité commence à droite du timbre ; le factum occupe toute la largeur |

*Essai* : augmenter la taille du corps à 14, **imprimer** une décision (Rédaction > imprimante) et comparer avec l'ancienne impression ; déplacer le timbre de 10 mm. Remettre les valeurs par défaut. ⛔ Une marge de 200 mm est refusée.

#### Onglet 6 · Décisions — la numérotation

| Champ | Ce que ça fait |
|---|---|
| Moment où le numéro est attribué | **À la validation** (le numéro D-N/année est généré automatiquement quand le juge valide après son factum) · **Par le greffier de la chambre** (bouton « Donner le numéro » quand le juge a terminé ; la validation **exige** alors ce numéro) · **À la signature** (le texte validé garde un numéro en pointillés jusqu'à la signature) |

🔎 **Caché** : le choix **change tout le circuit de la partie 7.3** (qui fait quoi, quel bouton apparaît, quel refus s'affiche). **Tester les trois modes** avec une décision chacun. Un numéro déjà attribué **ne change jamais** : ne modifier ce réglage qu'en connaissance de cause (valeur à confirmer avec la greffière en chef).

#### Onglet 7 · Pilotage et IA (droit « secrets ») — l'analyse

| Champ | Ce que ça fait |
|---|---|
| Analyse par l'IA | **Non** : analyse automatique seulement ; **Oui** : le bouton « Analyser avec l'IA » apparaît sur Analyse et pilotage (si la clé est aussi présente dans le `.env`) |
| Modèle | identifiant du modèle d'IA à utiliser |
| Analyses par jour (au plus) | plafond quotidien pour maîtriser le coût (1 à 200) ; au-delà, l'analyse automatique reste disponible |

- 🔎 **Caché** : la **clé d'API ne se saisit pas ici** : elle se place dans le fichier `.env` du serveur (`PILOTAGE_IA_CLE`). **Seuls des totaux** (nombres d'affaires, délais, retards par chambre) sont transmis : **jamais** un nom de partie, un numéro d'affaire ni un document. L'IA ne part que **sur demande**.
- *Essai* : avec « Non », la page Analyse et pilotage n'offre que l'analyse automatique ; avec « Oui » **mais sans clé** dans le `.env`, l'IA reste **indisponible** (l'écran indique que l'analyse par l'IA n'est pas activée) ; avec « Oui » **et** la clé, le bouton « Analyser avec l'IA » apparaît.

#### Onglet 8 · Interface — le confort

| Champ | Ce que ça fait |
|---|---|
| Lignes par page dans les tableaux | 10, 25, 50 ou 100 lignes avant la pagination pour les **petits tableaux** (salles, sections, matières…). Les **longues listes** (affaires, détenus, requêtes, rôles, journaux…) affichent **20 lignes** par page, chargées au fur et à mesure |

#### Contrôles à faire sur tout le module

1. **Faire** : sur chaque onglet, modifier **un champ**, **Enregistrer**, **recharger** : la valeur est conservée et l'onglet affiche « modifié ».
2. **Faire** : **Rétablir les valeurs par défaut** de l'onglet : tout revient ; le nombre de champs modifiés tombe à 0.
3. ⛔ **Refus attendu** : valeur non numérique dans un champ entier ; valeur hors limites ; texte plus long que le maximum ; **liste** modifiée à la main avec une option inexistante.
4. **Droits** : avec un compte **sans** « Réglages : secrets », les onglets **Courriels** et **Pilotage et IA** sont **absents** et les adresses directes renvoient **403**. Avec un compte **sans** le droit « Réglages », la page entière est refusée.
5. **Journal d'audit** : une ligne par enregistrement, avec avant / après ; **aucun mot de passe** lisible.
6. 🔎 **Caché** : la commande `php artisan tpi:reglages:reinitialiser` remet **une rubrique** à ses valeurs d'origine depuis le serveur (secours si un réglage bloque la connexion, par exemple une inactivité réglée trop bas).
7. **Effet immédiat** : un réglage s'applique **sans redémarrer** ; vérifier avec l'en-tête (organisation) et l'inactivité (sécurité).

---

## 3. Enregistrer les affaires (Affaires > Liste des affaires)

Compte : **greffier de chambre** (les cas pénal et instruction : compte du greffier concerné, ou coordonnateur). Le numéro RG est **saisi à la main** (celui du registre du greffe) : l'application ne le fabrique pas, elle le **contrôle**.

### 3.1 Affaire civile (série RG)

> **Données à utiliser :** F3.1 (et A.4 : affaires prêtes) de DONNEES_TEST_COMPLET.

1. **Faire** : **Enrôler** une affaire civile : numéro RG (`1500/26`), date du RG, mode de saisine (assignation, requête…), **objet du litige** (texte libre : « paiement de loyers impayés et expulsion »), parties.
   **Attendu** : l'application **propose** la nature d'affaire, la matière, la chambre et le modèle de décision d'après les **mots-clés** de l'objet ; plusieurs propositions → la plus précise d'abord. Un mot-clé présent dans deux chambres oblige à **choisir**.
2. **Parties** : ajouter un demandeur (homme), une défenderesse (femme), un couple, une **personne morale** (dénomination), l'**État**. Pour chaque partie : nom, prénoms, naissance (date exacte **ou** « vers 1970 »), nationalité, profession, domicile, **avocat** (texte libre ou fiche de la liste Avocats).
   **Attendu** : intitulé « KOFFI Adjoua c/ Société BATIMAT SARL » ; les couples et personnes morales s'écrivent correctement.
3. **Première audience** : la date est **fixée dès l'enrôlement** (calendrier de la chambre) ; l'heure vient du calendrier.
4. ⛔ **Refus attendu** (essayer **chaque** cas) :
   - **même numéro RG** déjà utilisé : « déjà enrôlé » — sauf si l'interrupteur **Jonction** est activé ;
   - **Jonction** vers un numéro **qui n'existe pas** : « Aucune affaire n'a le numéro … ; ce n'est pas une jonction » ;
   - **Jonction** vers une affaire d'**une autre chambre** : « Une jonction se fait dans la même chambre » ;
   - **numéro d'une affaire annulée** : « un numéro n'est jamais réutilisé » ;
   - **date du RG dans le futur** ;
   - **première audience trop proche** (art. 7) sans dérogation ; avec la case « **Dérogation de la Présidente** » (réservée à qui a ce droit spécial) : acceptée ;
   - **aucune partie** ; **objet vide** ; nature d'affaire hors liste (la nature se choisit dans une **liste fermée**).
5. 🔎 **Caché** : le **greffier** n'emploie pas le mot « enrôlement » dans l'écran (vocabulaire « Saisie des affaires » réglé par le tribunal).
6. 🔎 **Caché** : la saisie simultanée (deux greffiers enrôlant en même temps) ne crée **ni doublon ni trou** de numéro : essayer à deux navigateurs.

### 3.2 Fiche de l'affaire

Ouvrir une affaire (la ligne ou le numéro). Vérifier : en-tête (numéro, chambre, statut), **parties**, historique, **dossier numérique** (onglet), rôles où elle a figuré, décisions, alertes, **journal** de l'affaire.

1. **Corriger le numéro** (droit spécial « corriger le numéro ») : renseigner le nouveau numéro et le **motif**. **Attendu** : l'ancien numéro est conservé dans la fiche. ⛔ Refusé : numéro déjà pris ; affaire annulée ou jointe ; numéro attribué par l'application (seuls les numéros reportés à la main se corrigent).
2. **Modifier** l'affaire (objet, chambre, première audience) ; **réattribuer** à une autre chambre (droit spécial) : l'affaire change de périmètre.
3. **Annuler** une affaire (motif obligatoire) : elle n'apparaît plus dans les rôles, son numéro n'est plus jamais réutilisable.
4. **Filtres de la liste** : pastilles (RG, RP, RI, RE), chambre, période, statut ; la recherche en haut cherche numéro, objet, parties. Compter les lignes : la liste se charge **par pages de 20** sans recharger.
5. **Exporter** la liste (Excel) en gardant les filtres : le fichier ne contient que ce qui est filtré **et visible** pour ce compte.

### 3.3 Affaire pénale (série RP)

> **Données à utiliser :** F3.2 de DONNEES_TEST_COMPLET.

1. **Faire** : **Enrôler une affaire pénale** : numéro RP, **N° du parquet**, infraction (liste : la nature de la prévention et les **articles** se remplissent, modifiables), prévenus, victimes.
2. **Prévenu détenu** : renseigner **mandat de dépôt** (date), **lieu de détention**, échéance éventuelle.
   **Attendu** : il apparaît dans **Affaires > Détenus**, classé par **échéance** (les plus pressants d'abord), avec « échéance dépassée / proche / dans N jours ».
3. ⛔ **Refus attendu** : même **N° du parquet** → « déjà enrôlé sous RP … » ; avec jonction cochée : accepté. Mandat de dépôt **avant** la date de l'infraction ou dans le futur : refusé.
4. **Détenus** : filtrer par pastille (dépassées, proches, autres), chercher un nom ; la page affiche 20 détenus par page. 🔎 Une personne **libérée** (décision ou date de libération) disparaît de la liste.
5. **Scellés** (fiche de l'affaire pénale) : ajouter un scellé (numéro, désignation, nature, lieu de conservation, état) ; modifier l'état (conservé, restitué, détruit…). ⛔ Numéro de scellé en doublon dans la même affaire : refusé.

### 3.4 Instruction (RI adultes, RE enfants) et mineurs

> **Données à utiliser :** F3.3 de DONNEES_TEST_COMPLET.

1. **Faire** : enrôler une affaire d'**instruction** : choisir la nature (la série RI ou RE **suit la nature**), le cabinet, l'inculpé (avec détention), la victime.
2. **Attendu** : avec le compte **sans** droit « instruction », l'affaire est **invisible** (ni liste, ni recherche, ni adresse directe). Avec le compte du **juge d'instruction** ou du **juge des enfants** : visible.
3. ⛔ **Refus attendu** : une nature d'instruction avec la série civile ; un inculpé **sans rôle** ; mineur sans le droit « mineurs ».

### 3.5 Avocats (Affaires > Avocats)

> **Données à utiliser :** F3.4 de DONNEES_TEST_COMPLET.

1. Ajouter un avocat (type : avocat ou cabinet, nom, prénoms, cabinet, barreau, téléphone, e-mail), le **modifier**, le **désactiver**, marquer « à compléter ».
2. ⛔ **Refus attendu** : doublon (même clé nom + prénoms) ; supprimer un avocat **rattaché à des parties** : refusé (désactiver).
3. **Attendu** : à l'enrôlement, l'avocat actif apparaît en liste, l'inactif non. La page se charge 20 lignes par page.

### 3.6 Dossier numérique (Affaires > Dossier numérique)

> **Données à utiliser :** F3.5 de DONNEES_TEST_COMPLET.

1. Dans la fiche d'une affaire, onglet **Dossier numérique** : ajouter **plusieurs pièces** à la fois (PDF et images), avec type, titre et date.
2. ⛔ **Refus attendu** : fichier qui n'est ni un vrai PDF ni une vraie image (même renommé) → refus de **tout le lot** ; fichier trop gros ; plus de 20 fichiers ; **doublon** (même contenu) ignoré avec message ; pièce dans le **futur**.
3. Ouvrir une pièce dans la **visionneuse** ; la télécharger (droit « télécharger »). 🔎 Chaque ouverture et chaque téléchargement sont **journalisés** (qui, quand) ; le chemin du fichier ne sort **jamais** dans la page.
4. **Retirer** une pièce avec un motif : elle n'est **pas supprimée**, elle est retirée de la vue ; le motif reste au journal.
5. **Localisation du dossier papier** : saisir l'emplacement (« Salle d'archives 2, étagère B »). **Attendu** : visible dans la liste « Dossier numérique ».
6. Dans **Affaires > Dossier numérique** : pastilles « Avec pièces » / « Dossiers vides », recherche, pagination de 20.
7. ⛔ Affaire annulée : plus de nouvelles pièces.

### 3.7 Importation d'affaires existantes (Affaires > Importation affaire)

> **Données à utiliser :** F3.6 de DONNEES_TEST_COMPLET.

1. **Faire** : télécharger le **modèle** Excel ; remplir 5 lignes correctes et 3 lignes volontairement fausses (numéro en double, date invalide, chambre inconnue) ; lancer l'**assistant en cinq étapes** (fichier, correspondance des colonnes, contrôle, import, rapport).
2. **Attendu** : barre de progression ; **rapport des erreurs** ligne par ligne ; les lignes correctes sont importées, **les anciens numéros sont conservés**. Possibilité d'**annuler** l'import (tout ce qu'il a créé disparaît).
3. ⛔ Fichier non Excel, trop gros ou macro-activé : refusé.

### 3.8 Recherche plein texte

> **Données à utiliser :** F3.7 de DONNEES_TEST_COMPLET.

1. Champ **« Rechercher un dossier »** dans l'en-tête : saisir deux mots (nom d'une partie + mot de l'objet).
   **Attendu** : **tous les mots** doivent se retrouver ; résultats groupés (affaires, décisions, requêtes, décisions pénales, pièces, assistance judiciaire). Chacun ne voit que **ce que ses droits lui permettent**.
2. 🔎 **Caché** : les caractères `%` et `_` sont traités comme du texte (taper `100%` ne ramène pas tout).

---

## 4. Requêtes, hérédité et mise en état

### 4.1 Requêtes (Affaires > Requêtes et hérédité)

> **Données à utiliser :** F4.1 (dépôt), F4.2 (répartir, traiter, classer) de DONNEES_TEST_COMPLET.

1. **Greffier** : déposer une requête de chaque type : *hérédité*, *ordonnance sur requête*, *voies d'exécution*, *injonction de payer*, *bref délai*, *autre*. Numéros **RQ 1/26, RQ 2/26…** automatiques, sans trou.
   **Attendu** : statut « À répartir ».
2. **Requête en hérédité** : saisir le **défunt** (nom, date et lieu de décès) et les **requérants** (un par ligne).
   **Attendu** : mention « Succession de … († date) » dans la liste.
3. ⛔ **Refus attendu** : date de dépôt dans le futur ; requête sans requérant ; hérédité sans défunt.
4. **Présidente / secrétariat** : **répartir** une requête vers une chambre (note de répartition possible). **Attendu** : statut « Répartie » ; elle devient visible du greffier de cette chambre.
   🔎 **Caché** : une requête **non répartie depuis plus de 2 jours** passe en **retard** (puce rouge « depuis N jours ») et déclenche une **alerte** au secrétariat de la présidence.
5. **Greffier** : tenter de marquer **traitée** une injonction de payer : ⛔ le bouton n'existe pas, mention « **signature réservée** ». **Présidente** : la marquer traitée → « signée le … par … ». Une requête **d'hérédité** : le greffier la traite lui-même.
6. ⛔ **Refus attendu** : traiter une requête **non répartie** : « Une requête doit être répartie avant d'être traitée » ; répartir ou modifier une requête **déjà traitée ou classée** : refusé.
7. **Classer sans suite** (motif) une autre requête. **Modifier** (corriger) une requête ouverte.
8. **Périmètre** : le greffier voit les requêtes qu'il a déposées et celles réparties vers ses chambres, pas celles des autres. Vérifier avec un second greffier.
9. Pastilles « À répartir / Réparties / Clôturées », recherche, pagination de 20.

### 4.2 Mise en état (Affaires > Mise en état)

> **Données à utiliser :** F4.3 de DONNEES_TEST_COMPLET.

1. **Greffier** : **Confier une affaire** civile RG à un juge chargé (date de réception du dossier, juge de la chambre).
   **Attendu** : statut « En cours » ; l'affaire n'est **plus proposée** pour les rôles ordinaires.
2. ⛔ **Refus attendu** (essayer tous) :
   - affaire **pénale** ou d'instruction : seules les affaires **RG** passent en mise en état ;
   - date de réception **dans le futur** ;
   - juge qui **n'est pas un magistrat de la chambre** de l'affaire ;
   - **première audience avant la réception** du dossier ;
   - **deuxième mise en état ouverte** pour la même affaire (une seule « en cours » à la fois) ;
   - affaire hors de votre périmètre : « introuvable ».
3. **Clôturer** : n° et date de l'**ordonnance de clôture**, chambre et date de l'**audience de jugement**.
   ⛔ **Refus** : clôture avant la réception ou avant la première audience ; audience de jugement **avant** la clôture.
   **Attendu** : « Clôturée » ; l'affaire est **« à mettre au rôle »** à la date indiquée.
4. **Retirer** (motif obligatoire) : l'affaire redevient ordinaire.
5. 🔎 **Caché** : une mise en état de **plus de 90 jours** depuis la première audience est marquée « **En cours, longue** » (rouge) et compte dans la vigilance. ⛔ Une mise en état **clôturée ou retirée ne se modifie plus**.
6. Tuiles de l'en-tête : en cours, longues, clôturées en 30 jours, **durée moyenne**. Pastilles, recherche, pagination de 20.

---

## 5. Rôles d'audience (Audiences > Rôles d'audience)

Compte : **greffier de chambre**. Préparer **au moins trois chambres** avec des affaires : une chambre civile (1re), une chambre de référé, une chambre correctionnelle (pour le rôle pénal par le parquet).

### 5.1 Créer et composer un rôle civil

> **Données à utiliser :** F5.1, F5.2 de DONNEES_TEST_COMPLET.

1. **Faire** : sous les tuiles, le bandeau orange liste les **audiences des 14 prochains jours sans rôle** ; cliquer la pastille d'une chambre. La fenêtre propose chambre, date, heure et salle (du calendrier). **Créer et composer le rôle**.
   **Attendu** : le rôle se compose seul en **trois rubriques** : **Affaires en délibéré**, **Affaires anciennes**, **Affaires nouvelles**.
   - *Nouvelles* : affaires dont la **première audience** est cette date ;
   - *Anciennes* : affaires **renvoyées** dont la date est arrivée (et celles **jamais portées** à un rôle) ;
   - *Délibérés* : affaires dont la dernière suite est « mise en délibéré ».
   🔎 **Caché** : **ne figurent pas** : les affaires jugées, radiées, annulées, jointes (une **jointe** apparaît sur la ligne de sa principale : « 690/26 / Et 848/26 ») ni celles **en mise en état** ouverte.
2. **Composition automatique** : le président, les assesseurs, le greffier viennent de la **formation** de la chambre **à la date du rôle** (composition datée). Changer l'affectation puis recomposer : la composition change.
3. **Affaires proposées** : le bloc de droite indique ce qu'il reste à ajouter ; **Ajouter au rôle**.
4. **Ajouter à la main** une affaire par son numéro (`1391/25`, `RP 1334/26`) : parties, avocats, objet repris. Ajouter une **ligne libre** sans numéro.
5. **Modifier** une ligne (texte, rubrique), la **monter / descendre**, la **retirer** (corbeille).
6. **Audience** (en-tête du rôle) : date, heure, salle, lieu, date de signature, signataire, interrupteur **Afficher les avocats**.
7. ⛔ **Refus attendu** :
   - **deux rôles** pour la même chambre et la même date : « Un rôle existe déjà » ;
   - date trop éloignée (plus d'un an) ou avant 2020 ;
   - **publier un rôle vide** : bouton grisé ;
   - **publier avec une salle en conflit** (même salle, même créneau qu'un autre rôle) : refusé ;
   - modifier un rôle **publié** (il faut d'abord le rouvrir) ;
   - **supprimer** un rôle déjà publié : refusé ;
   - créer un **rôle pénal** sans le droit « Rôles pénaux » (réservé au parquet) : refusé.

### 5.2 Publier, versions, exports

> **Données à utiliser :** F5.2, F5.5 de DONNEES_TEST_COMPLET.

1. **Publier** : la **version 1** est figée (numérotée). **Rouvrir** (motif) → corriger → republier : **version 2** ; la version 1 reste consultable.
2. **Exporter** en **Word** puis en **PDF** : en-tête automatique (juridiction, chambre, date, heure, salle, composition), trois rubriques, lignes numérotées, avocats si l'interrupteur est actif. Ouvrir et relire le fichier.
3. 🔎 **Caché** : après publication, l'audience devient disponible dans **Feuille d'audience**.

### 5.3 Plusieurs chambres, plusieurs jours

> **Données à utiliser :** F5.3, F5.4 de DONNEES_TEST_COMPLET.

1. Créer des rôles pour **trois chambres** le **même jour**, des salles **différentes** ; puis deux rôles dans la **même salle** à la **même heure**.
2. **Planning des salles** : vue de la semaine salle par salle ; le **conflit** est signalé en rouge sur les deux rôles et **bloque** leur publication. Changer la salle d'un des deux : le conflit disparaît.
3. **Rôle pénal** (compte du **parquet**) : créer un rôle de type pénal ; ajouter des affaires RP ; les rubriques et le type de procédure s'adaptent. Un compte sans le droit pénal ne le voit pas modifiable.
4. **Rôle de référé** : affaires d'urgence ; 🔎 la **limite de renvois** d'un référé-expulsion (un seul renvoi) est surveillée (voir 11).

### 5.4 Rôle du jour (Audiences > Rôle du jour)

1. **Faire** : ouvrir le **Rôle du jour** un jour où plusieurs rôles sont publiés (créer-en trois à la date du jour).
   **Attendu** : les rôles de la journée en **accordéon** (heure, chambre, salle, **avancement** — la barre orange à côté du « x/y traité(s) » — état de l'audience) ; une rangée de pastilles permet d'**aller** à un rôle ; **Tout déplier / Tout replier**.
2. **Attendu** : mise à jour **automatique toutes les 30 secondes** (noter une suite sur la feuille d'audience d'un autre navigateur et attendre) ; bouton **plein écran** pour l'affichage en salle.
3. 🔎 Un rôle **non publié** ou d'une chambre **hors périmètre** n'y figure pas.

---

## 6. Faire les audiences

### 6.1 Feuille d'audience (Audiences > Feuille d'audience)

> **Données à utiliser :** F6.1 de DONNEES_TEST_COMPLET.

Compte : **greffier de chambre**. Ouvrir la feuille d'un rôle **publié** du jour.

1. **Composition** : reprise de la formation ; **noter un empêchement** (juge absent) et choisir un remplaçant.
2. **Pour chaque affaire**, noter l'**issue** :

| Issue | Ce qu'elle déclenche |
|---|---|
| **Renvoyée** | motif obligatoire (liste de motifs courants ou texte libre) + **date de renvoi** ; l'affaire reviendra **automatiquement** sur le rôle de cette date |
| **Mise en délibéré** | date de délibéré ; l'affaire reviendra dans la rubrique « Délibérés » |
| **Jugée** | termine le parcours à l'audience ; propose de **rédiger la décision** |
| **Radiée** | termine le parcours |
| **Jointe à une autre** | choisir l'affaire principale ; termine le parcours |
| **Désistement** | termine le parcours |

3. **Présence** : toutes les parties, avocats seulement, demandeur seul, défendeur seul, aucune partie.
4. ⛔ **Refus attendu** :
   - **renvoi sans motif** ou **sans date** ; date de renvoi **avant l'audience** ;
   - **jointe** sans affaire principale, ou principale **dans une autre chambre** ;
   - issue notée sur un rôle **non publié** (404) ;
   - affaire d'un autre périmètre (404).
5. **Clôturer l'audience** : bouton « Terminer l'audience ». **Attendu** : la feuille se **fige**. ⛔ Modifier après terminaison : « L'audience est terminée : rouvrez-la pour corriger. » **Rouvrir** (droit nécessaire) → corriger → re-terminer.
6. 🔎 **Caché** : si **toutes** les lignes n'ont pas d'issue, l'application prévient avant de terminer.
7. **Observations** de l'audience (facultatif) : enregistrées à la clôture.

### 6.2 Renvois et délibérés (Audiences > Renvois et délibérés)

> **Données à utiliser :** F6.2 de DONNEES_TEST_COMPLET.

1. **Faire** : ouvrir la page : deux listes — **affaires renvoyées** (avec leur date) et **affaires en délibéré** (avec le délai restant).
2. **Changer la date d'un renvoi** (bouton date) : saisir une nouvelle date ouvrée ; **Attendu** : l'affaire figurera au rôle de cette nouvelle date. ⛔ Date dans le passé, un dimanche, un jour férié ou de vacances : refusée.
3. ⛔ **Refus attendu** : « Cette affaire a déjà un passage plus récent sur un rôle : modifiez-y la suite » ; « Cette suite a changé : actualisez la page » (deux greffiers modifient la même ligne).
4. **Vider un délibéré** : noter la décision (jugée) depuis cette page.
5. 🔎 **Caché** : les délibérés dépassant leur délai sont en **rouge** et déclenchent une alerte (voir 11).

### 6.3 Suites d'audience (Audiences > Suites d'audience)

> **Données à utiliser :** F6.3 de DONNEES_TEST_COMPLET.

1. Consulter la liste de toutes les suites par période, chambre, issue ; filtrer ; **exporter en PDF**.
2. **Attendu** : les chiffres concordent avec les feuilles saisies en 6.1.

### 6.4 Séances hors audience (Audiences > Séances hors audience)

> **Données à utiliser :** F6.4 de DONNEES_TEST_COMPLET.

1. **Planifier** une séance : type **Réception des requêtes**, **Conciliation** ou **Tutelle**, date, heure, lieu, **juge**, **greffier**, chambre (ou toutes).
   **Attendu** : la liste « Magistrat » ne contient que des magistrats, « Greffier » que des greffiers.
2. **Ajouter des dossiers** à la séance (affaire enrôlée ou texte libre, objet, issue) ; les retirer.
3. ⛔ **Refus attendu** : déclarer **tenue** une séance **à venir** ; modifier une séance **tenue ou annulée** : « ne se modifie plus » ; ajouter une affaire **hors périmètre**.
   Corriger la date à aujourd'hui ou avant, puis **déclarer tenue** : accepté. **Annuler** une autre séance (droit d'annulation).
4. Pastilles Prévue / Tenue / Annulée, recherche, pagination de 20. Tuiles : séances à venir, tenues.

### 6.5 Analyse et pilotage (Audiences > Analyse et pilotage)

> **Données à utiliser :** F6.5 de DONNEES_TEST_COMPLET.

1. Choisir une période et une chambre : **indicateurs de célérité** (taux de renvois, durée moyenne, délibérés en retard, rôle moyen par audience), **graphiques** et **analyse en langage clair** générée automatiquement.
2. **Exporter la note** en PDF. **Attendu** : les chiffres suivent les feuilles saisies ; sans données, l'analyse l'indique sans erreur.

---

## 7. Rédiger la décision jusqu'à la signature

### 7.1 Modèles et blocs (Décisions > Modèles de décision, Blocs de texte)

> **Données à utiliser :** F7.3 de DONNEES_TEST_COMPLET.

Compte : **magistrat** ou **coordonnateur**.

1. **Modèles de décision** : ouvrir le modèle « jugement d'hérédité » ; lire ses **deux parties** (qualité et factum), les **variables** `{{ ... }}` et les **blocs** `{{> code }}`. Créer une **version** ; l'activer ; essayer de le **supprimer** s'il est utilisé : refusé.
2. **Blocs de texte** : créer un bloc (code, nom, catégorie visas / formule / dépens…, contenu avec variables) ; **le modifier** ; vérifier la colonne **« Utilisé dans »** (nombre de modèles).
3. ⛔ **Refus attendu** : code en majuscules ou avec espaces ; code en doublon ; variable inexistante (`{{ x|nexistepas }}`) ; supprimer un bloc **utilisé par un modèle** ; changer le **code** d'un bloc utilisé.
4. Liste des blocs : 20 par page, tri par colonne, recherche.

### 7.2 Rédiger (Décisions > Rédaction)

> **Données à utiliser :** F7.1 de DONNEES_TEST_COMPLET.

Compte : **magistrat** (rédaction) ; **greffier** (numéro) selon le réglage.

1. **Faire** : **Rédiger une décision** : choisir l'**affaire** (une affaire jugée à l'audience, ou le bouton « rédiger » de la feuille d'audience qui présélectionne l'affaire) et le **modèle**.
   **Attendu** : le formulaire est déduit du modèle, en **blocs repliables**, avec un **aperçu A4 en direct** (zoom 125 %). Les champs connus sont **déjà remplis** : juridiction, cour d'appel, numéro du RG, parties, avocats, composition, date d'audience, type de décision.
2. **Qualité** : générée depuis l'affaire (parties, avocats, mode de comparution) ; **factum** : visas, faits, motifs, dispositif, à rédiger.
3. **Enregistrer** (brouillon) → recharger → les valeurs sont conservées. **Recomposer** la qualité après un changement dans l'affaire.
4. **Retoucher** une phrase directement dans l'aperçu : la retouche est mémorisée ; revenir au texte généré.
5. ⛔ **Refus attendu** :
   - **marquer « rédigée »** avec des variables **manquantes** : « Enregistrez puis complétez : il manque … » ;
   - rédiger pour une affaire **d'une autre chambre** (404) ;
   - un modèle **sans version** n'est pas proposé.

### 7.3 Le circuit : brouillon → rédigée → numéro → validée → signée → délivrée

> **Données à utiliser :** F7.2 de DONNEES_TEST_COMPLET.

| Étape | Qui | Ce qu'il faut faire | Contrôle à essayer |
|---|---|---|---|
| **Rédigée** | magistrat | « Marquer comme rédigée » | ⛔ impossible si champs manquants ; impossible si ce n'est pas un brouillon |
| **Numéro** | greffier de la chambre *(si le réglage Décisions place le numéro à la rédaction)* | « Donner le numéro » : format **D-N/AAAA** (D-145/2026, compteur par année, sans trou) | ⛔ le numéro ne se donne qu'à une décision **rédigée** ; déjà numérotée : refusé ; réglage différent : « Le numéro n'est pas attribué à cette étape » |
| **Validée** | magistrat avec le droit « valider » | « Valider » : le **texte est figé** | ⛔ sans numéro (si le réglage l'exige) : « Le greffier de la chambre doit d'abord donner le numéro » ; déjà validée : refusé |
| **Renvoyer en rédaction** | magistrat (« valider ») | repasser une rédigée en brouillon pour correction | ⛔ impossible une fois **signée** |
| **Signée** | magistrat avec le droit « signer » | date de signature | ⛔ date **dans le futur** ; décision **non validée** : « Seule une décision validée peut être signée » ; déjà signée |
| **Délivrée** | greffier avec le droit « délivrer » | type (**expédition**, **grosse**, **copie**), **à qui**, date de remise | ⛔ décision **non signée** ; **une grosse** déjà remise à la même personne (une copie reste possible) ; date dans le futur |

1. **Faire** : conduire **une décision complète** (jugement d'hérédité) à travers **toutes** ces étapes, en changeant de compte à chaque étape (magistrat, greffier, magistrat, greffier). **Attendu** : à chaque étape, le **statut** change, le **suivi** (frise) se remplit, le **Journal d'audit** garde une ligne, et la **cloche** du suivant s'allume (voir 11).
2. **Imprimer** la décision (bouton imprimante) : police **Centaur 16** pour le corps, **timbre 12** (colonne de gauche : cour d'appel, tribunal, RG, chambre, type, numéro, parties). Enregistrer en PDF : mise en page A4 correcte, pas de variable `{{ }}` restante.
3. 🔎 **Caché** : après **validation**, le texte ne se modifie plus. Seule la **correction** (droit spécial, motif obligatoire) crée une **nouvelle version** tracée ; l'ancienne reste consultable.
4. **Supprimer un brouillon** (corbeille) : accepté. ⛔ **Supprimer** une décision validée, signée ou délivrée : bouton absent, route refusée.
5. 🔎 **Caché** : le **numéro est définitif** : une décision supprimée **n'est jamais renumérotée** (pas de réutilisation).
6. **Liste Rédaction** : 20 par page, tri, recherche (référence ou nom du modèle), états en couleur (brouillon orange, rédigée bleu, validée vert, signée violet, délivrée doré).

### 7.4 Registre des décisions (Décisions > Registre des décisions)

> **Données à utiliser :** F7.4 de DONNEES_TEST_COMPLET.

1. **Faire** : le registre ne montre que les décisions **dont le texte est figé** (validée, signée, délivrée). Filtrer par **année**, **chambre**, **statut** (les compteurs des pastilles suivent) ; chercher par **numéro** ou référence.
2. **Attendu** : 20 décisions par page ; colonne « **Remises** » (nombre de délivrances) ; l'œil ouvre la décision (si le compte a le droit Rédaction).
3. **Exporter** en **Excel** et **PDF** (droit « exporter ») : les filtres sont repris ; le fichier est tracé au journal. ⛔ Sans le droit : bouton absent, route refusée.

---

## 8. Pénal, instruction et services annexes

### 8.1 Répertoire pénal (Décisions > Répertoire pénal)

> **Données à utiliser :** F8.1 de DONNEES_TEST_COMPLET.

Compte : **parquet** ou **greffier pénal**.

1. **Enregistrer une décision pénale** : choisir l'**affaire** RP, le **prévenu**, la date, la contradiction (contradictoire, défaut, réputée contradictoire), l'**issue** (condamné, relaxé, acquitté, dispense de peine, irrecevable, incompétent…), la **peine**.
2. ⛔ **Refus attendu** (tous) :
   - **condamné sans peine** ;
   - **relaxé avec une amende** ;
   - **sursis supérieur à l'emprisonnement** ; **sursis sans emprisonnement** ;
   - date **avant l'enregistrement de l'affaire** ou dans le futur.
3. Condamner à **24 mois dont 6 avec sursis et 100 000 F** : la peine s'affiche « **2 ans dont 6 mois avec sursis ; Amende : 100 000 F CFA** ».
4. **Relaxer un prévenu détenu** : dans **Détenus** il n'est plus détenu (libération à la date de la décision).
5. **Minute** : état « À transmettre » avec le **nombre de jours** ; **enregistrer la transmission** (date, destinataire). ⛔ Date avant la décision : refusée.
6. **Filtres** : pastilles « Minute à transmettre / transmise ». 20 par page. 🔎 Au-delà de **30 jours** sans transmission : ligne rouge et **alerte** « Minute pénale à transmettre ».
7. **Supprimer** (droit de suppression) : la détention du prévenu reprend si la décision avait ordonné sa libération.

### 8.2 Dossiers d'instruction (Instruction et annexes > Dossiers d'instruction)

> **Données à utiliser :** F8.2 de DONNEES_TEST_COMPLET.

Compte : **juge d'instruction** ; puis **juge des enfants**.

1. **Faire** : ouvrir un dossier (onglets **Le dossier**, **Inculpés**, **Victimes**) ; saisir le **réquisitoire** (date), les faits, la qualification, l'**ordonnance de clôture** ; pour chaque inculpé : **statut de détention** (détenu, libre, sous contrôle judiciaire), date de mandat, **renouvellements** ; pour chaque victime : auditions.
2. ⛔ **Refus attendu** : clôture **avant** le réquisitoire ; renouvellement de détention **avant** le mandat ; date dans le futur ; un compte **sans** droit « modifier » : tout est en lecture seule.
3. **Commissions rogatoires** (bouton en haut) : choisir **cabinet**, **année**, **sens** (émises / reçues) et **portée** ; saisir stock initial, nouvelles, exécutées ; le **stock restant** se calcule. ⛔ **Plus d'exécutées que de commissions à exécuter** : refusé.
4. **Filtres** : « En cours » / « Clôturés » ; 20 dossiers par page ; compteur de **détenus** dans l'en-tête.
5. 🔎 Un greffier **d'une autre chambre** et le **juge d'instruction d'un autre cabinet** ne voient pas ce dossier (secret de l'instruction).

### 8.3 Assistance judiciaire (Instruction et annexes > Assistance judiciaire)

> **Données à utiliser :** F8.3 de DONNEES_TEST_COMPLET.

1. **Ajouter** une demande (n° et date du RG, demandeur, objet, nature, date d'audience) ; la **décider** (accord ou rejet, n° et date de décision) ; saisir un **recours** et sa **transmission à la DACP**.
2. **Attendu** : tuiles (demandes reçues, accords et %, rejets, **délai moyen**), pastilles À traiter / Accords / Rejets, 20 par page ; télécharger le **registre** Excel.
3. ⛔ **Refus attendu** : date de décision avant la date du RG ; recours **sans décision** ; suppression sans le droit.

### 8.4 Activités du juge de l'application des peines (Instruction et annexes > Activités du JAP)

> **Données à utiliser :** F8.4 de DONNEES_TEST_COMPLET.

1. Choisir l'**année**, saisir les **compteurs du mois** (libérations conditionnelles, aménagements…). **Attendu** : le **stock du mois suivant se calcule** ; télécharger le classeur (`.xlsm`, il **garde ses macros**).
2. ⛔ Compteur négatif ou plus de décisions que de dossiers : refusé.

---

## 9. Recours et certificats (Décisions > Recours et certificats)

Il faut une décision **signée** (partie 7).

### 9.1 Délais de recours (coordonnateur, droit « délais »)

> **Données à utiliser :** F9.2 de DONNEES_TEST_COMPLET.

1. **Délais de recours** (bouton de la page) : vérifier les durées par nature d'affaire et par voie (appel civil 1 mois, opposition 15 jours, référé 15 jours, appel correctionnel 10 jours) ; **confirmer** chaque délai.
   🔎 **Caché** : tant qu'un délai **n'est pas confirmé**, **aucun certificat** n'est possible : « Le délai de recours applicable n'est pas encore confirmé » (voulu).

### 9.2 Cycle d'une décision

> **Données à utiliser :** F9.1 de DONNEES_TEST_COMPLET.

| État | Quand | À essayer |
|---|---|---|
| **Signification à enregistrer** | décision signée, pas de signification | enregistrer la **signification** : date, nature (appel / opposition), signifié à ; ⛔ date **future** |
| **Délai de recours en cours** | signification faite, échéance non atteinte | l'**échéance** s'affiche, reportée au **jour ouvré** si elle tombe un week-end ou une fermeture |
| **Recours formé** | un recours enregistré | enregistrer un recours (nature, date) ; **le transmettre** à la Cour ; le **certificat est alors refusé** |
| **Délai expiré** | échéance passée, aucun recours | bouton **Certificat** |
| **Certificat délivré** | certificat établi | n° **1/2026**, PDF à imprimer |
| **Aucun délai défini** | pas de délai pour cette nature | ajouter le délai dans « Délais de recours » |

1. **Faire** : conduire **quatre décisions** vers quatre états : certifiée, avec recours, expirée sans certificat, en cours.
2. **Certificat** (coordonnateur, droit « certifier ») : saisir « **Délivré à** » ; **Attendu** : numéro séquentiel de l'année **sans trou**, PDF avec la date, la décision, la juridiction. ⛔ Certificat de non-appel pour une décision avec **recours** : refusé ; **second certificat** de même nature : refusé.
3. **Abandonner** un recours : le certificat redevient possible.
4. Les **compteurs** de l'en-tête (à signifier, en cours, certificats à délivrer, avec recours) se chargent **après** la page. Pastilles par état, recherche, pagination de 20.
5. Compte **sans** le droit : boutons absents ; routes refusées.

---

## 10. Statistiques, registres et exports

Compte : **coordonnateur** (tous les droits statistiques). `php artisan statistiques:demo` doit avoir été lancé.

### 10.1 Statistiques civiles (Statistiques > Statistiques civiles)

> **Données à utiliser :** F10.1 de DONNEES_TEST_COMPLET.

1. Les 4 **tuiles** (stock début, nouvelles, vidées, stock fin), les **graphiques**, puis les **onglets** : Récapitulatif annuel, Détail mensuel, État civil, Matrimonial et adoption, Durées, **À compléter**.
2. **Changer les années et la chambre** : tout se recalcule **sans recharger**.
3. **Cliquer sur un chiffre** : la liste des affaires qui le composent s'ouvre.
4. Onglet **À compléter** : préciser une affaire jugée (accordée / rejetée, adoption, statut des adoptants) ; elle apparaît aussitôt dans les tableaux.
5. **Excel** et **PDF** : mêmes onglets, blocs et libellés que les classeurs du tribunal.
6. **Contrôle croisé** : comparer le nombre d'affaires de la liste (Affaires > Liste) avec le **stock** et les **nouvelles** de la période.

### 10.2 Statistiques pénales et de l'instruction

> **Données à utiliser :** F10.2 de DONNEES_TEST_COMPLET.

1. **Pénales** : condamnés, infractions, peines, victimes, durées, **minutes à transmettre** ; export Excel avec la **liste des décisions** (colonnes du classeur « AUDIENCE PÉNALE »).
2. **Instruction** : dossiers ouverts et clôturés, stock, durée d'instruction, inculpés, détenus, victimes, commissions rogatoires.

### 10.3 Registres du tribunal (Statistiques > Registres du tribunal)

> **Données à utiliser :** F10.3 de DONNEES_TEST_COMPLET.

1. Une **carte par classeur** du tribunal (21 registres) avec le nombre d'affaires ; le filtre **Chambre** recalcule les nombres.
2. **Télécharger** « Affaires matrimoniales » et l'ouvrir dans Excel : mêmes **colonnes**, mêmes **listes déroulantes**, mêmes **couleurs** que le classeur du greffe. Activer les calculs si Excel le demande.
3. Onglet **RECAP ENROL. CIV PAR AN** : stocks, nouvelles, à juger et vidées **se remplissent tout seuls** ; comparer avec la page Statistiques civiles. *(Si un récapitulatif affiche des zéros : refaire le test après « Activer la modification » et noter la capture.)*
4. Pénal : télécharger « Délits divers » (total condamné, nationalités, professions, victimes, dates de minute). Instruction : « Instruction : 1er cabinet » (trois tableaux + feuilles CR JI).
5. 🔎 **Caché** : chaque export est **tracé** au journal d'audit (qui, quand, quel registre, quels filtres).

### 10.4 Droits sur les statistiques

> **Données à utiliser :** F10.4 de DONNEES_TEST_COMPLET.

1. Compte avec **« voir » seulement** (magistrat) : les **chiffres** s'affichent, **aucun nom ni numéro de dossier**, **pas d'export**.
2. Compte **sans** droit : 403.
3. Un greffier de chambre ne voit que **les chiffres de sa chambre**.

---

## 11. Voir les alertes fonctionner

L'application calcule chaque jour les échéances du circuit et alerte **l'acteur qui doit agir**. Menu **Alertes**.

### 11.1 Les pages

| Page | À faire |
|---|---|
| **Mes notifications** | lire ses alertes (cloche en haut à droite), « tout marquer comme lu » |
| **Suivi des alertes** | liste de toutes les alertes en cours (droit « alertes ») : filtrer par type, niveau, chambre ; **justifier un retard** (motif + date jusqu'à laquelle l'alerte est suspendue) |
| **Règles d'alerte** | créer, modifier, **activer / désactiver**, supprimer une règle : type d'échéance, **décalage** (J-3, J+1, J+15), niveau (**info, attention, critique**), destinataires |
| **Destinataires** | voir qui reçoit quoi selon les affectations et les responsabilités (greffier de la chambre, président de formation, Présidente, secrétariat…) |
| **Délais** | délais **par étape** du circuit (mise au rôle, publication du rôle, saisie des suites, validation, signature…) et délais de procédure (civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours) |

### 11.2 Provoquer chaque alerte (une par une)

> **Données à utiliser :** F11 (une date par type d'alerte) de DONNEES_TEST_COMPLET.

Pour chaque ligne : **Mise en place** → lancer le moteur → **Attendu** → agir → l'alerte se **referme toute seule**.

Lancer le moteur à une date donnée (sur la base de test) :

```bash
php artisan alertes:evaluer --simuler               # montre ce qui partirait, sans rien envoyer
php artisan alertes:evaluer --date=2026-12-15      # évalue seulement ce jour (essayer plusieurs dates)
php artisan alertes:evaluer                         # tous les jours depuis la dernière évaluation (rattrapage, 14 jours au plus)
```

| Type d'alerte | Mise en place | Destinataire attendu | Se referme quand |
|---|---|---|---|
| **Mise au rôle** | affaire dont la première audience approche, absente de tout rôle | greffier de la chambre | l'affaire est portée au rôle |
| **Jamais portée** | affaire enrôlée jamais portée à un rôle | greffier | portée au rôle |
| **Publication du rôle** | rôle en préparation la veille de l'audience | greffier / chef de service | rôle publié |
| **Feuille à saisir** | audience passée sans suites saisies | greffier | feuille terminée |
| **Renvoi échu** | affaire renvoyée dont la date est passée sans passage | greffier | affaire portée au rôle |
| **Délibéré** | affaire en délibéré dont le délai est dépassé | président de formation | décision rendue / validée |
| **Rédaction de la décision** | affaire jugée sans décision rédigée | magistrat | décision marquée « rédigée » |
| **Validation de la décision** | décision rédigée non validée | magistrat | validée |
| **Signature de la décision** | décision validée non signée | magistrat | signée |
| **Répartition d'une requête** | requête non répartie depuis 2 jours | secrétariat de la présidence | répartie |
| **Délai de procédure** | affaire civile > 6 mois ; correctionnelle > 3 mois ; référé-expulsion > 15 jours ; **retard grave J+15 et J+30** | Présidente, vice-présidents, coordonnateur du greffe | affaire jugée |
| **Limite de renvois** | référé-expulsion renvoyée plus d'une fois | président de formation | jugée |
| **Détention** | échéance de détention approchant ou dépassée | juge / parquet | libération ou prolongation |
| **Minute à transmettre** | décision pénale > 30 jours sans transmission | greffier pénal | transmission enregistrée |
| **Certificat de recours** | décision au délai expiré, sans recours ni certificat | greffier en chef | certificat délivré |

1. **Faire** : dans la base de test, créer **au moins une situation** par type, puis `alertes:evaluer --simuler` : la liste correspond.
2. **Faire** : `alertes:evaluer` pour de vrai ; ouvrir la **cloche** de chaque destinataire attendu : l'alerte est **là, avec l'action à mener**, et **uniquement chez lui**.
3. ⛔ **Refus attendu** : lancer **deux fois** la même évaluation : **aucune alerte en double** pour la même personne et le même canal.
4. **Justifier** un retard (page Suivi) : l'alerte est **suspendue jusqu'à la date indiquée** ; après cette date, elle **repart**.
5. 🔎 **Caché** : l'**e-mail** est un **seul récapitulatif** par personne et par jour (essai d'envoi dans Réglages). Un serveur resté éteint : le moteur **rattrape** les jours manqués (14 au plus).
6. **Règles** : désactiver une règle → plus aucune alerte de ce type ; changer le **décalage** de J-3 à J-1 → l'alerte part un autre jour (vérifier avec `--date`).
7. 🔎 **Caché** : les **alertes de sécurité** (5 échecs de connexion en 15 minutes, nouvelle adresse IP…) vont aux **administrateurs** (voir 1.3).

---

## 12. Journaux, tableau de bord et contrôles d'ensemble

### 12.1 Journaux (Administration > Paramètres et journaux)

1. **Journal de connexion** : filtrer par **dates**, **événement** (connexion réussie, échec, code, déconnexion), **résultat**, recherche (nom, e-mail, IP) ; **exporter** (CSV, droit propre). 20 lignes par page, sans recharger.
2. **Journal d'audit** : filtrer par **action** (compte créé, décision validée, registre exporté…), **auteur**, dates ; ouvrir le **détail avant / après** d'une ligne. **Exporter** (CSV).
3. ⛔ **Refus attendu** : date de fin avant la date de début ; type inconnu ; recherche de plus de 100 caractères ; export sans le droit.
4. 🔎 **Caché** : les exports sont **protégés contre l'injection de formules** (une cellule commençant par `=` est neutralisée) ; le journal d'un export est lui-même **tracé**.

### 12.2 Tableau de bord (page d'accueil)

1. Chaque profil voit **sa** page d'accueil : indicateurs de sa chambre, ses alertes, ses prochaines audiences, ses tâches en attente.
2. **Attendu** : les chiffres concordent avec les pages détaillées ; un compte sans droits ne voit que ce qui le concerne.

### 12.3 Mon compte et notifications

1. Changer son mot de passe, regénérer ses codes de secours, voir ses notifications ; **tout lire**.

### 12.4 Attaques simples à essayer (sécurité)

> **Données à utiliser :** Partie 13 de DONNEES_TEST_COMPLET.

Avec le compte **Sans droit** et en étant connecté :

1. Modifier l'**identifiant** dans l'adresse (`/affaires/liste/1`, `/decisions/redaction/5`…) pour ouvrir un dossier d'une autre chambre : **404**, jamais le dossier.
2. Saisir `<script>alert(1)</script>` dans un **nom de partie**, un **objet**, un **lieu de séance**, un **motif** : le texte s'affiche **tel quel**, **sans** exécution, dans **toutes** les pages et listes.
3. Envoyer un formulaire avec des champs en trop (`statut=tenue`, `cree_par=…`) : ignorés (assignation de masse impossible).
4. Ouvrir l'adresse d'un **fichier du dossier numérique** sans être connecté : redirection vers la connexion.
5. **Trop de requêtes** à la suite sur une liste (ou la recherche) : l'application répond « trop de tentatives » au lieu de ralentir.

---

## 13. Fonctions cachées et contrôles à ne pas oublier (aide-mémoire)

| Sujet | Ce qu'il faut savoir |
|---|---|
| Numéros RG | saisis à la main, jamais réutilisés (même annulés), doublon bloqué sauf jonction, jonction dans la même chambre |
| Dates | pas de date future pour un fait accompli (dépôt, signature, remise, réception) ; renvois sur jour **ouvré** |
| Chambres | périmètre par chambre sur **toutes** les pages, listes, recherches, exports, adresses directes |
| Familles protégées | mineurs et instruction : visibles seulement avec le droit spécial |
| Rôles | un par chambre et par date ; publication bloquée si vide ou salle en conflit ; versions numérotées |
| Feuille d'audience | seulement sur rôle **publié** ; figée après « terminer » ; rouvrir pour corriger |
| Renvois | motif **et** date obligatoires ; limite de renvois en référé-expulsion |
| Décisions | statuts : brouillon, rédigée, validée, signée, délivrée ; numéro **D-N/AAAA** définitif ; texte figé à la validation |
| Délivrance | une **grosse** une seule fois par personne ; copie et expédition possibles plusieurs fois |
| Recours | délai **confirmé** requis ; échéance prorogée au jour ouvré ; certificat refusé s'il y a un recours |
| Requêtes | signature **réservée** pour ordonnances et injonctions ; répartition avant traitement |
| Mise en état | une seule ouverte par affaire ; > 90 jours = « longue » |
| Détenus | classés par échéance ; sortie à la libération ; alertes de détention |
| Pénal | règles de cohérence peine / sursis / issue ; minute à transmettre sous 30 jours |
| Listes | 20 lignes par page, tri, recherche et filtres **côté serveur** (aucun chargement massif) |
| Exports | tracés au journal ; limités aux données visibles ; protégés contre les formules |
| Journal | non modifiable ; avant / après ; sans mots de passe |
| Réglages | 8 onglets, enregistrés séparément, contrôlés, rétablissables ; secrets chiffrés et jamais réaffichés ; tout est au journal |
| Sessions | verrouillage après inactivité ; verrouillage de compte après échecs ; double authentification |
| Alertes | une seule fois par personne et par canal ; justification suspend ; rattrapage de 14 jours |

---

## 14. Scénario de bout en bout (à dérouler en une séance de formation)

Un fil unique, du début à la fin, en changeant de compte. Chaque ligne renvoie au détail plus haut.

| # | Compte | Action | Réf. |
|---|---|---|---|
| 1 | Admin | créer les profils et comptes d'essai | 1.1–1.2 |
| 2 | Chaque compte | se connecter (code de secours) | 1.3 |
| 3 | Admin | vérifier les chambres, formations, calendrier, fermetures | 2 |
| 4 | Admin | reconduire l'année vers la suivante, vérifier | 2.7 |
| 5 | Greffier | enrôler une affaire civile (RG), puis un doublon refusé, puis une jonction | 3.1 |
| 6 | Greffier | enrôler une requête d'hérédité ; Présidente la répartit ; greffier la traite | 4.1 |
| 7 | Greffier | confier une autre affaire en mise en état ; la clôturer | 4.2 |
| 8 | Greffier | enrôler une affaire pénale avec détenu ; une affaire d'instruction | 3.3–3.4 |
| 9 | Greffier | déposer 3 pièces au dossier numérique | 3.6 |
| 10 | Greffier | créer et composer les rôles de **trois chambres** ; résoudre un conflit de salle ; publier | 5 |
| 11 | Parquet | créer un rôle pénal | 5.3 |
| 12 | Greffier | tenir l'audience : renvoyer, mettre en délibéré, juger, radier, joindre ; terminer | 6.1 |
| 13 | Greffier | modifier une date de renvoi ; vider un délibéré | 6.2 |
| 14 | Greffier / Magistrat | planifier et tenir une séance hors audience | 6.4 |
| 15 | Magistrat | rédiger la décision jusqu'à « rédigée » | 7.2–7.3 |
| 16 | Greffier | donner le numéro | 7.3 |
| 17 | Magistrat | valider, puis signer | 7.3 |
| 18 | Greffier | délivrer une expédition, une grosse, une copie | 7.3 |
| 19 | Parquet / Greffier pénal | enregistrer la décision pénale ; transmettre la minute | 8.1 |
| 20 | Juge d'instruction | saisir un dossier, une commission rogatoire | 8.2 |
| 21 | Greffier | signifier la décision ; enregistrer un recours ; Coordonnateur : confirmer les délais, délivrer un certificat | 9 |
| 22 | Coordonnateur | consulter les statistiques ; télécharger deux registres | 10 |
| 23 | Moteur | `alertes:evaluer` ; ouvrir la cloche de chacun ; justifier un retard | 11 |
| 24 | Admin | relire le journal d'audit de toute la journée | 12.1 |

---

## Annexes

### Annexe A. Menus à parcourir (cocher quand chaque page a été ouverte, utilisée et testée avec un compte sans droit)

| Menu | Page | Ouverte | Utilisée | Droit refusé |
|---|---|---|---|---|
| Tableau de bord | Accueil | [ ] | [ ] | – |
| Administration > Utilisateurs | Liste des utilisateurs | [ ] | [ ] | [ ] |
| | Profils et droits | [ ] | [ ] | [ ] |
| Administration > Organisation | Année judiciaire | [ ] | [ ] | [ ] |
| | Salles | [ ] | [ ] | [ ] |
| | Magistrats et greffiers | [ ] | [ ] | [ ] |
| | Sections | [ ] | [ ] | [ ] |
| | Chambres | [ ] | [ ] | [ ] |
| | Services et responsabilités | [ ] | [ ] | [ ] |
| | Jours fériés et vacances | [ ] | [ ] | [ ] |
| | Dates limites d'enrôlement | [ ] | [ ] | [ ] |
| | Reconduire une année | [ ] | [ ] | [ ] |
| | Archive des ordonnances | [ ] | [ ] | [ ] |
| Administration > Référentiels | Types d'audience | [ ] | [ ] | [ ] |
| | Modes de saisine | [ ] | [ ] | [ ] |
| | Matières | [ ] | [ ] | [ ] |
| | Infractions | [ ] | [ ] | [ ] |
| | Natures d'affaire | [ ] | [ ] | [ ] |
| | Objets du litige | [ ] | [ ] | [ ] |
| Administration > Paramètres | Réglages | [ ] | [ ] | [ ] |
| | Journal de connexion | [ ] | [ ] | [ ] |
| | Journal d'audit | [ ] | [ ] | [ ] |
| Affaires | Liste des affaires | [ ] | [ ] | [ ] |
| | Dossier numérique | [ ] | [ ] | [ ] |
| | Mise en état | [ ] | [ ] | [ ] |
| | Requêtes et hérédité | [ ] | [ ] | [ ] |
| | Détenus | [ ] | [ ] | [ ] |
| | Avocats | [ ] | [ ] | [ ] |
| | Importation affaire | [ ] | [ ] | [ ] |
| Audiences | Rôle du jour | [ ] | [ ] | [ ] |
| | Rôles d'audience | [ ] | [ ] | [ ] |
| | Planning des salles | [ ] | [ ] | [ ] |
| | Feuille d'audience | [ ] | [ ] | [ ] |
| | Renvois et délibérés | [ ] | [ ] | [ ] |
| | Séances hors audience | [ ] | [ ] | [ ] |
| | Suites d'audience | [ ] | [ ] | [ ] |
| | Analyse et pilotage | [ ] | [ ] | [ ] |
| Décisions | Modèles de décision | [ ] | [ ] | [ ] |
| | Blocs de texte | [ ] | [ ] | [ ] |
| | Rédaction | [ ] | [ ] | [ ] |
| | Registre des décisions | [ ] | [ ] | [ ] |
| | Répertoire pénal | [ ] | [ ] | [ ] |
| | Recours et certificats | [ ] | [ ] | [ ] |
| Instruction et annexes | Dossiers d'instruction | [ ] | [ ] | [ ] |
| | Assistance judiciaire | [ ] | [ ] | [ ] |
| | Activités du JAP | [ ] | [ ] | [ ] |
| Statistiques | Statistiques civiles | [ ] | [ ] | [ ] |
| | Statistiques pénales | [ ] | [ ] | [ ] |
| | Statistiques de l'instruction | [ ] | [ ] | [ ] |
| | Registres du tribunal | [ ] | [ ] | [ ] |
| Alertes | Mes notifications | [ ] | [ ] | – |
| | Suivi des alertes | [ ] | [ ] | [ ] |
| | Règles d'alerte | [ ] | [ ] | [ ] |
| | Destinataires | [ ] | [ ] | [ ] |
| | Délais | [ ] | [ ] | [ ] |
| En-tête | Recherche plein texte | [ ] | [ ] | – |
| | Mon compte | [ ] | [ ] | – |

### Annexe B. Commandes utiles

| Commande | Effet |
|---|---|
| `php artisan affaires:demo` / `--retirer` | crée / retire les affaires fictives |
| `php artisan statistiques:demo` / `--retirer` | crée / retire les données statistiques fictives |
| `php artisan tpi:codes-test` | 8 codes de secours par compte de test |
| `php artisan tpi:droits-test-lots-o-p` | donne aux profils de test les droits pénal, requêtes, instruction, annexes |
| `php artisan alertes:evaluer [--simuler] [--date=AAAA-MM-JJ]` | moteur d'alertes |
| `php artisan tpi:admin` | crée un administrateur ou change son mot de passe |
| `php artisan tpi:reglages:reinitialiser` | remet une rubrique de Réglages à ses valeurs d'origine |
| `php artisan test` | lance les tests automatiques (à lancer **avant** et **après** la séance de test) |

### Annexe C. Fiche d'anomalies

| N° étape | Compte utilisé | Ce que j'ai fait | Ce qui est arrivé | Ce qui était attendu | Capture | Gravité (bloquante / gênante / mineure) |
|---|---|---|---|---|---|---|
| | | | | | | |
| | | | | | | |
| | | | | | | |

### Annexe D. Points à faire valider par la greffière en chef

1. Les **délais de recours** installés (appel civil 1 mois, opposition 15 jours, référé 15 jours, appel correctionnel 10 jours).
2. Le **délai de transmission de la minute** (30 jours par défaut).
3. Les **articles** de chaque infraction.
4. Qui signe le **certificat** et son texte exact.
5. Le **moment d'attribution du numéro de décision** (rédaction ou validation) et son format **D-N/AAAA**.
6. Les **modèles Word vierges** des rôles et des décisions.
7. Les **zéros** éventuels des récapitulatifs Excel (voir 10.3).
