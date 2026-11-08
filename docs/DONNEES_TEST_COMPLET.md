# Données du test pratique complet : quoi utiliser, quel champ remplir

Ce document accompagne **TEST_PRATIQUE_COMPLET** (le déroulé). Il dit, **pour chaque action**, **quelle donnée utiliser**, **quels champs remplir** et **quelles valeurs fausses essayer** pour voir chaque contrôle réagir.

> Base de test uniquement. Toutes les données sont fictives (noms inventés, mention « essai »).

## Comment l'utiliser

- Chaque fiche porte un numéro (**F1.1**, **F3.2**…) cité dans le déroulé.
- **Valeurs valides** : à saisir pour réussir l'action. **Valeurs à refuser** : à saisir ensuite, une par une ; le **message attendu** est indiqué quand il est connu (sinon : « refusé avec un message »).
- `J` désigne **la date du jour**. `J-3` = il y a 3 jours ; `J+10` = dans 10 jours. Écrire les dates au format `jj/mm/aaaa`.

---

## A. Préparation et inventaire des données prêtes

### A.1 Une seule commande prépare tout

```bash
php artisan tpi:formation            # efface les données d'essai existantes, puis recrée tout le jeu (≈ 5 minutes avec les statistiques)
php artisan tpi:formation --sans-stats   # idem sans les ~500 affaires fictives des statistiques (plus rapide)
php artisan tpi:formation --retirer      # efface seulement
```

- **Efface** : les affaires d'essai (`DEMO`, `DEMO-STAT`) et tout ce qui s'y rattache (rôles, décisions, recours, certificats, mises en état, décisions pénales, instruction, scellés, pièces) ; les requêtes, séances, demandes d'assistance et avocats marqués d'essai ; les alertes (elles se recalculent) ; les notifications des comptes de test.
- **Ne touche jamais** : l'ordonnance (chambres, formations, agents), les référentiels, les modèles et blocs de décision, les réglages, les profils et comptes, les journaux.
- **Refuse de s'exécuter** s'il existe une affaire qui n'est **pas** d'essai (donnée réelle).
- Une fois lancée, relancer la commande remet le jeu **à l'état initial** : pratique pour recommencer une formation.
- Compléments : importer `database/sql/comptes_test.sql` (36 comptes, mot de passe `Tpi@2026`) puis `php artisan tpi:droits-test-lots-o-p` et `php artisan tpi:codes-test` (codes de secours dans `storage/app/codes_secours_test.txt`).

### A.2 Comptes à utiliser (mot de passe `Tpi@2026`, puis un code de secours)

| Rôle | Compte | Pour quoi faire |
|---|---|---|
| **Administrateur** | le compte administrateur réel | profils, comptes, organisation, réglages, journaux |
| **Présidente** | `appa.nguessan@tpi.test` | répartir et signer les ordonnances, dérogations, voir toutes les chambres |
| **Vice-président** | `kablan.ndraman@tpi.test` | idem (1re, 2e, 5e chambres civiles) |
| **Coordonnateur du greffe** | `prisca.toure@tpi.test` | délais de recours, certificats, référentiels, statistiques, alertes |
| **Greffier 1re chambre civile** | `guedji.kouadio@tpi.test` | enrôler, rôles, feuille d'audience, requêtes |
| **Greffier 2e chambre civile** | `yaba.assamoi@tpi.test` | idem (autre périmètre : contrôle des droits) |
| **Greffier 3e chambre civile** | `joniack.zouhou@tpi.test` | idem |
| **Greffier 5e chambre civile** | `stephanie.bah@tpi.test` | audience du jour de la 5e chambre |
| **Juge 1re chambre civile** | `myriam.diako@tpi.test` | rédiger, valider, signer, mise en état |
| **Juge 3e chambre civile et 1re correctionnelle** | `carine.boka@tpi.test` | séances, répertoire pénal |
| **Juge d'instruction (1er cabinet)** | `wagnimin.kone@tpi.test` | dossiers d'instruction |
| **Juge d'instruction (2e cabinet)** | `elisabeth.konan@tpi.test` | secret de l'instruction (ne voit pas le 1er cabinet) |
| **Juge des enfants** | `peka.cauphy@tpi.test` | affaires de mineurs |
| **Greffier du JAP** | `ndey.djole@tpi.test` | activités du juge de l'application des peines |

Les six comptes « essai » de la partie 1 (`essai.greffier@tpi.test`, `essai.magistrat@tpi.test`, `essai.coordonnateur@tpi.test`, `essai.parquet@tpi.test`, `essai.lecteur@tpi.test`, `essai.sansdroit@tpi.test`) sont **créés par vous** pendant le test.

### A.3 Les chambres et salles à connaître

Chambres : 1re chambre civile (présidentielle et administrative) · 2e (immobilière et des paiements) · 3e (affaires familiales et état des personnes) · 4e (tutelles) · 5e (urgence) · 1re chambre correctionnelle (délits économiques et divers) · 2e (flagrants délits) · 1er et 2e cabinets d'instruction · Cabinet du juge des enfants · Juridiction de l'application des peines.
Salles : Cabinet du juge · Cabinet du Président · Grande salle de réunion · Salle d'audience Jean Sansan Kambile (n°3) · Salle d'audience KONE Mamadou (n°4).

### A.4 Affaires prêtes (clé : numéro RG ou série ; « essai » = jeu de formation)

