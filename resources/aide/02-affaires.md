---
titre: Affaires
icone: fa-solid fa-folder-open
couleur: vert
resume: Enrôler une affaire, gérer ses parties, ses pièces, les requêtes, la mise en état et les détenus.
droit: affaires.voir, dossiers.voir, mise_en_etat.voir, requetes.voir, detenus.voir, avocats.voir, reprise.voir
routes: affaires.*
ordre: 2
---
Le menu **Affaires** est le cœur de l'application : c'est là que naît et que vit chaque dossier. Une affaire a un **numéro** (RG civil, RP pénal, RI et RE instruction), des **parties**, une **chambre**, une **première audience** et un **dossier numérique**.

## Enrôler une affaire

*Affaires > Liste des affaires > bouton orange « Saisir une affaire ».*

1. Choisissez le registre : **civil (RG)**, **pénal (RP)** ou **instruction (RI/RE)**.
2. Saisissez le **numéro** tel qu'il figure au registre du greffe (ex. `1391/26`) et la **date du RG**. L'application **ne fabrique pas** le numéro : elle le **contrôle**.
3. Écrivez l'**objet du litige** avec vos mots (« paiement de loyers impayés et expulsion »). L'application **propose** la nature d'affaire, la matière et la chambre d'après les mots-clés reconnus.
4. Vérifiez ou corrigez la **chambre**, la **première audience** (date et heure) et le **mode de saisine**.
5. Ajoutez les **parties** : demandeur, défendeur, couple, personne morale, État ou ministère public. Pour une personne : nom, prénoms, naissance (date exacte **ou** année seule), nationalité, profession, domicile et **avocat**.
6. Enregistrez.

> **Attention** Un **numéro déjà utilisé** est refusé, sauf s'il s'agit d'une **jonction** (cochez « Jonction ») : elle doit viser une affaire **existante**, de **la même chambre**, et **non annulée**. Un numéro d'affaire annulée n'est **jamais réutilisé**.

> **À savoir** Il faut au moins **un demandeur** (ou un prévenu au pénal). La date du RG ne peut pas être dans le futur, et la première audience ne peut pas être trop proche de l'enrôlement, sauf **dérogation de la Présidente**.

## La fiche d'une affaire

Cliquez sur le numéro d'une affaire pour ouvrir sa **fiche** : parties, historique, rôles où elle a figuré, décisions, alertes, dossier numérique.

- **Corriger le numéro** (droit spécial) : saisissez le bon numéro et le **motif** ; l'ancien numéro est conservé dans la fiche.
- **Réattribuer** à une autre chambre ou **annuler** (avec motif) : ces actions sont tracées au journal.
- Les **filtres** de la liste (série, chambre, période, statut) et la **recherche** fonctionnent sans recharger la page ; la liste affiche **20 lignes** puis charge les pages suivantes à la demande.

## Dossier numérique

*Fiche de l'affaire, onglet « Dossier numérique » ; ou Affaires > Dossier numérique pour la vue d'ensemble.*

1. Glissez vos fichiers (PDF ou images) dans la zone, ou cliquez pour les choisir.
2. Indiquez pour chacun un **type**, un **titre** et une **date**.
3. Enregistrez : les pièces apparaissent dans le sommaire et s'ouvrent dans la **visionneuse**.

> **Attention** Seuls les **vrais PDF et vraies images** sont acceptés (un fichier simplement renommé est refusé), **vingt fichiers au plus** à la fois, avec une taille maximale. Un fichier refusé fait refuser **tout le lot**.

Une pièce ne se supprime pas : on la **retire avec un motif**, et le motif reste au journal. Chaque ouverture ou téléchargement est **journalisé**. Notez aussi l'**emplacement du dossier papier** (« Archives, étagère B-12 »).

## Requêtes et hérédité

*Affaires > Requêtes et hérédité.*

1. **Déposer** la requête : type (hérédité, ordonnance sur requête, voies d'exécution, injonction de payer, bref délai, autre), date de dépôt, objet, requérants (un par ligne). Pour l'hérédité, indiquez aussi le **défunt**, sa date et son lieu de décès.
2. La **Présidente** (ou le secrétariat) la **répartit** vers une chambre.
3. Le greffier de la chambre la **traite** ou la **classe sans suite** (avec motif).

> **À savoir** Les **ordonnances sur requête et injonctions** ont une **signature réservée** : seul le magistrat habilité les marque « traitées ». Une requête doit être **répartie avant d'être traitée**. Non répartie après **2 jours**, elle passe **en retard** et alerte la présidence.

## Mise en état

*Affaires > Mise en état.* Seules les affaires **civiles (RG)** peuvent y passer.

1. **Confier une affaire** à un juge chargé de la chambre, avec la **date de réception** du dossier.
2. Renseignez la **première audience de mise en état**.
3. Quand le dossier est prêt : **clôturez** avec le numéro et la date de l'**ordonnance de clôture**, la chambre et la date de l'**audience de jugement**. L'affaire est alors **à mettre au rôle**.
4. Ou **retirez-la** (motif obligatoire) : elle redevient une affaire ordinaire.

> **Attention** Une mise en état de **plus de 90 jours** est marquée « longue » en rouge. Une mise en état **clôturée ou retirée ne se modifie plus**. Les dates doivent être cohérentes : la clôture ne précède ni la réception ni la première audience.

## Détenus

*Affaires > Détenus.* La liste des prévenus et inculpés sous **mandat de dépôt**, classés par **échéance** (les plus pressantes d'abord), avec « dépassée », « proche » ou « dans N jours ». Elle se met à jour **toute seule** : une personne **libérée** (décision ou date de libération) disparaît de la liste.

Pour qu'un détenu figure ici, saisissez sa **date de mandat de dépôt** et son **lieu de détention** sur la fiche de l'affaire.

## Avocats

*Affaires > Avocats.* Le répertoire des avocats et cabinets : ajoutez, modifiez, désactivez. Un avocat **déjà rattaché à des parties ne se supprime pas** : désactivez-le. Les doublons (même nom et prénoms) sont refusés.

## Importer des affaires existantes

*Affaires > Importation affaire.* Un assistant en **cinq étapes** (fichier, correspondance des colonnes, contrôle, import, rapport) reprend vos classeurs Excel. Les lignes correctes sont importées, les autres sont **listées avec leur erreur**, et les **anciens numéros sont conservés**. Vous pouvez **annuler** un import : tout ce qu'il a créé disparaît.
