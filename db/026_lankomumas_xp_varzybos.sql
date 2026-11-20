-- 026: renginio lankomumas („Buvo“ / „Nebuvo“ Treniruotės puslapyje), XP kategorija „Pagalba treneriui“,
-- varžybų transliacija (ekranai, žiūrovų nuoroda) ir kitų klubų registracija per nuorodą.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

-- Ar narys tikrai buvo renginyje: NULL - nepažymėta, 1 - buvo, 0 - nebuvo
ALTER TABLE event_participants ADD COLUMN IF NOT EXISTS attended TINYINT(1) NULL AFTER status;

-- XP, kurį skiria treneris (Valdymas → XP → Skirti XP, arba „+XP“ treniruotėje)
INSERT IGNORE INTO point_categories (code, name, points, sort_order) VALUES ('help_coach', 'Pagalba treneriui', 5, 65);

-- Varžybos: nuoroda žiūrovams ir ekranams (share_token), registracijos nuoroda kitiems klubams (reg_token, reg_open)
ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS share_token VARCHAR(32) NULL;
ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS reg_token VARCHAR(32) NULL;
ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS reg_open TINYINT(1) NOT NULL DEFAULT 0;

-- Kas vyksta ant kiekvieno tatamio dabar (kova, balai, laikrodis) - ekranams ir žiūrovams
CREATE TABLE IF NOT EXISTS tournament_live (
  tournament_id INT UNSIGNED NOT NULL,
  area_id       SMALLINT UNSIGNED NOT NULL,
  data          TEXT NOT NULL,
  updated_ms    BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tournament_id, area_id),
  CONSTRAINT fk_tlive_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kitų klubų registracija per nuorodą (vardas, gimimo metai, lytis, diržas); Denis peržiūri ir prideda į varžybas
CREATE TABLE IF NOT EXISTS tournament_entries (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT UNSIGNED NOT NULL,
  club          VARCHAR(120) NOT NULL,
  contact       VARCHAR(190) NULL,
  name          VARCHAR(120) NOT NULL,
  birth_year    SMALLINT UNSIGNED NULL,
  gender        CHAR(1) NULL,
  belt          TINYINT UNSIGNED NULL,
  status        ENUM('new','added','rejected') NOT NULL DEFAULT 'new',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tentries (tournament_id, status),
  CONSTRAINT fk_tentries_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('026_lankomumas_xp_varzybos.sql');