| Numéro | Chambre | Ce qu'elle sert à tester |
|---|---|---|
| **RG-1391/26** KOFFI Adjoua c/ Société BATIMAT SARL, « paiement de loyers impayés et expulsion » | 1re civile | affaire type ; mise en état **longue** ; première audience dans la semaine |
| **RG-1391/26 (bis)** TRAORE Salif c/ BATIMAT, dommages-intérêts | 1re civile | **jointe** à la précédente (apparaît « 1391/26 / Et … ») |
| **RG-1402/26** DIABY Karamoko c/ GNAGNE Brice, déguerpissement | 1re civile | mise en état **récente** |
| **RG-1410/26** BAMBA Fatoumata c/ **MP**, exequatur d'un divorce | 2e civile | le ministère public comme partie |
| **RG-1415/26** OUATTARA Siaka | 1re civile | **annulée** : son numéro ne se réutilise jamais |
| **RG-1501/26** à **1503/26** (DEMO ABOUA, TANOH, YAPI) | 1re civile | **brouillon** (1501), **rédigée** (1502), **validée** (1503) |
| **RG-1511/26** à **1514/26** | 2e civile | décisions **signées** : à signifier (1511), délai **expiré** (1512), **recours** (1513), délai **en cours** (1514) |
| **RG-1521/26**, **1522/26** | 5e et 4e civiles | décision **délivrée** (1521), **certificat** déjà délivré (1522) |
| **RG-1531/26** | 3e civile | décision signée non signifiée depuis 14 jours |
| **RG-1532/26**, **1541/26** | 5e et 1re civiles | mise en état **retirée** (1532) et **clôturée** (1541) |
| **RG-1601/26** à **1606/26** | 5e civile | **audience du jour à tenir** (première audience J) : renvoyer, délibérer, juger, radier, joindre |
| **RG-1611/26** à **1615/26** | 5e civile | audiences **passées closes** (suites déjà notées) |
| **RG-1621/26** à **1627/26** | 3e civile | **retards** : renvoi échu, délibéré dépassé, décision à rédiger |
| **RP-1334/26** JOHNSON Wedha, KOUADIO Kouakou | 1re correctionnelle | prévenus **détenus** : un **dépassé**, un **proche** ; 2 scellés |
| **RP-1340/26** « abus de confiance » | 1re correctionnelle | prévenu personne morale ; détenu à échéance normale |
| **RI-0045/26** DOSSO Ibrahim | 1er cabinet d'instruction | dossier d'instruction avec réquisitoire ; inculpé détenu |
| **RE-0045/26** MINEUR A. | cabinet du juge des enfants | **mineur** : visible seulement du juge des enfants |

Les ≈ 330 affaires civiles « DEMO-STAT » et 190 décisions pénales alimentent les **statistiques** (partie 10).

### A.5 Décisions prêtes (jugement d'hérédité, modèle installé)

| Affaire | Étape | Utilisation |
|---|---|---|
| 1501/26 | **Brouillon** (champs à compléter) | compléter les champs, essayer « rédigée » avec des manques |
| 1502/26 | **Rédigée** (depuis 8 jours) | valider ; alerte « validation en retard » |
| 1503/26 | **Validée** n° D-1 (depuis 5 jours) | signer ; alerte « signature en retard » |
| 1511/26 | **Signée** n° D-2 (J-2) | état **Signification à enregistrer** |
| 1512/26 | Signée n° D-3, signification J-40 | état **Délai expiré** → délivrer le **certificat** |
| 1513/26 | Signée n° D-4, signification J-20, **recours** formé J-10 | transmettre, abandonner ; certificat refusé |
| 1514/26 | Signée n° D-5, signification J-5 | état **Délai en cours** |
| 1521/26 | **Délivrée** n° D-6 (expédition) | délivrer une grosse, une copie ; refus d'une 2e grosse |
| 1522/26 | Signée n° D-7, **certificat** déjà délivré | un 2e certificat est refusé |
| 1531/26 | Signée n° D-8, non signifiée depuis 14 j | saisir la signification |

Le numéro **D-N/année** est attribué selon le réglage « Décisions » (à la validation par défaut).

### A.6 Autres données prêtes

| Domaine | Données | Pour tester |
|---|---|---|
| **Requêtes** (8) | RQ-0001 hérédité à répartir (J-4) ; RQ-0002 ordonnance répartie 2e civile ; **RQ-0003** hérédité à répartir **en retard** (J-6) ; RQ-0004 injonction de payer à répartir (J-1) ; RQ-0005 ordonnance sur requête répartie 1re civile ; RQ-0006 hérédité répartie 2e civile ; RQ-0007 hérédité traitée ; RQ-0008 classée | répartir, signature réservée, retard, clôture |
| **Mises en état** (4) | 1391/26 **longue** (J-130) ; 1402/26 récente ; 1541/26 **clôturée** (ordonnance OC-12) ; 1532/26 **retirée** (motif) | clôturer, retirer, vigilance 90 jours |
| **Séances hors audience** (4) | réception des requêtes (J+6) ; tutelle (J+13) ; conciliation **tenue** (J-7, 2 dossiers) ; une **annulée** | planifier, tenir, annuler |
| **Assistance judiciaire** (6) | AJ 1 à AJ 6 : 2 accords, 2 rejets (dont 1 avec recours), 2 à traiter | décider, recours, délai moyen |
| **Détenus** | 4 détenus : échéance **dépassée**, **proche**, 2 **normales** | tri par échéance, alertes |
| **Instruction** | RI 45/26 (réquisitoire J-80) et RE 45/26 (réquisitoire J-65, **clôturé** J-10) | saisir, clôturer, secret |
| **Avocats** (10) | 8 avocats, 1 cabinet **à compléter**, 1 **inactif** | liste, filtres, doublon |
| **Rôles** | passés (5e, 3e civiles), **1re civile le J-4 et le jour**, **audience du jour** (5e civile), rôles en préparation (J+3, J+10, J+20…), **conflit de salle** (J+20), **rôle pénal** et **référé** | publication, conflit, rôle du jour |

---

## B. Fiches de saisie (champ par champ)

### Partie 1 — Profils, comptes, sécurité

**F1.1 Créer un profil** (Administration > Utilisateurs > Profils et droits)

| Champ | Valeur valide | Valeur à refuser |
|---|---|---|
| Nom | `Greffier de chambre (essai)` | vide ; un nom déjà pris ; plus de 255 caractères |
| Description | `Enrôle, tient les rôles et les feuilles d'audience` | — |
| Niveau de chaque page | voir le tableau du déroulé (aucun / lecture / saisie / gestion) | un niveau qui n'existe pas pour la page : « Niveau d'accès invalide » |
| Droits spéciaux | cocher ceux de la ligne du profil | — |

