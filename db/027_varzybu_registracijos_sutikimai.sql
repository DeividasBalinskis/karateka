-- 027: kitų klubų registracija į varžybas: vieno iš tėvų telefonas, sutikimas dėl nuotraukų ir filmavimo,
-- kada sutikta su duomenų naudojimu. Telefonai ištrinami praėjus mėnesiui po varžybų (app/cleanup.php).
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE tournament_entries ADD COLUMN IF NOT EXISTS parent_phone VARCHAR(40) NULL AFTER contact;
ALTER TABLE tournament_entries ADD COLUMN IF NOT EXISTS photo_ok TINYINT(1) NULL AFTER belt;
ALTER TABLE tournament_entries ADD COLUMN IF NOT EXISTS consent_at DATETIME NULL AFTER photo_ok;

INSERT IGNORE INTO db_versija (failas) VALUES ('027_varzybu_registracijos_sutikimai.sql');
