-- 038: LTKF svetainė redaguojama (puslapių tekstai, skiltys, blokai, failų dėžė); registracija į egzaminą be paskyros
-- (nuoroda, vardas, pavardė, el. paštas); grupių kaina už apsilankymą su mėnesio riba. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

-- LTKF puslapiai: kiekvienam puslapiui - pakeisti tekstai, paslėptos skiltys, pridėti blokai (JSON); naujiems puslapiams - ir pavadinimas
CREATE TABLE IF NOT EXISTS ltkf_pages (
  slug        VARCHAR(80) NOT NULL PRIMARY KEY,
  data        MEDIUMTEXT NULL,
  prev        MEDIUMTEXT NULL,                    -- ankstesnė versija („Atšaukti“)
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by  VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failų dėžė: redaktoriai įmeta failus (ir nuotraukas), adminas vėliau peržiūri ir įdeda, kur reikia
CREATE TABLE IF NOT EXISTS ltkf_files (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file        VARCHAR(255) NOT NULL,
  orig        VARCHAR(255) NOT NULL,
  size        INT UNSIGNED NOT NULL DEFAULT 0,
  note        TEXT NULL,
  uploaded_by VARCHAR(120) NULL,
  done_at     DATETIME NULL,                      -- pažymėta „Įdėta“
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ltkf_files_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registracija į egzaminą per nuorodą (žmonėms, kurių nėra sistemoje): nuoroda renginiui ir užsiregistravusieji
CREATE TABLE IF NOT EXISTS exam_signup_links (
  event_id    INT UNSIGNED NOT NULL PRIMARY KEY,
  token       VARCHAR(32) NOT NULL,
  is_open     TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_esl_token (token),
  CONSTRAINT fk_esl_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_signups (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    INT UNSIGNED NOT NULL,
  first_name  VARCHAR(80) NOT NULL,
  last_name   VARCHAR(80) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  birth_date  DATE NULL,
  club        VARCHAR(120) NULL,
  kyu         VARCHAR(30) NULL,
  phone       VARCHAR(40) NULL,
  row_id      INT UNSIGNED NULL,                  -- eilutė egzamino protokole
  invited_at  DATETIME NULL,                      -- kvietimas išsiųstas el. paštu (dar neužsiregistravęs)
  signed_at   DATETIME NULL,                      -- užsiregistravo
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_es_event (event_id),
  UNIQUE KEY uq_es_event_email (event_id, email),
  CONSTRAINT fk_es_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupės kaina už apsilankymą (pvz. darželiui 6 € už treniruotę) ir mėnesio riba (pvz. 35 € - daugiau nebedidėja)
ALTER TABLE training_groups ADD COLUMN IF NOT EXISTS price_visit DECIMAL(6,2) NULL;
ALTER TABLE training_groups ADD COLUMN IF NOT EXISTS price_cap DECIMAL(7,2) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('038_ltkf_turinys_egzamino_registracija.sql');
