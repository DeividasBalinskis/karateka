-- 015: SMS žinutės (siunčia tik adminas) ir jų istorija
SET NAMES utf8mb4;

CREATE TABLE sms_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  body        TEXT NOT NULL,
  parts       TINYINT UNSIGNED NOT NULL DEFAULT 1,       -- iš kiek SMS dalių sudaryta viena žinutė
  provider    VARCHAR(20) NOT NULL,                      -- log (bandomasis režimas) | twilio
  sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  cost_eur    DECIMAL(8,3) NULL,                         -- apytikslė kaina pagal config sms.price_eur
  label       VARCHAR(190) NULL,                         -- kam siųsta, pvz. „Grupės: Jaunimas“
  sent_by     INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sms_created (created_at),
  CONSTRAINT fk_sms_by FOREIGN KEY (sent_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_recipients (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id   INT UNSIGNED NOT NULL,
  account_id   INT UNSIGNED NULL,
  name         VARCHAR(190) NOT NULL,
  phone        VARCHAR(40) NOT NULL,
  for_members  VARCHAR(255) NULL,                        -- dėl kurių narių (pvz. tėvams - vaikų vardai)
  status       ENUM('sent','failed','test') NOT NULL,
  provider_id  VARCHAR(64) NULL,
  error        VARCHAR(255) NULL,
  KEY idx_smsr_msg (message_id),
  CONSTRAINT fk_smsr_msg FOREIGN KEY (message_id) REFERENCES sms_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_smsr_acc FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
