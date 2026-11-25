-- v2.02: Peržiūra (Valdymas → 🔎 Peržiūra, tik administratoriai). Darbų eilė taisymo agentui ir pakeitimų įvertinimai test svetainėje.
-- review_jobs - ką paprašyta padaryti: taisyti pažymėtus atsiliepimus, įkelti paruoštus pataisymus į test, peržiūros rezultatas.
-- review_marks - auksu pažymėtų pakeitimų įvertinimai (✓ gerai / ✗ blogai + komentaras), kiekvienas administratorius atskirai.
-- Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS review_jobs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(12)  NOT NULL,                 -- fix (taisyti atsiliepimus) / deploy (įkelti į test) / review (peržiūros rezultatas)
  status      VARCHAR(12)  NOT NULL DEFAULT 'queued', -- queued (laukia) / working (daroma) / done (padaryta) / failed (nepavyko) / cancelled (atšaukta)
  items       MEDIUMTEXT   NULL,                     -- JSON: atsiliepimai su komentarais arba įvertinimai
  note        TEXT         NULL,                     -- bendras komentaras
  rev         VARCHAR(24)  NULL,                     -- kurio pakeitimų sąrašo (app/review.json) peržiūra
  result      TEXT         NULL,                     -- ką atsakė agentas
  account_id  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at  DATETIME     NULL,
  finished_at DATETIME     NULL,
  KEY idx_review_jobs_status (status, id),
  CONSTRAINT fk_review_jobs_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_marks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rev         VARCHAR(24)  NOT NULL,
  change_id   VARCHAR(64)  NOT NULL,
  account_id  INT UNSIGNED NOT NULL,
  mark        VARCHAR(4)   NULL,                     -- good / bad / NULL (dar neįvertinta, tik komentaras)
  comment     TEXT         NULL,
  job_id      INT UNSIGNED NULL,                     -- pateikta su šiuo review darbu (NULL - dar nepateikta)
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_marks (rev, change_id, account_id),
  CONSTRAINT fk_review_marks_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('060_perziura.sql');
