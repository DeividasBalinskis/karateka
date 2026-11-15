-- 030: varžybų istorija - kopija serveryje kas minutę (kai kas nors keičiama), kad būtų galima atkurti, kaip buvo prieš 5 min. ar valandą.
-- Vienoms varžyboms - iki 400 paskutinių kopijų. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_versions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tournament_id  INT UNSIGNED NOT NULL,
  version        INT UNSIGNED NOT NULL,
  state          MEDIUMTEXT NOT NULL,
  results        MEDIUMTEXT NULL,
  note           VARCHAR(120) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tver (tournament_id, id),
  CONSTRAINT fk_tver_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('030_varzybu_istorija.sql');
