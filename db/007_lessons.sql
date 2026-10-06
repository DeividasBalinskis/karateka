-- 3 etapas: pamokos (tekstas ir / ar YouTube „Unlisted“ video), matomos tik nariams
SET NAMES utf8mb4;

CREATE TABLE lessons (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(190) NOT NULL,
  topic         VARCHAR(60) NULL,            -- tema, pvz. Kata, Kihon, Kumite (laisvai įvedama)
  body          TEXT NULL,
  videos        TEXT NULL,                   -- YouTube video ID, atskirti kableliais
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  author_id     INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  KEY idx_lessons_topic (topic),
  CONSTRAINT fk_lessons_author FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kurioms grupėms pamoka skirta. Jei eilučių nėra - visiems nariams.
CREATE TABLE lesson_groups (
  lesson_id  INT UNSIGNED NOT NULL,
  group_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (lesson_id, group_id),
  CONSTRAINT fk_lg_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_lg_group  FOREIGN KEY (group_id)  REFERENCES training_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
