-- v1.93 (Deividas): pateisinimų sistema - prašymai pateisinti praleistas pamokas pagal mūsų atsiųstą nuorodą (karateka.lt/p/?kodas).
-- Administratorius peržiūri užpildytą pateisinimą (Word šablonas) ir patvirtina - jis išsiunčiamas nurodytu el. paštu.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS justifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student     VARCHAR(160) NOT NULL,                -- mokinio vardas ir pavardė
  school      VARCHAR(190) NOT NULL,                -- mokyklos pavadinimas
  director    VARCHAR(160) NOT NULL,                -- direktoriaus vardas ir pavardė
  class_name  VARCHAR(30)  NOT NULL,                -- klasė
  date_from   DATE         NOT NULL,                -- laikotarpis nuo
  date_to     DATE         NOT NULL,                -- laikotarpis iki
  email       VARCHAR(190) NOT NULL,                -- kur siųsti pateisinimą
  status      ENUM('new','sent','rejected') NOT NULL DEFAULT 'new',
  sent_at     DATETIME     NULL,
  sent_by     INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_just_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('056_pateisinimai.sql');
