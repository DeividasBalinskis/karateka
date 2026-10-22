-- 016: pamokų aplankai (aplankai aplankuose), pamokos turinys blokais (tekstas, video, nuotrauka, pastaba...), tvarka
SET NAMES utf8mb4;

CREATE TABLE lesson_folders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     INT UNSIGNED NULL,                  -- NULL - pagrindinis lygis
  title         VARCHAR(120) NOT NULL,
  icon          VARCHAR(16) NULL,                   -- emoji, pvz. 🥋
  description   TEXT NULL,
  audience      ENUM('karateka','teisejas','egzaminatorius') NOT NULL DEFAULT 'karateka',
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lf_parent (parent_id, sort_order),
  CONSTRAINT fk_lf_parent FOREIGN KEY (parent_id) REFERENCES lesson_folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lessons
  ADD COLUMN folder_id  INT UNSIGNED NULL AFTER id,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER folder_id,
  ADD CONSTRAINT fk_lessons_folder FOREIGN KEY (folder_id) REFERENCES lesson_folders(id) ON DELETE SET NULL;

-- Pamokos turinys: blokai vienas po kito. Senos pamokos be blokų rodomos kaip anksčiau (tekstas + video).
CREATE TABLE lesson_blocks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id   INT UNSIGNED NOT NULL,
  type        ENUM('heading','text','video','image','note','link') NOT NULL,
  body        TEXT NULL,             -- tekstas, pastaba, antraštė arba nuorodos tekstas
  youtube_id  VARCHAR(20) NULL,
  image       VARCHAR(100) NULL,     -- failas uploads/lessons/
  url         VARCHAR(500) NULL,
  caption     VARCHAR(255) NULL,     -- prierašas po video ar nuotrauka
  sort_order  INT NOT NULL DEFAULT 0,
  KEY idx_lb_lesson (lesson_id, sort_order),
  CONSTRAINT fk_lb_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Esamos pamokų temos tampa aplankais (pamokos nesikeičia, tik atsiduria savo temos aplanke)
INSERT INTO lesson_folders (title) SELECT DISTINCT topic FROM lessons WHERE topic IS NOT NULL AND topic <> '';
UPDATE lesson_folders SET sort_order = id;
UPDATE lessons l JOIN lesson_folders f ON f.title = l.topic AND f.parent_id IS NULL SET l.folder_id = f.id;
UPDATE lessons SET sort_order = id;
