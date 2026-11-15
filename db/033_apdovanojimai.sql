-- 033: varžybų apdovanojimai. Sekretoriatas baigtą grupę „Siunčia apdovanojimams“, apdovanojimų planšetėje
-- (nuoroda su raktu awards_token) amžiaus grupė užsidega žaliai, kai visos jos rungtys išsiųstos, o apdovanojus - „Užbaigti“.
-- Būsenos: sent - laukia apdovanojimo, done - apdovanota. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_awards (
  tournament_id  INT UNSIGNED NOT NULL,
  cat_id         VARCHAR(40) NOT NULL,
  status         ENUM('sent','done') NOT NULL DEFAULT 'sent',
  sent_at        DATETIME NULL,
  done_at        DATETIME NULL,
  PRIMARY KEY (tournament_id, cat_id),
  CONSTRAINT fk_tawards_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS awards_token VARCHAR(32) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('033_apdovanojimai.sql');
