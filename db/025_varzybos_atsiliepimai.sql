-- 025: Atsiliepimai apie svetainę (mygtukas „Atsiliepimas“ visuose puslapiuose) ir varžybų sistema svetainėje.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

-- Atsiliepimai: ką pastebėjo nariai, treneriai ar lankytojai. Adminas mato visus: Valdymas → Atsiliepimai.
CREATE TABLE IF NOT EXISTS feedback (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id  INT UNSIGNED NULL,                 -- NULL - neprisijungęs lankytojas
  name        VARCHAR(120) NULL,                 -- lankytojo vardas ar kontaktas (nebūtina)
  page        VARCHAR(255) NULL,                 -- kuriame puslapyje parašė
  body        TEXT NOT NULL,
  status      ENUM('new','planned','done','declined') NOT NULL DEFAULT 'new',
  reply       TEXT NULL,                         -- admino atsakymas (mato autorius)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  KEY idx_feedback_status (status, created_at),
  KEY idx_feedback_account (account_id),
  CONSTRAINT fk_feedback_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varžybos (Valdymas → Varžybos): klubai, dalyviai, kategorijos, lentelės, tvarkaraštis - vienas JSON (state);
-- results - prizininkai ir klubų medaliai (iš jų - narių taškai ir LTKF rezultatai).
CREATE TABLE IF NOT EXISTS tournaments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(190) NOT NULL,
  starts_on   DATE NOT NULL,
  ends_on     DATE NULL,
  location    VARCHAR(190) NULL,
  status      ENUM('draft','registration','running','finished') NOT NULL DEFAULT 'draft',
  is_test     TINYINT(1) NOT NULL DEFAULT 0,      -- test varžybos: ištrinamos vienu mygtuku, oficialioje svetainėje nerodomos
  is_public   TINYINT(1) NOT NULL DEFAULT 1,      -- rodyti rezultatus viešai (LTKF, karateka.lt)
  state       MEDIUMTEXT NULL,
  results     MEDIUMTEXT NULL,
  version     INT UNSIGNED NOT NULL DEFAULT 0,    -- kad du žmonės vienu metu neperrašytų vienas kito
  event_id    INT UNSIGNED NULL,                  -- karateka.lt renginys (Renginiai, narių taškai)
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  KEY idx_tournaments_date (starts_on),
  CONSTRAINT fk_tournaments_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  CONSTRAINT fk_tournaments_author FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('025_varzybos_atsiliepimai.sql');
