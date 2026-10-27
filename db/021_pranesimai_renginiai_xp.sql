-- 021: pranešimai paskyroje, renginių registracija (terminas, vėlavimas), klausimai Deniui, anoniminiai komentarai,
-- XP už egzamino programos punktus ir testus, kainos pagal treniruočių vietą.
-- Failą galima importuoti ir antrą kartą - nieko nepridės dvigubai.
SET NAMES utf8mb4;

-- Pranešimai: kam (paskyra), apie ką (narys), nuoroda. important = 0 - tik informacija (nesiskaičiuoja raudoname skaičiuke).
-- read_at - perskaityta, done_at - išspręsta (pvz. treneris patvirtino) - tada dingsta iš „Svarbu“.
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id  INT UNSIGNED NOT NULL,
  member_id   INT UNSIGNED NULL,
  type        VARCHAR(30) NOT NULL,
  title       VARCHAR(190) NOT NULL,
  body        TEXT NULL,
  link        VARCHAR(255) NULL,
  ref_id      INT UNSIGNED NULL,
  important   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at     DATETIME NULL,
  done_at     DATETIME NULL,
  KEY idx_nt_account (account_id, done_at, created_at),
  KEY idx_nt_ref (type, ref_id, member_id),
  CONSTRAINT fk_nt_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_nt_member  FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renginiai: trumpas tekstas sąraše (atidarius - visas aprašymas); „vėlavo“ - užsiregistravo po termino, laukia Denio
ALTER TABLE events ADD COLUMN IF NOT EXISTS summary VARCHAR(300) NULL AFTER description;
ALTER TABLE event_participants MODIFY status ENUM('invited','going','declined','late') NOT NULL;

-- Klausimai Deniui prie renginių ir naujienų. is_public - Denis parodė klausimą ir atsakymą visiems, show_name - su klaususiojo vardu
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS event_id INT UNSIGNED NULL AFTER lesson_id;
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS news_id INT UNSIGNED NULL AFTER event_id;
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER read_at;
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS show_name TINYINT(1) NOT NULL DEFAULT 0 AFTER is_public;

-- Anoniminiai komentarai: kas parašė - nesaugoma
CREATE TABLE IF NOT EXISTS anon_feedback (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  body        TEXT NOT NULL,
  page        VARCHAR(190) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at     DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ar narys jau matė pasiūlymą rašyti treneriui (pokalbio langelis apačioje dešinėje)
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS chat_hint_at DATETIME NULL;

-- Egzamino programa: fizinis pasiruošimas, XP už kiekvieną punktą; „Moku“ tvirtina treneris (status pending -> approved).
-- Jau pažymėti punktai lieka patvirtinti.
ALTER TABLE belt_requirements MODIFY section ENUM('kihon','kata','kumite','teorija','fizinis','kita') NOT NULL DEFAULT 'kita';
ALTER TABLE belt_requirements ADD COLUMN IF NOT EXISTS xp SMALLINT UNSIGNED NOT NULL DEFAULT 20 AFTER lesson_id;
ALTER TABLE requirement_progress ADD COLUMN IF NOT EXISTS status ENUM('pending','approved') NOT NULL DEFAULT 'approved' AFTER requirement_id;
ALTER TABLE requirement_progress ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER done_at;

-- Naujos taškų (XP) kategorijos; vertes keičia adminas: Treneriams -> Taškai
INSERT IGNORE INTO point_categories (code, name, points, sort_order) VALUES
 ('skill',        'Egzamino programos punktas', 0, 6),
 ('test_attempt', 'Testo bandymas (pirmas per sezoną)', 2, 7),
 ('test_pass',    'Išlaikytas testas (pirmą kartą per sezoną)', 5, 8),
 ('event_other',  'Renginys: dalyvavimas', 3, 55);

-- Kiek XP reikia vienam lygiui (keičiama: Treneriams -> Taškai)
INSERT IGNORE INTO site_settings (name, value) VALUES ('xp_per_level', '100');

-- Kaina vietoje, jei skiriasi nuo kategorijos kainos (pvz. „45 €/mėn, 1 kartą per savaitę“)
ALTER TABLE training_groups ADD COLUMN IF NOT EXISTS price_note VARCHAR(120) NULL AFTER label;

-- Pranešimai visiems (istorija): kam ir kaip išsiųsta
CREATE TABLE IF NOT EXISTS broadcasts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(190) NOT NULL,
  body        TEXT NOT NULL,
  channels    VARCHAR(40) NOT NULL,
  recipients  INT UNSIGNED NOT NULL DEFAULT 0,
  sent_by     INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bc_by FOREIGN KEY (sent_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
