-- v1.94 (Deividas): pateisinimą galima pataisyti - prašymo duomenys ir tekstas (custom_text), arba įkelti galutinį failą
-- (Word .docx arba PDF; file_name - originalus pavadinimas), kuris išsiunčiamas vietoj sugeneruoto. Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

ALTER TABLE justifications
  ADD COLUMN IF NOT EXISTS custom_text TEXT         NULL DEFAULT NULL AFTER email,
  ADD COLUMN IF NOT EXISTS file_name   VARCHAR(190) NULL DEFAULT NULL AFTER custom_text;

INSERT IGNORE INTO db_versija (failas) VALUES ('057_pateisinimu_failai.sql');
