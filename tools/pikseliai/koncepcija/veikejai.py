# Koncepcijos veikėjai (48x48, žiūri į dešinę): Kitsunė karatistė ir Meistras Drakonas; gyvūnėliai 32x32.
from pxkit import Spr, ramp, hx, NAVY

FUR = ramp('#F7AE62', '#E8892B', '#C4651F', '#8F4419')
CREAM = ramp('#FFFDF6', '#FFF1DC', '#EBD3B8', '#C9AE93')
GI = ramp('#FFFFFF', '#F2F0F8', '#CBC6E0', '#A39CC2')
BLACKBELT = ramp('#5A5A70', '#30303E', '#24242F', '#191920')
RED = ramp('#F27C6F', '#D8434B', '#A6303F', '#782236')
DARK = ramp('#6E4E5A', '#4B3542', '#3A2832', '#2A1C24')
PINK = ramp('#FFD8C8', '#F5B3A2', '#DE9188', '#B9706E')
E_DARK = NAVY
WHITE = (255, 255, 255)

SCALE = ramp('#FFFFFF', '#E7E0F4', '#B9ABD8', '#8C7BB6')
GOLD = ramp('#FFE597', '#F4BE45', '#D48E24', '#9D5F19')
BELLY = ramp('#FFF8E6', '#FCE8C2', '#E9CC9A', '#C8A574')
HORN = ramp('#FFF9EA', '#F2E3C3', '#D8C097', '#B09770')
TEAL = hx('#25B5A5')


