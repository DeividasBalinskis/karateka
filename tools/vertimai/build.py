# Taikos ir LTKF vertimų žodynai (assets/tl.js): tikslūs tekstai, šablonai su skaičiais ({#}), frazių taisyklės ir mėnesiai
import json, re, os
HERE = os.path.dirname(os.path.abspath(__file__))
APP = os.path.join(HERE, '..', '..', 'public_html')
rows = []
for f in ('taika.tsv', 'ltkf.tsv'):
    for line in open(os.path.join(HERE, f), encoding='utf-8'):
        if line.strip():
            p = line.rstrip('\n').split('\t')
            if len(p) == 3: rows.append(p)
abbr = {'sau': ('Jan', 'янв'), 'vas': ('Feb', 'фев'), 'kov': ('Mar', 'мар'), 'bal': ('Apr', 'апр'), 'geg': ('May', 'май'), 'bir': ('Jun', 'июн'),
        'lie': ('Jul', 'июл'), 'rgp': ('Aug', 'авг'), 'rgs': ('Sep', 'сен'), 'spa': ('Oct', 'окт'), 'lap': ('Nov', 'ноя'), 'gru': ('Dec', 'дек')}
months = ['sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio']
m_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
m_ru = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря']
# Frazės sudėtiniuose tekstuose (kategorijų pavadinimai, „Ištrinti Jonas“ ir pan.) - ilgesnės pirma
rules = [
  ('Išimti iš', 'Remove from', 'Убрать из'), ('Ištrinti', 'Delete', 'Удалить'), ('Pašalinti', 'Remove', 'Убрать'), ('Perkelti į', 'Move to', 'Перенести в'),
  ('m. ir vyr.', 'and older', 'и старше'), ('m. ir jaun.', 'and younger', 'и младше'), ('visi diržai', 'all belts', 'все пояса'),
  ('jau pridėtas', 'already added', 'уже добавлен'), ('Ketvirtfinalis', 'Quarter-final', 'Четвертьфинал'), ('Pusfinalis', 'Semi-final', 'Полуфинал'),
  ('Finalas', 'Final', 'Финал'), ('Bronza', 'Bronze', 'Бронза'), ('Paguoda', 'Repechage', 'Утешение'), ('ratas', 'round', 'круг'),
  ('Dabar', 'Now', 'Сейчас'), ('Toliau', 'Next', 'Далее'), ('TATAMIS', 'TATAMI', 'ТАТАМИ'), ('komandos', 'teams', 'команды'),
  ('Apdovanojimai', 'Awards', 'Награждение'), ('vėliavėlės', 'flags', 'флажки'), ('Kitei', 'Kitei', 'Китэй'), ('dalyv.', 'part.', 'уч.'),
  ('kateg.', 'cat.', 'кат.'), ('Treneris', 'Coach', 'Тренер'), ('Kita:', 'Next:', 'Далее:'), ('Ankstesnė:', 'Previous:', 'Предыдущая:'),
  ('Test varžybos', 'Test competition', 'Тестовые соревнования'), ('pavyzdys', 'example', 'пример'), ('Vilnius', 'Vilnius', 'Вильнюс'),
  ('Sporto centras Viršuliškės', 'Viršuliškės sports centre', 'спортивный центр Виршулишкес'), ('klubai su medaliais', 'clubs with medals', 'клубы с медалями'),
  ('Vaikai', 'Children', 'Дети'), ('kyu', 'kyu', 'кю'), ('Mix', 'Mix', 'Смеш.'),
]
for li, lang in ((1, 'en'), (2, 'ru')):
    exact, pat = {}, {}
    for r in rows:
        (pat if '{#}' in r[0] else exact)[r[0]] = r[li]
    for k, v in abbr.items(): exact[k] = v[li - 1]; exact[k.upper()] = v[li - 1].upper()
    out = {'lang': lang, 'map': exact, 'pat': pat, 'rules': [[a, (b if li == 1 else c)] for a, b, c in sorted(rules, key=lambda x: -len(x[0]))],
           'months': [months, m_en if li == 1 else m_ru], 'dateFmt': '{d} {m} {y}' if li == 1 else '{d} {m} {y} г.', 'yearSuffix': '' if li == 1 else ' г.'}
    json.dump(out, open(os.path.join(APP, 'app', 'lang', 'tl', lang + '.json'), 'w', encoding='utf-8'), ensure_ascii=False, separators=(',', ':'))
    print(lang, len(exact), len(pat))
    # LTKF - atskira svetainė: tas pats žodynas jos aplanke
    import shutil
    shutil.copy(os.path.join(APP, 'app', 'lang', 'tl', lang + '.json'), os.path.join(APP, 'ltkf', 'inc', 'lang', lang + '.json'))
