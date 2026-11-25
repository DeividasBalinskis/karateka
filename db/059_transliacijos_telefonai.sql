-- v1.98: Transliacija - telefonai kameros (Budo → 🎥 Transliacija). Telefonas nuskenuoja vieną klubo QR kodą (varzybos-kamera.php),
-- atsiranda sąraše, o kompiuteryje jis priskiriamas tatamiui (vienas telefonas - vienam tatamiui). Priskirtas telefonas siunčia vaizdą
-- to tatamio nuolatiniu srautu (vdo.ninja), todėl jį iškart mato sekretoriatas, peržiūra ir OBS. Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS stream_phones (
  id          VARCHAR(16)  NOT NULL PRIMARY KEY,     -- telefono atsitiktinis raktas (saugomas telefone)
  name        VARCHAR(60)  NOT NULL DEFAULT '',      -- pavadinimas sąraše („Telefonas K7Q“, galima pakeisti telefone)
  area_id     INT UNSIGNED NULL,                     -- priskirtas tatamis (NULL - nepriskirtas)
  area_name   VARCHAR(60)  NULL,                     -- tatamio pavadinimas priskyrimo metu (rodomas telefone)
  seen_at     DATETIME     NOT NULL,                 -- paskutinis ryšys (telefonas praneša kas kelias sekundes)
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stream_phones_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('059_transliacijos_telefonai.sql');
