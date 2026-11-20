-- v1.53: „Kodas kitam įrenginiui“ (sprendimas 2026-11-20, vietoj bendro „dev code“).
-- Tikras administratorius sukuria vienkartinį kodą; kitas įrenginys (pvz. sekretoriato planšetė) jį įveda ir ribotą laiką
-- dirba to administratoriaus teisėmis. Kodas įvedamas per 10 min., tik vieną kartą; įrenginį galima bet kada atjungti.
-- Saugomas tik kodo hash. Įrašai - žurnalas (kas sukūrė, kada ir kuriame įrenginyje panaudota), trinami po 1 metų.
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS device_codes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id    INT UNSIGNED NOT NULL,
  label         VARCHAR(80) NULL,
  code_hash     CHAR(64) NOT NULL,
  hours         SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME NULL,
  used_ip       VARCHAR(45) NULL,
  used_device   VARCHAR(120) NULL,
  session_until DATETIME NULL,
  revoked_at    DATETIME NULL,
  revoked_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dc_code (code_hash),
  KEY idx_dc_account (account_id, created_at),
  CONSTRAINT fk_dc_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('047_irenginio_kodas.sql');
