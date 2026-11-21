-- v1.78: paskyros kalba (C2), registracijos į Budo varžybas rungtys, laiškų siuntimo eilė (C8).
-- accounts.lang - LT / EN / RU, pasirinkta viršuje; prisijungus vėl ta pati, laiškai ta kalba.
-- tournament_entries.disciplines - kur dalyvis varžosi (pvz. „kata,kumite“); Budo pagal tai deda į grupes.
-- mail_outbox - masiniai laiškai (pranešimas nariams, atšauktos treniruotės) siunčiami dalimis, matosi rezultatas kiekvienam;
--   išsiųsti įrašai ištrinami po 90 dienų (app/cleanup.php).
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS lang CHAR(2) NULL AFTER theme;
ALTER TABLE tournament_entries ADD COLUMN IF NOT EXISTS disciplines VARCHAR(60) NULL AFTER belt;

CREATE TABLE IF NOT EXISTS mail_outbox (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  batch       VARCHAR(24)  NOT NULL,
  title       VARCHAR(190) NULL,
  account_id  INT UNSIGNED NULL,
  to_email    VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  body        TEXT         NOT NULL,
  status      VARCHAR(10)  NOT NULL DEFAULT 'queued',
  error       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at     DATETIME     NULL,
  KEY idx_outbox_batch (batch, status),
  KEY idx_outbox_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('049_kalba_rungtys.sql');
