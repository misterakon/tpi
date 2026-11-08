# Guide pratique : rôles d'audience et planning des salles

Menu **Audiences** > **Rôles d'audience** et **Planning des salles**. Les exemples utilisent les données d'essai (`php artisan affaires:demo`) : elles sont fictives.

## 1. Comprendre en deux minutes

- Un **rôle** = une chambre + une date. Il se compose tout seul à partir des affaires, puis on le relit, on le corrige, on le **publie** et on l'exporte en **Word** ou **PDF**.
- Trois rubriques, comme sur le papier : **Affaires en délibéré**, **Affaires anciennes**, **Affaires nouvelles**.
- Trois types : **civil**, **référé**, **pénal** (réservé au parquet).
- Un rôle est **en préparation** (modifiable) puis **publié** (texte figé, version numérotée).
- Le **planning** montre la semaine salle par salle et signale les conflits.

## 2. Qui peut faire quoi (droits)

| Droit | Permet |
|---|---|
| `roles.voir` | Consulter la liste, un rôle, le planning |
| `roles.creer` | Créer un rôle |
| `roles.modifier` | Modifier un rôle en préparation, noter la suite donnée |
| `roles.supprimer` | Supprimer un rôle jamais publié |
| `roles.imprimer` | Exporter en Word et PDF |
| `roles.publier` | Publier et rouvrir un rôle |
| `roles.penal` | Créer et modifier les rôles pénaux (parquet) |

À accorder par profil dans **Utilisateurs > Profils et droits**. Chacun ne voit que les chambres de son périmètre.

## 3. Cas pratique 1 : préparer le rôle de jeudi (civil)

1. **Audiences > Rôles d'audience.** Sous les 4 tuiles, le bandeau orange « Audiences des 14 prochains jours sans rôle » liste les chambres qui siègent dans les 14 jours à venir (d'après leur calendrier d'audience) et dont le rôle n'est pas encore créé. La pastille disparaît dès que le rôle existe.
2. Cliquez sur la pastille « 1ère chambre civile · jeudi ». La fenêtre s'ouvre avec la chambre, la date, **l'heure** et la **salle** déjà remplies. Vérifiez, puis **Créer et composer le rôle**.
3. Le rôle s'ouvre et se compose seul :
   - **Nouvelles** : affaires de la chambre dont la première audience est cette date ;
   - **Anciennes** : affaires renvoyées dont la date de renvoi est arrivée (et celles jamais portées à un rôle) ;
   - **Délibérés** : affaires dont la dernière suite notée est « mise en délibéré ».
   Les affaires jugées, radiées ou jointes à une autre n'y figurent pas ; une affaire jointe apparaît sur la ligne de sa principale (« 690/26 / Et 848/26 »).
4. À droite, le bloc **Affaires proposées** indique s'il reste des affaires à ajouter. **Ajouter au rôle** les reprend.
5. Une affaire manque ? Dans la rubrique voulue, **Ajouter** : saisissez son numéro comme au registre (`1391/25`, ou `RP 1334/26`). Les parties, avocats et objet sont repris. Sans numéro, vous ajoutez une **ligne libre** à remplir au crayon.
6. Sur chaque ligne : **crayon** (modifier le texte, changer de rubrique), **flèches** (monter, descendre), **corbeille** (retirer).
7. Bloc **Audience** > crayon : date, heure, salle, lieu et date de signature, signataire, et l'interrupteur **Afficher les avocats** (« (Me X) » sous les parties, comme sur le rôle de janvier).
8. **Publier le rôle** (bloc Publication) : la version 1 est figée. Le bouton est grisé si le rôle est vide ou si la salle est en conflit.
9. **Word** ou **PDF** (en haut de la page) : le document reprend votre modèle (papier à en-tête, armoiries, filigrane de la balance, titres de colonnes, « Fait à Bingerville, le … / Le Président »).

## 4. Cas pratique 2 : après l'audience, noter ce qui s'est passé

