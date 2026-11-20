-- v1.9: grupių spalvos (lankomumo kvadratai), komentarai su autoriumi ir vieta (anoniminiai - kitiems vardas nerodomas)
-- Failą galima importuoti ir antrą kartą. Esami duomenys nekeičiami.

SET NAMES utf8mb4;

-- Grupės spalva „Treniruotė“ puslapio kvadratams (tuščia - parenkama automatiškai)
ALTER TABLE training_groups ADD COLUMN IF NOT EXISTS color VARCHAR(7) NULL AFTER price_note;

-- Komentaras: anoniminis (Denis mato, kas parašė; paviešinus kitiems vardas nerodomas) ir kuriame puslapyje paliktas
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER show_name;
ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS page VARCHAR(190) NULL AFTER anonymous;

-- Kurie db failai jau importuoti (nuo šio failo; senesni - visada). Kiekvienas naujas db failas pabaigoje įsirašo čia.
-- Lokaliai tools\lokali-atnaujinti.ps1 pagal šią lentelę importuoja tik trūkstamus failus.
CREATE TABLE IF NOT EXISTS db_versija (
  failas     VARCHAR(120) NOT NULL PRIMARY KEY,
  importuota DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO db_versija (failas) VALUES ('022_pokalbiai_grupiu_spalvos.sql');
