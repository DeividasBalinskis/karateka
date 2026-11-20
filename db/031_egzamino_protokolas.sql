-- 031: egzamino protokolas internete (pagal LTKA protokolo formą): antraštė (organizacija, kyu, data ir vieta, egzaminatorius)
-- ir dalyviai su unikaliu numeriu MM-mm-dd-eilė (pvz. 26-11-11-01). Neatvykęs ištrinamas „minkštai“ - jo numeris daugiau nepriskiriamas.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_protocols (
  event_id          INT UNSIGNED NOT NULL PRIMARY KEY,
  org_name          VARCHAR(190) NOT NULL DEFAULT 'LIETUVOS TRADICINIO KARATE ASOCIACIJA',
  kyu_label         VARCHAR(60) NULL,
  place_text        VARCHAR(190) NULL,
  examiner_name     VARCHAR(120) NULL,
  examiner_license  VARCHAR(60) NULL,
  updated_at        DATETIME NULL,
  CONSTRAINT fk_eprot_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_protocol_rows (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    INT UNSIGNED NOT NULL,
  number      VARCHAR(20) NOT NULL,
  num_date    DATE NOT NULL,
  seq         SMALLINT UNSIGNED NOT NULL,
  member_id   INT UNSIGNED NULL,
  name        VARCHAR(160) NOT NULL,
  birth_date  DATE NULL,
  kyu         VARCHAR(30) NULL,
  kihon       VARCHAR(60) NULL,
  kata        VARCHAR(60) NULL,
  kumite      VARCHAR(60) NULL,
  test        VARCHAR(60) NULL,
  seminars    VARCHAR(255) NULL,
  notes       TEXT NULL,
  result      ENUM('', 'passed', 'failed') NOT NULL DEFAULT '',
  deleted_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_eprot_number (number),
  UNIQUE KEY uq_eprot_seq (num_date, seq),
  KEY idx_eprot_event (event_id),
  CONSTRAINT fk_eprow_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('031_egzamino_protokolas.sql');
