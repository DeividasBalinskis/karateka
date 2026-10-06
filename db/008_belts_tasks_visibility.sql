-- Diržai, pamokos pagal diržą, užduotys pastabose, renginiai / naujienos tik nariams
SET NAMES utf8mb4;

-- Diržas: 1 = 9 kyu (baltas) ... 9 = 1 kyu (rudas), 10 = 1 dan ... 18 = 9 dan. NULL - nenurodytas.
ALTER TABLE members ADD COLUMN belt_level TINYINT UNSIGNED NULL AFTER group_id;

-- Pamokos diržui (NULL - visiems)
ALTER TABLE lessons ADD COLUMN belt_level TINYINT UNSIGNED NULL AFTER topic;

-- Pastaba gali būti užduotis (vaikas pažymi „Atlikta“) ir turėti prisegtą pamoką
ALTER TABLE coach_notes
  ADD COLUMN lesson_id INT UNSIGNED NULL AFTER youtube_id,
  ADD COLUMN is_task TINYINT(1) NOT NULL DEFAULT 0 AFTER lesson_id,
  ADD COLUMN done_at DATETIME NULL AFTER read_at,
  ADD CONSTRAINT fk_cn_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL;

-- Matomumas: 1 - tik prisijungusiems nariams
ALTER TABLE events ADD COLUMN members_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_abroad;
ALTER TABLE news   ADD COLUMN members_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published;
