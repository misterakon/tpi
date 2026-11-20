# Fichier Excel de l'ordonnance : format (version 1, à valider)

Objectif : charger l'organisation du tribunal (articles 1 à 11 de l'ordonnance) depuis un classeur Excel, au lieu de remplir les formulaires un à un.
Le même classeur sert à **exporter** l'organisation en cours, à la corriger, puis à la **ré-importer**.

## Principes

1. **Une feuille par notion.** Une ligne = une chose (une personne dans une formation, un créneau d'audience…). Pas de cellules fusionnées.
2. **Les numéros relient les feuilles** : chaque chose qui existe déjà en base porte son **N° = l'identifiant entier de la base** (colonne « N° »). Les feuilles qui s'y rapportent le reprennent (ex. « Formation (N°) » dans Composition). Ni les numéros de ligne ni les numéros d'article ne servent de clé. Pour une **nouvelle** chose (chambre, formation, salle, agent, service) : N° vide, et on écrit son **nom** dans les feuilles qui s'y rapportent ; l'application lui donne son numéro à la création.
3. **Les noms peuvent varier.** Dans les feuilles *Composition* et *Responsables*, la colonne « Agent » accepte un N° **ou** un nom écrit comme dans l'ordonnance. À l'import, l'application rapproche le texte des agents connus (accents, majuscules, « épouse X », ordre des prénoms ignorés). Un nom douteux ou inconnu n'est jamais deviné : l'écran de rapprochement demande de choisir l'agent. Le choix est mémorisé (alias) pour les imports suivants.
4. **Rien n'est supprimé.** Ce qui disparaît du fichier est proposé comme « retiré » avec une date de fin ; les rôles et décisions déjà établis gardent leur composition.
5. **Le fichier n'écrit rien tout de suite** : il est lu, contrôlé et comparé ; on applique après avoir vu ce qui change, et on peut annuler l'import.
6. **Les articles gardent leur texte.** Chaque article est conservé tel quel dans la feuille *Articles* ; un article inconnu n'est jamais perdu.

## Feuilles

| # | Feuille | Une ligne = | Colonnes |
|---|---|---|---|
| 0 | **Lisez-moi** | — | mode d'emploi, légende des couleurs (non lue à l'import) |
| 1 | **Ordonnance** | l'ordonnance (1 ligne) | année judiciaire, n° d'ordonnance, type (annuelle / modificative), objet, date de signature, date d'effet, début et fin de l'année, observations |
| 2 | **Sections** | une section | N°, libellé, ordre (+ code interne, information) |
| 3 | **Salles** | une salle ou un cabinet | N°, nom, type (audience / cabinet / reunion), emplacement |
| 4 | **Agents** | une personne | N°, nom, prénoms, sexe (M / F), fonction, catégorie, matricule, téléphone, e-mail |
| 5 | **Chambres** | une chambre | N°, section (N° ou nom), groupe, libellé, intitulé, attributions, remarque, ordre |
| 6 | **Formations** | une formation (facultative) | N°, chambre (N° ou nom), nom, intitulé, attributions, ordre |
| 7 | **Composition** | une personne dans une formation | formation (N°), rôle, suppléant (oui / non), agent (N° ou nom), chef de section (oui / non), du, au, remarque |
| 8 | **Audiences** | un créneau | formation (N°), type, jour, heure, fréquence, salle (N° ou nom), précision, du, au |
| 9 | **Services** | un service, comité, délégation, fonction ou bureau | N°, catégorie, libellé, ordre, modalité / remarque |
| 10 | **Responsables** | une personne dans un service | service (N° ou nom), agent (N° ou nom), fonction exercée, ordre |
| 11 | **Membres extérieurs** | un membre extérieur au tribunal | service (N° ou nom), nom, qualité, institution, ordre |
| 12 | **Dates limites** | un jour d'audience (article 7) | jour d'audience, jour limite, heure, chambres concernées, exclusions |
| 13 | **Articles** | un article | numéro, intitulé, texte complet, rattaché à (Services / Composition / Dates limites / Audiences / Aucun) |
| 14 | **Remarques** | une remarque libre | texte (variantes d'orthographe, points à confirmer) |

### Valeurs admises

- **Rôle** (Composition) : `president`, `juge`, `juge_delegue`, `assesseur`, `assesseur_spjej`, `assesseur_employeur`, `assesseur_travailleur`, `representant_spjej`, `greffier`.
- **Catégorie** (Services) : `coordination`, `comite`, `delegation`, `service`, `fonction`, `bureau`.
- **Fréquence** : `hebdomadaire`, `trimestrielle`, `a_la_demande`, `ponctuelle` (une seule date).
- **Jour** : lundi, mardi, mercredi, jeudi, vendredi, samedi, dimanche (ou 1 à 7).
- **Dates** : `jj/mm/aaaa`. **Heure** : `hh:mm`. **Oui / non** : `oui`, `non`.
- Un champ vide veut dire « non renseigné », jamais « effacer ».

## Les articles de l'ordonnance n°600/2026 et leur place dans le fichier

| Article | Contenu | Feuille(s) |
|---|---|---|
| 1 | six sections | Sections, Articles |
| 2 | organisation des chambres : composition, attributions, audiences, services intérieurs | Chambres, Formations, Composition, Audiences, Articles |
| 3 | service social (SPJEJ), juges taxateurs | Services, Responsables, Articles |
| 4 | testaments : juges délégués et greffier chef adjoint ; audiences « selon les nécessités » | Services, Responsables, Articles |
| 5 | certificats de nationalité : tous les magistrats du siège | Services (sans responsable), Articles |
| 6 | procurations et actes d'individualité : magistrats délégués | Services, Responsables, Articles |
| 7 | fin de l'enregistrement des dossiers civils (jour limite et 16 h ; référés et voies d'exécution exclus) | Dates limites, Articles |
| 8 à 10 | juges hors de leur chambre ; le Président préside toute chambre ; représentation du tribunal | Articles (texte) |
| 11 | prise d'effet à la signature ; modification possible | Ordonnance (date d'effet), Articles |

