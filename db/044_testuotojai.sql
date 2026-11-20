-- 044: testuotojai. Tikri administratoriai - Denis ir Deividas; visi kiti, kurie dabar turi administratoriaus rolę, tampa testuotojais:
-- ženkliukas „Testuotojas“, gali tiek, kiek treneris, nustatymus mato, bet keisti negali, Dojo ir žaidimo nemato.
-- Keičiama: Valdymas → Nustatymai → Paskyros ir rolės → „🧪 Testuotojas“.
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS is_tester TINYINT(1) NOT NULL DEFAULT 0;

-- Tik pirmą kartą (vėliau importuojant dar kartą - nieko nekeičia):
-- testuotojais pažymimi administratoriai, kurie nėra žaidimo sąraše (ten - Denis, db/043) ir nėra IT paskyra be pokalbių lango (#21).
-- Jei žaidimo sąrašo nėra arba jame nėra nė vieno aktyvaus administratoriaus - niekas nepažymimas (pažymėti galima rankomis).
SET @keep = (SELECT COUNT(*) FROM accounts a JOIN site_settings s ON s.name = 'game_access' AND JSON_VALID(s.value) AND JSON_TYPE(s.value) = 'ARRAY'
             WHERE a.role = 'admin' AND a.status = 'active' AND JSON_CONTAINS(s.value, CAST(a.id AS CHAR)));
SET @first = (SELECT COUNT(*) = 0 FROM db_versija WHERE failas = '044_testuotojai.sql');
UPDATE accounts a JOIN site_settings s ON s.name = 'game_access' AND JSON_VALID(s.value) AND JSON_TYPE(s.value) = 'ARRAY'
SET a.is_tester = 1
WHERE @keep > 0 AND @first = 1 AND a.role = 'admin' AND a.chat_off = 0 AND NOT JSON_CONTAINS(s.value, CAST(a.id AS CHAR));

INSERT IGNORE INTO db_versija (failas) VALUES ('044_testuotojai.sql');
