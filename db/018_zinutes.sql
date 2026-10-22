-- 018: nario ir trenerio žinutės: atsakymai į trenerio pastabas, klausimai prie pamokų, bendros žinutės.
-- Nario žinutes mato tik treneriai, trenerio atsakymus - tik tas narys (ir jo tėvai).
SET NAMES utf8mb4;

CREATE TABLE member_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id   INT UNSIGNED NOT NULL,              -- kurio nario pokalbis
  note_id     INT UNSIGNED NULL,                  -- atsakymas į trenerio pastabą
  lesson_id   INT UNSIGNED NULL,                  -- klausimas ar komentaras prie pamokos
  author_id   INT UNSIGNED NULL,                  -- kas parašė (paskyra)
  from_staff  TINYINT(1) NOT NULL DEFAULT 0,      -- 1 - treneris, 0 - narys ar tėvai
  body        TEXT NOT NULL,
  read_at     DATETIME NULL,                      -- kada perskaitė kita pusė
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mm_member (member_id, created_at),
  KEY idx_mm_unread (from_staff, read_at),
  CONSTRAINT fk_mm_member FOREIGN KEY (member_id) REFERENCES members(id)      ON DELETE CASCADE,
  CONSTRAINT fk_mm_note   FOREIGN KEY (note_id)   REFERENCES coach_notes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_mm_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id)      ON DELETE SET NULL,
  CONSTRAINT fk_mm_author FOREIGN KEY (author_id) REFERENCES accounts(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
