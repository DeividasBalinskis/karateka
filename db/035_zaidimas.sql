-- 035: žaidimas „Mano dojo“ (personažai, daiktai, gyvūnėliai, dovanų dėžės, kovos, žaidimai) ir paskyra be pokalbių lango.
-- Personažas priklauso nariui (vaikui) arba paskyrai (treneriui / adminui be savo nario). Lygis - iš tikro XP (treniruotės, renginiai).
-- Pinigų nėra: daiktai - iš lygių, renginių, dovanų dėžių; žaidimuose - tik reakcijos (be susirašinėjimo). Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS chat_off TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS game_heroes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id     INT UNSIGNED NULL,
  account_id    INT UNSIGNED NULL,
  name          VARCHAR(40) NOT NULL,
  species       VARCHAR(20) NOT NULL DEFAULT 'tiger',
  gender        CHAR(1) NOT NULL DEFAULT 'm',
  look          TEXT NULL,          -- spalvos: kailis, akys, plaukai
  equipped      TEXT NULL,          -- užsidėti daiktai pagal vietą
  home          TEXT NULL,          -- aplinka, namas, dekoracijos su vietomis
  alloc         TEXT NULL,          -- paskirstyti savybių taškai
  pet_id        INT UNSIGNED NULL,
  rating_power  INT NOT NULL DEFAULT 1000,
  rating_fair   INT NOT NULL DEFAULT 1000,
  wins          INT UNSIGNED NOT NULL DEFAULT 0,
  losses        INT UNSIGNED NOT NULL DEFAULT 0,
  invites       TINYINT(1) NOT NULL DEFAULT 1,
  test_level    SMALLINT NULL,      -- tik testavimui (adminas)
  test_belt     TINYINT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  UNIQUE KEY uq_gh_member (member_id),
  UNIQUE KEY uq_gh_account (account_id),
  CONSTRAINT fk_gh_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_gh_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_items (
  hero_id     INT UNSIGNED NOT NULL,
  item_key    VARCHAR(40) NOT NULL,
  qty         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  source      VARCHAR(20) NOT NULL DEFAULT 'level',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hero_id, item_key),
  CONSTRAINT fk_gi_hero FOREIGN KEY (hero_id) REFERENCES game_heroes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_pets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hero_id     INT UNSIGNED NOT NULL,
  species     VARCHAR(20) NOT NULL,
  name        VARCHAR(30) NOT NULL,
  color       VARCHAR(9) NULL,
  xp          INT UNSIGNED NOT NULL DEFAULT 0,
  happy       TINYINT UNSIGNED NOT NULL DEFAULT 80,
  fed_at      DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gp_hero (hero_id),
  CONSTRAINT fk_gp_hero FOREIGN KEY (hero_id) REFERENCES game_heroes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dovanų dėžės: už kiekvieną lygį, renginį, dienos pergales; atidarius - atsitiktinis daiktas
CREATE TABLE IF NOT EXISTS game_boxes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hero_id     INT UNSIGNED NOT NULL,
  reason      VARCHAR(60) NOT NULL,
  title       VARCHAR(160) NOT NULL,
  item_key    VARCHAR(40) NULL,
  opened_at   DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gb_reason (hero_id, reason),
  CONSTRAINT fk_gb_hero FOREIGN KEY (hero_id) REFERENCES game_heroes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kovos ir stalo žaidimai (kvietimas → vyksta → baigta). b_hero NULL - prieš treniruočių partnerį (botą)
CREATE TABLE IF NOT EXISTS game_matches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game        VARCHAR(12) NOT NULL,
  mode        VARCHAR(12) NOT NULL DEFAULT 'power',
  a_hero      INT UNSIGNED NOT NULL,
  b_hero      INT UNSIGNED NULL,
  status      VARCHAR(12) NOT NULL DEFAULT 'invited',
  state       MEDIUMTEXT NULL,
  winner      TINYINT NULL,
  deadline    DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  KEY idx_gm_a (a_hero, status),
  KEY idx_gm_b (b_hero, status),
  CONSTRAINT fk_gm_a FOREIGN KEY (a_hero) REFERENCES game_heroes(id) ON DELETE CASCADE,
  CONSTRAINT fk_gm_b FOREIGN KEY (b_hero) REFERENCES game_heroes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ritmo žaidimo ir kiti rezultatai (lentelė „savaitės geriausi“)
CREATE TABLE IF NOT EXISTS game_scores (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hero_id     INT UNSIGNED NOT NULL,
  game        VARCHAR(20) NOT NULL,
  song        VARCHAR(20) NOT NULL DEFAULT '',
  score       INT UNSIGNED NOT NULL,
  grade       VARCHAR(2) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gs_game (game, created_at),
  CONSTRAINT fk_gs_hero FOREIGN KEY (hero_id) REFERENCES game_heroes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('035_zaidimas.sql');
