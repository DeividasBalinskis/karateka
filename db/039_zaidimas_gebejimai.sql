-- Žaidimas: personažų gebėjimai ir antras gyvūnėlis kovoje
-- char_wins - pergalės su kiekvienu personažu (JSON {personažas: pergalės}); kuo daugiau, tuo dažniau suveikia gebėjimas
-- pet2_id - antras gyvūnėlis, kurį galima pasiimti į kovą
ALTER TABLE game_heroes ADD COLUMN IF NOT EXISTS char_wins TEXT NULL;
ALTER TABLE game_heroes ADD COLUMN IF NOT EXISTS pet2_id INT UNSIGNED NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('039_zaidimas_gebejimai.sql');