Profils à créer : **Greffier de chambre (essai)**, **Magistrat (essai)**, **Coordonnateur (essai)**, **Parquet (essai)**, **Lecteur (essai)**, **Sans droit (essai)**.

**F1.2 Créer un compte**

| Champ | Valeur valide | Valeur à refuser (message) |
|---|---|---|
| Nom | `Essai Greffier` | vide : « Le nom est obligatoire. » |
| E-mail | `essai.greffier@tpi.test` | vide : « L'adresse e-mail est obligatoire. » ; `essai.greffier` (sans @) ; un e-mail déjà pris : « Cette adresse e-mail est déjà utilisée par un autre compte. » |
| Mot de passe | `Essai@2026x` (12 caractères) | `Abc123` (moins que la longueur minimale réglée, 8 par défaut) |
| Profil | `Greffier de chambre (essai)` | aucun : « Choisissez un profil. » ; un profil non attribuable |
| Actif | oui | — |

Comptes : `essai.greffier`, `essai.magistrat`, `essai.coordonnateur`, `essai.parquet`, `essai.lecteur`, `essai.sansdroit` (tous `@tpi.test`).
Essais de blocage : se choisir **soi-même** un autre profil : « Vous ne pouvez pas modifier votre propre profil ni votre statut » ; désactiver puis tenter de se connecter.

**F1.3 Connexion et verrouillage** (utiliser `essai.sansdroit@tpi.test`)

| Essai | Saisie | Attendu |
|---|---|---|
| Mauvais mot de passe | `Faux@0000` × 5 | compte **verrouillé** quelques minutes ; alerte aux administrateurs ; lignes dans le journal de connexion |
| Mauvais code | `000000` × 3 | code invalidé |
| Code de secours | un code du fichier `codes_secours_test.txt` | connexion ; le **même code** refusé ensuite |
| Mon compte > mot de passe | ancien `Essai@2026x`, nouveau `Nouveau@2027y` | accepté ; un nouveau mot de passe trop court : refusé |
| Inactivité | ne rien faire pendant la durée réglée | écran de verrouillage ; mauvais mot de passe : refus ; trop d'essais : session fermée |

### Partie 2 — Organisation

**F2.1 Année judiciaire** : Libellé `2027-2028` · Début `01/09/2027` · Fin `31/07/2028` · Ordonnance `Ordonnance n°700/2027 (essai)`.
À refuser : libellé déjà pris ; **fin avant ou égale au début** (`fin` doit être après `début`) ; libellé de plus de 20 caractères.

**F2.2 Salle** : Nom `Salle d'audience n°5 (essai)` · Type (liste) · Emplacement `Rez-de-chaussée, aile est`. À refuser : nom **déjà pris** (`Cabinet du juge`) ; nom vide.

**F2.3 Agent (magistrat ou greffier)** : Nom `ESSAI` · Prénoms `Test Agent` · Sexe `femme` · Fonction `greffier` · Matricule `M-2026-901` · Téléphone `07 00 00 00 99` · E-mail `agent.essai@tpi.test` · Compte (liste) `essai.greffier@tpi.test`.
À refuser : fonction hors liste ; **matricule déjà pris** ; téléphone `abc` ; e-mail `pas-un-mail` ; **compte déjà relié** à un autre agent ; compte désactivé.

**F2.4 Section et chambre** : Section (liste) · Libellé `6e chambre civile (essai)` · Intitulé `Essai` · Ordre `60` · Attributions `Affaires d'essai`.
À refuser : libellé vide : « Le nom de la chambre est obligatoire. » ; année ou section non choisie ; ordre `99999` ; deux chambres de même libellé dans la même année.

**F2.5 Formation et affectation** : dans la chambre créée, ajouter **président** = `ESSAI Test Agent`… puis essayer d'affecter deux fois le même agent (refusé) ou un greffier comme président (refusé).

**F2.6 Calendrier d'audience (créneaux)** : chambre `6e chambre civile (essai)` · jour `jeudi` · heure `08:30` · salle `Salle d'audience n°5 (essai)` · début d'application `J`.
À refuser : même salle, même jour, même heure qu'une autre chambre (**conflit** signalé au planning) ; heure `25:00`.

**F2.7 Jours fériés et vacances**

| Champ | Valide | À refuser |
|---|---|---|
| Type | `Jour férié` | type hors liste : « Type invalide. » |
| Libellé | `Fête de la Paix (essai)` | vide : « Le libellé est obligatoire. » |
| Début | `J+20` | vide : « La date de début est obligatoire. » ; `32/13/2026` : « invalide » |
| Fin | vide (un seul jour) | **fin avant début** : « La date de fin ne peut pas précéder la date de début. » |
| Vacances | début `J+40`, fin `J+47` | — |

**F2.8 Service et responsabilité** : Catégorie `Coordination, contrôle et supervision` · Libellé `Cellule de formation (essai)` · Ordre `5` · Agents : `ESSAI Test Agent` fonction `Chef de cellule`.
À refuser : catégorie vide ; libellé vide ; **ligne d'agent vide** : « Choisissez une personne ou retirez la ligne vide. »

**F2.9 Reconduction** : Année à reconduire = **l'année courante** · Année qui reçoit = `2027-2028` · cocher **composition**, **audiences**, **services**.
À refuser : **même année des deux côtés** : « Les deux années doivent être différentes. » ; une des deux non choisie. Vérifier ensuite que chambres, formations, créneaux et services sont **dans la nouvelle année**, et que reconduire une 2e fois ne crée **aucun doublon**.

**F2.10 Archive des ordonnances** : un vrai PDF de quelques pages. À refuser : un `.txt` renommé en `.pdf`.

**F2.11 Référentiels** (une fiche, valable pour les six pages)

| Page | Ajouter | À refuser |
|---|---|---|
| Types d'audience | `Audience de formation (essai)` | libellé déjà pris ; vide |
| Modes de saisine | `Assignation (essai)` registre civil | doublon |
| Matières | `Matière d'essai` | doublon ; suppression d'une matière **utilisée** |
| Infractions | `Infraction d'essai`, articles `art. 999 (essai)` | doublon ; compléter les articles d'une infraction installée sans articles |
| Natures d'affaire | `Nature d'essai` registre RG | doublon |
| Objets du litige | `Litige d'essai`, mots-clés `essai formation` ; matière et nature liées | doublon |

