-- 045: testų peržiūra Deniui (klausimas ar atsakymas pažymimas ✕, komentarai, juodraščiai, paveikslėlio aprašymas)
--      ir aktyvumo taškų kategorija „Kita“ (#141 - taškai su savo komentaru).
-- Juodraštis (is_draft = 1) - įkeltas peržiūrai, nariams testuose dar nerodomas.
ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS is_draft TINYINT(1) NOT NULL DEFAULT 0;
-- Ką turi rodyti paveikslėlis (ChatGPT eilutė „Paveikslėlis: ...“), kol paveikslėlio dar nėra.
ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS image_note VARCHAR(255) NULL;

-- Peržiūros žymos: eilutė su answer_id = 0 - klausimas peržiūrėtas (+ ✕ visam klausimui ir komentaras),
-- su answer_id - ✕ ar komentaras konkrečiam atsakymui.
CREATE TABLE IF NOT EXISTS quiz_review (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id  INT UNSIGNED NOT NULL,
  answer_id    INT UNSIGNED NOT NULL DEFAULT 0, -- 0 - pastaba apie visą klausimą
  flag         TINYINT(1) NOT NULL DEFAULT 0, -- 1 = ✕ nepatinka (pakeisti)
  comment      TEXT NULL,
  account_id   INT UNSIGNED NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qr (question_id, answer_id),
  KEY idx_qr_q (question_id),
  CONSTRAINT fk_qr_q FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plano žingsnis A5: viešų formų riba („2 treniruotės nemokamai“ - ne daugiau 3 per valandą iš vieno IP ir el. pašto).
CREATE TABLE IF NOT EXISTS form_submissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  form        VARCHAR(20) NOT NULL,
  ip          VARCHAR(45) NOT NULL,
  email       VARCHAR(190) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fs_ip (form, ip, created_at),
  KEY idx_fs_email (form, email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO point_categories (code, name, points, is_active, sort_order) VALUES ('other', 'Kita (su komentaru)', 5, 1, 99);

-- #140: Dojo paslepiamas, kol žaidimas nebaigtas (vieną kartą: jei buvo atidarytas visiems). Administratoriai jį atidaro per Testavimas.
SET @first045 = (SELECT COUNT(*) = 0 FROM db_versija WHERE failas = '045_testu_perziura_kita.sql');
UPDATE site_settings SET value = '[]' WHERE name = 'game_access' AND value = 'all' AND @first045 = 1;

INSERT IGNORE INTO db_versija (failas) VALUES ('045_testu_perziura_kita.sql');
