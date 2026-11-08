# Test pratique des lots O (matière pénale) et P (requêtes, mise en état, recours)

À faire sur la **base de test** uniquement. Durée : environ une heure.

## 0. Préparation (une seule fois)

1. Importer les comptes de test si ce n'est pas fait : `database/sql/comptes_test.sql` (mot de passe `Tpi@2026`).
2. Donner aux profils de test les droits des lots O et P :
   ```bash
   php artisan tpi:droits-test-lots-o-p
   ```
3. Générer les codes de secours (la boîte e-mail des comptes de test est fictive) :
   ```bash
   php artisan tpi:codes-test
   ```
   Les codes sont dans `storage/app/codes_secours_test.txt`.
4. Créer des affaires d'essai : `php artisan affaires:demo` (à retirer ensuite avec `--retirer`).

Comptes utilisés (un compte par rôle) :

| Rôle | Compte | Sert à |
|---|---|---|
| Présidente | `appa.nguessan@tpi.test` | signer les ordonnances sur requête, répartir les requêtes |
| Magistrat | `carine.boka@tpi.test` | séances, répertoire pénal, mise en état |
| Greffier de chambre | `yaba.assamoi@tpi.test` | enrôlement, signification, recours, répertoire |
| Coordonnateur du greffe | `prisca.toure@tpi.test` | certificats, délais de recours, infractions |

Se connecter avec **un seul compte par navigateur** (ou une fenêtre privée par compte).

---

## Lot P

### P1. Mise en état (greffier, puis magistrat)
1. Greffier : **Affaires > Mise en état > Confier une affaire** : choisir une affaire civile RG, la date de réception, un juge chargé de la chambre.
   - Attendu : l'affaire apparaît « En cours » ; elle disparaît des propositions de rôle.
2. Essayer une affaire pénale : **refusée** (seules les affaires RG passent en mise en état).
3. Clôturer : numéro et date d'ordonnance de clôture, date d'audience de jugement.
   - Attendu : statut « Clôturée », l'affaire est « à mettre au rôle » à la date indiquée.
4. Ouvrir une autre mise en état puis la **retirer** avec un motif : l'affaire redevient ordinaire.

### P2. Signature réservée des ordonnances sur requête (Présidente)
1. Greffier : **Affaires > Requêtes et hérédité** : déposer une requête de type « Injonction de payer ».
2. Présidente : la répartir vers une chambre.
3. Greffier : tenter de la marquer « traitée » : le bouton n'existe pas, mention « signature réservée ».
4. Présidente : la marquer traitée. Attendu : « signée le … par … ».
5. Requête d'hérédité : le greffier peut la clôturer lui-même (pas de signature réservée).

### P3. Séances hors audience (magistrat, greffier)
1. **Audiences > Séances hors audience > Planifier une séance** : type « Réception des requêtes », une date, une heure, un juge et un greffier.
   - Attendu : la liste Magistrat ne contient que des magistrats, la liste Greffier que des greffiers.
