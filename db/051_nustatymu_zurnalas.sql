-- v1.81 (C2): nustatymų pakeitimų žurnalas - kas, kada ir ką pakeitė (Valdymas → Nustatymai → Visi nustatymai).
-- Visai svetainei skirtus nustatymus galima „Atkurti“. Saugoma 1 metus. Slaptų nuorodų reikšmės neįrašomos.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings_log (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(80)  NOT NULL,
  old_value       MEDIUMTEXT   NULL,
  new_value       MEDIUMTEXT   NULL,
  changed_by      INT UNSIGNED NULL,
  changed_by_name VARCHAR(120) NULL,
  changed_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_settings_log_name (name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('051_nustatymu_zurnalas.sql');