def kitsune(b=0, t=0, pose='idle', belt=BLACKBELT, fur=FUR):
    s = Spr(48, 48)
    sw = [0, 1, 1, 0][t % 4]          # uodegos siūbavimas
    rs = [0, 1, 2, 1][t % 4]          # raiščio galų plazdėjimas
    lean = {'idle': 0, 'punch': 1, 'block': 0, 'hit': -2}[pose]
    hb = b
    # uodega (J forma, baltas galiukas)
    tip = (8.2 + sw, 14.8 + b)
    tail = s.cap([(18 + lean, 33.5), (11.5, 33.2), (6.8, 28), (5.8 + sw * 0.5, 21.5 + b * 0.5), tip], [2.6, 4.2, 4.8, 4.3, 2.7])
    tipm = tail & s.ell(tip[0] - 0.4, tip[1] + 0.6, 4.6, 4.4)
    s.add(tail & ~tipm, fur, blur=3)
    s.add(tipm, CREAM, sep=None, blur=2)
    # raiščio galai (už galvos)
    hx0 = 15.5 + lean
    rib = s.cap([(hx0, 9.2 + hb), (11.5, 10.6 + hb + rs * 0.3), (6.5 - rs * 0.5, 13.2 + hb + rs * 0.6)], [1.1, 0.95, 0.8])
    s.add(rib, RED, blur=1, vert=0)
    # galinė koja
    s.add(s.cap([(20, 34), (16.5, 39), (14, 43)], [3.6, 3.4, 3.0]), GI, blur=2)
    s.add(s.ell(13.4, 44.7, 3.7, 1.9), DARK, blur=1)
    # priekinė koja
    s.add(s.cap([(26.5, 34), (30.5, 38.5), (32, 43)], [3.8, 3.4, 3.0]), GI, blur=2)
    s.add(s.ell(33.6, 44.7, 3.9, 1.9), DARK, blur=1)
    # liemuo (kimono)
    tx = 23.5 + lean
    torso = s.ell(tx, 28.8 + b * 0.5, 7.4, 7.8 - b * 0.5) | s.poly([(16.5 + lean, 31), (30.5 + lean, 31), (31.5 + lean, 36.6), (15.5 + lean, 36.6)])
    s.add(torso, GI, blur=3)
    # kaklas / kimono apykaklė (kreminis V)
    s.add(s.poly([(tx - 1.6, 21.2 + b), (tx + 3.2, 21.2 + b), (tx + 1.2, 25.6 + b)]) & torso, CREAM, sep=None, blur=1, vert=0)
    # diržas
    belt_m = torso & s.rect(10, 31.5, 40, 33.5)
    s.add(belt_m, belt, sep=None, blur=1, vert=0)
    kx = 28.6 + lean
    s.add(s.ell(kx, 32.5, 1.9, 1.6) | s.cap([(kx - 0.6, 33.6), (kx - 1.8, 38.2)], [1.0, 0.9]) | s.cap([(kx + 0.8, 33.6), (kx + 2.4, 37.6)], [1.0, 0.9]), belt, blur=1, vert=0)
    # galinė ranka (kumštis prie krūtinės)
    if pose in ('idle', 'hit'):
        s.add(s.cap([(tx + 3, 26 + b), (tx + 6.2, 27.6 + b)], [2.2, 2.0]), GI, blur=1)
        s.add(s.ell(tx + 7.6, 27.8 + b, 2.3, 2.1), fur, blur=1, vert=0)
    elif pose == 'punch':
        s.add(s.cap([(tx + 1, 27 + b), (tx - 2, 29 + b)], [2.2, 2.0]), GI, blur=1)   # atitraukta (hikite)
    # ausys (už galvos)
    ex = lean
    far = s.poly([(15.6 + ex, 9.6 + hb), (17.6 + ex, 1.3 + hb), (23.2 + ex, 5.4 + hb)])
    near = s.poly([(24.6 + ex, 5.0 + hb), (30.7 + ex, 1.1 + hb), (32.7 + ex, 9.0 + hb)])
    for e in (far, near):
        s.add(e, fur, blur=1, vert=0)
    s.add((far | near) & (s.Y < 4.2 + hb), DARK, sep=None, blur=1, vert=0)
    s.add(s.poly([(17.4 + ex, 7.6 + hb), (18.0 + ex, 4.2 + hb), (21.4 + ex, 6.0 + hb)]) |
          s.poly([(26.6 + ex, 5.6 + hb), (29.8 + ex, 3.9 + hb), (30.9 + ex, 8.0 + hb)]), PINK, sep=None, blur=1, vert=0)
    # galva
    head = s.ell(24.5 + ex, 12.9 + hb, 9.8, 8.6) | s.ell(33.4 + ex, 16.1 + hb, 5.3, 3.5)
    s.add(head, fur, blur=3)
    s.add(head & s.ell(31.6 + ex, 17.6 + hb, 7.8, 4.5), CREAM, sep=None, blur=2, vert=0.2)
    # raištis
    band = head & (s.Y > 6.3 + hb - (s.X - 24) * 0.09) & (s.Y < 8.8 + hb - (s.X - 24) * 0.09)
    s.add(band, RED, sep=None, blur=1, vert=0)
    s.add(s.ell(15.6 + ex, 9.2 + hb, 1.7, 1.6), RED, blur=1, vert=0)
    # priekinė ranka
    if pose in ('idle', 'hit'):
        arm = s.cap([(tx - 1, 24.2 + b), (tx + 5, 25.4 + b), (tx + 10.8, 22.8 + b)], [2.9, 2.6, 2.4])
        fist = (tx + 13.4, 22.4 + b)
    elif pose == 'punch':
        arm = s.cap([(tx - 0.5, 23.8 + b), (tx + 7, 23.2 + b), (tx + 14.5, 22.6 + b)], [2.9, 2.6, 2.4])
        fist = (tx + 17.3, 22.4 + b)
    else:  # block - age uke
        arm = s.cap([(tx - 1, 24.2 + b), (tx + 6.5, 19.6 + b), (tx + 7.5, 10.5 + b)], [2.9, 2.6, 2.4])
        fist = (tx + 7.6, 7.4 + b)
    s.add(arm, GI, blur=2)
    s.add(s.ell(fist[0], fist[1], 2.7, 2.5), fur, blur=1, vert=0)
    # kimono atlapas
    s.line(tx + 1.2, 25.8 + b, tx + 4.2, 31, GI[3])
    img_stamps(s, ex, hb, pose)
    return s.render()


