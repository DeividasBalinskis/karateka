-- C1: paskyrų pakeitimų žurnalas ir „kiti įrenginiai atjungiami“.
-- account_log - kas, kada ir ką pakeitė paskyroje: rolė, būsena, testuotojo žymė, kas mato žaidimą, pradžios redagavimas, slaptažodis
--   (slaptažodžio reikšmė neįrašoma - tik „pakeistas“ / „atkurtas“). Matoma: Valdymas → Nustatymai → Paskyros ir rolės. Saugoma 1 metus.
-- accounts.session_ver - padidėja pakeitus ar atkūrus slaptažodį (PIN): kitų įrenginių prisijungimai nebegalioja.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS account_log (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id      INT UNSIGNED NOT NULL,               -- kurios paskyros (0 - visų, pvz. žaidimas atidarytas visiems)
  field           VARCHAR(30)  NOT NULL,               -- created / role / status / is_tester / game_access / home_editor / password
  old_value       VARCHAR(255) NULL,
  new_value       VARCHAR(255) NULL,
  changed_by      INT UNSIGNED NULL,                   -- kas pakeitė (NULL - pats žmogus per nuorodą ar sistema)
  changed_by_name VARCHAR(120) NULL,
  changed_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_account_log_account (account_id, id),
  KEY idx_account_log_time (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS session_ver INT UNSIGNED NOT NULL DEFAULT 0;

INSERT IGNORE INTO db_versija (failas) VALUES ('061_paskyru_zurnalas.sql');
