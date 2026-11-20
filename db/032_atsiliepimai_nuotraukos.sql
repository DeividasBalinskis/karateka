-- 032: atsiliepimai su nuotraukomis (įklijuojamos Ctrl+V), kurioje svetainėje parašyta (karateka, taika, ltkf)
-- ir kontekstas (puslapio pavadinimas, ekrano dydis) - kad būtų aišku, ką taisyti. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE feedback ADD COLUMN IF NOT EXISTS site VARCHAR(20) NULL AFTER page;
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS context TEXT NULL AFTER site;

CREATE TABLE IF NOT EXISTS feedback_images (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id  INT UNSIGNED NOT NULL,
  file         VARCHAR(64) NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fbimg (feedback_id),
  CONSTRAINT fk_fbimg_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('032_atsiliepimai_nuotraukos.sql');
