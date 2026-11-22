-- v1.86 (Denis): LTKF teisėjų registras - lankomumas (seminarai, teisėjavimas varžybose, egzaminai) ir diplomai (failai).
-- Diplomų failai - uploads/teisejai (naršyklei užrakinta), atsisiunčia tik personalas per Valdymas → Federacijos registrai → Teisėjai.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ltkf_judge_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  judge_id   INT UNSIGNED NOT NULL,
  on_date    DATE         NOT NULL,
  kind       VARCHAR(20)  NOT NULL DEFAULT 'seminar',
  title      VARCHAR(190) NOT NULL,
  note       VARCHAR(255) NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ltkf_jev (judge_id, on_date),
  CONSTRAINT fk_ltkf_jev_judge FOREIGN KEY (judge_id) REFERENCES ltkf_judges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ltkf_judge_files (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  judge_id   INT UNSIGNED NOT NULL,
  title      VARCHAR(190) NOT NULL,
  file       VARCHAR(120) NOT NULL,
  orig       VARCHAR(190) NULL,
  size       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ltkf_jf (judge_id),
  CONSTRAINT fk_ltkf_jf_judge FOREIGN KEY (judge_id) REFERENCES ltkf_judges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('052_teiseju_registras.sql');
