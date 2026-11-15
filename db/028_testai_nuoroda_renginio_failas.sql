-- 028: testo nuoroda žmonėms be paskyros (share_token, share_open) ir jų rezultatai (vardas, balas);
-- renginio priedas - vienas failas (pvz. reglamentas), atsisiunčiamas renginio puslapyje.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS share_token VARCHAR(32) NULL;
ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS share_open TINYINT(1) NOT NULL DEFAULT 0;

-- Sprendusieji per nuorodą be paskyros: tik įrašytas vardas ir rezultatas (ištrinama po metų, app/cleanup.php)
CREATE TABLE IF NOT EXISTS quiz_guest_attempts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  club        VARCHAR(120) NULL,
  score       SMALLINT UNSIGNED NOT NULL,
  total       SMALLINT UNSIGNED NOT NULL,
  passed      TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_qga_quiz (quiz_id, created_at),
  CONSTRAINT fk_qga_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renginio priedas: failas uploads/events/ (atsitiktinis vardas) ir pradinis pavadinimas atsisiuntimui
ALTER TABLE events ADD COLUMN IF NOT EXISTS file_path VARCHAR(190) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS file_name VARCHAR(190) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('028_testai_nuoroda_renginio_failas.sql');