def dragon(b=0, t=0, pose='idle'):
    """Meistras Drakonas: šviesūs žvynai, auksiniai karčiai ir diržas, balti antakiai ir barzdelė."""
    s = Spr(48, 48)
    sw = [0, 1, 1, 0][t % 4]
    ws = [0, 1, 2, 1][t % 4]
    lean = {'idle': 0, 'punch': 1, 'block': 0, 'hit': -2}[pose]
    hb = b
    # uodega
    tail = s.cap([(18 + lean, 34), (9.5, 35.5), (4.6, 30.5), (4.6 + sw * 0.5, 23.5), (8.5 + sw, 19 + b * 0.5)], [3.8, 3.4, 2.8, 2.1, 1.3])
    s.add(tail, SCALE, blur=2)
    s.add(s.ell(9.4 + sw, 16.8 + b * 0.5, 2.4, 2.6) | s.ell(11 + sw, 18.4 + b * 0.5, 1.8, 1.6), GOLD, blur=1, vert=0)
    # karčiai ir ragai (už galvos)
    h0 = 23.6 + lean
    mane = s.ell(h0 - 6.2, 13.4 + hb, 4.2, 6.6) | \
        s.poly([(h0 - 8, 7 + hb), (h0 - 13.6, 4.4 + hb), (h0 - 9.4, 10.4 + hb)]) | \
        s.poly([(h0 - 9, 11.6 + hb), (h0 - 15.2, 12.6 + hb), (h0 - 9.4, 15.8 + hb)]) | \
        s.poly([(h0 - 8.6, 16.4 + hb), (h0 - 13.8, 20.8 + hb), (h0 - 6.8, 19.8 + hb)]) | \
        s.poly([(h0 - 5, 19.4 + hb), (h0 - 7.6, 24.8 + hb), (h0 - 1.6, 21.6 + hb)])
    s.add(mane, GOLD, blur=2)
    s.add(s.cap([(h0 - 2.6, 6.6 + hb), (h0 - 5.6, 2.8 + hb), (h0 - 9.2, 2.0 + hb)], [1.5, 1.1, 0.6]), HORN, blur=1, vert=0)
    s.add(s.cap([(h0 + 1.4, 6.0 + hb), (h0 - 0.6, 1.9 + hb), (h0 - 4.2, 0.9 + hb)], [1.7, 1.2, 0.6]), HORN, blur=1, vert=0)
    mx = lean
    # kojos
    s.add(s.cap([(20, 33), (16.5, 38.5), (15, 43)], [4.0, 3.4, 3.0]), SCALE, blur=2)
    s.add(s.ell(14.4, 44.8, 3.9, 1.8), SCALE, blur=1)
    s.add(s.cap([(27, 33), (31, 38), (32.5, 43)], [4.2, 3.5, 3.0]), SCALE, blur=2)
    s.add(s.ell(34.2, 44.8, 4.0, 1.8), SCALE, blur=1)
    # liemuo + pilvo plokštelės
    tx = 24 + lean
    torso = s.ell(tx, 28.6 + b * 0.5, 7.6, 8.0 - b * 0.5)
    s.add(torso, SCALE, blur=3)
    belly = torso & s.ell(tx + 3.2, 29 + b * 0.5, 4.2, 7.4)
    s.add(belly, BELLY, sep=None, blur=1, vert=0.2)
    for yy in (24, 27, 30, 35):
        s.line(tx + 0.6, yy + b * (yy < 31), tx + 5.6, yy + b * (yy < 31), BELLY[3])
    # auksinis diržas
    s.add(torso & s.rect(10, 31.5, 40, 33.5), GOLD, sep=None, blur=1, vert=0)
    kx = 29.2 + lean
    s.add(s.ell(kx, 32.5, 1.9, 1.6) | s.cap([(kx - 0.6, 33.6), (kx - 1.8, 38.2)], [1.0, 0.9]) | s.cap([(kx + 0.8, 33.6), (kx + 2.4, 37.6)], [1.0, 0.9]), GOLD, blur=1, vert=0)
    # galinė ranka
    if pose in ('idle', 'hit'):
        s.add(s.cap([(tx + 3, 25.6 + b), (tx + 6.4, 27.4 + b)], [2.3, 2.1]), SCALE, blur=1)
        s.add(s.ell(tx + 7.8, 27.6 + b, 2.2, 2.0), HORN, blur=1, vert=0)
    # galva: apatinis žandas (už), kaukolė + snukis (priekyje, burnos linija), barzdelė
    hx_ = 23.6 + lean
    s.add(s.ell(hx_ + 8.2, 18.7 + hb, 5.9, 2.3), BELLY, blur=1, vert=0)
    head = s.ell(hx_, 12.4 + hb, 7.8, 7.2) | s.ell(hx_ + 9.8, 14.4 + hb, 6.6, 3.3)
    s.add(head, SCALE, blur=3)
    s.add(s.ell(hx_ + 4.4, 22.2 + hb, 2.0, 2.6), CREAM, blur=1, vert=0)
    # ūsai
    s.add(s.cap([(hx_ + 12.6, 16.6 + hb), (hx_ + 7, 20 + hb + ws * 0.3), (hx_ + 0.6, 24 + hb + ws * 0.6)], [0.75, 0.7, 0.6]), GOLD, blur=1, vert=0)
    # priekinė ranka
    if pose in ('idle', 'hit'):
        arm = s.cap([(tx - 1, 24 + b), (tx + 5, 25.2 + b), (tx + 10.6, 22.6 + b)], [3.0, 2.7, 2.4])
        fist = (tx + 13.2, 22.2 + b)
    elif pose == 'punch':
        arm = s.cap([(tx - 0.5, 23.8 + b), (tx + 7, 23.2 + b), (tx + 14.5, 22.6 + b)], [3.0, 2.7, 2.4])
        fist = (tx + 17.2, 22.4 + b)
    else:
        arm = s.cap([(tx - 1, 24 + b), (tx + 6.5, 19.6 + b), (tx + 7.5, 10.5 + b)], [3.0, 2.7, 2.4])
        fist = (tx + 7.6, 7.4 + b)
    s.add(arm, SCALE, blur=2)
    s.add(s.ell(fist[0], fist[1], 2.6, 2.4), HORN, blur=1, vert=0)
    pal = {'E': NAVY, 'e': (255, 255, 255), 'T': TEAL, 'W': (255, 255, 255), 'B': hx('#FFF6E6'), 'N': NAVY, 'G': GOLD[2], 'g': GOLD[1]}
    ex = round(lean)
    if pose == 'hit':
        s.stamp(26 + ex, 10 + hb, ['E..', '.EE', 'E..'], pal)
    else:
        s.stamp(26 + ex, 10 + hb, ['EEE', 'TNT', '.TT'], pal)
    s.stamp(24 + ex, 8 + hb, ['BBBW'], pal)                           # balti antakiai
    s.stamp(38 + ex, 12 + hb, ['N'], pal)                             # šnervė
    s.stamp(36 + ex, 17 + hb, ['W'], pal)                             # iltis
    return s.render()


