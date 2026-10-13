-- 011: svetainės nustatymai, kuriuos keičia adminas.
-- Pirmas nustatymas: kuriuos emoji nariai gali naudoti reakcijoms (Treneriams -> Naujienos 🙂).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
  name        VARCHAR(60) NOT NULL PRIMARY KEY,
  value       TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pradžiai - tie patys 5 emoji, kurie buvo iki šiol
INSERT IGNORE INTO site_settings (name, value) VALUES ('reactions', '👍 ❤️ 🔥 👏 🥋');
