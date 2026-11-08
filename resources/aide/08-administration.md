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
2. **Salles**, **Magistrats et greffiers** (fonction, matricule unique, compte relié), **Sections** et **Chambres**.
3. **Formations et affectations** dans chaque chambre : c'est elles qui donnent la **composition des rôles**, le **périmètre de chacun** et les **destinataires des alertes**.
4. **Calendrier** des audiences par chambre (jour, heure, salle). Deux chambres ne peuvent pas avoir **la même salle au même créneau**.
5. **Jours fériés et vacances** : ils bloquent les rôles et décalent les échéances de recours.
6. **Dates limites d'enrôlement** (art. 7 de l'ordonnance) et **Services et responsabilités**.
7. **Archive des ordonnances** : téléversez l'ordonnance signée (PDF) pour la consulter dans l'application.

## Reconduire une année

*Organisation > Reconduire une année.* Au début d'une année judiciaire, repartez de l'année précédente plutôt que de tout ressaisir.

1. Choisissez l'**année à reconduire** et l'**année qui reçoit** la copie (**elles doivent être différentes**).
2. Cochez ce qu'il faut reprendre : la **composition** des formations, les **audiences** (calendrier) et les **services**.
3. Validez : un récapitulatif indique ce qui a été copié. Ajustez ensuite selon la **nouvelle ordonnance**.

> **À savoir** Reconduire **deux fois** la même année vers la même cible **ne crée aucun doublon**.

## Référentiels

*Administration > Référentiels.* Les listes qui alimentent les formulaires : types d'audience, modes de saisine, matières, **infractions**, natures d'affaire, **objets du litige**.

- On peut **ajouter, modifier, désactiver, supprimer** (si l'élément n'est pas utilisé). Les **doublons** sont refusés.
- Les **objets du litige** portent des **mots-clés** : ce sont eux qui font proposer nature, matière et chambre à l'enrôlement.
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
