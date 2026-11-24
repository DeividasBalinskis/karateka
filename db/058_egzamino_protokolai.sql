-- v1.94 (Denis, #174): tos pačios egzamino dienos papildomi protokolai (pvz. kitiems diržams) - tai to paties renginio dalys.
-- parent_id - pagrindinis renginys; renginių sąrašuose (naujienos, renginiai, pradžia, kalendorius) rodomas tik pagrindinis.
-- Esami papildomi protokolai („Pavadinimas · žymė“ ta pačia diena) susiejami automatiškai. Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL DEFAULT NULL AFTER tournament_id,
  ADD KEY IF NOT EXISTS idx_events_parent (parent_id);

UPDATE events c
  JOIN events p ON p.type = 'exam' AND c.type = 'exam' AND p.id < c.id AND p.starts_on = c.starts_on
                 AND p.parent_id IS NULL AND c.title LIKE CONCAT(p.title, ' · %')
   SET c.parent_id = p.id
 WHERE c.parent_id IS NULL;

INSERT IGNORE INTO db_versija (failas) VALUES ('058_egzamino_protokolai.sql');
