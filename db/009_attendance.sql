-- Lankomumas: kas buvo / nebuvo treniruotėje (žymima trenerio puslapyje „Treniruotė“)
SET NAMES utf8mb4;

CREATE TABLE attendance (
  member_id      INT UNSIGNED NOT NULL,
  training_date  DATE NOT NULL,
  group_id       INT UNSIGNED NULL,
  present        TINYINT(1) NOT NULL,          -- 1 buvo, 0 nebuvo
  marked_by      INT UNSIGNED NULL,
  marked_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id, training_date),
  KEY idx_att_group_date (group_id, training_date),
  CONSTRAINT fk_att_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_group  FOREIGN KEY (group_id)  REFERENCES training_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_att_by     FOREIGN KEY (marked_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