## Écrans prévus

1. **Charger** : dépôt du fichier, rappel du mode, comptage par bloc.
2. **Rapprocher** (seulement s'il y a des noms à confirmer) : pour chaque nom incertain, les agents proches sont proposés ; on choisit ou on crée.
3. **Contrôler** : erreurs bloquantes en rouge, avertissements en orange, avec le numéro de ligne.
4. **Comparer** : arbre par section > chambre > formation, avec les ajouts (vert), modifications (orange) et retraits (rouge) ; chaque changement se décoche pour l'écarter.
5. **Appliquer** : en une opération ; l'historique des imports permet de **revenir en arrière** sur le dernier import.

## Modes

- **Mise à jour** (par défaut) : ajoute et modifie ce que contient le fichier, ne touche à rien d'autre.
- **Ordonnance complète** (case à cocher) : ce qui n'est plus dans le fichier est proposé comme retiré (date de fin = date d'effet).

## État d'avancement

- Fait : numéros (identifiants entiers de la base), tables des articles et des membres extérieurs, génération du fichier (bouton « Fichier Excel de l'ordonnance » dans Organisation > Ordonnances), exemple dans `docs/MODELE_ordonnance_2026-2027_noms_fictifs.xlsx` (noms fictifs de la base de démonstration).
- Fait aussi : chargement du fichier (Organisation > Ordonnances > Charger un fichier Excel, droit « ordonnances.importer » : Présidente et administrateur), lecture et contrôle ligne par ligne, rapprochement des noms douteux avec mémoire (alias).
- Fait aussi : comparaison (ajouts, modifications, retraits décochables), application en une opération, annulation du dernier import, page « Articles », aide intégrée, tests automatiques.
- À faire : charger un fichier venu d'une autre base (rapprochement par noms).

## Fichier venu d'une autre base

Les N° sont ceux de la base qui a produit le fichier. Si on charge un fichier produit ailleurs (démonstration, autre tribunal), l'application le détecte, ignore les N° et rapproche tout par les noms.
