-- v1.90 (Deividas): atsiliepimas su klausimu autoriui - būsena „Reikia atsakymo“ (question); autorius atsako lange (answer),
-- tada atsiliepimas vėl tampa „Naujas“ ir Claude jį tęsia. Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

ALTER TABLE feedback MODIFY status ENUM('new','planned','question','done','declined') NOT NULL DEFAULT 'new';
ALTER TABLE feedback
  ADD COLUMN IF NOT EXISTS answer      TEXT     NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS answered_at DATETIME NULL DEFAULT NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('054_atsiliepimu_klausimai.sql');
