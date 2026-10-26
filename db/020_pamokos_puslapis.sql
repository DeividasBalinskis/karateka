-- 020: pamokų aplankai pagal diržą; pagrindinio puslapio blokai (tekstai, nuotraukos, tvarka)
-- Failą galima importuoti ir antrą kartą - nieko nepridės dvigubai.
SET NAMES utf8mb4;

-- Aplanko diržas (pvz. Egzaminams -> „8 kyu“): narys iš karto mato savo diržo aplanką. NULL - visiems diržams.
ALTER TABLE lesson_folders ADD COLUMN IF NOT EXISTS belt_level TINYINT UNSIGNED NULL AFTER audience;

-- Pagrindinio puslapio blokai saugomi site_settings (home_page) - TEXT gali neužtekti
ALTER TABLE site_settings MODIFY value MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
