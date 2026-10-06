-- Pagrindinio puslapio kainos ir tvarkaraštis iš duomenų bazės (keičiama admin panelėje)
SET NAMES utf8mb4;

-- Grupės vieta pagrindinio puslapio sąraše ir trumpa žymė (pvz. „pažengę“)
ALTER TABLE training_groups
  ADD COLUMN place_type ENUM('sporto_centras','mokykla','darzelis','kita') NOT NULL DEFAULT 'kita' AFTER location,
  ADD COLUMN label VARCHAR(60) NULL AFTER place_type;

UPDATE training_groups SET place_type = 'sporto_centras' WHERE id IN (1, 2, 12, 13);
UPDATE training_groups SET place_type = 'mokykla'        WHERE id IN (3, 4, 5, 6, 7);
UPDATE training_groups SET place_type = 'darzelis'       WHERE id IN (8, 9, 10, 11);
UPDATE training_groups SET label = 'pažengę'  WHERE id = 1;
UPDATE training_groups SET label = 'naujokai' WHERE id = 2;

-- Kainos kiekvienai amžiaus kategorijai
CREATE TABLE category_prices (
  category    ENUM('vaikai','jaunimas','suauge') NOT NULL PRIMARY KEY,
  price_main  VARCHAR(40)  NOT NULL,
  price_note  VARCHAR(120) NULL,
  price_alt   VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO category_prices (category, price_main, price_note, price_alt) VALUES
 ('vaikai',   '60€/mėn', '(pasirašius metinę sutartį)', '90€/mėn be sutarties'),
 ('jaunimas', '70€/mėn', '(pasirašius metinę sutartį)', '100€/mėn be sutarties'),
 ('suauge',   '75€/mėn', '(pasirašius metinę sutartį)', '100€/mėn be sutarties');

-- Jaunimo sekmadienio treniruotė (yra pagrindiniame puslapyje)
INSERT INTO schedule (group_id, weekday, start_time, end_time)
SELECT 12, 7, '10:00', '11:30' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule WHERE group_id = 12 AND weekday = 7);