2. Ajouter un dossier à la séance (objet, issue), puis le retirer.
3. Déclarer **tenue** une séance à venir : refusé. Corriger la date à aujourd'hui ou avant puis déclarer tenue : accepté.
4. Annuler une autre séance (bouton réservé à qui a le droit d'annuler).

### P4. Recours et certificats (greffier, coordonnateur)
Il faut une décision **signée** (Décisions > Rédaction, jusqu'à la signature).
1. Greffier : **Décisions > Recours et certificats** : la décision apparaît « Signification à enregistrer ».
2. Enregistrer la signification (date passée) : état « Délai de recours en cours » ou « Délai expiré », avec l'échéance.
3. Coordonnateur : **Délais de recours** : vérifier les durées, cocher « Confirmé ». *Tant que ce n'est pas fait, aucun certificat n'est possible : c'est voulu.*
4. Décision dont le délai est expiré : coordonnateur, bouton **Certificat** : saisir « Délivré à ». Attendu : numéro `1/2026`, PDF à imprimer.
5. Autre décision : enregistrer un **recours**, puis le **transmettre** à la Cour : le certificat est refusé. Marquer le recours **abandonné** : le certificat redevient possible.
6. Vérifier la cloche : alerte « Certificat de non-appel à délivrer » pour une décision éligible.

---

## Lot O

### O1. Infractions (coordonnateur)
1. **Référentiels > Infractions** : une liste de 14 infractions est installée **sans articles**. Renseigner les articles du code pénal de quelques-unes (à valider par le tribunal).
2. Ajouter une infraction, tenter de créer un doublon : refusé.

### O2. Enrôlement pénal avec numéro du parquet (greffier)
1. **Affaires > Liste > Enrôler une affaire pénale (RP)** : choisir une infraction dans la liste.
   - Attendu : la nature de la prévention et les articles se remplissent ; on peut les modifier.
2. Saisir un **N° du parquet** et enregistrer. Le numéro apparaît sur la fiche de l'affaire.
3. Enrôler une autre affaire avec le **même** N° du parquet : refusé (« déjà enrôlé sous RP … »), sauf en cochant la jonction.
4. Ajouter un prévenu avec mandat de dépôt : il apparaît dans **Détenus**.

### O3. Répertoire pénal (magistrat ou greffier)
1. **Décisions > Répertoire pénal > Enregistrer une décision** : choisir l'affaire, le prévenu, la date.
2. Tester les règles :
   - « Condamné » sans peine : refusé ;
   - « Relaxé » avec une amende : refusé ;
   - sursis supérieur à l'emprisonnement, ou sursis sans emprisonnement : refusé ;
   - date avant l'enregistrement de l'affaire : refusé.
3. Condamner à 24 mois dont 6 avec sursis et 100 000 F : la peine s'affiche « 2 ans dont 6 mois avec sursis ; Amende : 100 000 F CFA ».
4. **Relaxer un prévenu détenu** : dans **Détenus**, il n'est plus détenu (remis en liberté à la date de la décision).
5. **Minute** : « À transmettre » avec le nombre de jours. Enregistrer la transmission (date, destinataire). Une date avant la décision est refusée.
6. Cloche : au-delà de 30 jours sans transmission, alerte « Minute pénale à transmettre » (durée à confirmer par le tribunal).

---

## Contrôles transversaux
- **Journal d'audit** : chaque action ci-dessus laisse une ligne (séance, signification, recours, certificat, décision pénale, minute, infraction).
- **Droits** : se connecter avec un compte sans les droits (ex. un magistrat sur « Délais de recours ») : le bouton n'apparaît pas et l'adresse directe renvoie « interdit ».
- **Périmètre** : un greffier ne voit que les décisions et séances de sa chambre.

## À noter à la fin
Pour chaque étape : OK / anomalie / question. Points à trancher avec la greffière en chef :
1. Les **délais de recours** (appel civil, opposition, appel correctionnel, référé) : valeurs installées provisoires.
2. Le **délai de transmission de la minute** (30 jours par défaut).
3. Les **articles** de chaque infraction.
4. Qui signe le **certificat** (greffier en chef) et le texte exact du certificat.

---

## Statistiques (civiles et pénales)
Préparation : `php artisan tpi:droits-test-lots-o-p` puis `php artisan statistiques:demo` (données fictives). Compte : coordonnateur du greffe (tous les droits statistiques).
1. **Statistiques > Statistiques civiles** : les 4 tuiles (stock début, nouvelles, vidées, stock fin), les graphiques, puis les onglets Récapitulatif annuel, Détail mensuel, État civil, Matrimonial et adoption, Durées.
2. Changer les années et la chambre : tout se recalcule sans recharger la page.
3. Cliquer sur un chiffre d'un tableau : la liste des affaires qui le composent s'ouvre.
4. Onglet **À compléter** : préciser une affaire jugée (accordée ou rejetée, adoption) ; elle apparaît aussitôt dans les tableaux « accordées » et « rejetées ».
5. **Excel** et **PDF** : mêmes onglets, blocs et libellés que les classeurs du tribunal.
6. **Statistiques pénales** : condamnés, infractions, peines, victimes, durées, minutes à transmettre ; export Excel avec la liste des décisions (colonnes du classeur « AUDIENCE PENALE »).
7. Avec un compte qui n'a que « voir » (un magistrat) : les chiffres s'affichent, mais aucun nom ni numéro de dossier, et pas d'export.
8. Retirer les données fictives : `php artisan statistiques:demo --retirer`.

## Registres au modèle du tribunal
1. **Statistiques > Registres du tribunal** : une carte par classeur du tribunal, avec le nombre d'affaires (ou de décisions pour le pénal). Le filtre « Chambre » recalcule les nombres.
2. **Télécharger** un registre (ex. « Affaires matrimoniales ») et l'ouvrir dans Excel : mêmes colonnes, mêmes listes déroulantes, mêmes couleurs que le classeur du greffe. Activer les calculs si Excel le demande.
3. Onglet **RECAP ENROL. CIV PAR AN** : les stocks, nouvelles, à juger et vidées se remplissent tout seuls ; comparer avec la page Statistiques civiles.
4. Dans « Statistiques civiles > À compléter » : renseigner le caractère de la décision et le statut des adoptants ; ils apparaissent dans le registre.
5. Pénal : télécharger « Délits divers » ; les colonnes « Total condamné », nationalités, professions, victimes et dates de minute sont remplies.

---

## Instruction et services annexes
Préparation : `php artisan tpi:droits-test-lots-o-p` (donne aussi les droits d'instruction et des annexes aux profils de test).
1. **Instruction > Dossiers** (compte du juge d'instruction) : la liste des dossiers RI et RE de son cabinet. Ouvrir un dossier : onglets « Le dossier », « Inculpés », « Victimes ». Saisir un réquisitoire, une clôture, un statut de détention, des auditions ; essayer des dates incohérentes (clôture avant la saisine, 2e audition sans la 1re) : elles sont refusées.
2. **Commissions rogatoires** : choisir le cabinet, l'année, le sens et la portée ; saisir stock, nouvelles, exécutées ; le stock restant se calcule ; plus d'exécutées que de commissions à exécuter est refusé.
3. **Statistiques > Statistiques de l'instruction** : dossiers ouverts et clôturés, stock, durée de l'instruction, inculpés, détenus, victimes, commissions rogatoires.
4. **Statistiques > Registres du tribunal** : télécharger « Instruction : 1er cabinet » ; les trois tableaux (dossiers, suivi des inculpés, suivi des victimes) et les feuilles « CR JI » sont remplis au modèle du tribunal.
5. **Services annexes > Assistance judiciaire** : ajouter une demande, la décider (accord ou rejet), saisir un recours ; les tuiles se mettent à jour ; télécharger le registre.
6. **Services annexes > Activités du JAP** : choisir l'année, saisir les compteurs du mois ; le stock du mois suivant se calcule ; télécharger le classeur (.xlsm, avec ses macros).

