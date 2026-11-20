-- 041: personažai panda, baltasis tigras ir varnas išimti (#115) - jų turėję gauna panašų: šešėlių katę, vėžlį meistrą, šventąją gervę
UPDATE game_heroes SET species = 'scat' WHERE species IN ('wtiger', 'tiger');
UPDATE game_heroes SET species = 'turtle' WHERE species = 'panda';
UPDATE game_heroes SET species = 'crane' WHERE species = 'raven';

INSERT IGNORE INTO db_versija (failas) VALUES ('041_be_pandos_tigro.sql');
