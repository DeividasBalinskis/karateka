-- 020: nuotraukų sutikimai pagal tai, KAS skelbia (ne kur nufotografuota).
--   members.photo_consent - klubas: karateka.lt ir klubo socialiniai tinklai (kaip iki šiol)
--   members.photo_events  - varžybų organizatorius: varžybų nuotraukos ir video (NULL - tėvai dar neatsakė)
-- Kiekvienas atsakymas įrašomas į consent_log: kas, kada ir ką atsakė.
-- Failą galima importuoti ir antrą kartą: kas jau yra - praleidžiama.
SET NAMES utf8mb4;

ALTER TABLE members ADD COLUMN IF NOT EXISTS photo_events TINYINT(1) NULL AFTER photo_consent;
ALTER TABLE members ADD COLUMN IF NOT EXISTS photo_answered_at DATETIME NULL AFTER photo_events;
ALTER TABLE members ADD COLUMN IF NOT EXISTS photo_answered_by INT UNSIGNED NULL AFTER photo_answered_at;

CREATE TABLE IF NOT EXISTS consent_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id   INT UNSIGNED NOT NULL,
  kind        VARCHAR(30) NOT NULL,          -- photo_club | photo_events
  value       TINYINT(1) NOT NULL,
  account_id  INT UNSIGNED NULL,             -- kas atsakė (tėvai, pats narys ar treneris)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_member (member_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
