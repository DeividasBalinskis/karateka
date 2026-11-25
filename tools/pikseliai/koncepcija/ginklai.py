# Ginklai vienu dydžiu 24x24 (įstrižai iš apačios kairės į viršų dešinę), vienas kontūras.
from pxkit import Spr, ramp

WOOD = ramp('#E3AE72', '#B9804B', '#8D5A35', '#663D24')
WOOD2 = ramp('#F0C892', '#D09A5E', '#A6703F', '#7A4E2C')
IRON = ramp('#E4E9F2', '#AEB6C8', '#7C849C', '#565C74')
JADE = ramp('#9CF0B8', '#4DBB7E', '#2F8C5C', '#1F6444')
GOLDR = ramp('#FFF1B0', '#F6C94E', '#D3962A', '#9C651B')
WRAP = ramp('#5F5A80', '#3F3A60', '#2E2A4A', '#211E36')
PINKW = ramp('#FFD6E6', '#F4A3C4', '#D778A2', '#AD5A80')
FLAME = ramp('#FFE07A', '#FF9A3C', '#E0522E', '#A8332A')
ICE = ramp('#E6FBFF', '#A6E6F6', '#6CBCDC', '#4A8CB2')
SHADOW = ramp('#C9B2F2', '#8E6AD6', '#6447AE', '#45307E')
REDF = ramp('#FF9C9C', '#E0505A', '#B03448', '#7E2236')


def staff(rp, a=(3.6, 20.4), b=(20.4, 3.6), r=1.25, caps=None, grip=None):
    s = Spr(24, 24)
    s.add(s.cap([a, b], [r, r]), rp, blur=1, vert=0)
    if grip:
        m = (a[0] + (b[0] - a[0]) * 0.42, a[1] + (b[1] - a[1]) * 0.42)
        n = (a[0] + (b[0] - a[0]) * 0.58, a[1] + (b[1] - a[1]) * 0.58)
        s.add(s.cap([m, n], [r + 0.15, r + 0.15]), grip, sep=None, blur=1, vert=0)
    if caps:
        for p in (a, b):
            s.add(s.ell(p[0], p[1], r + 0.5, r + 0.5), caps, sep=None, blur=1, vert=0)
    return s


def sword(blade, guard, handle, curve=0.0):
    s = Spr(24, 24)
    s.add(s.cap([(4.2, 19.8), (8.0, 16.0)], [1.2, 1.2]), handle, blur=1, vert=0)
    s.add(s.cap([(8.8, 15.2), (15, 8.6 - curve), (20.6, 3.4)], [1.15, 1.05, 0.7]), blade, blur=1, vert=0)
    s.add(s.ell(8.4, 15.6, 2.2, 1.0, ang=-0.785), guard, blur=1, vert=0)
    return s


def nunchaku(rp, cap=None):
    s = Spr(24, 24)
    s.add(s.cap([(3.6, 20.6), (8.6, 12.4)], [1.4, 1.4]) | s.cap([(13.0, 10.2), (20.6, 5.0)], [1.4, 1.4]), rp, blur=1, vert=0)
    s.line(9, 11, 10, 9, IRON[2])
    s.line(11, 9, 12, 10, IRON[2])
    if cap:
        s.add(s.ell(3.8, 20.4, 1.7, 1.7) | s.ell(20.4, 5.2, 1.7, 1.7), cap, sep=None, blur=1, vert=0)
    return s


def sai(rp):
    s = Spr(24, 24)
    s.add(s.cap([(3.8, 20.2), (8.2, 15.8)], [1.3, 1.3]), WRAP, blur=1, vert=0)
    s.add(s.cap([(8.6, 15.4), (20.6, 3.4)], [0.95, 0.7]), rp, blur=1, vert=0)
    s.add(s.cap([(9, 15), (6.2, 10.4), (7.6, 8.2)], [0.8, 0.7, 0.6]) | s.cap([(9, 15), (13.6, 17.8), (15.8, 16.4)], [0.8, 0.7, 0.6]), rp, blur=1, vert=0)
    return s


def kama(blade):
    s = Spr(24, 24)
    s.add(s.cap([(4, 20.6), (13.6, 9.4)], [1.25, 1.25]), WOOD, blur=1, vert=0)
    s.add(s.cap([(13.2, 9.8), (17.4, 5.0), (21.0, 6.2), (20.6, 9.6)], [1.3, 1.5, 1.1, 0.6]), blade, blur=1, vert=0)
    return s


def tonfa():
    s = Spr(24, 24)
    s.add(s.cap([(3.8, 20.2), (19.6, 4.4)], [1.4, 1.4]), WOOD2, blur=1, vert=0)
    s.add(s.cap([(8.2, 15.8), (12.6, 20.2)], [1.4, 1.2]), WOOD, blur=1, vert=0)
    s.add(s.ell(12.6, 20.2, 1.6, 1.6), WOOD, sep=None, blur=1, vert=0)
    return s


def tessen():
    s = Spr(24, 24)
    s.add(s.poly([(4, 20.5), (16.2, 2.6), (21.6, 8.0)]), REDF, blur=1, vert=0)
    s.add(s.ell(4.6, 19.6, 1.9, 1.9), IRON, blur=1, vert=0)
    s.line(5, 19, 17, 4, REDF[3])
    s.line(5, 19, 19, 6, REDF[3])
    s.line(5, 19, 20, 8, REDF[0])
    return s


WEAPONS = {
    'katana_gold': ('Auksinė katana', 4, lambda: sword(GOLDR, IRON, WRAP, curve=0.8)),
    'dragon_staff': ('Drakono lazda', 3, lambda: staff(JADE, caps=GOLDR, grip=GOLDR)),
    'nunchaku_flame': ('Liepsnos nunčakai', 3, lambda: nunchaku(FLAME, cap=GOLDR)),
    'sai_ice': ('Ledo sai', 3, lambda: sai(ICE)),
    'kama_shadow': ('Šešėlių kama', 3, lambda: kama(SHADOW)),
    'sai': ('Sai', 2, lambda: sai(IRON)),
    'bokken': ('Bokenas', 2, lambda: sword(WOOD2, WOOD, WOOD)),
    'jo': ('Jo lazda', 2, lambda: staff(WOOD, a=(5, 19), b=(19, 5), r=1.1)),
    'kama': ('Kama', 2, lambda: kama(IRON)),
    'tessen': ('Tessenas', 2, lambda: tessen()),
    'bo_iron': ('Plieninė bo', 2, lambda: staff(IRON, caps=WRAP)),
    'bokken_sakura': ('Sakuros bokenas', 2, lambda: sword(PINKW, WOOD, WOOD)),
    'bo': ('Bo lazda', 1, lambda: staff(WOOD, grip=WRAP)),
    'tonfa': ('Tonfos', 1, lambda: tonfa()),
    'nunchaku': ('Nunčakai', 1, lambda: nunchaku(WOOD)),
}


def render_all():
    return {k: (v[0], v[1], v[2]().render()) for k, v in WEAPONS.items()}
