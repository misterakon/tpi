# Rapport de test de sécurité (5 novembre 2026)

Attaque menée sur une copie locale avec des comptes de test, sans envoi vers l'extérieur.

## Failles corrigées
| # | Faille | Gravité | Correction |
|---|---|---|---|
| 1 | Connexion : message et temps de réponse différents selon que l'e-mail existe (0,5 s contre 0,15 s) | Moyenne | Même message, temps égalisé (`LoginController`) |
| 2 | Mot de passe oublié : 4 s si le compte existe, 0,15 s sinon | Moyenne | Courriel envoyé après la réponse (`PasswordResetController`) |
| 3 | Suppression de compte : réponses différentes avant contrôle du droit | Faible | Droit contrôlé en premier (`CompteController`) |
| 4 | `symfony/mime` vulnérable (CVE-2026-45070, injection d'en-têtes d'e-mail) | Moyenne | Mise à jour ; `composer audit` sans alerte |

Aussi : en-tête `X-Powered-By` retiré.

## Résistances constatées
Routes protégées sans connexion ; compte sans droit (150 routes qui écrivent, aucune ouverte, aucune erreur 500) ; XSS stocké sur 6 pages ; assignation de masse ; blocage après 5 essais de connexion ; jeton CSRF ; fixation de session ; envois de fichiers (contenu réel, nom généré, stockage privé) ; exports Excel (pas de formule) ; fichiers sensibles non servis (`.env`, `vendor`, `artisan`, journaux) ; aucune injection SQL possible.

Tests d'attaque conservés : `tests/Feature/AttaqueSecuriteTest.php` (4 tests). Suite complète : 549 tests au vert.

## Risques restants, à traiter avant la mise en service
1. `APP_DEBUG=true` : à passer à `false`.
2. `SESSION_SECURE_COOKIE=false` et HTTPS à imposer.
3. Mot de passe : 8 caractères minimum seulement. Recommandé : 12 et refus des mots de passe courants.
4. Comptes de test (`Tpi@2026`) et `storage/app/codes_secours_test.txt` : à supprimer avant toute mise en ligne.
5. Politique de contenu (CSP) avec `unsafe-inline` et `unsafe-eval` : protection XSS réduite.
6. Séparer les droits `recours.delais` (changer les délais) et `recours.certifier` (délivrer les certificats).
7. Un tiers qui connaît une adresse peut verrouiller le compte 15 minutes (5 essais) : compromis classique, acceptable.
8. Sauvegardes chiffrées, test de restauration, serveur de messagerie : lot « Hébergement ».
