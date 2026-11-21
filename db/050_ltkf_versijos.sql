-- v1.80 (D6 1 dalis): LTKF puslapių versijos - kiekvienas išsaugojimas lieka istorijoje (paskutinės 30 kiekvienam puslapiui),
-- redaguojant „🕘 Versijos“ → „Atkurti“. Anksčiau buvo tik viena ankstesnė versija (ltkf_pages.prev).
-- ltkf_items - naujienos ir dokumentai kaip įrašai (D6 2 dalis): redaktoriai prideda, keičia ir trina LTKF svetainėje
-- („✏️ Redaguoti“ → Naujienos / Dokumentai). Pirmą kartą užpildoma iš ltkf/turinys/naujienos.php ir dokumentai.php.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ltkf_page_versions (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(80)  NOT NULL,
  data      MEDIUMTEXT   NULL,
  saved_by  VARCHAR(120) NULL,
  saved_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ltkf_ver_slug (slug, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ltkf_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(10)  NOT NULL,
  slug        VARCHAR(80)  NULL,
  title       VARCHAR(200) NOT NULL,
  summary     VARCHAR(400) NULL,
  body        TEXT         NULL,
  item_date   DATE         NULL,
  grp         VARCHAR(80)  NULL,
  file        VARCHAR(255) NULL,
  is_example  TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  INT          NOT NULL DEFAULT 0,
  updated_by  VARCHAR(120) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ltkf_items_kind (kind, item_date),
  UNIQUE KEY uq_ltkf_items_slug (kind, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('050_ltkf_versijos.sql');
