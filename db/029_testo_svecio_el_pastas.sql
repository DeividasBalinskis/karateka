-- 029: testas per nuorodą be paskyros - žmogus gali įrašyti el. paštą (nebūtina), rezultatas jam išsiunčiamas.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE quiz_guest_attempts ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER club;

INSERT IGNORE INTO db_versija (failas) VALUES ('029_testo_svecio_el_pastas.sql');
