-- ============================================================================
-- RETIRER LES COMPTES DE TEST (adresses @tpi.test) — TPI de Bingerville
-- Défait database/sql/comptes_test.sql : délie les agents, supprime les comptes de test,
-- leurs codes de secours et leurs notifications, puis les profils « (test) » devenus vides.
-- Le profil « President » et les vrais comptes ne sont jamais touchés.
-- ============================================================================

START TRANSACTION;

UPDATE `agents` SET `user_id` = NULL WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%@tpi.test');

DELETE FROM `codes_secours` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%@tpi.test');
DELETE FROM `notifications` WHERE `notifiable_type` = 'App\\Models\\User' AND `notifiable_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%@tpi.test');
DELETE FROM `sessions` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%@tpi.test');

DELETE FROM `users` WHERE `email` LIKE '%@tpi.test';

DELETE FROM `profils` WHERE `nom` LIKE '%(test)' AND `systeme` = 0 AND `id` NOT IN (SELECT DISTINCT `profil_id` FROM `users` WHERE `profil_id` IS NOT NULL);

COMMIT;
