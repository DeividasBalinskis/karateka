#!/usr/bin/env python3
"""Sudeda db/0xx failus į vieną atnaujinimo failą, kurį galima importuoti kelis kartus:
CREATE TABLE -> IF NOT EXISTS, ALTER ... ADD COLUMN / ADD CONSTRAINT - tik jei dar nėra.
INSERT leidžiamas tik su sąlyga @do_... toje pačioje eilutėje (pvz. db/019: įkeliama, tik jei testo dar nėra).
Naudojimas: python tools/atnaujinimo-sql.py 8 18 ikelimui/atnaujinti-db-v1.4.sql 1.4"""
import re, sys, pathlib

lo, hi, out, ver = int(sys.argv[1]), int(sys.argv[2]), sys.argv[3], sys.argv[4]
db = pathlib.Path(__file__).resolve().parent.parent / 'db'

def q(s):
    return "'" + s.replace("'", "''") + "'"

def guard(check, stmt):
    return (f"SET @s = IF(({check}) = 0, {q(stmt)}, 'DO 0');\n"
            "PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;")

def col_check(t, c):
    return ("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            f"AND TABLE_NAME = '{t}' AND COLUMN_NAME = '{c}'")

def fk_check(t, n):
    return ("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() "
            f"AND TABLE_NAME = '{t}' AND CONSTRAINT_NAME = '{n}'")

def alter(m):
    t, body = m.group(1), m.group(2)
    parts = re.split(r',\s*\n\s*(?=ADD )', body.strip())
    res = []
    for p in parts:
        p = ' '.join(p.split())
        mc = re.match(r'ADD COLUMN (?:IF NOT EXISTS )?(\w+) ', p)
        mf = re.match(r'ADD CONSTRAINT (\w+) ', p)
        if mc:
            res.append(guard(col_check(t, mc.group(1)), f'ALTER TABLE {t} {p}'))
        elif mf:
            res.append(guard(fk_check(t, mf.group(1)), f'ALTER TABLE {t} {p}'))
        elif p.startswith('MODIFY '):
            res.append(f'ALTER TABLE {t} {p};')   # stulpelio tipo keitimą galima kartoti
        else:
            sys.exit('Nežinomas ALTER: ' + p)
    return '\n'.join(res)

FOLDERS_OLD = """INSERT INTO lesson_folders (title) SELECT DISTINCT topic FROM lessons WHERE topic IS NOT NULL AND topic <> '';
UPDATE lesson_folders SET sort_order = id;
UPDATE lessons l JOIN lesson_folders f ON f.title = l.topic AND f.parent_id IS NULL SET l.folder_id = f.id;
UPDATE lessons SET sort_order = id;"""
FOLDERS_NEW = """-- (tik pirmą kartą: kol aplankų dar nėra ir pamokos dar nesurikiuotos)
SET @do_folders = ((SELECT COUNT(*) FROM lesson_folders) = 0 AND (SELECT COUNT(*) FROM lessons WHERE sort_order <> 0) = 0);
INSERT INTO lesson_folders (title) SELECT DISTINCT topic FROM lessons WHERE topic IS NOT NULL AND topic <> '' AND @do_folders = 1;
UPDATE lesson_folders SET sort_order = id WHERE @do_folders = 1;
UPDATE lessons l JOIN lesson_folders f ON f.title = l.topic AND f.parent_id IS NULL SET l.folder_id = f.id WHERE @do_folders = 1;
UPDATE lessons SET sort_order = id WHERE @do_folders = 1;"""

chunks = []
files = sorted(f for f in db.glob('0*.sql') if lo <= int(f.name[:3]) <= hi)
for f in files:
    s = f.read_text(encoding='utf-8')
    s = re.sub(r'CREATE TABLE (?!IF NOT EXISTS)(\w+)', r'CREATE TABLE IF NOT EXISTS \1', s)
    if FOLDERS_OLD in s:
        s = s.replace(FOLDERS_OLD, FOLDERS_NEW)
    elif 'lesson_folders (title)' in s:
        sys.exit('016 duomenų dalis pasikeitė - atnaujinkite FOLDERS_OLD')
    s = s.replace('INSERT INTO point_categories', 'INSERT IGNORE INTO point_categories')   # code - unikalus
    s = re.sub(r'ALTER TABLE (\w+)\s+(.*?);', alter, s, flags=re.S)
    if re.search(r'^\s*(INSERT INTO(?!.*@do_)|DROP|DELETE|TRUNCATE)', s, re.M):
        sys.exit(f'{f.name}: nepatikrinta komanda (INSERT/DROP/DELETE) - peržiūrėkite')
    chunks.append(f'-- ===== {f.name} =====\n' + s.strip() + '\n')

head = (f"-- karateka.lt test svetainės DB atnaujinimas iki v{ver} (db/{lo:03d} - db/{hi:03d} vienu failu).\n"
        "-- phpMyAdmin -> aus15792_test -> Import. Galima importuoti ir kelis kartus: kas jau yra, praleidžiama.\n"
        "-- Esami duomenys (naujienos, renginiai, nariai) nekeičiami - tik pridedamos lentelės ir stulpeliai.\n"
        "SET NAMES utf8mb4;\n\n")
pathlib.Path(out).write_text(head + '\n'.join(chunks), encoding='utf-8')
print('ok', out, [f.name for f in files])
