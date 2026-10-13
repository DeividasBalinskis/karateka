-- 3 etapas: trenerio pastabos nariui po treniruotės
SET NAMES utf8mb4;

CREATE TABLE coach_notes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id   INT UNSIGNED NOT NULL,
  author_id   INT UNSIGNED NULL,
  note_date   DATE NOT NULL,                 -- kurios treniruotės pastaba
  body        TEXT NOT NULL,
  youtube_id  VARCHAR(20) NULL,              -- nebūtina video nuoroda (pvz. ką pasikartoti)
  read_at     DATETIME NULL,                 -- kada narys / tėvai pirmą kartą pamatė
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cn_member (member_id, note_date),
  CONSTRAINT fk_cn_member FOREIGN KEY (member_id) REFERENCES members(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cn_author FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
