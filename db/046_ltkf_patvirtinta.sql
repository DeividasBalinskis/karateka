-- 046: LTKF kalendoriuje - tik federacijos patvirtintos varžybos (sprendimas 2026-11-20).
-- Administratorius pažymi Budo varžybų nustatymuose „Federacijos kalendoriuje (patvirtinta)“.
ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS ltkf_approved TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO db_versija (failas) VALUES ('046_ltkf_patvirtinta.sql');
