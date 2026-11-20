-- karateka.lt narių sistema: schema (be jokių narių duomenų)
-- Paleidžiama automatiškai lokaliai; serveryje importuojama per phpMyAdmin.

SET NAMES utf8mb4;

-- Kas prisijungia
CREATE TABLE accounts (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(190) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  first_name        VARCHAR(80)  NOT NULL,
  last_name         VARCHAR(80)  NOT NULL,
  phone             VARCHAR(40)  NULL,
  role              ENUM('member','coach','admin') NOT NULL DEFAULT 'member',
  status            ENUM('pending_email','pending_parent','pending_approval','active','disabled') NOT NULL DEFAULT 'pending_email',
  email_verified_at DATETIME NULL,
  approved_at       DATETIME NULL,
  last_login_at     DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_accounts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupės
CREATE TABLE training_groups (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  category    ENUM('vaikai','jaunimas','suauge') NOT NULL,
  location    VARCHAR(190) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kas treniruojasi
CREATE TABLE members (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name         VARCHAR(80) NOT NULL,
  last_name          VARCHAR(80) NOT NULL,
  birth_date         DATE NOT NULL,
  group_id           INT UNSIGNED NULL,
  photo_consent      TINYINT(1) NOT NULL DEFAULT 0,
  parent_consent_at  DATETIME NULL,
  status             ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_members_group FOREIGN KEY (group_id) REFERENCES training_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paskyra <-> narys. 'self' = nario paties prisijungimas, 'parent' = tėvų paskyra.
-- Narys turi daugiausia vieną 'self' paskyrą, paskyra - daugiausia vieną 'self' narį.
CREATE TABLE account_members (
  account_id   INT UNSIGNED NOT NULL,
  member_id    INT UNSIGNED NOT NULL,
  relation     ENUM('self','parent') NOT NULL,
  self_member  INT UNSIGNED AS (IF(relation = 'self', member_id, NULL)) STORED,
  self_account INT UNSIGNED AS (IF(relation = 'self', account_id, NULL)) STORED,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (account_id, member_id),
  UNIQUE KEY uq_self_member (self_member),
  UNIQUE KEY uq_self_account (self_account),
  KEY idx_am_member (member_id),
  CONSTRAINT fk_am_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_am_member  FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Savaitinis tvarkaraštis (weekday: 1 = pirmadienis ... 7 = sekmadienis)
CREATE TABLE schedule (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id    INT UNSIGNED NOT NULL,
  weekday     TINYINT UNSIGNED NOT NULL,
  start_time  TIME NULL,
  end_time    TIME NULL,
  note        VARCHAR(190) NULL,
  KEY idx_schedule_group (group_id),
  CONSTRAINT fk_schedule_group FOREIGN KEY (group_id) REFERENCES training_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Egzaminai, varžybos, seminarai...
CREATE TABLE events (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         ENUM('exam','competition','seminar','camp','other') NOT NULL,
  title        VARCHAR(190) NOT NULL,
  starts_on    DATE NOT NULL,
  ends_on      DATE NULL,
  start_time   TIME NULL,
  location     VARCHAR(190) NULL,
  description  TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_date (starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kurioms grupėms renginys skirtas. Jei eilučių nėra - visiems.
CREATE TABLE event_groups (
  event_id  INT UNSIGNED NOT NULL,
  group_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, group_id),
  CONSTRAINT fk_eg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_eg_group FOREIGN KEY (group_id) REFERENCES training_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vienkartinės nuorodos (saugomas tik SHA-256 hash)
CREATE TABLE email_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purpose     ENUM('verify_email','parent_consent','password_reset','invite') NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  account_id  INT UNSIGNED NULL,
  member_id   INT UNSIGNED NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  CONSTRAINT fk_tok_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tok_member  FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(190) NOT NULL,
  body          TEXT NOT NULL,
  author_id     INT UNSIGNED NULL,
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  published_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  KEY idx_news_pub (is_published, published_at),
  CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nuotraukos (file = failo vardas uploads/news/ aplanke) ir YouTube video (youtube_id)
CREATE TABLE news_media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  news_id     INT UNSIGNED NOT NULL,
  type        ENUM('image','youtube') NOT NULL,
  file        VARCHAR(80) NULL,
  youtube_id  VARCHAR(20) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  KEY idx_media_news (news_id),
  CONSTRAINT fk_media_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Viena reakcija vienam žmogui prie vieno įrašo
CREATE TABLE reactions (
  news_id     INT UNSIGNED NOT NULL,
  account_id  INT UNSIGNED NOT NULL,
  emoji       VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,   -- _bin: kitaip MySQL visus emoji laiko vienodais
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (news_id, account_id),
  CONSTRAINT fk_react_news    FOREIGN KEY (news_id)    REFERENCES news(id)     ON DELETE CASCADE,
  CONSTRAINT fk_react_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL,
  ip            VARCHAR(45) NOT NULL,
  success       TINYINT(1) NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_email (email, attempted_at),
  KEY idx_la_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
