-- Taškai už lankomumą (skiriami automatiškai pažymėjus „Buvo“) ir iš kur atėjo trenerio pastaba
SET NAMES utf8mb4;

-- Vertę ar išjungimą keičia administratorius: Treneriams -> Taškai
INSERT INTO point_categories (code, name, points, sort_order) VALUES ('attendance', 'Treniruotės lankymas', 1, 5);

-- Pastaba palikta po treniruotės (grupė) arba po renginio (renginys); senos pastabos - be šaltinio
ALTER TABLE coach_notes
  ADD COLUMN group_id INT UNSIGNED NULL AFTER author_id,
  ADD COLUMN event_id INT UNSIGNED NULL AFTER group_id,
  ADD CONSTRAINT fk_cn_group FOREIGN KEY (group_id) REFERENCES training_groups(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_cn_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;
