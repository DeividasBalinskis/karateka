-- 063: senas nustatymas „xp_per_level“ (db/021) nebenaudojamas nuo v1.96 - lygio kaina dabar xp_level_base + xp_level_step.
-- Deividas leido ištrinti (2026-11-26). Kitų duomenų nekeičia. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

DELETE FROM site_settings WHERE name = 'xp_per_level';
INSERT IGNORE INTO db_versija (failas) VALUES ('063_seno_nustatymo_trynimas.sql');