C'est ce qui permet au rôle suivant de se composer sans ressaisie.

1. Ouvrez le rôle publié. Sur chaque ligne, l'icône **drapeau** permet de noter la **suite donnée** :
   - **Renvoyée** : indiquez la **date de renvoi** (postérieure à l'audience) ;
   - **Mise en délibéré** ;
   - **Jugée**, **Radiée**, **Jointe à une autre**.
2. La suite apparaît en pastille sur la ligne (elle n'est **pas imprimée**).
3. Créez ensuite le rôle suivant : une affaire renvoyée au 12/11 apparaît dans les **anciennes** du rôle du 12/11 ; une affaire en délibéré apparaît dans les **délibérés** ; une affaire jugée disparaît.

À retenir : une ligne dont la suite n'a pas été notée est reprise parmi les **anciennes** du rôle suivant. Notez donc une suite pour chaque ligne.

## 5. Cas pratique 3 : corriger un rôle déjà publié

1. Rôle publié > **Rouvrir pour corriger**. Un **motif** est obligatoire (ex. « affaire 1391/25 oubliée »).
2. Le rôle repasse en préparation : la version 1 reste conservée telle qu'elle a été diffusée, vos corrections formeront la **version 2**.
3. **Publier** à nouveau. Dans « Versions publiées », chaque version a ses icônes Word et PDF.

Un rôle publié ne se supprime pas ; un rôle jamais publié se supprime (lien rouge « Supprimer ce rôle »).

## 6. Cas pratique 4 : le planning et les conflits de salle

1. **Planning des salles** : une ligne par salle, un jour par colonne (lundi à samedi), le jour en cours en orange léger, les jours fermés (fériés, vacances) hachurés avec leur libellé.
2. **Changer de semaine sans recharger la page** : flèches, bouton « Cette semaine », ou le **sélecteur de date** (tapez une date, la semaine correspondante s'affiche).
3. Cartes : vert = publié, orange = en préparation, rouge = conflit, **pointillés** = audience habituelle sans rôle.
4. Cliquez sur une carte en pointillés : la fenêtre de création s'ouvre pré-remplie.
5. **Règle** : deux chambres ne peuvent pas prendre la même salle le même jour à moins de **3 heures** d'écart. Le système refuse la création ou la modification, et la publication, avec un message qui nomme la chambre et l'heure en cause (« La salle … est déjà prise ce jour-là à 10h00 par la 1re chambre correctionnelle… »). Exemple : 1ère civile à 09h00 en salle 4 ; la 2e civile ne peut pas y être à 10h30, mais peut y être à 12h00. Un rôle sans heure occupe la salle toute la journée.
6. **Ce qui est signalé sans bloquer** :
   - **Jour fermé** (férié, fermeture exceptionnelle, vacances judiciaires) : bandeau orange dans l'éditeur, jour hachuré sur le planning.
   - **Jour non habituel** de la chambre : simple information.
   - **Avertissement magistrat ou greffier déjà pris** : si un magistrat ou le greffier (hors suppléants) de la composition de la chambre figure aussi dans la composition d'un autre rôle du même jour à moins de 3 heures, une note orange s'affiche dans l'éditeur et la puce « N avertissements » sur le planning. Cela n'empêche jamais d'enregistrer ni de publier : c'est à vous de vérifier la composition. Sans heure sur l'un des deux rôles, rien n'est signalé.
7. Pour un conflit rouge : crayon du bloc **Audience**, changez la salle ou l'heure, enregistrez.

## 7. Cas pratique 5 : un rôle de référé (salle 3)

1. **Nouveau rôle** > **Type de rôle : Rôle de référé**. Choisissez ou saisissez l'**intitulé** (ex. « REFERE EXPULSION – ORDINAIRE – HEURE A HEURE »).
2. Titre obtenu : « ROLE D'AUDIENCE DE REFERE / DU MERCREDI 21 OCTOBRE 2026 A 09 HEURES / (REFERE EXPULSION – ORDINAIRE – HEURE A HEURE) ».
3. La colonne **Observations** est préremplie : **EXPUL.** si l'objet parle d'expulsion ou de déguerpissement, sinon **ORDIN.** Corrigez au crayon si besoin.

## 8. Cas pratique 6 : un rôle pénal du parquet

Nécessite le droit `roles.penal`.

1. **Nouveau rôle** (le type **pénal** est proposé automatiquement quand on choisit une chambre correctionnelle) > **Rôle pénal (parquet)**, chambre correctionnelle, date, heure, salle. Intitulé au choix : « AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS », « … DES DELITS EN MATIERE DE POLICE DE LA CIRCULATION », etc. Case **Rôle additif** pour ajouter « (Rôle additif) ».
2. Les affaires RP de la chambre dont la première audience est cette date sont reprises : n° RP, **identité** (nom, âge, nationalité, profession), **mandat de dépôt**, **nature de la prévention** suivie de « Arts : … », mention « Scellé » s'il y a des scellés.
3. Bloc **Audience** > crayon : **Président, Assesseurs, Procureur, Greffier** (laissés vides, ils se remplissent à la main sur le papier), intitulé, additif.
4. Si au moins une ligne a une date de mandat de dépôt, la colonne « DATE DU MANDAT DE DEPOT » s'imprime et la dernière colonne s'appelle « OBSERVATION » ; sinon elle disparaît et la dernière colonne s'appelle « DECISION DU TRIBUNAL ».
5. Document : papier à en-tête du parquet, titre, équipe d'audience avec « 10 HEURES » à droite, tableau, puis « Fait au Parquet de Bingerville, le … / LE PROCUREUR DE LA REPUBLIQUE ».

## 9. Mise en forme des documents

- **En gras** : numéro d'ordre (et n° RP), nom des parties, date du mandat, articles visés.
- **En italique** : âge, nationalité, profession, domicile ; avocats.
- Titres de colonnes répétés sous chaque rubrique ; numéro de page dans un petit cadre en bas à droite ; filigrane de la balance sur chaque page.
- Le nom de la cour d'appel et du tribunal viennent des **Réglages** de l'application.

## 10. Données d'essai

`php artisan affaires:demo` crée des rôles et des situations fictifs pour tout essayer :

- civil publié (29/10), civil en préparation (12/11, avec 3 affaires à proposer), pénal (jeudi 05/11, salle 4, 10h00, flagrants délits), référé (lundi 09/11, salle 3) ;
- **suites à noter** : rôle publié de la 2e chambre civile (lundi 02/11) avec 4 affaires sans suite ;
- **conflit de salle** : 5e civile (09h00) et 4e civile (10h00) dans la même salle, mercredi 18/11 ;
- **avertissement magistrat/greffier** : 3e civile et 5e civile, vendredi 20/11, mêmes agents dans les deux compositions ;
- **jour fermé** : « Fermeture exceptionnelle (essai) » jeudi 19/11, avec un rôle de la 1re civile ce jour-là, et « Vacances judiciaires (essai) » du 14 au 18/12.

Attention : le rôle pénal du 05/11 en salle 4 à 10h00 est en conflit avec le créneau habituel de la 1re chambre civile (jeudi 09h00, salle 4) : créez le rôle civil de ce jour en changeant la salle (ou l'heure, 13h00 par exemple).

`php artisan affaires:demo --retirer` supprime ces rôles, les fermetures d'essai et les affaires d'essai.

## 11. Limites actuelles

- Les affaires venues de l'**import Excel** n'entrent pas d'elles-mêmes dans un rôle (leur état est inconnu) : ajoutez-les par leur numéro.
- Le détail de la feuille d'audience (décision de chaque affaire, répertoire) relèvera du lot suivant ; ici on note seulement la **suite donnée**.
- Le « quota » d'affaires par audience n'est pas géré (l'ordonnance n'en parle pas).
