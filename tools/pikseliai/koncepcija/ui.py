# Pikselinis šriftas 5x7 (didžiosios + lietuviškos raidės) ir UI detalės (panelės, mygtukai, gyvybių juostos).
import numpy as np
from pxkit import hx, NAVY

G = {
    'A': ['.###.', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
    'B': ['####.', '#...#', '#...#', '####.', '#...#', '#...#', '####.'],
    'C': ['.####', '#....', '#....', '#....', '#....', '#....', '.####'],
    'D': ['####.', '#...#', '#...#', '#...#', '#...#', '#...#', '####.'],
    'E': ['#####', '#....', '#....', '####.', '#....', '#....', '#####'],
    'F': ['#####', '#....', '#....', '####.', '#....', '#....', '#....'],
    'G': ['.####', '#....', '#....', '#..##', '#...#', '#...#', '.####'],
    'H': ['#...#', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
    'I': ['###', '.#.', '.#.', '.#.', '.#.', '.#.', '###'],
    'J': ['..###', '...#.', '...#.', '...#.', '...#.', '#..#.', '.##..'],
    'K': ['#...#', '#..#.', '#.#..', '##...', '#.#..', '#..#.', '#...#'],
    'L': ['#....', '#....', '#....', '#....', '#....', '#....', '#####'],
    'M': ['#...#', '##.##', '#.#.#', '#.#.#', '#...#', '#...#', '#...#'],
    'N': ['#...#', '##..#', '#.#.#', '#..##', '#...#', '#...#', '#...#'],
    'O': ['.###.', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
    'P': ['####.', '#...#', '#...#', '####.', '#....', '#....', '#....'],
    'R': ['####.', '#...#', '#...#', '####.', '#.#..', '#..#.', '#...#'],
    'S': ['.####', '#....', '#....', '.###.', '....#', '....#', '####.'],
    'T': ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '..#..'],
    'U': ['#...#', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
    'V': ['#...#', '#...#', '#...#', '#...#', '.#.#.', '.#.#.', '..#..'],
    'W': ['#...#', '#...#', '#...#', '#.#.#', '#.#.#', '##.##', '#...#'],
    'X': ['#...#', '#...#', '.#.#.', '..#..', '.#.#.', '#...#', '#...#'],
    'Y': ['#...#', '#...#', '.#.#.', '..#..', '..#..', '..#..', '..#..'],
    'Z': ['#####', '....#', '...#.', '..#..', '.#...', '#....', '#####'],
    '0': ['.###.', '#...#', '#..##', '#.#.#', '##..#', '#...#', '.###.'],
    '1': ['.#.', '##.', '.#.', '.#.', '.#.', '.#.', '###'],
    '2': ['.###.', '#...#', '....#', '...#.', '..#..', '.#...', '#####'],
    '3': ['####.', '....#', '....#', '.###.', '....#', '....#', '####.'],
    '4': ['#..#.', '#..#.', '#..#.', '#####', '...#.', '...#.', '...#.'],
    '5': ['#####', '#....', '#....', '####.', '....#', '....#', '####.'],
    '6': ['.###.', '#....', '#....', '####.', '#...#', '#...#', '.###.'],
    '7': ['#####', '....#', '...#.', '..#..', '..#..', '..#..', '..#..'],
    '8': ['.###.', '#...#', '#...#', '.###.', '#...#', '#...#', '.###.'],
    '9': ['.###.', '#...#', '#...#', '.####', '....#', '....#', '.###.'],
    '!': ['#', '#', '#', '#', '#', '.', '#'],
    '?': ['.###.', '#...#', '....#', '...#.', '..#..', '.....', '..#..'],
    '.': ['.', '.', '.', '.', '.', '.', '#'],
    ',': ['.', '.', '.', '.', '.', '#', '#'],
    ':': ['.', '.', '#', '.', '.', '#', '.'],
    '-': ['...', '...', '...', '###', '...', '...', '...'],
    '+': ['.....', '..#..', '..#..', '#####', '..#..', '..#..', '.....'],
    '/': ['..#', '..#', '.#.', '.#.', '.#.', '#..', '#..'],
    '%': ['##..#', '##.#.', '...#.', '..#..', '.#...', '.#.##', '#..##'],
    'x': ['.....', '.....', '#...#', '.#.#.', '..#..', '.#.#.', '#...#'],
    '(': ['.#', '#.', '#.', '#.', '#.', '#.', '.#'],
    ')': ['#.', '.#', '.#', '.#', '.#', '.#', '#.'],
    ' ': ['...', '...', '...', '...', '...', '...', '...'],
}
# lietuviškos raidės: bazė + ženklas (virš: 2 eilutės, apačioje: 2 eilutės)
ACC = {'Ą': ('A', 'og'), 'Č': ('C', 'car'), 'Ę': ('E', 'og'), 'Ė': ('E', 'dot'), 'Į': ('I', 'og'), 'Š': ('S', 'car'),
       'Ų': ('U', 'og'), 'Ū': ('U', 'mac'), 'Ž': ('Z', 'car')}


def glyph(ch):
    """Grąžina (plotis, taškų sąrašas [(x, y)]) - y nuo -2 (ženklai) iki 8."""
    acc = None
    if ch in ACC:
        ch, acc = ACC[ch]
    rows = G.get(ch, G['?'])
    w = len(rows[0])
    pts = [(x, y) for y, r in enumerate(rows) for x, c in enumerate(r) if c == '#']
    m = w // 2
    if acc == 'car':
        pts += [(m - 1, -2), (m + 1, -2), (m, -1)]
    elif acc == 'dot':
        pts += [(m, -2)]
    elif acc == 'mac':
        pts += [(m - 1, -2), (m, -2), (m + 1, -2)]
    elif acc == 'og':
        pts += [(w - 2, 7), (w - 1, 8)] if w > 3 else [(1, 7), (2, 8)]
    return w, pts


def text_w(t):
    t = t.upper().replace('×', 'x')
    return sum(glyph(c)[0] + 1 for c in t) - 1 if t else 0


def text(im, x, y, t, col, shadow=None, outline=None):
    """im: numpy HxWx3/4 masyvas. y - didžiosios raidės viršus."""
    t = t.upper().replace('×', 'x').replace('−', '-')
    Hh, Ww = im.shape[:2]

    def put(px, py, c):
        if 0 <= px < Ww and 0 <= py < Hh:
            im[py, px, :3] = c
            if im.shape[2] == 4:
                im[py, px, 3] = 255
    cx = x
    allpts = []
    for ch in t:
        w, pts = glyph(ch)
        allpts += [(cx + px, y + py) for px, py in pts]
        cx += w + 1
    if outline is not None:
        s = set(allpts)
        for px, py in allpts:
            for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1), (1, 1), (-1, -1), (1, -1), (-1, 1)):
                if (px + dx, py + dy) not in s:
                    put(px + dx, py + dy, outline)
    if shadow is not None:
        for px, py in allpts:
            put(px, py + 1, shadow)
    for px, py in allpts:
        put(px, py, col)
    return cx - x - 1


def rect(im, x0, y0, x1, y1, c):
    im[max(0, y0):max(0, y1), max(0, x0):max(0, x1), :3] = c
    if im.shape[2] == 4:
        im[max(0, y0):max(0, y1), max(0, x0):max(0, x1), 3] = 255


CREAM = hx('#FBF4E6')
CREAM2 = hx('#EFE2C8')
GOLD = hx('#E2B04A')
GOLD2 = hx('#B9852F')
INDIGO = hx('#3F3796')
VIOLET = ramp3 = (hx('#A68BF0'), hx('#7C5CD6'), hx('#563CAE'))
BLUE = (hx('#8CC2FF'), hx('#4A8BE6'), hx('#2F62B8'))
REDB = (hx('#FF9A8C'), hx('#E2504F'), hx('#AE3140'))
INK = hx('#2C2E56')
MUTED = hx('#8A80A8')
WHITE = (255, 255, 255)
GREEN = (hx('#9AF0A6'), hx('#4CC870'), hx('#2E9654'))


def panel(im, x0, y0, x1, y1, fill=CREAM):
    """Šviesi panelė: navy kraštas su nukirstais kampais, indigo rėmelis, auksinė vidinė linija."""
    rect(im, x0 + 1, y0, x1 - 1, y0 + 1, NAVY)
    rect(im, x0 + 1, y1 - 1, x1 - 1, y1, NAVY)
    rect(im, x0, y0 + 1, x0 + 1, y1 - 1, NAVY)
    rect(im, x1 - 1, y0 + 1, x1, y1 - 1, NAVY)
    rect(im, x0 + 1, y0 + 1, x1 - 1, y1 - 1, INDIGO)
    rect(im, x0 + 2, y0 + 2, x1 - 2, y1 - 2, GOLD)
    rect(im, x0 + 3, y0 + 3, x1 - 3, y1 - 3, fill)
    rect(im, x0 + 3, y1 - 4, x1 - 3, y1 - 3, CREAM2)


def arrow(im, x, y, d, c):
    """Mažas trikampis 4x7: d = 'right' arba 'left'."""
    rows = ['#...', '##..', '###.', '####', '###.', '##..', '#...']
    for j, r in enumerate(rows):
        for i, ch in enumerate(r):
            if ch == '#':
                xx = x + (i if d == 'right' else 3 - i)
                im[y + j, xx, :3] = c


def button(im, x0, y0, x1, y1, label, kind='plain', pressed=False, icon=None, align='left', stripe=None):
    """kind: plain (kreminis), block (mėlynas), strike (raudonas), action (violetinis)."""
    if pressed:
        y0 += 1
        y1 += 1
    rect(im, x0 + 1, y0, x1 - 1, y0 + 1, NAVY)
    rect(im, x0 + 1, y1 - 1, x1 - 1, y1, NAVY)
    rect(im, x0, y0 + 1, x0 + 1, y1 - 1, NAVY)
    rect(im, x1 - 1, y0 + 1, x1, y1 - 1, NAVY)
    if kind == 'plain':
        hi, base, lo, tc = WHITE, hx('#F6EEDD'), hx('#D9C9A8'), INK
    else:
        hi, base, lo = {'block': BLUE, 'strike': REDB, 'action': VIOLET}[kind]
        tc = WHITE
    rect(im, x0 + 1, y0 + 1, x1 - 1, y1 - 1, base)
    rect(im, x0 + 1, y0 + 1, x1 - 1, y0 + 2, hi)
    if not pressed:
        rect(im, x0 + 1, y1 - 2, x1 - 1, y1 - 1, lo)
    tx = x0 + 4
    if stripe is not None:
        rect(im, x0 + 1, y0 + 1, x0 + 3, y1 - 1, stripe)
        tx = x0 + 6
    if icon is not None:
        icon(im, x0 + 3, y0 + 2)
        tx = x0 + 13
    if align == 'center':
        tx = (x0 + x1 - text_w(label)) // 2
    text(im, tx, y0 + 3 if (y1 - y0) >= 12 else y0 + 2, label, tc, shadow=(lo if kind != 'plain' else None))


def hpbar(im, x0, y0, w, frac, ghost=None, h=5):
    rect(im, x0, y0, x0 + w, y0 + h, NAVY)
    rect(im, x0 + 1, y0 + 1, x0 + w - 1, y0 + h - 1, hx('#3A2E52'))
    fw = int(round((w - 2) * max(0, min(1, frac))))
    if ghost is not None:
        gw = int(round((w - 2) * ghost))
        rect(im, x0 + 1, y0 + 1, x0 + 1 + gw, y0 + h - 1, hx('#FFF2B0'))
    rect(im, x0 + 1, y0 + 1, x0 + 1 + fw, y0 + h - 1, GREEN[1])
    rect(im, x0 + 1, y0 + 1, x0 + 1 + fw, y0 + 2, GREEN[0])


def icon_fist(im, x, y, c=None):
    pts = ['.###.', '#####', '#####', '.####', '..##.']
    for j, r in enumerate(pts):
        for i, ch in enumerate(r):
            if ch == '#':
                im[y + j, x + i, :3] = c or WHITE


def icon_foot(im, x, y, c=None):
    pts = ['.##..', '.##..', '.##..', '.####', '#####']
    for j, r in enumerate(pts):
        for i, ch in enumerate(r):
            if ch == '#':
                im[y + j, x + i, :3] = c or WHITE


def icon_shield(im, x, y, c=None):
    pts = ['#####', '#####', '#####', '.###.', '..#..']
    for j, r in enumerate(pts):
        for i, ch in enumerate(r):
            if ch == '#':
                im[y + j, x + i, :3] = c or WHITE


MINI = {
    'heart': ['.##.##.', '#######', '#######', '.#####.', '..###..', '...#...', '.......'],
    'sword': ['......#', '.....#.', '#...#..', '.#.#...', '..#....', '.#.#...', '#......'],
    'shield': ['#######', '#######', '#######', '.#####.', '.#####.', '..###..', '...#...'],
    'star': ['...#...', '..###..', '#######', '.#####.', '..###..', '.##.##.', '.#...#.'],
    'belt': ['.......', '.......', '#######', '#######', '..#.#..', '.#...#.', '.......'],
    'x': ['#.....#', '.#...#.', '..#.#..', '...#...', '..#.#..', '.#...#.', '#.....#'],
}


def mini(im, x, y, name, c, outline=None):
    rows = MINI[name]
    pts = [(i, j) for j, r in enumerate(rows) for i, ch in enumerate(r) if ch == '#']
    if outline is not None:
        s = set(pts)
        for i, j in pts:
            for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                if (i + dx, j + dy) not in s:
                    im[y + j + dy, x + i + dx, :3] = outline
    for i, j in pts:
        im[y + j, x + i, :3] = c


def spark(im, cx, cy, r, c1, c2):
    """Smūgio žvaigždė: 8 spinduliai."""
    for k in range(-r, r + 1):
        for (dx, dy) in ((k, 0), (0, k)):
            x, y = cx + dx, cy + dy
            if 0 <= x < im.shape[1] and 0 <= y < im.shape[0]:
                im[y, x, :3] = c1 if abs(k) < r - 1 else c2
    for k in range(-(r - 2), r - 1):
        for (dx, dy) in ((k, k), (k, -k)):
            x, y = cx + dx, cy + dy
            if 0 <= x < im.shape[1] and 0 <= y < im.shape[0] and abs(k) > 0:
                im[y, x, :3] = c2 if abs(k) > 1 else c1
    for dx in (-1, 0, 1):
        for dy in (-1, 0, 1):
            im[cy + dy, cx + dx, :3] = WHITE


def shield_burst(im, cx, cy):
    """Bloko ženklas: mėlynas skydas su švytinčiu kraštu."""
    rows = ['..#######..', '.#BBBBBBB#.', '#BBWWBBBBB#', '#BBWBBBBBB#', '#BBBBBBBBB#', '.#BBBBBBB#.', '..#BBBBB#..', '...#BBB#...', '....#B#....', '.....#.....']
    pal = {'#': NAVY, 'B': BLUE[1], 'W': BLUE[0]}
    for j, r in enumerate(rows):
        for i, ch in enumerate(r):
            if ch in pal:
                x, y = cx - 5 + i, cy - 5 + j
                if 0 <= x < im.shape[1] and 0 <= y < im.shape[0]:
                    im[y, x, :3] = pal[ch]
    for (dx, dy) in ((-8, 0), (8, 0), (-6, -6), (6, -6), (0, -8), (-6, 6), (6, 6)):
        x, y = cx + dx, cy + dy
        if 0 <= x < im.shape[1] and 0 <= y < im.shape[0]:
            im[y, x, :3] = BLUE[0]
