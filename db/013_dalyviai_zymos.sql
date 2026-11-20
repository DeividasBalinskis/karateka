-- 013: renginių dalyviai (registracija, kvietimai) ir narių žymos (teisėjas, egzaminatorius)
SET NAMES utf8mb4;

-- Ar nariai gali registruotis patys ir iki kada (NULL - iki renginio pradžios)
ALTER TABLE events
  ADD COLUMN registration TINYINT(1) NOT NULL DEFAULT 1 AFTER members_only,
  ADD COLUMN registration_until DATE NULL AFTER registration;

-- Dalyviai: pakviesti, dalyvaus, negalės. role = 'referee' - registruojasi kaip teisėjas
CREATE TABLE event_participants (
  event_id      INT UNSIGNED NOT NULL,
  member_id     INT UNSIGNED NOT NULL,
  status        ENUM('invited','going','declined') NOT NULL,
  role          ENUM('participant','referee') NOT NULL DEFAULT 'participant',
  invited_by    INT UNSIGNED NULL,
  responded_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, member_id),
  KEY idx_ep_member (member_id, status),
  CONSTRAINT fk_ep_event  FOREIGN KEY (event_id)   REFERENCES events(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ep_member FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ep_by     FOREIGN KEY (invited_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Žymos nustato, kokią papildomą medžiagą ir testus žmogus mato. „Karateka“ turi visi, todėl nesaugoma.
CREATE TABLE member_tags (
  member_id   INT UNSIGNED NOT NULL,
  tag         ENUM('teisejas','egzaminatorius') NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id, tag),
  CONSTRAINT fk_mt_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
