# Koncepcija: veikėjas, aplinka, kova. Paleisti: py -3.11 koncepcija.py  (rezultatai -> out/)
import os
import numpy as np
from PIL import Image
from pxkit import flip, up, sheet, whiten, hx, NAVY
from veikejai import kitsune, dragon, pet_turtle, pet_foxcub, pet_dragonling
from aplinka import dojo_kiemas, contact_shadow
import ui

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
os.makedirs(OUT, exist_ok=True)
IDLE = ((0, 0), (1, 1), (1, 2), (0, 3))


def blit(im, spr, x, y):
    a = np.array(spr)
    h, w = a.shape[:2]
    x0, y0 = max(0, x), max(0, y)
    x1, y1 = min(im.shape[1], x + w), min(im.shape[0], y + h)
    if x1 <= x0 or y1 <= y0:
        return
    sub = a[y0 - y:y1 - y, x0 - x:x1 - x]
    m = sub[..., 3] > 0
    reg = im[y0:y1, x0:x1]
    reg[..., :3][m] = sub[..., :3][m]
    if im.shape[2] == 4:
        reg[..., 3][m] = 255


def save_gif(frames, path, durations, k=3):
    pal_src = [up(Image.fromarray(f), k) for f in frames]
    p0 = pal_src[0].quantize(colors=256, method=Image.MEDIANCUT, dither=Image.NONE)
    q = [f.quantize(palette=p0, dither=Image.NONE) for f in pal_src]
    q[0].save(path, save_all=True, append_images=q[1:], duration=durations, loop=0, optimize=False, disposal=1)


# ---------- 1. Sprite lapai ----------
kit_fr = [kitsune(b, t) for b, t in IDLE] + [kitsune(0, 0, 'punch'), kitsune(0, 0, 'block'), kitsune(0, 0, 'hit')]
dr_fr = [dragon(b, t) for b, t in IDLE] + [dragon(0, 0, 'punch'), dragon(0, 0, 'block'), dragon(0, 0, 'hit')]
pets = {'vezliukas': [pet_turtle(0), pet_turtle(1)], 'lapiukas': [pet_foxcub(0, 0), pet_foxcub(1, 1)], 'drakoniukas': [pet_dragonling(0, 0), pet_dragonling(1, 1)]}
sheet(kit_fr).save(os.path.join(OUT, 'kitsune_48.png'))
sheet(dr_fr).save(os.path.join(OUT, 'drakonas_48.png'))
for k, v in pets.items():
    sheet(v).save(os.path.join(OUT, 'gyv_%s_32.png' % k))

# ---------- 2. Aplinka ----------
env = dojo_kiemas(G=118)
Image.fromarray(env.im).save(os.path.join(OUT, 'aplinka_dojo_kiemas.png'))
up(Image.fromarray(env.im), 4).save(os.path.join(OUT, 'aplinka_dojo_kiemas_x4.png'))

