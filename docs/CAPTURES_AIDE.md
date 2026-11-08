# Captures d'écran du manuel d'aide : comment les refaire

Les captures du manuel sont **faites par un navigateur automatique** : plus aucune capture à la main.

```bash
php artisan aide:captures                                   # refait toutes les captures (≈ 3 minutes)
php artisan aide:captures --seulement=role-composition      # n'en refait qu'une (répéter l'option pour plusieurs)
```

À lancer **chaque fois qu'un écran change**. Les fichiers PNG sont remplacés dans `public/assets/aide/` ; les **épingles numérotées** et leurs légendes, elles, vivent dans les chapitres (`resources/aide/*.md`) et ne bougent pas.

Prérequis : les données de formation (`php artisan tpi:formation`), Node.js et Chrome ou Edge. La commande crée au besoin un compte administrateur d'essai `aide.captures@tpi.test` (il disparaît avec les comptes de test).

## Ajouter une capture annotée dans un chapitre

```markdown
![Légende de la capture](aide/nom-du-fichier.png "1@30,12=Texte du repère 1 | 2@75,60=Texte du repère 2")
```

- `1@30,12` : épingle n° 1 posée à **30 % de la largeur** et **12 % de la hauteur** de l'image ; le texte après `=` apparaît dans la légende.
- Les épingles apparaissent **une à une** quand la capture entre à l'écran ; survoler une ligne de légende fait **sauter** l'épingle correspondante ; cliquer sur l'image l'**agrandit**.
- Sans guillemets, la capture s'affiche seule, légendée.

Pour **ajouter un nouvel écran** à capturer, ajouter une ligne dans la méthode `liste()` de `app/Console/Commands/AideCaptures.php` (nom du fichier, page, et au besoin un petit script « avant » pour ouvrir un panneau).

> **État au 7 novembre 2026** : toutes les captures ont été supprimées à la demande du tribunal (certaines ne montraient pas la bonne page) et les chapitres sont revenus au texte seul. L'outil reste disponible : à réactiver écran par écran, après vérification des images.
