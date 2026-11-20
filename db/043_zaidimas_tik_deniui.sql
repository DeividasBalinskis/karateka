-- 043: žaidimas (Dojo, arena, žaidimai) kol kas paslėptas - mato tik Denio paskyra. Keičiama: Nustatymai → „🏯 Žaidimas“.
-- Jei nustatymas jau yra (pakeistas nustatymuose) - nekeičiamas.
INSERT INTO site_settings (name, value)
SELECT 'game_access', CONCAT('[', COALESCE(GROUP_CONCAT(id ORDER BY id), ''), ']') FROM accounts
WHERE email = 'info@karateka.lt' OR (first_name = 'Denis' AND last_name LIKE 'Balinsk%' AND role = 'admin')
ON DUPLICATE KEY UPDATE value = value;

INSERT IGNORE INTO db_versija (failas) VALUES ('043_zaidimas_tik_deniui.sql');
