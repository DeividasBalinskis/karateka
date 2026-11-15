-- 034: renginio registracijos nuoroda (pvz. Google Forms) - prie renginio rodomas mygtukas „📝 Registracija →“.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

ALTER TABLE events ADD COLUMN IF NOT EXISTS registration_url VARCHAR(500) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('034_renginiu_registracija.sql');