GHOST = ramp('#FFFFFF', '#E3EBFF', '#B3C4F0', '#8395CE')
HOOD = ramp('#3A3F72', '#262A52', '#1D2042', '#151832')
BLUEB = ramp('#8CC2FF', '#4A8BE6', '#2F62B8', '#20448A')


def dvasia(t=0, pose='idle'):
    """Dvasia: plūduriuojantis gobtuvas su mėlynu diržu; uodegėlės banguoja."""
    s = Spr(48, 48)
    f = [0, -1, -2, -1][t % 4]
    w = [0, 1, 2, 1][t % 4]
    tail = s.cap([(21, 35 + f), (15 - w * 0.5, 40 + f), (9 - w, 41.5 + f + w * 0.4)], [5.0, 3.0, 1.3]) | \
        s.cap([(26, 36 + f), (24 + w * 0.3, 42 + f), (20 + w, 45 + f)], [4.2, 2.4, 1.0])
    s.add(tail, GHOST, blur=2)
    body = s.ell(24, 26 + f, 10.4, 11) | s.ell(23.6, 14.4 + f, 9.2, 9.2)
    s.add(body, GHOST, blur=3)
    s.add(s.ell(27.4, 15.4 + f, 5.2, 5.6), HOOD, blur=1, vert=0)
    s.add(body & s.rect(10, 26.5 + f, 40, 28.5 + f), BLUEB, sep=None, blur=1, vert=0)
    s.add(s.ell(31.4, 27.6 + f, 1.8, 1.5) | s.cap([(30.8, 28.6 + f), (29.6, 33 + f)], [0.9, 0.8]) | s.cap([(32.2, 28.6 + f), (33.8, 32.6 + f)], [0.9, 0.8]), BLUEB, blur=1, vert=0)
    if pose == 'punch':
        arm = s.cap([(25, 21.5 + f), (34, 21 + f), (41, 20.5 + f)], [2.7, 2.4, 2.1])
        hand = (43.2, 20.4 + f)
    else:
        arm = s.cap([(25, 21.5 + f), (32, 23 + f), (36, 19.6 + f)], [2.7, 2.4, 2.1])
        hand = (38, 18.8 + f)
    s.add(arm, GHOST, blur=1)
    s.add(s.ell(hand[0], hand[1], 2.2, 2.0), GHOST, blur=1, vert=0)
    pal = {'C': hx('#8FF3FF'), 'c': hx('#41C8E8'), 'S': hx('#BFD8FF')}
    s.stamp(26, 14 + f, ['C..C', 'c..c'], pal)
    sp = [(8, 12), (39, 9), (42, 31), (6, 27)]
    for i, (x, y) in enumerate(sp):
        if (i + t) % 2 == 0:
            s.stamp(x, y + ((t + i) % 3) - 1, ['.S.', 'SSS', '.S.'] if i % 2 else ['S'], pal)
    return s.render()


