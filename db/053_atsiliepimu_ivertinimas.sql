-- v1.89 (Deividas): atsiliepimo autorius įvertina pataisymą test svetainėje - „👍 Patinka“ (paliekama) arba „↩ Grąžinti kaip buvo“
-- (Claude atšaukia tą pakeitimą ir vėl įkelia į test). verdict: ok | revert | reverted (atšaukta). Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

ALTER TABLE feedback
  ADD COLUMN IF NOT EXISTS verdict    VARCHAR(10) NULL DEFAULT NULL AFTER reply,
  ADD COLUMN IF NOT EXISTS verdict_at DATETIME    NULL DEFAULT NULL AFTER verdict;

INSERT IGNORE INTO db_versija (failas) VALUES ('053_atsiliepimu_ivertinimas.sql');
