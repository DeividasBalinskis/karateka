-- v1.77 (#160): LTKF registrai - klubai, teisėjai; nariams (federation_people) - dabartinis laipsnis.
-- Klubų sąrašas pirmą kartą užpildomas iš ltkf/turinys/klubai.php (kode, ne čia - be asmens duomenų faile).
-- Tvarko: Valdymas → Federacija (Nariai / Klubai / Teisėjai). LTKF svetainė rodo klubus ir teisėjus iš čia.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ltkf_clubs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(60)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  legal_name  VARCHAR(160) NULL,
  city        VARCHAR(80)  NULL,
  coach       VARCHAR(120) NULL,
  email       VARCHAR(120) NULL,
  phone       VARCHAR(40)  NULL,
  website     VARCHAR(200) NULL,
  since       SMALLINT     NULL,
  lat         DECIMAL(9,6) NULL,
  lng         DECIMAL(9,6) NULL,
  status      VARCHAR(12)  NOT NULL DEFAULT 'member',
  sort_order  INT          NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ltkf_club_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ltkf_judges (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  club        VARCHAR(120) NULL,
  category    VARCHAR(60)  NULL,
  license_no  VARCHAR(40)  NULL,
  valid_until DATE         NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ltkf_judge_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE federation_people ADD COLUMN IF NOT EXISTS grade VARCHAR(20) NULL AFTER club;

INSERT IGNORE INTO db_versija (failas) VALUES ('048_ltkf_registrai.sql');
