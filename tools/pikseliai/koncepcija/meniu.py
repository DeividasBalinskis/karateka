# Naujo dizaino maketai 320x180: Dojo pradžia ir Kuprinė (viskas pikseliais, vienas mastelis).
import os
import numpy as np
from PIL import Image
from pxkit import up, hx, NAVY
from veikejai import kitsune, pet_turtle, pet_foxcub, pet_dragonling
from aplinka import dojo_kiemas, contact_shadow
from ginklai import render_all
import ui

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
ICONS = os.path.join(HERE, '..', 'icons')


def icon(name):
    return Image.open(os.path.join(ICONS, name + '.png')).convert('RGBA')


def blit(im, spr, x, y):
    a = np.array(spr)
    h, w = a.shape[:2]
    m = a[..., 3] > 0
    reg = im[y:y + h, x:x + w]
    reg[..., :3][m] = a[..., :3][m]


def dojo_home():
    s = dojo_kiemas(G=126)
    contact_shadow(s, 160, 150.5, 17, 2.6)
    contact_shadow(s, 132, 150, 9, 1.8)
    contact_shadow(s, 192, 149, 6, 1.4)
    im = np.dstack([s.im, np.full(s.im.shape[:2], 255, np.uint8)])
    blit(im, pet_turtle(0), 116, 121)
    blit(im, pet_dragonling(0, 0), 120, 82)
    blit(im, kitsune(0, 0), 138, 104)
    blit(im, pet_foxcub(0, 0), 176, 118)
    # žaidėjo kortelė
    ui.panel(im, 4, 4, 112, 52)
    ui.text(im, 9, 9, 'DEIVIDAS B.', ui.INK)
    ui.mini(im, 92, 9, 'star', ui.GOLD, outline=NAVY)
    ui.text(im, 101, 9, '1', ui.INK)
    ui.mini(im, 9, 20, 'belt', hx('#2F2F3C'))
    ui.text(im, 19, 20, '3 DAN', ui.INK)
    ui.rect(im, 9, 30, 89, 35, NAVY)
    ui.rect(im, 10, 31, 88, 34, hx('#3A2E52'))
    ui.rect(im, 10, 31, 14, 34, ui.VIOLET[1])
    ui.text(im, 92, 29, 'XP', ui.MUTED)
    ui.mini(im, 9, 40, 'heart', ui.REDB[1])
    ui.text(im, 18, 40, '35', ui.INK)
    ui.mini(im, 36, 40, 'sword', ui.INK)
    ui.text(im, 45, 40, '2-5', ui.INK)
    ui.mini(im, 70, 40, 'shield', ui.BLUE[1])
    ui.text(im, 79, 40, '0', ui.INK)
    # skyriai
    ui.panel(im, 4, 55, 112, 176)
    rows = [('dovana', 'DOVANOS'), ('teptukas', 'IŠVAIZDA'), ('kuprine', 'KUPRINĖ'), ('letena', 'GYVŪNĖLIAI'), ('aplinka', 'APLINKA'), ('knyga', 'KOLEKCIJA')]
    for i, (ic, label) in enumerate(rows):
        y = 59 + i * 19
        if i == 2:
            ui.rect(im, 7, y, 109, y + 18, ui.CREAM2)
            ui.rect(im, 7, y, 9, y + 18, ui.VIOLET[1])
        blit(im, icon(ic), 12, y)
        ui.text(im, 34, y + 6, label, ui.INK)
    ui.text(im, 92, 59 + 5 * 19 + 6, '54', ui.MUTED)
    # žaisti
    ui.panel(im, 208, 4, 316, 112)
    ui.text(im, 214, 10, 'ŽAISTI', ui.VIOLET[2])
    games = [('kova', 'KARATE KOVA'), ('greita', 'GREITA KOVA'), ('keturi', 'KETURI IŠ EILĖS'), ('ritmas', 'KATA RITMAS')]
    for i, (ic, label) in enumerate(games):
        y = 21 + i * 22
        ui.button(im, 211, y, 313, y + 20, '', 'plain')
        blit(im, icon(ic), 213, y + 1)
        ui.text(im, 233, y + 7, label, ui.INK)
    ui.button(im, 208, 156, 316, 174, 'KVIESK DRAUGĄ', 'action', align='center')
    # centras
    ui.button(im, 114, 6, 206, 21, 'SUSIKURK HEROJŲ!', 'plain', align='center')
    ui.rect(im, 115, 7, 205, 19, hx('#FFD25A'))
    ui.rect(im, 115, 7, 205, 8, hx('#FFF0A8'))
    ui.rect(im, 115, 18, 205, 19, hx('#D99A2B'))
    ui.text(im, 160 - ui.text_w('SUSIKURK HEROJŲ!') // 2, 10, 'SUSIKURK HEROJŲ!', ui.INK)
    return im[..., :3].copy()


def kuprine():
    s = dojo_kiemas(G=126)
    base = (s.im.astype(float) * 0.42 + np.array(hx('#2B2557')) * 0.25).astype(np.uint8)
    im = np.dstack([base, np.full(base.shape[:2], 255, np.uint8)])
    ui.panel(im, 8, 6, 312, 176)
    blit(im, icon('kuprine'), 14, 10)
    ui.text(im, 36, 16, 'KUPRINĖ', ui.INK)
    ui.text(im, 36, 27, '60 DAIKTŲ', ui.MUTED)
    # uždėta
    ui.text(im, 128, 16, 'UŽDĖTA', ui.MUTED)
    W = render_all()
    slots = [('katana_gold', 4), (None, 0), ('kimono', 1)]
    for i, (k, r) in enumerate(slots):
        x = 172 + i * 30
        slot(im, x, 9, r, sel=False, dashed=k is None)
        if k and k in W:
            blit(im, W[k][2], x + 1, 10)
        if k == 'kimono':
            kim = kitsune(0, 0).crop((14, 20, 38, 44))
            blit(im, kim, x + 1, 10)
    ui.button(im, 292, 10, 306, 24, '', 'plain')
    ui.mini(im, 295, 13, 'x', ui.INK)
    # skirtukai
    tabs = ['VISKAS', 'APRANGA', 'GINKLAI', 'APLINKOS', 'SPALVOS', 'KIAUŠINIAI']
    for i, t in enumerate(tabs):
        y = 44 + i * 15
        if i == 0:
            ui.button(im, 14, y, 84, y + 13, t, 'action')
        else:
            ui.text(im, 19, y + 3, t, ui.INK)
    # tinklelis 7 x 3
    keys = list(W.keys())
    for i in range(21):
        cx, cy = 92 + (i % 7) * 30, 42 + (i // 7) * 30
        k = keys[i] if i < len(keys) else None
        slot(im, cx, cy, W[k][1] if k else 0, sel=(i == 0))
        if k:
            blit(im, W[k][2], cx + 1, cy + 1)
    # aprašas
    ui.rect(im, 92, 134, 302, 135, ui.CREAM2)
    slot(im, 92, 140, 4, sel=False)
    blit(im, W['katana_gold'][2], 93, 141)
    ui.text(im, 124, 142, 'AUKSINĖ KATANA', ui.INK)
    ui.text(im, 124, 153, 'LEGENDINIS', ui.GOLD2)
    ui.text(im, 124, 163, 'JĖGA +2  NUOJAUTA +1  ŽALA 7-11', ui.MUTED)
    ui.button(im, 250, 140, 304, 154, 'UŽDĖTI', 'action', align='center')
    return im[..., :3].copy()


RAR = {1: None, 2: hx('#4A8BE6'), 3: hx('#9A6AE6'), 4: hx('#F2B940')}


def slot(im, x, y, rar, sel=False, dashed=False):
    """Daikto langelis 26x26: 24x24 vieta + rėmelis; retumas - kampo trikampis; pasirinktas - violetinis rėmelis."""
    fr = ui.VIOLET[1] if sel else NAVY
    ui.rect(im, x, y, x + 26, y + 26, fr)
    ui.rect(im, x + 1, y + 1, x + 25, y + 25, ui.CREAM if not dashed else ui.CREAM2)
    if sel:
        ui.rect(im, x - 1, y - 1, x + 27, y, ui.VIOLET[1])
        ui.rect(im, x - 1, y + 26, x + 27, y + 27, ui.VIOLET[1])
        ui.rect(im, x - 1, y, x, y + 26, ui.VIOLET[1])
        ui.rect(im, x + 26, y, x + 27, y + 26, ui.VIOLET[1])
    c = RAR.get(rar)
    if c:
        for k in range(5):
            ui.rect(im, x + 25 - (5 - k), y + 1 + k, x + 25, y + 2 + k, c)
    if dashed:
        for k in range(2, 24, 3):
            ui.rect(im, x + k, y + 13, x + k + 1, y + 14, ui.MUTED)


if __name__ == '__main__':
    a = dojo_home()
    Image.fromarray(a).save(os.path.join(OUT, 'dojo_pradzia.png'))
    up(Image.fromarray(a), 4).save(os.path.join(OUT, 'dojo_pradzia_x4.png'))
    b = kuprine()
    Image.fromarray(b).save(os.path.join(OUT, 'kuprine.png'))
    up(Image.fromarray(b), 4).save(os.path.join(OUT, 'kuprine_x4.png'))
    print('ok')
