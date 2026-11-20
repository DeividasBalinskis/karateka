-- 040: egzaminas be paskyros ir kainos; atsiliepimų autorius pagal naršyklę
-- Registracijai užtenka: vardas, pavardė, gimimo data, kontaktinis telefonas (el. paštas nebūtinas).
-- Kiekvienas protokolo dalyvis gauna asmeninę nuorodą (token) į savo egzamino puslapį - programa, testas, mokėjimas, rezultatas.
-- paid_at - pažymėta „Sumokėjo“; cert_at - sertifikatas išsiųstas (kol kas ruošiama); score_base - nuo kokio balo pradeda +/−.
SET NAMES utf8mb4;

ALTER TABLE exam_signups MODIFY email VARCHAR(190) NULL;
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS token VARCHAR(24) NULL;
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL;
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL;
ALTER TABLE exam_protocol_rows ADD COLUMN IF NOT EXISTS cert_at DATETIME NULL;
ALTER TABLE exam_protocol_rows ADD UNIQUE INDEX IF NOT EXISTS uq_epr_token (token);
ALTER TABLE exam_protocols ADD COLUMN IF NOT EXISTS score_base DECIMAL(4,1) NULL;

-- Atsiliepimai: kas rašė (naršyklės žymė) - kad ir bendroje paskyroje kiekvienas matytų ir taisytų tik savo
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS writer VARCHAR(32) NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('040_egzaminai_be_paskyros.sql');
