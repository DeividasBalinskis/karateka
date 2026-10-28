-- v1.10: prisijungimas su kodu el. paštu (įjungia pats žmogus paskyros nustatymuose), patikimi įrenginiai (30 d.),
-- atšauktos treniruotės ir atostogos (datos, priežastis, grupės)
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS two_factor TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;

-- Vienkartiniai 6 skaitmenų kodai (saugomas tik hash), galioja 10 min., daugiausia 5 bandymai
CREATE TABLE IF NOT EXISTS login_codes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  purpose    ENUM('login', 'enable') NOT NULL DEFAULT 'login',
  code_hash  CHAR(64) NOT NULL,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lc_account (account_id, created_at),
  CONSTRAINT fk_lc_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- „Pasitikėti šiuo įrenginiu“: slapuke - atsitiktinis žetonas, čia - jo hash
CREATE TABLE IF NOT EXISTS trusted_devices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id   INT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  label        VARCHAR(120) NULL,
  expires_at   DATETIME NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_td_token (token_hash),
  KEY idx_td_account (account_id),
  CONSTRAINT fk_td_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atšauktos treniruotės ir atostogos: nuo-iki, priežastis. Grupės - training_cancellation_groups (nė vienos = visos grupės).
CREATE TABLE IF NOT EXISTS training_cancellations (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  starts_on  DATE NOT NULL,
  ends_on    DATE NOT NULL,
  reason     VARCHAR(190) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tc_dates (ends_on, starts_on),
  CONSTRAINT fk_tc_account FOREIGN KEY (created_by) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_cancellation_groups (
  cancellation_id INT UNSIGNED NOT NULL,
  group_id        INT UNSIGNED NOT NULL,
  PRIMARY KEY (cancellation_id, group_id),
  KEY idx_tcg_group (group_id),
  CONSTRAINT fk_tcg_cancellation FOREIGN KEY (cancellation_id) REFERENCES training_cancellations (id) ON DELETE CASCADE,
  CONSTRAINT fk_tcg_group FOREIGN KEY (group_id) REFERENCES training_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('023_kodas_atsauktos_treniruotes.sql');
