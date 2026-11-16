-- 037: egzamino protokolas - keičiami įvertinimo stulpeliai, „Klubas“; Federacijos registras (unikalūs numeriai 26.11.15-1);
-- paskyros tema (išsaugoma paskyrai). Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE exam_protocols ADD COLUMN IF NOT EXISTS cols TEXT NULL;            -- įvertinimo stulpeliai [[raktas, pavadinimas]]
ALTER TABLE exam_protocols ADD COLUMN IF NOT EXISTS fed_sent_at DATETIME NULL;  -- kada išsiųsta Federacijai
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS club VARCHAR(120) NULL;
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS extra TEXT NULL;        -- papildomų stulpelių reikšmės (JSON)

-- Federacijos registras: kiekvienas laikęs egzaminą žmogus - vienas nuolatinis numeris (pvz. 26.11.15-1), niekada nesikartoja
CREATE TABLE IF NOT EXISTS federation_people (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fed_no      VARCHAR(20) NOT NULL,
  name        VARCHAR(160) NOT NULL,
  birth_date  DATE NULL,
  club        VARCHAR(120) NULL,
  member_id   INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fed_no (fed_no),
  KEY idx_fp_member (member_id),
  KEY idx_fp_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS federation_exams (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id   INT UNSIGNED NOT NULL,
  event_id    INT UNSIGNED NOT NULL,
  row_id      INT UNSIGNED NOT NULL,
  exam_date   DATE NULL,
  kyu         VARCHAR(60) NULL,
  result      VARCHAR(10) NULL,
  scores      TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fe_row (row_id),
  KEY idx_fe_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paskyros tema (kiekvienas renkasi savo; neprisijungusiems - svetainės tema)
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS theme VARCHAR(20) NULL;

-- Protokolo antraštė: Lietuvos tradicinio karate do federacija
UPDATE exam_protocols SET org_name = 'LIETUVOS TRADICINIO KARATE DO FEDERACIJA' WHERE org_name LIKE '%TRADICINIO KARAT%';
UPDATE site_settings SET value = REPLACE(value, 'LIETUVOS TRADICINIO KARATE ASOCIACIJA', 'LIETUVOS TRADICINIO KARATE DO FEDERACIJA') WHERE name = 'exam_protocol_defaults';

INSERT IGNORE INTO db_versija (failas) VALUES ('037_federacija_temos.sql');
