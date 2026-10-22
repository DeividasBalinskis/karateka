-- Ryšys su varžybų sistema (varzybos.karateka.lt / ltkf.lt)
SET NAMES utf8mb4;

-- Lytis reikalinga varžybų kategorijoms (vaikinai / merginos). NULL - dar nenurodyta.
ALTER TABLE members ADD COLUMN gender ENUM('M','F') NULL AFTER birth_date;

-- Kurios varžybų sistemos varžybos atitinka šį renginį (iš ten įkeliami rezultatai)
ALTER TABLE events ADD COLUMN tournament_id INT UNSIGNED NULL AFTER members_only;
