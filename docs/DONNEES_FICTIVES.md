# Données fictives pour les essais, la formation et les démonstrations

Le cahier des charges impose des **données fictives** pour les maquettes, les tests, les formations et les démonstrations. Tout ce qui suit est inventé : aucun nom réel de partie, aucune vraie affaire. À n'utiliser que sur une **base de test**, jamais en production.

## Mise en place, dans l'ordre

| Étape | Commande ou fichier | Ce que ça crée |
|---|---|---|
| 1 | `php artisan migrate --seed` | Les tables, l'ordonnance (chambres, formations, agents), les référentiels judiciaires et les modèles de décision |
| 2 | `database/sql/comptes_test.sql` (à importer dans MySQL) | 36 comptes de test, un par magistrat ou greffier de l'ordonnance, reliés à leur fiche d'agent, mot de passe `Tpi@2026`, adresses `@tpi.test` |
| 3 | `php artisan affaires:demo` | Des affaires fictives : civil, pénal, instruction (adultes et enfants), une jonction, une annulée, un groupe « et x autres » |
| 4 | `php artisan tpi:codes-test` | 8 codes de secours par compte de test, écrits dans `storage/app/codes_secours_test.txt` (leur boîte e-mail est fictive : le code reçu par e-mail ne leur arrive jamais) |

Pour retirer les comptes de test : importer `database/sql/comptes_test_retirer.sql` (il délie les agents, supprime les comptes `@tpi.test`, leurs codes et notifications, puis les profils « (test) » devenus vides ; les vrais comptes ne sont pas touchés).

Pour retirer les affaires d'essai : `php artisan affaires:demo --retirer` (les vraies affaires ne sont jamais touchées).

## Se connecter avec un compte de test

1. Saisir l'adresse (par exemple `appa.nguessan@tpi.test`) et le mot de passe `Tpi@2026`.
2. Sur la page du code, cliquer sur « Je ne reçois pas le code : utiliser un code de secours », puis saisir un code de `codes_secours_test.txt`. Chaque code ne sert qu'une fois ; relancer `php artisan tpi:codes-test` en génère de nouveaux.

## Qui voit quoi (pour les démonstrations)

- **Présidente, vice-présidents, coordonnateur du greffe** : toutes les chambres.
- **Juges et greffiers** : les chambres où ils sont affectés d'après l'ordonnance.
- **Juge d'instruction** : son cabinet ; **juge des enfants** : ses chambres de mineurs (droits « instruction » et « mineurs »).

## Rappel de sécurité

Le fichier `comptes_test.sql` contient un mot de passe commun connu de tous. Il ne doit **jamais** être importé sur le serveur de production.

## Statistiques (nov. 2026)
`php artisan statistiques:demo` crée environ 330 affaires civiles (2022 à aujourd'hui, avec sorties, accordées ou rejetées, adoptions) et 190 décisions pénales fictives, marquées « DEMO-STAT » dans « ancien numéro ». Retrait : `php artisan statistiques:demo --retirer`. À n'utiliser que sur une base de test.
