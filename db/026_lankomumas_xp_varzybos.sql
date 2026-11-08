-- 026: renginio lankomumas („Buvo“ / „Nebuvo“ Treniruotės puslapyje), XP kategorija „Pagalba treneriui“.
-- Failą galima importuoti ir antrą kartą.
SET NAMES utf8mb4;

-- Ar narys tikrai buvo renginyje: NULL - nepažymėta, 1 - buvo, 0 - nebuvo
ALTER TABLE event_participants ADD COLUMN IF NOT EXISTS attended TINYINT(1) NULL AFTER status;

-- XP, kurį skiria treneris (Valdymas → XP → Skirti XP, arba „+XP“ treniruotėje)
INSERT IGNORE INTO point_categories (code, name, points, sort_order) VALUES ('help_coach', 'Pagalba treneriui', 5, 65);

INSERT IGNORE INTO db_versija (failas) VALUES ('026_lankomumas_xp_varzybos.sql');
