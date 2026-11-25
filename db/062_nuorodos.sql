-- 062 (C3): visos slaptos nuorodos vienoje lentelėje (app/links.php, Valdymas → Nustatymai → „Visos nuorodos“).
-- Saugomas tik nuorodos rakto „pirštų atspaudas“ (sha256) ir užrakinta kopija (kad būtų galima nukopijuoti dar kartą).
-- Senos nuorodos (site_settings, tournaments.*_token, exam_signup_links, exam_protocol_rows.token, quizzes.share_token) lieka kaip buvo
-- ir toliau veikia: app/links.php jas perkelia į šią lentelę pirmą kartą prireikus. Nieko netrinama.
-- Kas išsaugojo: Budo istorijos kopijos ir apdovanojimai su vardu (sekretoriatas, apdovanojimų planšetė, treneris).
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS access_links (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind          VARCHAR(20)  NOT NULL,                 -- sekr, awards, live, reg, demo, cam, exam_signup, exam_waitlist, exam_row, quiz, just, ltkf_editor, ltkf_review
  owner_id      INT UNSIGNED NULL,                     -- kam priklauso: varžybos, egzaminas, protokolo eilutė, testas (bendroms - NULL)
  label         VARCHAR(120) NULL,                     -- pavadinimas (pvz. LTKF redaktoriaus vardas)
  token_hash    CHAR(64)     NOT NULL,                 -- sha256(raktas) - pagal jį ieškoma
  token_lock    VARCHAR(255) NULL,                     -- užrakinta rakto kopija (kopijuoti dar kartą)
  can_write     TINYINT(1)   NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME     NULL,                     -- NULL - pagal rūšies taisyklę arba be pabaigos
  uses          INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at  DATETIME     NULL,
  revoked_at    DATETIME     NULL,                     -- išjungta arba pakeista
  replaced_by   INT UNSIGNED NULL,                     -- „↻ Nauja nuoroda“: kuri ją pakeitė
  legacy        TINYINT(1)   NOT NULL DEFAULT 0,       -- perkelta iš senos vietos
  UNIQUE KEY uq_access_links_hash (token_hash),
  KEY idx_access_links_kind (kind, owner_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kas išsaugojo (vardai nuo ankstesnės kopijos, atskirti „, “)
ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS saved_by VARCHAR(255) NULL;
ALTER TABLE tournament_versions ADD COLUMN IF NOT EXISTS saved_by VARCHAR(255) NULL;
ALTER TABLE tournament_awards ADD COLUMN IF NOT EXISTS by_name VARCHAR(120) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('062_nuorodos.sql');
