-- Pradinės grupės ir tvarkaraštis, perkelti iš page1.html. Treneris gali taisyti admin panelėje.
SET NAMES utf8mb4;

INSERT INTO training_groups (id, name, category, location, sort_order) VALUES
 (1,  'Vaikai: Viršuliškės, pažengę',         'vaikai',   'Sporto centras Viršuliškės (Laisvės pr. 58)', 10),
 (2,  'Vaikai: Viršuliškės, naujokai',        'vaikai',   'Sporto centras Viršuliškės (Laisvės pr. 58)', 11),
 (3,  'Vaikai: Avižienių gimnazija',          'vaikai',   'Avižienių gimnazija (Avižieniai)', 20),
 (4,  'Vaikai: Šv. Juozapo mokykla (Jeruzalė)',    'vaikai', 'Šv. Juozapo mokykla (Jeruzalė)', 21),
 (5,  'Vaikai: Šv. Juozapo mokykla (Pašilaičiai)', 'vaikai', 'Šv. Juozapo mokykla (Pašilaičiai)', 22),
 (6,  'Vaikai: S. Kovalevskajos progimnazija', 'vaikai',  'Sofijos Kovalevskajos progimnazija (Šeškinė)', 23),
 (7,  'Vaikai: VDU licėjus „Sokratus“',        'vaikai',  'VDU licėjus „Sokratus“ (Žvėrynas)', 24),
 (8,  'Vaikai: Avižienių darželis',            'vaikai',  'Avižienių darželis (Avižieniai)', 30),
 (9,  'Vaikai: L/d „Žiedas“',                  'vaikai',  'Lopšelis-darželis „Žiedas“ (Pašilaičiai)', 31),
 (10, 'Vaikai: Šv. Juozapo darželis',          'vaikai',  'Šv. Juozapo darželis (Pašilaičiai)', 32),
 (11, 'Vaikai: L/d „Sveikuolis“',              'vaikai',  'Lopšelis-darželis „Sveikuolis“ (Šeškinė)', 33),
 (12, 'Jaunimas',                              'jaunimas','Sporto centras Viršuliškės (Laisvės pr. 58)', 40),
 (13, 'Suaugusieji',                           'suauge',  'Sporto centras Viršuliškės (Laisvės pr. 58)', 50);

INSERT INTO schedule (group_id, weekday, start_time, end_time, note) VALUES
 (1, 1, '17:30', '18:30', NULL), (1, 3, '17:30', '18:30', NULL),
 (2, 2, '17:30', '18:15', NULL), (2, 4, '17:30', '18:15', NULL),
 (3, 2, '16:00', '16:45', NULL), (3, 4, '16:00', '16:45', NULL),
 (4, 1, '16:15', '17:00', NULL), (4, 3, '16:15', '17:00', NULL),
 (5, 5, '16:15', '17:00', NULL),
 (6, 1, '17:00', '18:00', NULL), (6, 2, '19:00', '20:00', NULL), (6, 4, '18:00', '19:00', NULL),
 (7, 3, NULL, NULL, 'Laikas tikslinamas'), (7, 5, NULL, NULL, 'Laikas tikslinamas'),
 (9, 2, NULL, NULL, 'Laikas tikslinamas'), (9, 4, NULL, NULL, 'Laikas tikslinamas'),
 (11, 1, '15:00', '16:00', NULL), (11, 3, '15:00', '16:00', NULL),
 (12, 1, '18:30', '20:00', NULL), (12, 3, '18:30', '20:00', NULL),
 (12, 2, '18:00', '19:45', NULL), (12, 4, '18:00', '19:45', NULL), (12, 5, '18:00', '19:45', NULL),
 (13, 1, '18:00', '19:15', NULL), (13, 3, '18:00', '19:15', NULL),
 (13, 5, '17:00', '18:00', NULL), (13, 7, '10:00', '11:30', NULL);