TURTLE = ramp('#A6E39A', '#62B96C', '#3F8E57', '#2C6A46')
TSKIN = ramp('#E2F6BC', '#BFE493', '#94C26C', '#6E9C52')
RIM = ramp('#FFF3B8', '#F2D684', '#D3B060', '#A98A45')
GREEN = ramp('#A8F0C2', '#6CC895', '#479C74', '#317356')
WING = ramp('#FFE2A0', '#F7B866', '#DB8C45', '#A9643A')


def pet_turtle(b=0):
    s = Spr(32, 32)
    s.add(s.ell(9.5, 27, 2.4, 2.2) | s.ell(21.5, 27, 2.4, 2.2), TSKIN, blur=1, vert=0)
    s.add(s.poly([(5.5, 24), (2.2, 25.2), (5.5, 26.2)]), TSKIN, blur=1, vert=0)
    shell = s.ell(15, 21 - b, 10, 7.6) & (s.Y < 25 - b)
    s.add(shell, TURTLE, blur=2)
    s.add(s.ell(15, 21 - b, 10.6, 7.8) & (s.Y >= 23 - b) & (s.Y < 25.6 - b), RIM, sep=None, blur=1, vert=0)
    s.add(s.ell(26, 18.6 - b, 4.0, 3.6) | s.cap([(22, 21 - b), (25, 19.5 - b)], [2.2, 2.4]), TSKIN, blur=1)
    pal = {'E': NAVY, 'e': (255, 255, 255), 'S': TURTLE[3], 'P': hx('#F49A9A')}
    s.stamp(9, 15 - b, ['..SSS..', '.S...S.', 'S.....S'], pal)
    s.stamp(14, 19 - b, ['S...S', '.SSS.'], pal)
    s.stamp(26, 17 - b, ['Ee', 'EE'], pal)
    s.stamp(25, 20 - b, ['P'], pal)
    return s.render()


def pet_foxcub(b=0, t=0):
    s = Spr(32, 32)
    sw = [0, 1][t % 2]
    tail = s.cap([(12, 27), (6, 25.5), (4 + sw * 0.5, 19), (6.5 + sw, 14.5)], [2.6, 3.4, 3.2, 2.0])
    tipm = tail & s.ell(6.4 + sw, 14.8, 3.2, 3.0)
    s.add(tail & ~tipm, FUR, blur=2)
    s.add(tipm, CREAM, sep=None, blur=1)
    s.add(s.ell(14.5, 23 - b * 0.5, 6.4, 5.8) | s.rect(9, 23, 20, 29), FUR, blur=2)
    s.add(s.ell(17, 24 - b * 0.5, 3.0, 4.2), CREAM, sep=None, blur=1, vert=0)
    s.add(s.ell(15.5, 28.2, 1.8, 1.4) | s.ell(19.5, 28.2, 1.8, 1.4), DARK, blur=1, vert=0)
    s.add(s.poly([(12.5, 11 - b), (13.5, 4.5 - b), (17.5, 9 - b)]) | s.poly([(18.5, 9 - b), (22.5, 4.2 - b), (23.5, 11 - b)]), FUR, blur=1, vert=0)
    head = s.ell(18, 13.6 - b, 6.4, 5.4) | s.ell(23.4, 15.4 - b, 3.2, 2.2)
    s.add(head, FUR, blur=2)
    s.add(head & s.ell(21.5, 16.4 - b, 5, 2.8), CREAM, sep=None, blur=1, vert=0)
    pal = {'E': NAVY, 'e': (255, 255, 255), 'N': NAVY, 'P': hx('#F49A9A'), 'D': DARK[1]}
    s.stamp(13, 5 - b, ['D....', '.....'], pal)
    s.stamp(21, 5 - b, ['D'], pal)
    s.stamp(19, 12 - b, ['Ee', 'EE'], pal)
    s.stamp(15, 12 - b, ['E', 'E'], pal)
    s.stamp(26, 14 - b, ['N'], pal)
    s.stamp(17, 15 - b, ['P'], pal)
    return s.render()


