---
titre: Décisions
icone: fa-solid fa-pen-nib
couleur: violet
resume: Rédiger avec un modèle, valider, numéroter, signer, délivrer, puis suivre les recours et certificats.
droit: modeles.voir, blocs.voir, redaction.voir, registre_decisions.voir, repertoire_penal.voir, recours.voir
routes: decisions.*
ordre: 4
---
Une décision suit un **circuit** : **brouillon → rédigée → validée → signée → délivrée**. À chaque étape, la bonne personne agit et l'application **vérifie** que l'étape précédente est faite.

## Modèles et blocs de texte

*Décisions > Modèles de décision et Blocs de texte.*

- Un **modèle** a deux parties : la **qualité** (parties, composition, comparution) et le **factum** (visas, faits, motifs, dispositif). Il contient des **variables** `{{ ... }}` remplies automatiquement ou saisies.
- Un **bloc de texte** est un passage réutilisé (visas, formule de dépens, clôture). Il s'insère dans un modèle avec `{{> code }}`.

> **À savoir** Un bloc **utilisé par un modèle ne se supprime pas** et son **code ne change pas**. Un code s'écrit en minuscules, sans espace (`visa_requete`).

## Rédiger une décision

*Décisions > Rédaction > Rédiger une décision* (ou le bouton « rédiger » de la feuille d'audience, qui présélectionne l'affaire).

1. Choisissez l'**affaire** (jugée à l'audience) et le **modèle**.
2. Le formulaire est **déduit du modèle**, en blocs repliables, avec un **aperçu A4 en direct**. Ce que l'application sait déjà (juridiction, chambre, parties, composition, date d'audience) est **déjà rempli**.
3. Complétez les champs restants : demandeur, défunt, actes, dates, témoins…
4. **Enregistrez** : le brouillon est conservé, vous pouvez le reprendre plus tard.
5. Au besoin, **retouchez une phrase** directement dans l'aperçu : la retouche est mémorisée.
6. Quand tout est complet, **marquez la décision « rédigée »**.

> **Attention** Si des champs obligatoires manquent, le message liste **exactement ce qu'il manque**. Vous ne pouvez rédiger que pour les affaires **de votre chambre**.

## Signer et délivrer

| Étape | Qui | Comment |
|---|---|---|
| **Numéro** | le greffier de la chambre, **ou** automatique | selon le réglage du tribunal : à la rédaction, à la **validation** (par défaut) ou à la signature ; format **D-N/année**, sans trou |
| **Valider** | le magistrat | le **texte est figé** ; la décision doit être complète (et numérotée si le réglage l'exige) |
| **Signer** | le magistrat | indiquez la **date de signature** (jamais dans le futur) ; seule une décision **validée** se signe |
| **Délivrer** | le greffier | choisissez **expédition**, **grosse** ou **copie simple**, **à qui** et la **date de remise** |

> **Attention** Une **grosse** ne se délivre **qu'une fois à la même personne** (copie et expédition restent possibles). Une décision **non signée** ne se délivre pas. Après validation, le texte **ne se modifie plus** : seule une **correction** tracée (avec motif) crée une nouvelle version.

> **Astuce** Pour **imprimer**, utilisez l'icône imprimante : le corps est en **Centaur 16** et le timbre en 12 (réglables par l'administration). Choisissez « Enregistrer au format PDF » pour garder une copie.

## Registre des décisions

*Décisions > Registre des décisions.* Toutes les décisions **dont le texte est figé**. Filtrez par **année**, **chambre** et **statut** ; cherchez par **numéro** ou référence. La colonne **Remises** compte les délivrances. Les boutons **Excel** et **PDF** exportent la liste **avec vos filtres** (droit d'exportation).

## Répertoire pénal

*Décisions > Répertoire pénal.* L'enregistrement des décisions pénales : affaire, prévenu, date, contradiction, **issue** (condamné, relaxé, acquitté…) et **peine**.

- La peine s'affiche en clair : « 2 ans dont 6 mois avec sursis ; Amende : 100 000 F CFA ».
- **Relaxer un détenu** le fait **sortir de la liste des détenus**.
- La **minute** doit être transmise : enregistrez la **date de transmission** et le destinataire.

> **Attention** Sont **refusés** : un condamné **sans peine** ; un relaxé **avec amende** ; un **sursis supérieur** à l'emprisonnement ou **sans emprisonnement** ; une date **avant l'enregistrement de l'affaire** ou dans le futur. Au-delà de **30 jours** sans transmission de minute, la ligne passe en rouge et une alerte part.

## Recours et certificats

*Décisions > Recours et certificats.* Pour chaque décision **signée**, l'application suit le **délai de recours**.

1. **Enregistrez la signification** : date, nature (**appel** ou **opposition**) et personne signifiée. Le **délai court à partir de cette date**.
2. L'application calcule l'**échéance**, reportée au **premier jour ouvré** si elle tombe un week-end ou un jour férié.
3. Si un recours est **formé**, enregistrez-le puis **transmettez-le** à la Cour. Vous pouvez aussi le **marquer abandonné**.
4. Une fois le délai **expiré** sans recours, le coordonnateur délivre le **certificat de non-appel (ou de non-opposition)** : numéro de l'année, sans trou, et **PDF à imprimer**.

| État affiché | Signification |
|---|---|
| Signification à enregistrer | décision signée, délai pas encore lancé |
| Délai de recours en cours | l'échéance est affichée |
| Recours formé | **aucun certificat** n'est possible |
| Délai expiré | le **certificat** peut être délivré |
| Certificat délivré | terminé |
| Aucun délai défini | à compléter dans « Délais de recours » |

> **À savoir** Un certificat n'est possible que si le **délai applicable est confirmé** par la personne habilitée (bouton « Délais de recours »). C'est voulu : tant que le délai n'est pas validé, l'application refuse.
