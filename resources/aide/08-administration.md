---
titre: Administration
icone: fa-solid fa-sitemap
couleur: vert
resume: Comptes et profils, organisation du tribunal, référentiels, réglages et journaux.
droit: comptes.voir, profils.voir, annees.voir, salles.voir, personnel.voir, sections.voir, chambres.voir, services.voir, fermetures.voir, reconduction.voir, ordonnances.voir, reglages.voir, journaux.audit, journaux.connexion, matieres.voir, infractions.voir, types_audience.voir, modes_saisine.voir, natures_affaire.voir, objets_litige.voir
routes: utilisateur.*, profils.*, organisation.*, referentiels.*, reglages.*, journal.*
ordre: 8
---
Ce menu est réservé à l'administration et au greffe en chef. Il pose les **règles du jeu** : qui a accès à quoi, comment le tribunal est organisé, et quels réglages s'appliquent.

## Profils et comptes

*Administration > Utilisateurs.*

1. **Créez d'abord le profil** : un nom, puis un **niveau par page** (aucun, lecture, saisie, gestion) et des **droits spéciaux** à cocher.
2. **Créez le compte** : nom, e-mail, mot de passe provisoire, **profil**. Reliez-le à la **fiche d'agent** correspondante : c'est ce lien qui donne le **périmètre de chambre**.
3. Désactivez un compte plutôt que de le supprimer quand une personne quitte le tribunal.

> **Attention** Vous **ne pouvez pas modifier votre propre profil ni votre statut**. Le profil **Admin** a tous les droits et **ne se modifie pas**. Un profil **utilisé par des comptes** ne se supprime pas.

## Organisation du tribunal

*Administration > Organisation du tribunal.* Dans cet ordre conseillé :

1. **Année judiciaire** : libellé, début et fin (la fin est **après** le début). Une seule année est « courante ».
2. **Salles**, **Personnel du tribunal** (catégorie, fonction, matricule unique, compte relié), **Sections** et **Chambres**.
3. **Formations et affectations** dans chaque chambre : c'est elles qui donnent la **composition des rôles**, le **périmètre de chacun** et les **destinataires des alertes**.
4. **Calendrier** des audiences par chambre (jour, heure, salle). Deux chambres ne peuvent pas avoir **la même salle au même créneau**.
5. **Jours fériés et vacances** : ils bloquent les rôles et décalent les échéances de recours.
6. **Dates limites d'enregistrement** (art. 7 de l'ordonnance) et **Services et responsabilités**.
7. **Archive des ordonnances** : téléversez l'ordonnance signée (PDF) pour la consulter dans l'application.

## Charger l'ordonnance par fichier Excel

*Organisation > Ordonnances > « Charger un fichier Excel ». Réservé à la Présidente et à l'administrateur (droit « Charger l'ordonnance par fichier Excel »).* Au lieu de remplir les formulaires un à un, on corrige un classeur puis on le recharge.