# ---------- 3. Veikėjas aplinkoje (animacija) ----------
rnd = np.random.RandomState(4)
fall = [(rnd.randint(0, 320), rnd.randint(0, 180), rnd.choice([0, 1])) for _ in range(14)]
frames = []
for i in range(8):
    s = dojo_kiemas(G=118)
    HX, FY = 112, 156
    contact_shadow(s, HX + 22, FY + 0.5, 17, 2.6)
    contact_shadow(s, 66, FY - 0.5, 10, 2.0)
    contact_shadow(s, 94, FY - 2.5, 7, 1.6)
    contact_shadow(s, 44, FY + 1.5, 6, 1.4, strength=0.4)
    im = s.im
    pb = (i // 2) % 2
    blit(im, pets['vezliukas'][pb], 50, FY - 29)
    blit(im, pets['lapiukas'][pb], 80, FY - 31)
    blit(im, pets['drakoniukas'][pb], 28, FY - 58 - pb)
    blit(im, kit_fr[i % 4], HX, FY - 46)
    for (x, y, c) in fall:
        yy = (y + i * 3) % 180
        xx = (x + int(np.sin((y + i * 3) / 9.0) * 3) - i) % 320
        im[yy, xx] = hx('#FFC4D8') if c else hx('#F59CC3')
    frames.append(im.copy())
Image.fromarray(frames[0]).save(os.path.join(OUT, 'veikejas_aplinkoje.png'))
up(Image.fromarray(frames[0]), 4).save(os.path.join(OUT, 'veikejas_aplinkoje_x4.png'))
save_gif(frames, os.path.join(OUT, 'veikejas_aplinkoje.gif'), [190] * 8, k=3)


# ---------- 4. Kova ----------
HERO_X, DR_X, FEET = 70, 202, 113
ROWS = (131, 143, 155, 167)
BLOCKS = ['AGE UKE', 'SOTO UKE', 'GEDAN BARAI', 'TAI SABAKI']
STRIKES = ['MAWASHI GERI', 'GYAKU TSUKI', 'ASHI BARAI']
ZONES = ['GALVA', 'KŪNAS', 'KOJOS']


def battle(st):
    """st: žodynas su būsena (blk, stk, pressed, hero_pose, hero_dx, dr_pose, dr_dx, dr_flash, hp, ghost, fx[], turn)"""
    s = dojo_kiemas(G=92)
    contact_shadow(s, st.get('hero_dx', 0) + HERO_X + 22, FEET + 0.5, 17, 2.6)
    contact_shadow(s, st.get('dr_dx', 0) + DR_X + 26, FEET + 0.5, 17, 2.6)
    contact_shadow(s, 30, FEET - 0.5, 9, 1.8)
    contact_shadow(s, 54, FEET - 1.5, 6, 1.4)
    contact_shadow(s, 20, FEET + 1.5, 5, 1.2, strength=0.35)
    im = np.dstack([s.im, np.full(s.im.shape[:2], 255, np.uint8)])
    pb = st.get('pb', 0)
    blit(im, pets['vezliukas'][pb], 14, FEET - 29)
    blit(im, pets['lapiukas'][pb], 40, FEET - 31)
    blit(im, pets['drakoniukas'][pb], 6, FEET - 62 - pb - st.get('pet_jump', 0))
    hp = kit_fr[{'idle': st.get('hf', 0), 'punch': 4, 'block': 5, 'hit': 6}[st.get('hero_pose', 'idle')]]
    blit(im, hp, HERO_X + st.get('hero_dx', 0), FEET - 46)
    dp = dr_fr[{'idle': st.get('df', 0), 'punch': 4, 'block': 5, 'hit': 6}[st.get('dr_pose', 'idle')]]
    dp = flip(dp)
    if st.get('dr_flash'):
        dp = whiten(dp)
    blit(im, dp, DR_X + st.get('dr_dx', 0), FEET - 46)
    # HUD
    ui.panel(im, 3, 3, 124, 28)
    ui.text(im, 9, 8, 'DEIVIDAS B.', ui.INK)
    ui.text(im, 92, 8, 'LYG.1', ui.MUTED)
    ui.hpbar(im, 9, 18, 84, st.get('hp_me', 1.0))
    ui.text(im, 97, 17, str(st.get('hp_me_n', 35)), ui.INK)
    ui.panel(im, 196, 3, 317, 28)
    ui.text(im, 311 - ui.text_w('MEISTRAS DRAKONAS'), 8, 'MEISTRAS DRAKONAS', ui.INK)
    ui.hpbar(im, 227, 18, 84, st.get('hp_dr', 1.0), ghost=st.get('ghost'))
    ui.text(im, 202, 17, str(st.get('hp_dr_n', 40)), ui.INK)
    ui.panel(im, 134, 3, 186, 19)
    t = 'ĖJIMAS %d' % st.get('turn', 1)
    ui.text(im, 160 - ui.text_w(t) // 2, 9, t, ui.INK)
    # komandų panelė
    ui.panel(im, 2, 117, 318, 180)
    ui.text(im, 8, 122, 'KĄ BLOKUOTI?', ui.BLUE[2])
    ui.text(im, 312 - ui.text_w('KUR SMŪGIUOTI?'), 122, 'KUR SMŪGIUOTI?', ui.REDB[2])
    blk, stk = st.get('blk'), st.get('stk')
    for i, y in enumerate(ROWS):
        sel = blk == i
        ui.button(im, 7, y, 119, y + 11, BLOCKS[i], 'block' if sel else 'plain', stripe=None if sel else ui.BLUE[1])
    for i, y in enumerate(ROWS[:3]):
        sel = stk == i
        ui.button(im, 201, y, 313, y + 11, STRIKES[i], 'strike' if sel else 'plain', stripe=None if sel else ui.REDB[1])
        if i < 2:
            ui.text(im, 296, y + 2, '1/2', ui.WHITE if sel else ui.MUTED)
    info = 'x1.5  -15%' if stk == 0 else ''
    if info:
        ui.text(im, 313 - ui.text_w(info) - 2, ROWS[3] + 2, info, ui.INK)
        ui.text(im, 203, ROWS[3] + 2, 'SPYRIS', ui.MUTED)
    for i, y in enumerate(ROWS[:3]):
        ui.text(im, 160 - ui.text_w(ZONES[i]) // 2, y + 2, ZONES[i], ui.INK)
        if blk == i or (blk == 3 and i == 1):
            ui.arrow(im, 124, y + 2, 'right', ui.BLUE[1])
        if stk == i:
            ui.arrow(im, 192, y + 2, 'left', ui.REDB[1])
    ready = blk is not None and stk is not None
    ui.button(im, 125, ROWS[3], 195, ROWS[3] + 11, 'KOVOTI!', 'action' if ready else 'plain', pressed=st.get('pressed', False), align='center')
    # efektai
    for fx in st.get('fx', []):
        kind = fx[0]
        if kind == 'spark':
            ui.spark(im, fx[1], fx[2], fx[3], hx('#FFF2A8'), hx('#FF9A3C'))
        elif kind == 'num':
            ui.text(im, fx[1], fx[2], fx[3], fx[4], outline=NAVY)
        elif kind == 'label':
            ui.text(im, fx[1], fx[2], fx[3], fx[4], outline=NAVY)
        elif kind == 'shield':
            ui.shield_burst(im, fx[1], fx[2])
        elif kind == 'fire':
            # ugnies kamuolys su trumpa uodega (x1 - kamuolio centras)
            x0, y0, x1 = fx[1], fx[2], fx[3]
            yy0 = y0 + int((x1 - x0) * 0.06)
            for k in range(1, 14):
                x = x1 - 3 - k
                yy = yy0 + int(np.sin((x1 - k) / 2.5) * 1.2)
                if x > x0:
                    im[yy, x, :3] = hx('#FFD25A') if k < 6 else hx('#FF7A2A')
                    if k < 9:
                        im[yy - 1 + (k % 2) * 2, x, :3] = hx('#FF9A3C')
            for dy in range(-4, 5):
                for dx in range(-4, 5):
                    d = dx * dx + dy * dy
                    if d <= 16:
                        im[yy0 + dy, x1 + dx, :3] = NAVY if d > 10 else (hx('#FF7A2A') if d > 4 else hx('#FFE27A'))
    return im[..., :3].copy()


HEAD_DR = (DR_X + 16, FEET - 34)
HEAD_ME = (HERO_X + 40, FEET - 36)
base = dict(blk=None, stk=None, hp_me=1.0, hp_dr=1.0, turn=1)
seq = []


def add(d, n=1, ms=120, **kw):
    st = dict(base)
    st.update(d)
    st.update(kw)
    for k in range(n):
        st2 = dict(st)
        st2['hf'] = (len(seq) // 2) % 4
        st2['df'] = ((len(seq) // 2) + 2) % 4
        st2['pb'] = (len(seq) // 3) % 2
        seq.append((st2, ms))


add({}, 4, 160)
add({'blk': 0}, 3, 160)
add({'blk': 0, 'stk': 0}, 4, 160)
sel = {'blk': 0, 'stk': 0}
add(sel, 1, 140, pressed=True)
add(sel, 1, 90, hero_pose='punch', hero_dx=6)
add(sel, 2, 50, hero_pose='punch', hero_dx=8, dr_pose='hit', dr_flash=True, fx=[('spark', HEAD_DR[0], HEAD_DR[1], 7)])
for k in range(5):
    add(sel, 1, 90, hero_pose='punch' if k < 2 else 'idle', hero_dx=max(0, 8 - k * 3), dr_pose='hit', dr_dx=min(4, 2 + k),
        hp_dr=34 / 40, ghost=1.0 - k * 0.03, hp_dr_n=34,
        fx=[('num', HEAD_DR[0] + 4, HEAD_DR[1] - 14 - k * 2, '-6', ui.REDB[0]), ('label', HEAD_DR[0] - 14, HEAD_DR[1] - 28 - k, 'SMŪGIS!', hx('#FFE27A'))])
sel2 = dict(sel, hp_dr=34 / 40, hp_dr_n=34)
add(sel2, 2, 110)
add(sel2, 1, 90, dr_pose='punch', dr_dx=-7)
add(sel2, 1, 60, dr_pose='punch', dr_dx=-9, hero_pose='block', fx=[('shield', HEAD_ME[0] + 4, HEAD_ME[1] - 2)])
for k in range(4):
    add(sel2, 1, 100, dr_pose='punch' if k < 1 else 'idle', dr_dx=-9 + k * 3 if k < 3 else 0, hero_pose='block' if k < 3 else 'idle',
        fx=[('shield', HEAD_ME[0] + 4, HEAD_ME[1] - 2), ('label', HEAD_ME[0] - 26, HEAD_ME[1] - 24 - k, 'BLOKAS!', ui.BLUE[0]), ('num', HEAD_ME[0] - 4, HEAD_ME[1] - 12 - k * 2, '0', ui.WHITE)])
for k in range(6):
    add(sel2, 1, 80, pet_jump=4 if k < 3 else 0, fx=[('fire', 34, FEET - 47, 44 + int((DR_X + 10 - 44) * (k + 1) / 6.0))] +
        ([('label', 4, FEET - 78, 'UGNIS', hx('#FFB65A'))] if k >= 1 else []))
sel3 = dict(sel2, hp_dr=30 / 40, hp_dr_n=30)
for k in range(4):
    add(sel3, 1, 100, dr_pose='hit' if k < 2 else 'idle', dr_flash=(k == 0), ghost=34 / 40 if k < 3 else None,
        fx=[('num', HEAD_DR[0] + 6, HEAD_DR[1] - 6 - k * 2, '-4', hx('#FFB65A')), ('label', 8, FEET - 76, 'UGNIS', hx('#FFB65A'))])
add(dict(base, hp_dr=30 / 40, hp_dr_n=30, turn=2), 5, 170)

frames = [battle(st) for st, ms in seq]
save_gif(frames, os.path.join(OUT, 'kova.gif'), [ms for st, ms in seq], k=3)
# statinis kadras - viskas viename: pasirinkimai, pataikymas, blokas, gyvūnėlio ugnis
still = battle(dict(base, blk=0, stk=0, pressed=True, hero_pose='block', dr_pose='hit', dr_dx=2, hp_dr=30 / 40, hp_dr_n=30, ghost=34 / 40, pet_jump=4,
                    fx=[('spark', HEAD_DR[0], HEAD_DR[1], 7), ('num', HEAD_DR[0] + 6, HEAD_DR[1] - 18, '-6', ui.REDB[0]),
                        ('label', HEAD_DR[0] - 16, HEAD_DR[1] - 30, 'SMŪGIS!', hx('#FFE27A')),
                        ('shield', HEAD_ME[0] + 4, HEAD_ME[1] - 2), ('label', HEAD_ME[0] - 26, HEAD_ME[1] - 26, 'BLOKAS!', ui.BLUE[0]),
                        ('fire', 34, FEET - 47, 178), ('label', 4, FEET - 78, 'UGNIS -4', hx('#FFB65A'))]))
Image.fromarray(still).save(os.path.join(OUT, 'kova.png'))
up(Image.fromarray(still), 4).save(os.path.join(OUT, 'kova_x4.png'))
print('frames', len(frames), 'ok')