def pet_dragonling(b=0, t=0):
    s = Spr(32, 32)
    fl = [0, 1][t % 2]
    s.add(s.cap([(11, 26), (5.5, 25), (3.5, 21)], [2.4, 1.8, 1.0]) | s.poly([(1.8, 21.5), (3.6, 17.6), (5.4, 21.2)]), GREEN, blur=1, vert=0)
    s.add(s.poly([(11, 19 - b), (4.5, 12.5 - b - fl), (6.5, 18.5 - b), (3.6, 17.5 - b + fl), (9.5, 22.5 - b)]), WING, blur=1, vert=0)
    s.add(s.ell(14.5, 23 - b * 0.5, 6.2, 5.6) | s.rect(9, 23, 20, 29), GREEN, blur=2)
    s.add(s.ell(17.2, 24 - b * 0.5, 2.8, 4.0), BELLY, sep=None, blur=1, vert=0)
    s.add(s.ell(15.5, 28.2, 1.9, 1.4) | s.ell(19.6, 28.2, 1.9, 1.4), GREEN, blur=1, vert=0)
    s.add(s.cap([(15.5, 9.6 - b), (14, 5.6 - b)], [1.2, 0.6]) | s.cap([(19.5, 9.2 - b), (19.8, 5.0 - b)], [1.2, 0.6]), HORN, blur=1, vert=0)
    head = s.ell(18, 13.6 - b, 6.0, 5.2) | s.ell(23, 15.2 - b, 3.4, 2.6)
    s.add(head, GREEN, blur=2)
    s.add(head & s.ell(22, 17 - b, 4.6, 1.8), BELLY, sep=None, blur=1, vert=0)
    pal = {'E': NAVY, 'e': (255, 255, 255), 'N': NAVY, 'P': hx('#F49A9A')}
    s.stamp(19, 11 - b, ['Ee', 'EE', 'EE'], pal)
    s.stamp(15, 12 - b, ['E', 'E'], pal)
    s.stamp(25, 14 - b, ['N'], pal)
    s.stamp(17, 15 - b, ['P'], pal)
    return s.render()


def img_stamps(s, ex, hb, pose):
    pal = {'E': E_DARK, 'e': WHITE, 'N': NAVY, 'P': hx('#F49A9A')}
    if pose == 'hit':
        s.stamp(29 + ex, 10 + hb, ['E..', '.E.', 'E..'], pal)      # užsimerkęs „>“
        s.stamp(22 + ex, 11 + hb, ['E.', '.E'], pal)
    else:
        s.stamp(29 + ex, 10 + hb, ['EE.', 'EeE', '.EE'], pal)
        s.stamp(22 + ex, 10 + hb, ['eE', 'EE', '.E'], pal)
    s.stamp(37 + ex, 14 + hb, ['NN', 'NN'], pal)
    s.stamp(33 + ex, 18 + hb, ['N..', '.NN'], pal)
    s.stamp(26 + ex, 15 + hb, ['PP'], pal)


if __name__ == '__main__':
    import sys
    from pxkit import sheet, up
    from PIL import Image
    out = sys.argv[1]
    fr = [kitsune(b, t) for b, t in ((0, 0), (1, 2))] + [kitsune(0, 0, 'punch'), kitsune(0, 0, 'block')] + \
         [dragon(b, t) for b, t in ((0, 0), (1, 2))] + [dragon(0, 0, 'punch'), dragon(0, 0, 'hit')]
    row1 = sheet(fr, gap=2)
    pets = [pet_turtle(0), pet_turtle(1), pet_foxcub(0, 0), pet_foxcub(1, 1), pet_dragonling(0, 0), pet_dragonling(1, 1)]
    row2 = sheet(pets, gap=4)
    W = max(row1.width, row2.width)
    bg = Image.new('RGBA', (W, 48 + 4 + 32), (232, 226, 244, 255))
    bg.alpha_composite(row1, (0, 0))
    bg.alpha_composite(row2, (0, 52))
    up(bg, 6).save(out)
    print('ok', bg.size)
