-- 024: Denio rašomo turinio vertimai (naujienos, renginiai, pamokos, pagrindinis puslapis) į anglų ir rusų kalbas.
-- Vertimas pririštas prie lietuviško teksto (md5): pakeitus lietuvišką tekstą, senas vertimas nebetinka. Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS content_translations (
  hash        CHAR(32) NOT NULL,              -- md5(lietuviškas tekstas)
  lang        CHAR(2) NOT NULL,               -- en, ru
  source      MEDIUMTEXT NOT NULL,            -- lietuviškas tekstas (kad būtų matyti, kas versta)
  body        MEDIUMTEXT NOT NULL,            -- vertimas
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (hash, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('024_vertimai.sql');
