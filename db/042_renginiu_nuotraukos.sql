-- 042: renginio nuotrauka (#119) - rodoma „Naujienos ir renginiai“ ir renginių sąraše
ALTER TABLE events ADD COLUMN IF NOT EXISTS cover VARCHAR(190) NULL AFTER file_name;

INSERT IGNORE INTO db_versija (failas) VALUES ('042_renginiu_nuotraukos.sql');
