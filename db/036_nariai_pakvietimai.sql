-- 036: greitas nario pridėjimas (užtenka vardo, pavardės ir gimimo metų ar grupės) ir pakvietimo nuoroda,
-- kuri užsiregistravusį susieja su tuo nariu. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

-- 0 - tiksli gimimo data, 1 - žinomi tik metai (įrašoma sausio 1 d.), 2 - metai apytiksliai pagal grupę
ALTER TABLE members ADD COLUMN IF NOT EXISTS birth_precision TINYINT NOT NULL DEFAULT 0;

ALTER TABLE email_tokens MODIFY purpose ENUM('verify_email','parent_consent','password_reset','invite','change_email','join') NOT NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('036_nariai_pakvietimai.sql');