### Partie 3 — Enregistrer les affaires

**F3.1 Affaire civile (RG)** — Affaires > Liste > Enrôler. Le numéro est **saisi à la main**.

| Champ | Valeur valide | Valeur à refuser (message) |
|---|---|---|
| Numéro RG | `2001/26` | `2001` ; `2001-26` ; `12345678/26` (format `1 à 6 chiffres / 2 chiffres`) : « Saisissez le numéro RG tel … » |
| Date du RG | `J-5` | date **dans le futur** |
| Mode de saisine | `Assignation` | vide : « Choisissez le mode de saisine. » |
| Objet du litige | `Paiement de loyers impayés et expulsion` (l'application propose nature, matière, chambre) | vide : « L'objet du litige est obligatoire. » ; plus de 1 000 caractères |
| Nature d'affaire | celle proposée (liste fermée) | vide : « Choisissez la nature de l'affaire. » |
| Matière | celle proposée | vide : « Choisissez la matière. » |
| Chambre | `2e chambre civile` | vide : « Choisissez la chambre. » |
| Première audience | `J+15` ; heure `08:30` | heure `8h30` : « L'heure doit être au format HH:MM (ex. 09:00). » ; date passée (en mode automatique) |
| Jonction | décoché | coché vers un numéro **qui n'existe pas** ; vers une affaire d'**une autre chambre** ; vers une **annulée** |
| Partie 1 (demandeur) | rôle `Demandeur`, nature `Homme`, nom `AKOTO`, prénoms `Jean-Marc`, naissance `14/03/1982` (ou année seule `1982`), lieu `Bingerville`, nationalité `ivoirienne`, profession `commerçant`, domicile `Bingerville, quartier Cité`, avocat `Me KOUASSI Brou Marc` | nom vide : « Le nom est obligatoire pour une personne physique. » ; naissance dans le futur ; année `1700` |
| Partie 2 (défendeur) | rôle `Défendeur`, nature `Personne morale`, dénomination `Société ESSAI IMMO SARL` | dénomination vide : « La dénomination est obligatoire. » |
| Parties | au moins un demandeur | aucune partie ; **sans demandeur** : « Ajoutez au moins un demandeur. » ; plus de 30 parties |

Numéros d'essai à réserver : **RG 2001/26** (cas nominal), **2002/26** (doublon du 2001 : refusé), **2003/26** (jonction vers 2001 : accepté), **2004/26** (jonction vers `9999/26` : refusé), **2005/26** (jonction vers `1415/26` annulée : refusé), **2006/26** (couple et État comme parties), **2007/26** (nombre de parties « et x autres » : champ `autres` = `12`).
**Cas de numéro déjà pris** : saisir `1391/26` (existe) : « déjà enrôlé » ; `1415/26` (annulée) : « n'est jamais réutilisé ».
**Dérogation** : première audience trop proche (art. 7) : refusée ; avec la case « Dérogation de la Présidente » et un **motif** `Urgence : détention d'un bien` : acceptée (compte avec ce droit).

**F3.2 Affaire pénale (RP)** : Numéro `RP 2101/26` · **N° du parquet** `P-4101/26` · Infraction (liste) `Abus de confiance` · Articles (modifiable) · Prévenu `ESSAI Marc`, nature `Homme` · **mandat de dépôt** `J-30` · lieu de détention `MAMA d'Abidjan (essai)` · échéance (facultative) `J+150` · Victime `SERY Dakoury`.
À refuser : **même N° du parquet** `P-4101/26` (« déjà enrôlé sous RP … ») sauf jonction ; mandat de dépôt **dans le futur** ; échéance **avant** le mandat ; libération **avant** le mandat ; un prévenu **sans nom**.

**F3.3 Instruction** : choisir « instruction » · nature `Crime` (série **RI**) ou `Délit mineur` (série **RE**) · numéro `RI 2201/26` / `RE 2201/26` · cabinet `1er cabinet d'instruction` · inculpé `ESSAI Paul`, rôle `Inculpé` · victime. À refuser : inculpé absent ; série civile avec nature d'instruction.

**F3.4 Avocat** : Type `Avocat` · Nom `DIOMANDE` · Prénoms `Awa` · Cabinet `Cabinet Diomandé` · Barreau `BA-2001` · Téléphone `07 11 22 33 44` · E-mail `diomande@formation.test` · Actif oui.
À refuser : même nom + prénoms que `KOUASSI Brou Marc` (**doublon**) ; supprimer un avocat **rattaché à des parties** (le désactiver) ; e-mail invalide.

**F3.5 Dossier numérique** (fiche de l'affaire **RG-1391/26**, onglet Dossier numérique)

| Essai | Fichier | Attendu |
|---|---|---|
| Ajout normal | 3 PDF de 1 page (assignation, conclusions, pièce) types différents, titres `Assignation`, `Conclusions du défendeur`, `Pièce n°1`, date `J-10` | 3 pièces dans le sommaire |
| Faux PDF | un `.txt` renommé en `.pdf` | refusé : « n'est pas un vrai PDF ni une vraie image » (tout le lot) |
| Trop de fichiers | 21 fichiers | « Vingt fichiers au plus à la fois. » |
| Doublon | le même PDF une 2e fois | ignoré, message |
| Trop gros | un fichier plus gros que la limite des réglages | refusé : taille maximale |
| Date de pièce | `J+5` | refusée (dans le futur) |
| Retrait | pièce + motif `Pièce déposée par erreur` | retirée de la vue, motif au journal |
| Emplacement papier | `Archives, étagère B-12` | visible dans la liste |

**F3.6 Importation** : télécharger le modèle Excel ; remplir **8 lignes** : 5 correctes (numéros `3001/26` à `3005/26`) et 3 fausses : numéro en double (`3001/26` de nouveau), date `31/02/2026`, chambre `Chambre inconnue`. Attendu : rapport avec les 3 erreurs ligne par ligne, 5 affaires importées, ancien numéro conservé, **annulation** possible.

**F3.7 Recherche plein texte** : `KOFFI loyers` (affaire 1391/26 trouvée) · `BATIMAT` (plusieurs affaires) · `100%` (aucun résultat, pas tout) · `a` (trop court, ignoré).

### Partie 4 — Requêtes, hérédité, mise en état

**F4.1 Déposer une requête** (compte greffier 1re civile)

| Champ | Valide | À refuser |
|---|---|---|
| Type | `Requête en hérédité` | — |
| Date de dépôt | `J-1` | dans le futur |
| Objet | `Requête en hérédité : succession de feu KOUMAN Paul (essai)` | vide |
| Requérants | `KOUMAN Aya` (ligne 1), `KOUMAN Jean` (ligne 2) | vide |
| Défunt | `KOUMAN Paul` · décès `J-60` · lieu `Bingerville` | hérédité **sans défunt** |

Autres types à créer : `Injonction de payer` (`Société DELTA SARL`, objet `Injonction de payer : 1 800 000 F CFA`), `Ordonnance sur requête`, `Voies d'exécution`, `Bref délai`, `Autre`. Contrôle : numéros **RQ-0009/26, RQ-0010/26…** consécutifs sans trou.

**F4.2 Répartir / traiter / classer** (Présidente puis greffier)

| Action | Donnée | Attendu / refus |
|---|---|---|
| Répartir RQ-0004/26 | chambre `1re chambre civile`, note `À traiter cette semaine` | statut Répartie |
| Répartir une requête **traitée** (RQ-0007/26) | — | refusé : « ne se répartit plus » |
| Traiter RQ-0005/26 (**ordonnance sur requête**) avec le greffier | — | bouton absent : « signature réservée » |
| Traiter RQ-0005/26 avec la Présidente | — | « signée le … par … » |
| Traiter RQ-0003/26 (**non répartie**) | — | refusé : « Une requête doit être répartie avant d'être traitée. » |
| Classer RQ-0001/26 | motif `Requérant absent à l'audience` | statut Classée |
| Corriger RQ-0008/26 (classée) | — | refusé : « ne se modifie plus » |

**F4.3 Mise en état** (greffier 1re civile) — **Confier une affaire**

| Champ | Valide | À refuser (message) |
|---|---|---|
| Affaire | `RG-1503/26` | affaire **pénale** `RP-1334/26` ; affaire d'un autre périmètre ; affaire **déjà en mise en état** (1391/26) |
| Date de réception | `J-12` | `J+3` : « La date de réception ne peut pas être dans le futur. » |
| Juge chargé | `DIAKO Myriam` | un magistrat d'**une autre chambre** : « Le juge chargé doit être un magistrat de la chambre de l'affaire. » |
| Première audience | `J-5` | avant la réception : « La première audience ne peut pas précéder la réception du dossier. » |

**Clôturer** `RG-1402/26` : n° `OC-31/26` · date `J-1` · chambre de jugement `1re chambre civile` · audience de jugement `J+20`. À refuser : clôture **avant la réception** : « La clôture ne peut pas précéder la réception du dossier ni la première audience. » ; audience de jugement **avant la clôture** : « L'audience de jugement ne peut pas précéder la clôture. »
**Retirer** `RG-1503/26` : motif `Accord amiable des parties` ; sans motif : refusé. Essayer de modifier une mise en état **clôturée** ou **retirée** : « ne se modifie plus ».

### Partie 5 — Rôles d'audience

**F5.1 Créer un rôle** (greffier 1re civile) : chambre `1re chambre civile` · date `J+10` (un jeudi) · heure et salle du calendrier · type `civil`.
À refuser : **même chambre et même date qu'un rôle existant** (ex. la date du rôle déjà prêt `J+3`) : « Un rôle existe déjà » ; date `01/01/2018` ; date dans plus d'un an ; chambre hors périmètre.

**F5.2 Compléter un rôle** (rôle de la 1re civile du **J+3**, en préparation)

| Action | Donnée |
|---|---|
| Ajouter par numéro | `1541/26` dans « Affaires nouvelles » ; `RP 1334/26` dans le rôle pénal |
| Ligne libre | rubrique « Affaires anciennes », texte `Affaire sans numéro (essai) — dossier à régulariser` |
| Modifier / monter / descendre / retirer | sur une ligne |
| En-tête | date, heure `09:00`, salle `Salle d'audience KONE Mamadou (n°4)`, signataire `Le Greffier en chef`, **Afficher les avocats** coché |
| Publier | rôle non vide, salle libre → **version 1** |

À refuser : **publier un rôle vide** ; publier le rôle en **conflit** (voir F5.3) ; ajouter une affaire **annulée** ou d'**une autre chambre** ; modifier un rôle **publié** sans le rouvrir ; supprimer un rôle **publié**.

**F5.3 Conflit de salle** : deux rôles le même jour, même salle, même heure : prêts à J+20 (5e et 4e chambres civiles, Salle n°3, 09:00 et 10:00). Changer la salle d'un des deux : le conflit disparaît et la publication s'ouvre.

**F5.4 Rôle pénal** : avec `essai.parquet@tpi.test` : type `pénal`, chambre `1re chambre correctionnelle`, date `J+8`. Avec `essai.lecteur@tpi.test` : bouton absent ; création refusée (403).

**F5.5 Rouvrir et republier** : motif `Ajout d'une affaire urgente` → version **2**, la version 1 reste visible.

### Partie 6 — Audiences

**F6.1 Tenir l'audience du jour** (greffier 5e civile, rôle de **J**, chambre `5e chambre civile`) — une issue par affaire :

| Affaire | Issue | Champs |
|---|---|---|
| RG-1601/26 | **Renvoyée** | motif `Pour conclusions du défendeur` ; date de renvoi `J+14` (un jour ouvré) |
| RG-1602/26 | **Mise en délibéré** | date de délibéré `J+21` |
| RG-1603/26 | **Jugée** | (déclenche « rédiger la décision ») |
| RG-1604/26 | **Radiée** | — |
| RG-1605/26 | **Jointe** | affaire principale `RG-1601/26` |
| RG-1606/26 | **Désistement** | — |

À refuser : renvoi **sans motif** ; renvoi **sans date** ; date de renvoi **avant l'audience** ou un **dimanche** ou un **jour férié** ; **jointe** sans principale, ou principale d'**une autre chambre** (ex. `RG-1621/26`) ; une issue notée sur un rôle **non publié** (404). Cocher une présence : `Défendeur seul`. **Terminer l'audience**, puis modifier : « L'audience est terminée : rouvrez-la pour corriger. »

**F6.2 Renvois et délibérés** : changer la date du renvoi de `RG-1621/26` (échu) à `J+7` ; essayer `J-3`, un dimanche, un jour de vacances : refusés. Vider le délibéré de `RG-1622/26` en le notant « Jugée ». Deux navigateurs modifient la même ligne : « Cette suite a changé : actualisez la page. »

**F6.3 Suites d'audience** : filtrer `chambre = 5e civile`, période `J-30 → J`, issue `Renvoyée` ; exporter en PDF ; comparer avec F6.1.

**F6.4 Séance hors audience** (juge 1re civile ou compte magistrat)

| Champ | Valide | À refuser |
|---|---|---|
| Type | `Conciliation` | — |
| Date | `J+9` | — |
| Heure | `10:00` | `25:00` |
| Lieu | `Cabinet du juge` | — |
| Juge | `DIAKO Myriam` (liste = magistrats seulement) | un greffier : absent de la liste |
| Greffier | `KOUADIO Guedji Emile` (liste = greffiers) | — |
| Dossier | affaire `RG-1402/26`, objet `Conciliation de voisinage`, issue `Conciliée` | affaire hors périmètre |

Déclarer **tenue** la séance du `J+9` : refusé (à venir). Modifier la séance **tenue** (conciliation J-7) : refusé. Corriger la date de la séance à `J` puis la déclarer tenue : accepté. Annuler la séance `tutelle` à `J+13`.

**F6.5 Pilotage** : période `J-60 → J`, chambre `5e chambre civile`.

### Partie 7 — Rédaction (jugement d'hérédité)

**F7.1 Valeurs complètes d'un jugement d'hérédité** (pour rédiger **RG-1601/26** ou **1603/26** à la main)

| Champ | Valeur |
|---|---|
| Affaire / modèle | `RG-1603/26` (jugée à l'audience F6.1) · `Jugement d'hérédité` |
| Date de la décision | `J` |
| Matière | `civile d'état des personnes` · Type d'audience `ordinaire` |
| Demandeur | `KOFFI Adjoua` · genre `féminin` · naissance `14/03/1982` à `Bingerville` · nationalité `ivoirienne` · domicile `Bingerville, quartier Cité` · comparution `en personne` |
| Défunt | `TANO Michel` · genre `masculin` · domicile `Bingerville` |
| Décès | date `J-130` · lieu `Clinique Mamie` |
| Acte de décès | centre `Bingerville` · date `J-120` · n° `DRP2001` |
| Mariage | date `21/06/2008` · n° `600-08` · régime `la communauté de biens` |
| Enfants | nombre `2` · `TANO Aya`, `TANO Michel junior` |
| Témoins | nombre `2` · `DJOMAN Patrick`, `KOUAKOU Estelle` |
| Dates | requête `J-40` · conclusions du ministère public `J-5` |
| Articles visés | `731 et suivants du code civil (essai)` |

À refuser : marquer **rédigée** avec `date_deces` ou `acte_deces.numero` vides : « Enregistrez puis complétez : il manque … » ; choisir une affaire d'**une autre chambre** (404) ; un modèle **sans version**.

**F7.2 Circuit** (décisions prêtes) : `1502/26` → **valider** ; `1503/26` → **signer** avec date `J` ; `1521/26` → **délivrer** :
| Étape | Champ | Valide | À refuser |
|---|---|---|---|
| Signer | Date de signature | `J` | `J+2` : « La date de signature ne peut pas être dans le futur. » |
| Signer | — | décision **validée** (1503) | décision non validée (1502) : « Seule une décision validée peut être signée » ; déjà signée |
| Délivrer | Type | `Expédition` puis `Grosse` puis `Copie simple` | décision **non signée** (1503) |
| Délivrer | À qui | `KOFFI Adjoua` · qualité `demandeur` | vide : « Indiquez à qui la décision est remise. » |
| Délivrer | Date de remise | `J` | `J+1` ; une 2e **grosse** à `KOFFI Adjoua` : « elle ne se délivre qu'une fois à la même personne » |
| Valider | — | décision complète | décision avec champs manquants (1501) ; sans numéro si le réglage l'exige |
| Numéroter | — | (réglage « par le greffier ») | décision non rédigée ; déjà numérotée |

**F7.3 Bloc de texte** : Code `visa_essai` · Nom `Visa d'essai` · Catégorie `Visas` · Contenu `<p>Vu la requête du {{ date_requete }} ;</p>`. À refuser : code `Visa Essai` (majuscules/espace) ; code déjà pris ; contenu avec `{{ x|nexistepas }}` ; supprimer le bloc `formule_jugement` (utilisé par un modèle).

**F7.4 Registre des décisions** : filtres `année = année courante`, `chambre = 2e civile`, statut `Signées` ; recherche `D-3` ; exports Excel et PDF.

### Partie 8 — Pénal, instruction, annexes

**F8.1 Répertoire pénal** (affaire **RP-1334/26**)

| Essai | Saisie | Attendu |
|---|---|---|
| Condamnation normale | prévenu `JOHNSON Wedha` · date `J-3` · contradictoire · issue `Condamné` · emprisonnement `24` mois dont sursis `6` · amende `100 000` | « 2 ans dont 6 mois avec sursis ; Amende : 100 000 F CFA » |
| Condamné sans peine | issue `Condamné`, aucune peine | refusé |
| Relaxé avec amende | issue `Relaxé`, amende `50 000` | refusé |
| Sursis supérieur | emprisonnement `6`, sursis `12` | refusé |
| Sursis seul | emprisonnement vide, sursis `6` | refusé |
| Date | date **avant l'enregistrement de l'affaire** ou `J+2` | refusé |
| Relaxe d'un détenu | prévenu `KOUADIO Kouakou`, issue `Relaxé` | disparaît des **Détenus** |
| Minute | transmission `J`, destinataire `Greffe central` | « Transmise » ; une date **avant la décision** : refusée |

**F8.2 Instruction** (juge d'instruction 1er cabinet, **RI-0045/26**)

| Champ | Valeur |
|---|---|
| Réquisitoire | date `J-80` (déjà saisi) ; nouvelle date `J-90` |
| Faits | date `J-120` · lieu `Bingerville` · nature `Crime` · infraction `Association de malfaiteurs` |
| Inculpé `DOSSO Ibrahim` | statut `Détenu` ; mandat `J-60` ; **renouvellement** `J-5` |
| Victime | nom, audition `J-30` |
| Clôture | date `J-1` · nature `Renvoi devant la cour d'assises` |

À refuser : clôture **avant le réquisitoire** ; renouvellement **avant le mandat** ; dates futures. **Commissions rogatoires** : cabinet `1er cabinet d'instruction` · année courante · mois `octobre` · sens `émises` · portée `nationale` · stock initial `5` · nouvelles `3` · exécutées `4` (stock restant 4) ; à refuser : exécutées `9` (plus que `5+3`). Avec `elisabeth.konan` (2e cabinet) : le dossier RI-0045/26 est **invisible**.

**F8.3 Assistance judiciaire** : N° `AJ 7/26` · date du RG `J-5` · demandeur `DEMO ESSAI Awa` · objet `Divorce` · nature `Civile` · audience `J+10` ; décision `accord` n° `AJ-D7` date `J` ; recours date `J+3`. À refuser : décision **avant** la date du RG ; recours **sans décision**.

**F8.4 Activités du JAP** (compte `ndey.djole@tpi.test`) : année courante · mois `octobre` · compteurs `12`, `8`, `5` ; à refuser : valeur négative ; exporter le `.xlsm`.

### Partie 9 — Recours et certificats

**F9.1 Parcours** (greffier puis coordonnateur)

| Décision | Action | Données | Attendu / refus |
|---|---|---|---|
| **1511/26** (à signifier) | Signification | date `J-1` · nature `Appel` · à `KOFFI Adjoua (demandeur)` | « Délai en cours » ; date `J+2` : refusée |
| **1512/26** (expirée) | Certificat (coordonnateur) | Délivré à `KOFFI Adjoua`, qualité `demandeur` | n° suivant, PDF ; un 2e certificat refusé |
| **1513/26** (recours) | Transmettre le recours | date `J-2`, référence cour `CA-2026-77` | « Transmis » ; certificat **refusé** |
| **1513/26** | Abandonner le recours | — | certificat redevient possible |
| **1514/26** (en cours) | Enregistrer un recours | nature `Appel`, auteur `Société BATIMAT SARL`, date `J` | « Recours formé » |
| **1522/26** (certifiée) | 2e certificat | — | refusé |
| **1531/26** | Signification | `J-14` | état suivant |

**F9.2 Délais de recours** (coordonnateur) : vérifier `appel civil 1 mois`, `opposition 15 jours`, `référé 15 jours`, `appel correctionnel 10 jours` ; décocher « confirmé » sur un délai puis tenter un certificat : refusé ; le reconfirmer. Tester une échéance qui tombe un samedi (reportée au lundi).

### Partie 10 — Statistiques, registres

**F10.1 Statistiques civiles** : années `2022` à l'année courante ; chambre `Toutes` puis `1re civile` ; onglets dans l'ordre ; cliquer sur le chiffre « Nouvelles » d'un mois ; onglet **À compléter** : choisir une affaire jugée, caractère `accordée`, statut des adoptants `Marié(e)s`.
**F10.2 Statistiques pénales** : filtre année ; export Excel (liste des décisions).
**F10.3 Registres** : télécharger `Affaires matrimoniales`, `Audience civile diverse`, `Délits divers`, `Instruction : 1er cabinet`, `RECAP ENROL. CIV PAR AN` ; filtre chambre `2e civile`.
**F10.4 Droits** : `essai.lecteur@tpi.test` : 403 ; compte « voir seulement » : chiffres sans nom ni numéro, sans export.

### Partie 11 — Alertes

**F11 Provoquer chaque alerte avec le jeu préparé.** Le moteur envoie une alerte **le jour exact** où « échéance + décalage = jour évalué » (J-7, J-3, J-1, J+1, J+7, J+15…). On le fait tourner **à une date choisie** :

```bash
php artisan alertes:evaluer --simuler --date=2026-11-04   # montre ce qui partirait ce jour-là, sans rien envoyer
php artisan alertes:evaluer --date=2026-11-04             # envoie vraiment (cloche et e-mail récapitulatif)
```

> Les dates ci-dessous valent pour un jeu **créé le 05/11/2026**. Si le jeu est créé un autre jour, **décaler chaque date du même nombre de jours** (jeu créé le 12/11 : ajouter 7 jours).

| Alerte | Donnée du jeu | Date à passer à `--date` | Décalage | Qui la reçoit |
|---|---|---|---|---|
| **Mise au rôle** | RP-1334/26 : première audience le 08/11, absente de tout rôle | `2026-11-02` puis `2026-11-04` | J-3 puis J-1 | greffier de la chambre |
| **Publication du rôle** | rôle du 12/11 (1re civile) et du 08/11 (1re correctionnelle) en préparation | `2026-11-03` (J-7) et `2026-11-02` (J-3) | J-7, J-3 | greffier / chef de service |
| **Renvoi échu** | RG-1621/26 renvoyée au 29/10 ; RG-1625/26 renvoyée au 11/11 | `2026-11-05` (J+7) ; `2026-11-04` (J-7) | J-7, J+7 | greffier |
| **Délibéré à rendre** | RG-1622/26 : délibéré au 01/11 | `2026-11-02` puis `2026-11-08` | J+1, J+7 | président de formation |
| **Suites non saisies** | audience du 02/11 (2e civile, 4 affaires) ; audience du 05/11 (5e civile, 9 affaires) | `2026-11-04` ; `2026-11-07` | J+1, J+7 | greffier |
| **Première audience passée sans passage au rôle** | RG-1501/26 à 1503/26 (audience du 15/11) ; RP-1334/26 et 1340/26 (08/11) | `2026-11-16` ; `2026-11-09` | J+1, J+7 | greffier |
| **Répartition d'une requête** | RQ-0003/26 déposée le 30/10 ; RQ-0004/26 déposée le 04/11 | `2026-11-02` ; `2026-11-03` | J+1, J-3 | secrétariat de la présidence |
| **Validation de la décision** | décision 1502/26 rédigée le 20/10 | `2026-11-07` | J+15 | magistrat |
| **Signature de la décision** | décision 1503/26 validée le 25/10 | `2026-11-04` puis `2026-11-12` | J+7, J+15 | magistrat |
| **Certificat de recours à délivrer** | décision D-3/2026 (1512/26) : délai de recours expiré le 26/10 | `2026-11-03` puis `2026-11-11` | J+7, J+15 | greffier en chef |
| **Détention** | RP-1334/26 : échéance du mandat le 13/11 ; RI-0045/26 : le 14/11 | `2026-11-06` puis `2026-11-10` ; `2026-11-07` puis `2026-11-11` | J-7, J-3 | juge / parquet |
| **Délai de procédure** | affaires civiles de plus de 6 mois et correctionnelles de plus de 3 mois du jeu statistique (ex. 8015/26, RP 8027/26) | `2026-11-02` puis `2026-11-03` | J+7, J+15 | Présidente, vice-présidents, coordonnateur |
| **Minute pénale à transmettre** | décisions pénales sans transmission (ex. RP 8002/26, RP 8015/26) | `2026-11-07`, `2026-11-11` | J+15, J-7 | greffier pénal |
| **Limite de renvois (référé-expulsion)** | **à créer à la main** : renvoyer deux fois une affaire de référé-expulsion | le lendemain de la 2e audience | selon la règle | président de formation |

**Déroulé à suivre pour chaque ligne**

1. `--simuler --date=…` : la ligne attendue figure dans la liste, avec son **destinataire**.
2. Même commande **sans** `--simuler` : ouvrir la **cloche** du destinataire (le compte correspondant du tableau A.2) : l'alerte est là, avec **l'action à mener** et un lien vers l'affaire.
3. Relancer **la même commande** : aucune alerte en double (anti-doublon).
4. **Justifier un retard** (Alertes > Suivi) : motif `Dossier en attente des pièces du demandeur`, date de suspension `J+10` ; relancer à une date **avant** la suspension : l'alerte ne part pas ; **après** : elle repart.
5. **Faire l'action** qui règle la situation (publier le rôle, signer la décision, répartir la requête, délivrer le certificat…), puis évaluer à nouveau : l'alerte se **referme** toute seule (« Alertes refermées » dans le récapitulatif).
6. **Règles** : désactiver la règle « signature de la décision » ; évaluer `2026-11-12` : plus d'alerte de ce type ; la réactiver ; changer son décalage de `J+7` à `J+3` : l'alerte part un autre jour.
7. **Rattrapage** : `php artisan alertes:evaluer` sans date après une longue pause : le moteur rattrape les jours manqués (14 jours au plus).
8. **E-mail** : un **seul récapitulatif** par personne et par jour (Réglages > Courriels en mode « Test » : lu dans le journal du serveur).

### Partie 12 — Réglages (valeurs à essayer)

| Onglet | Champ | Valeur d'essai | Attendu |
|---|---|---|---|
| Organisation | Ville | `ESSAI-VILLE` | en-tête vert change ; rétablir |
| Sécurité | Verrouillage après inactivité | `1` | écran de verrouillage après 1 minute ; `500` : refusé (max 240) |
| Sécurité | Échecs avant verrouillage | `3` | compte bloqué au 3e échec ; `2` : refusé (min 3) |
| Sécurité | Longueur minimale du mot de passe | `12` | un mot de passe de 10 caractères refusé ; `6` : refusé (min 8) |
| Courriels | Mode d'envoi | `Test` | rien n'est envoyé ; essai d'envoi : message clair |
| Courriels | Port | `70000` | refusé (max 65 535) |
| Affaires | Numéro RG | `Attribué par l'application` | l'enrôlement propose un numéro et contrôle la date limite |
| Affaires | Durée du mandat de dépôt | `100` | échéances recalculées |
| Affaires | Taille max d'une pièce | `1` | un PDF de 3 Mo refusé |
| Actes | Taille du corps | `14` | impression change ; `100` : refusé (max 40) |
| Décisions | Moment du numéro | `Par le greffier de la chambre`, puis `À la signature`, puis `À la validation` | le circuit du F7.2 change |
| Pilotage | Analyse par l'IA | `Non` | seule l'analyse automatique |
| Interface | Lignes par page | `25` | petits tableaux ; les longues listes restent à 20 |

**Penser à rétablir tous les réglages à la fin.**

### Partie 13 — Attaques simples (sécurité)

| Essai | Saisie | Attendu |
|---|---|---|
| Script dans un nom de partie | `<script>alert(1)</script>` | affiché en texte, jamais exécuté |
| Image piégée dans un objet | `<img src=x onerror=alert(2)>` | idem |
| Identifiant modifié dans l'adresse | `/affaires/liste/1` avec un compte hors périmètre | 404 |
| Champs en trop | `statut=tenue`, `cree_par=1` dans un formulaire | ignorés |
| Rafale | 300 actualisations d'une liste en une minute | « trop de tentatives » |
