-- v1.90 (#165, Denis): viena registracijos nuoroda visiems egzaminams - užsiregistravę patenka į laukiančiųjų sąrašą;
-- Egzaminai → „⏳ Laukiantieji“: pažymi ir įtrauki į protokolą (egzamino dienai - atskiri protokolai skirtingiems diržams),
-- žmogui išsiunčiamas laiškas su data, vieta ir kaina pagal diržą. Failą galima importuoti ir antrą kartą.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_waitlist (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name   VARCHAR(80)  NOT NULL,
  last_name    VARCHAR(80)  NOT NULL,
  birth_date   DATE         NULL,
  club         VARCHAR(120) NULL,
  kyu          VARCHAR(30)  NULL,                 -- kokį kyu laikys (kaina pagal jį)
  phone        VARCHAR(40)  NULL,
  email        VARCHAR(190) NULL,
  note         VARCHAR(500) NULL,
  event_id     INT UNSIGNED NULL,                 -- įtrauktas į šio egzamino (protokolo) sąrašą
  row_id       INT UNSIGNED NULL,                 -- eilutė protokole
  assigned_at  DATETIME     NULL,
  mailed_at    DATETIME     NULL,                 -- laiškas su data, vieta ir kaina išsiųstas
  removed_at   DATETIME     NULL,                 -- išimtas iš sąrašo (ne trynimas)
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ewl_wait (event_id, removed_at, created_at),
  CONSTRAINT fk_ewl_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO db_versija (failas) VALUES ('055_egzaminu_laukiantieji.sql');
