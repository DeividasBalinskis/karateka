-- 014: egzamino programa pagal diržą, testai (kaip Regitroje), pamokos teisėjams ir egzaminatoriams
SET NAMES utf8mb4;

-- Ko reikia norint gauti diržą (belt_level - diržas, kurį gausi išlaikęs)
CREATE TABLE belt_requirements (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  belt_level  TINYINT UNSIGNED NOT NULL,
  section     ENUM('kihon','kata','kumite','teorija','kita') NOT NULL DEFAULT 'kita',
  title       VARCHAR(190) NOT NULL,
  body        TEXT NULL,
  youtube_id  VARCHAR(20) NULL,
  lesson_id   INT UNSIGNED NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_br_belt (belt_level, section, sort_order),
  CONSTRAINT fk_br_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kuriuos programos punktus narys jau moka (žvaigždutės ir pasiruošimo juosta)
CREATE TABLE requirement_progress (
  member_id       INT UNSIGNED NOT NULL,
  requirement_id  INT UNSIGNED NOT NULL,
  done_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id, requirement_id),
  CONSTRAINT fk_rp_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_req    FOREIGN KEY (requirement_id) REFERENCES belt_requirements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testai. audience: kas mato (karateka - visi nariai, kiti - pagal žymą). belt_level: kuriam diržui (NULL - visiems)
CREATE TABLE quizzes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(190) NOT NULL,
  audience        ENUM('karateka','teisejas','egzaminatorius') NOT NULL DEFAULT 'karateka',
  belt_level      TINYINT UNSIGNED NULL,
  description     TEXT NULL,
  question_count  TINYINT UNSIGNED NOT NULL DEFAULT 10,
  pass_percent    TINYINT UNSIGNED NOT NULL DEFAULT 83,
  kids_mode       TINYINT(1) NOT NULL DEFAULT 0,
  is_published    TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  KEY idx_quiz_aud (audience, belt_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_questions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id      INT UNSIGNED NOT NULL,
  body         TEXT NOT NULL,
  image        VARCHAR(80) NULL,
  youtube_id   VARCHAR(20) NULL,
  explanation  TEXT NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  KEY idx_qq_quiz (quiz_id),
  CONSTRAINT fk_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_answers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id  INT UNSIGNED NOT NULL,
  body         VARCHAR(255) NOT NULL,
  is_correct   TINYINT(1) NOT NULL DEFAULT 0,
  sort_order   INT NOT NULL DEFAULT 0,
  KEY idx_qa_q (question_id),
  CONSTRAINT fk_qa_q FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bandymai: tik „tikras“ testas (treniruotės režimas nesaugomas)
CREATE TABLE quiz_attempts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id       INT UNSIGNED NOT NULL,
  member_id     INT UNSIGNED NOT NULL,
  account_id    INT UNSIGNED NULL,
  question_ids  TEXT NOT NULL,
  score         SMALLINT UNSIGNED NULL,
  total         SMALLINT UNSIGNED NOT NULL,
  passed        TINYINT(1) NULL,
  started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at   DATETIME NULL,
  KEY idx_att_member (member_id, quiz_id),
  CONSTRAINT fk_qat_quiz    FOREIGN KEY (quiz_id)    REFERENCES quizzes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_qat_member  FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
  CONSTRAINT fk_qat_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_attempt_answers (
  attempt_id   INT UNSIGNED NOT NULL,
  question_id  INT UNSIGNED NOT NULL,
  answer_id    INT UNSIGNED NULL,
  is_correct   TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (attempt_id, question_id),
  CONSTRAINT fk_qaa_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pamokos teisėjams ir egzaminatoriams (mato tik turintys žymą)
ALTER TABLE lessons ADD COLUMN audience ENUM('karateka','teisejas','egzaminatorius') NOT NULL DEFAULT 'karateka' AFTER belt_level;
