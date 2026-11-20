-- 2 etapas: taškai, reitingas, istorija
SET NAMES utf8mb4;

-- Renginys užsienyje (varžyboms skiriasi taškai)
ALTER TABLE events ADD COLUMN is_abroad TINYINT(1) NOT NULL DEFAULT 0 AFTER location;

-- Už ką skiriami taškai. Reikšmes keičia administratorius.
-- code - sisteminėms kategorijoms (naudojamos skiriant taškus iš renginio); savoms kategorijoms NULL.
CREATE TABLE point_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(30) NULL,
  name        VARCHAR(120) NOT NULL,
  points      INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_pc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kiekvienas taškų skyrimas. points - kiek taškų buvo skirta TUO metu (vėliau pakeitus vertę, istorija nesikeičia).
CREATE TABLE point_awards (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id    INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  points       INT NOT NULL,
  awarded_on   DATE NOT NULL,
  event_id     INT UNSIGNED NULL,
  note         VARCHAR(190) NULL,
  awarded_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pa_member (member_id, awarded_on),
  KEY idx_pa_event (event_id),
  CONSTRAINT fk_pa_member   FOREIGN KEY (member_id)   REFERENCES members(id)          ON DELETE CASCADE,
  CONSTRAINT fk_pa_category FOREIGN KEY (category_id) REFERENCES point_categories(id),
  CONSTRAINT fk_pa_event    FOREIGN KEY (event_id)    REFERENCES events(id)           ON DELETE SET NULL,
  CONSTRAINT fk_pa_by       FOREIGN KEY (awarded_by)  REFERENCES accounts(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pradinės kategorijos (taškų vertės - pavyzdinės, administratorius pakeis)
INSERT INTO point_categories (code, name, points, sort_order) VALUES
 ('exam',            'Išlaikytas egzaminas',                    10, 10),
 ('comp_lt',         'Varžybos Lietuvoje: dalyvavimas',          5, 20),
 ('comp_abroad',     'Varžybos užsienyje: dalyvavimas',         10, 21),
 ('place1_lt',       'Varžybos Lietuvoje: 1 vieta',             15, 30),
 ('place2_lt',       'Varžybos Lietuvoje: 2 vieta',             10, 31),
 ('place3_lt',       'Varžybos Lietuvoje: 3 vieta',              7, 32),
 ('place1_abroad',   'Varžybos užsienyje: 1 vieta',             30, 40),
 ('place2_abroad',   'Varžybos užsienyje: 2 vieta',             20, 41),
 ('place3_abroad',   'Varžybos užsienyje: 3 vieta',             15, 42),
 ('seminar',         'Seminaras / stovykla',                     5, 50),
 ('lead_training',   'Treniruotės vedimas klube',                5, 60),
 ('organize',        'Pagalba organizuojant renginį',            5, 70),
 ('referee',         'Teisėjavimas',                             5, 80),
 ('referee_qual',    'Teisėjo kvalifikacija',                   10, 90);