1. **Télécharger le fichier** (bouton « Fichier Excel de l'ordonnance ») : il contient l'organisation en cours, une feuille par notion (sections, chambres, formations, composition, audiences, services, responsables, membres extérieurs, dates limites, articles). La feuille **Lisez-moi** explique comment le remplir.
2. **Corriger dans Excel** : les colonnes à en-tête **vert** sont lues, celles à en-tête **gris** ne servent qu'à la lecture. La colonne **N°** est le numéro de la base : ne la changez pas. Pour un **nouvel élément**, laissez le N° **vide** et écrivez son **nom** dans les feuilles qui s'y rapportent. Dans Composition et Responsables, l'agent s'écrit par son **N° ou son nom**.
3. **Charger** le fichier et choisir le mode : **Mise à jour** (ajoute et modifie, le reste ne bouge pas) ou **Ordonnance complète** (ce qui n'y figure plus est retiré : affectations et audiences reçoivent une **date de fin**, chambres et formations sont désactivées, rien n'est supprimé de l'historique).
4. **Contrôler** : l'application signale les erreurs **ligne par ligne** (rôle inconnu, date impossible, N° inexistant…). Rien n'est modifié à ce stade ; corrigez le fichier et rechargez-le.
5. **Rapprocher les noms** : un nom douteux (« KOUADJO » pour « KOUADIO », un titre, un ordre différent) n'est **jamais deviné**. L'écran propose les agents les plus proches : choisissez, créez un nouvel agent ou ignorez la ligne. Votre choix est **mémorisé** pour les fichiers suivants.
6. **Comparer** : chaque ajout, modification ou retrait est listé avec l'**avant → après**. **Décochez** ce que vous ne voulez pas appliquer.
7. **Appliquer** : tout est écrit en **une seule opération** (tout ou rien) et tracé au journal d'audit.
8. **Annuler** si besoin : l'import le plus récent peut être défait (ce qu'il a ajouté est ôté, ce qu'il a modifié retrouve sa valeur d'avant).

> **Attention** Un fichier téléchargé depuis **une autre base** (démonstration, autre tribunal) est refusé : ses N° ne correspondent pas aux personnes de cette base. Téléchargez toujours le fichier depuis cette application.

> **À savoir** Remplacer une personne sur une ligne de Composition **clôture** son affectation (date de fin) et en crée une nouvelle : les rôles et décisions déjà établis gardent leur composition. Le texte des **articles** est conservé en entier (page « Articles ») ; le numéro d'article n'est qu'une étiquette.

## Reconduire une année

*Organisation > Reconduire une année.* Au début d'une année judiciaire, repartez de l'année précédente plutôt que de tout ressaisir.

1. Choisissez l'**année à reconduire** et l'**année qui reçoit** la copie (**elles doivent être différentes**).
2. Cochez ce qu'il faut reprendre : la **composition** des formations, les **audiences** (calendrier) et les **services**.
3. Validez : un récapitulatif indique ce qui a été copié. Ajustez ensuite selon la **nouvelle ordonnance**.

> **À savoir** Reconduire **deux fois** la même année vers la même cible **ne crée aucun doublon**.

## Référentiels

*Administration > Référentiels.* Les listes qui alimentent les formulaires : types d'audience, modes de saisine, matières, **infractions**, natures d'affaire, **objets du litige**.

- On peut **ajouter, modifier, désactiver, supprimer** (si l'élément n'est pas utilisé). Les **doublons** sont refusés.
- Les **objets du litige** portent des **mots-clés** : ce sont eux qui font proposer nature, matière et chambre à l'enregistrement.
- Complétez les **articles** des infractions (à valider par le tribunal).

## Réglages

*Administration > Paramètres et journaux > Réglages.* Huit onglets, **enregistrés séparément** :

| Onglet | À quoi ça sert |
|---|---|
| **Organisation** | nom de la juridiction, sigle et ville de l'en-tête |
| **Sécurité** | durée d'inactivité, essais de connexion, durée du verrouillage, longueur du mot de passe |
| **Courriels** | expéditeur et serveur de messagerie (bouton « Tester l'envoi ») |
| **Affaires** | numéros saisis à la main ou automatiques, durée du mandat de dépôt, délais, taille des fichiers |
| **Impression des actes** | polices, marges et timbre des décisions |
| **Décisions** | **moment** où le numéro de décision est attribué |
| **Pilotage et IA** | analyse automatique ou par intelligence artificielle |
| **Interface** | lignes par page des petits tableaux |

> **Attention** Chaque champ a des **limites** (un minimum et un maximum) : une valeur hors limites est refusée. Le bouton **Rétablir les valeurs par défaut** remet **tout l'onglet** à zéro. Les mots de passe sont **chiffrés et jamais réaffichés**.

## Journaux

*Administration > Paramètres et journaux.*

- **Journal de connexion** : qui s'est connecté, quand, d'où, et les échecs. Filtrez par **dates**, **événement** et **résultat**.
- **Journal d'audit** : qui a fait quoi, avec les valeurs **avant et après**. Les mots de passe n'y figurent jamais.

> **À savoir** Les journaux **ne peuvent être ni modifiés ni effacés**. Les **exports CSV** sont eux-mêmes tracés.
