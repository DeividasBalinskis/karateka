-- 017: renginys tik pakviestiems (kai „Kam skirta?“ pažymėti tik konkretūs žmonės, o ne visa grupė)
SET NAMES utf8mb4;

ALTER TABLE events ADD COLUMN invited_only TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_until;
