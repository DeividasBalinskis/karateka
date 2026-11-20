# Pikseliniai fonai (320×180). Kiekviena scena - apačioje vieta personažams stovėti.
import sys, math, random
from kit import *
OUT = sys.argv[1] if len(sys.argv) > 1 else '.'

def sakura():
    s = Scene(11)
    s.grad([(0, '#2B1E4A'), (.28, '#5A3270'), (.5, '#B04A78'), (.66, '#F07C6A'), (.78, '#FFB27A'), (1, '#FFD9A0')], 0, 120)
    s.disc(214, 86, 15, hx('#FFE3A8')); s.disc(214, 86, 12, hx('#FFF2CF')); s.glow(214, 86, 15, 36, '#FFC98A', .7)
    s.stars(30, 40, ['#E8D8FF', '#FFFFFF'], seed=2)
    s.ridge(98, 22, .55, '#8A4A7A', seed=4, top='#C2708A')
    s.ridge(108, 14, .6, '#6A3462', seed=7, top='#9A4E7A')
    s.mist(96, 118, '#F2A08A', .45)
    s.pagoda(70, 118, 62, wall='#3E2440', roof='#24142A', trim='#5A2A44', floors=4, light='#FFC46A')
    s.ridge(122, 6, .7, '#3A1E3A', seed=12)
    s.reflect(124, 148, dark=.62, tint='#2A1838', ripple=1.2); s.streaks(124, 148, '#FFC98A', 30, 4)
    s.rect(0, 146, W, H, hx('#3A2236'))
    s.grad([(0, '#5E3A3E'), (.4, '#4A2C34'), (1, '#2E1A26')], 146, H)
    for x in range(0, W, 3):
        if random.Random(x).random() < .55: s.px(x, 146, hx('#8A5A5A'))
    s.blob_tree(24, 150, 26, ['#FFE0EC', '#FFB0CC', '#F07AA6', '#B24A7A', '#6E2A52'], seed=3, n=18)
    s.blob_tree(298, 152, 30, ['#FFE0EC', '#FFB0CC', '#F07AA6', '#B24A7A', '#6E2A52'], seed=8, n=20)
    s.lantern(262, 160, c='#8A7A7A', cd='#5A4A50')
    s.petals(70, ['#FFB0CC', '#FFD6E4', '#F07AA6'], 11, 30, 175)
    return s

def jura():
    s = Scene(21)
    s.grad([(0, '#1C1E4A'), (.22, '#33306A'), (.4, '#5E3C7E'), (.55, '#A04C80'), (.68, '#E0667A'), (.8, '#FF9468'), (.9, '#FFC27C'), (1, '#FFE2A6')], 0, 104)
    for cx, cy, w in ((60, 34, 64), (150, 50, 90), (262, 30, 70), (300, 62, 44)):
        for j in range(5):
            ww = w * (1 - j * .16)
            s.rect(cx - ww / 2, cy + j, cx + ww / 2, cy + j + 1, hx(['#FFB89A', '#FF9A8A', '#E07A8A', '#B65A86', '#7E4A86'][j]))
    s.disc(160, 98, 20, hx('#FFE9B0')); s.disc(160, 98, 16, hx('#FFF6D8')); s.glow(160, 98, 20, 48, '#FFB07A', .6)
    s.stars(24, 34, ['#FFFFFF', '#CFC6FF'], seed=12)
    s.poly([(0, 104), (0, 88), (14, 84), (30, 86), (46, 80), (62, 86), (80, 92), (96, 98), (110, 104)], '#2E2650')
    s.poly([(220, 104), (236, 96), (254, 90), (270, 84), (290, 86), (306, 80), (320, 84), (320, 104)], '#2E2650')
    for x, y in ((14, 84), (46, 80), (270, 84), (306, 80)): s.px(x, y, hx('#5A4A7A')); s.px(x + 1, y, hx('#5A4A7A'))
    s.rect(0, 104, W, 160, hx('#2E2A5A'))
    s.reflect(104, 160, dark=.62, tint='#1E1C46', ripple=1.5)
    s.waves(104, 160, '#4A3E7A', '#3A3268', seed=2, dens=.9)
    for y in range(104, 158, 2):                     # saulės kelias
        w = 6 + (y - 104) * 0.35
        for x in range(int(160 - w), int(160 + w)):
            if random.Random(x * 31 + y).random() < .55: s.px(x, y, hx('#FFD9A0' if random.Random(x + y * 7).random() < .6 else '#FFA87A'))
    s.torii(160, 126, 38, c='#D8402E', cd='#8A1E1E', top='#26141A')
    for y in range(127, 150):                        # toriio atspindys
        for x in range(130, 192):
            sy = 2 * 126 - y
            if 0 <= sy < H and tuple(s.im[sy, x]) in (hx('#D8402E'), hx('#8A1E1E'), hx('#26141A')) and (y % 2 == 0):
                s.px(x + int(math.sin(y) * 1.5), y, hx('#8A2E3A'))
    s.grad([(0, '#D8B888'), (.35, '#B89468'), (1, '#86664A')], 160, H, tw=.5)
    for x in range(0, W, 2): s.px(x + (x // 2) % 2, 160, hx('#F0DCB0'))
    for x in range(W):
        if (x * 7) % 11 == 0: s.px(x, 161, hx('#F0DCB0'))
    for i, (x, y, r) in enumerate(((20, 170, 5), (58, 174, 3), (252, 172, 4), (300, 168, 6))):
        s.disc(x, y, r, hx('#5E5A6E')); s.disc(x - 1, y - 1, max(1, r - 2), hx('#7E7A8E')); s.px(x - 2, y - r + 1, hx('#A8A4B6'))
    return s

def kalnai():
    s = Scene(41)
    s.grad([(0, '#5E9ED8'), (.35, '#7CB6E6'), (.7, '#A6D2F0'), (1, '#D4ECF8')], 0, 100)
    for cx, cy, w in ((62, 26, 46), (206, 16, 60), (284, 42, 36)):
        for j in range(6):
            ww = w * (1 - abs(j - 2) * .22)
            s.rect(cx - ww / 2, cy + j, cx + ww / 2, cy + j + 1, hx('#FFFFFF' if j < 3 else '#DCEAF4'))
    # tolimi snieguoti kalnai
    s.poly([(0, 88), (40, 58), (62, 46), (80, 56), (112, 34), (138, 52), (164, 44), (200, 66), (232, 40), (262, 26), (292, 48), (320, 60), (320, 100), (0, 100)], '#8EA6CC')
    s.poly([(52, 52), (62, 46), (72, 51), (66, 54), (60, 52)], '#F4F8FF'); s.poly([(100, 42), (112, 34), (124, 43), (116, 46), (108, 42)], '#F4F8FF')
    s.poly([(246, 34), (262, 26), (278, 37), (270, 40), (262, 34), (254, 38)], '#F4F8FF'); s.poly([(156, 48), (164, 44), (172, 49), (164, 50)], '#F4F8FF')
    s.poly([(112, 34), (138, 52), (124, 52), (118, 44)], '#7A92BC'); s.poly([(262, 26), (292, 48), (278, 48), (270, 36)], '#7A92BC')
    s.mist(70, 98, '#D4ECF8', .55)
    # vidurinė kalva su pušimis
    s.poly([(0, 104), (30, 92), (70, 86), (110, 90), (150, 84), (190, 90), (240, 82), (280, 88), (320, 84), (320, 140), (0, 140)], '#4E8A72')
    for x in range(4, W, 7): s.pine(x + random.Random(x).randint(-2, 2), 96 + random.Random(x * 3).randint(-6, 4), random.Random(x).randint(11, 17), '#2E6A56', '#245446')
    s.mist(98, 114, '#CFE8EE', .45)
    # uola su kriokliu
    s.poly([(180, 140), (184, 74), (196, 66), (232, 66), (246, 76), (250, 140)], '#5A6A70')
    s.poly([(232, 66), (246, 76), (250, 140), (238, 140), (236, 80)], '#465258')
    s.poly([(184, 74), (196, 66), (204, 66), (190, 80), (186, 140), (180, 140)], '#74868C')
    for y in range(68, 146):
        for x in range(204, 222):
            k = (x * 3 + y * 2 + (x % 3) * 5) % 11
            s.px(x, y, hx('#FFFFFF' if k < 4 else ('#CFEFFF' if k < 8 else '#8CCDE8')))
    s.rect(202, 64, 224, 68, hx('#8CCDE8'))
    s.pagoda(90, 112, 34, wall='#EADCC2', roof='#3A3240', trim='#B8382E', floors=2, light='#FFD27A')
    # ežerėlis
    s.grad([(0, '#7AC4DE'), (.5, '#4E98BC'), (1, '#2E6E92')], 138, 158, tw=.5)
    s.waves(138, 158, '#BCE6F4', '#8CCDE8', seed=8, dens=.7)
    for y in range(138, 152):
        for x in range(194, 232):
            if (x * 7 + y * 3) % 5 < 2: s.px(x, y, hx('#FFFFFF'))
    s.grad([(0, '#86BE5E'), (.25, '#6AA04C'), (.6, '#4E7E3C'), (1, '#3A5E30')], 158, H, tw=.5)
    for x in range(W):
        h_ = (x * 7) % 5
        for k in range(h_ // 2): s.px(x, 157 - k, hx('#9ACE6A'))
    for x, y, r in ((24, 164, 7), (46, 168, 4), (284, 162, 8), (306, 168, 5)):
        s.disc(x, y, r, hx('#6E727C')); s.disc(x - 1, y - 1, r - 2, hx('#8E929C')); s.disc(x - 2, y - 2, max(1, r - 4), hx('#B4B8C0'))
    s.lantern(256, 168, c='#A8A29A', cd='#6E6A66')
    return s

def gamta():
    """Bambukų giraitė: šviesos spinduliai, keli bambukų sluoksniai, akmeninis takas"""
    s = Scene(51)
    s.grad([(0, '#CFE8B0'), (.45, '#A8D488'), (1, '#7AB466')], 0, 150)
    def bamboo(x, w, c, cl, cd, node, leaf, top=0, seed=0):
        r = random.Random(seed)
        for y in range(top, 160):
            s.rect(x, y, x + w, y + 1, hx(c)); s.px(x, y, hx(cl)); s.px(x + w - 1, y, hx(cd))
        ny = top + r.randint(4, 20)
        while ny < 158:
            s.rect(x - 1, ny, x + w + 1, ny + 1, hx(node)); ny += r.randint(16, 26)
            if r.random() < .6:
                d = r.choice((-1, 1)); lx = x + (w if d > 0 else 0)
                for i in range(r.randint(6, 12)): s.px(lx + d * i, ny - 2 - i // 3, hx(leaf)); s.px(lx + d * i, ny - 1 - i // 3, hx(leaf))
    rr = random.Random(3)
    for x in range(-4, W, 11): bamboo(x + rr.randint(0, 6), 3, '#9CC884', '#B4DA9C', '#86B470', '#86B470', '#9CC884', 0, rr.randint(0, 99))
    s.mist(0, 150, '#E4F2D0', .35, invert=True)
    for x in range(2, W, 17): bamboo(x + rr.randint(0, 8), 5, '#6AA052', '#86BC66', '#4E843E', '#3E6E30', '#5A9446', 0, rr.randint(0, 99))
    # šviesos spinduliai
    for k, x0 in enumerate((40, 120, 210)):
        for y in range(0, 150):
            for x in range(int(x0 + y * .5), int(x0 + y * .5 + 14)):
                if B4[y % 4, x % 4] < .22 and 0 <= x < W: s.im[y, x] = (s.im[y, x] * .5 + np.array((255, 252, 210)) * .5).astype(np.uint8)
    for x in (8, 58, 250, 300): bamboo(x, 8, '#3E7E36', '#5E9E48', '#2A5E28', '#1E4A1E', '#2E6A2A', 0, x)
    s.grad([(0, '#5E8A44'), (.3, '#4A7438'), (1, '#2E4E26')], 150, H, tw=.5)
    for x in range(W):
        for k in range((x * 5) % 4): s.px(x, 149 - k, hx('#7AAE52'))
    for i, x in enumerate(range(90, 250, 22)):                 # akmeninis takas
        y = 160 + (i % 2) * 6; s.disc(x, y, 7, hx('#8E8A80')); s.disc(x - 1, y - 1, 5, hx('#A8A498')); s.px(x - 3, y - 4, hx('#C8C4B6'))
    s.lantern(270, 164, c='#9A968C', cd='#66625A')
    s.petals(40, ['#E8F2A0', '#C8E080'], 5, 10, 150)
    return s

def ziema():
    """Žiemos šventykla naktį: sniegas, žibintai, šviečiantys langai"""
    s = Scene(61)
    s.grad([(0, '#0E1430'), (.35, '#1C2650'), (.7, '#2E3E72'), (1, '#4E6496')], 0, 120)
    s.stars(80, 90, ['#FFFFFF', '#CFE0FF', '#9AB4F0'], seed=3)
    s.disc(250, 34, 11, hx('#F2F6FF')); s.disc(254, 31, 9, hx('#1C2650')); s.glow(250, 34, 11, 26, '#9AB4F0', .35)
    s.poly([(0, 110), (40, 76), (70, 88), (110, 60), (150, 84), (190, 70), (240, 92), (280, 72), (320, 86), (320, 120), (0, 120)], '#3A4A7A')
    for (a, b) in (((40, 76), (70, 88)), ((110, 60), (150, 84)), ((190, 70), (240, 92)), ((280, 72), (320, 86))):
        s.poly([a, (a[0] + 10, a[1] + 6), (a[0] - 8, a[1] + 8)], '#DCE6FA')
    s.mist(96, 120, '#6E86B8', .4)
    s.grad([(0, '#2A3A62'), (1, '#1A2648')], 118, 140, tw=.5)
    for x in range(4, W, 8): s.pine(x + random.Random(x).randint(-2, 2), 122 + random.Random(x).randint(-3, 3), random.Random(x * 3).randint(14, 22), '#1E2E4A', '#16243E')
    for x in range(4, W, 8):
        for i in range(0, 20, 5):
            s.px(x + random.Random(x).randint(-2, 2) - i // 4, 122 - 18 + i, hx('#E4ECFA'))
    # šventykla
    x0, yb = 120, 140
    s.rect(x0 - 40, yb - 26, x0 + 40, yb, hx('#5A3A3A')); s.rect(x0 - 36, yb - 22, x0 + 36, yb - 2, hx('#3E2A2E'))
    for i, xx in enumerate(range(x0 - 32, x0 + 32, 16)):
        s.rect(xx, yb - 20, xx + 12, yb - 6, hx('#FFD27A')); s.rect(xx + 5, yb - 20, xx + 7, yb - 6, hx('#C89A4A')); s.rect(xx, yb - 14, xx + 12, yb - 13, hx('#C89A4A'))
    s.poly([(x0 - 54, yb - 24), (x0 + 54, yb - 24), (x0 + 38, yb - 40), (x0 - 38, yb - 40)], '#2A2234')
    s.poly([(x0 - 56, yb - 24), (x0 + 56, yb - 24), (x0 + 50, yb - 28), (x0 - 50, yb - 28)], '#E4ECFA')
    s.poly([(x0 - 38, yb - 40), (x0 + 38, yb - 40), (x0 + 30, yb - 44), (x0 - 30, yb - 44)], '#E4ECFA')
    s.glow(x0, yb - 13, 20, 60, '#FFC870', .45)
    s.grad([(0, '#DCE6FA'), (.5, '#BCCBEA'), (1, '#8EA0CE')], 140, H, tw=.5)
    for x in range(W):
        if (x * 13) % 7 == 0: s.px(x, 141, hx('#FFFFFF'))
    s.lantern(60, 162, c='#9A9AAA', cd='#5E5E72', light='#FFD27A'); s.glow(60, 150, 3, 16, '#FFC870', .4)
    s.lantern(262, 164, c='#9A9AAA', cd='#5E5E72', light='#FFD27A'); s.glow(262, 152, 3, 16, '#FFC870', .4)
    s.petals(90, ['#FFFFFF', '#DCE6FA'], 6, 0, 175)
    return s

def kosmosas():
    """Dvasių pasaulis: žvaigždėtas ūkas, plūduriuojančios salos su šventykla, dvasių šviesos"""
    s = Scene(71)
    s.grad([(0, '#0A0820'), (.3, '#1A1040'), (.6, '#2E1A5E'), (1, '#4A2A7E')], 0, H)
    for cx, cy, r, c in ((90, 50, 50, '#6A3AA0'), (230, 70, 60, '#2E5AA0'), (170, 30, 40, '#A04A9A')):
        s.glow(cx, cy, 0, r, c, .55)
    s.stars(160, H, ['#FFFFFF', '#CFC6FF', '#9AE6FF', '#FFD6F4'], seed=8)
    def island(cx, cy, w, h, seed):
        r = random.Random(seed)
        pts = [(cx - w, cy)] + [(cx - w + i * 2 * w / 8, cy + h * (1 - abs(i - 4) / 4) * r.uniform(.8, 1.15)) for i in range(1, 8)] + [(cx + w, cy)]
        s.poly(pts, '#3A2E5A'); s.poly([(cx - w, cy), (cx + w, cy), (cx + w - 3, cy + 4), (cx - w + 3, cy + 4)], '#5A4A86')
        s.rect(cx - w, cy - 3, cx + w, cy, hx('#5AC69A')); s.rect(cx - w, cy - 3, cx + w, cy - 2, hx('#8AE6B4'))
        for i in range(0, int(h), 3): s.px(cx + r.randint(-int(w * .5), int(w * .5)), cy + 5 + i, hx('#9A7ACE'))
    island(70, 70, 26, 22, 1); island(250, 56, 22, 18, 2); island(170, 108, 40, 26, 3)
    s.torii(70, 67, 18, c='#E8506A', cd='#9A2A4A', top='#2A1A3A')
    s.pagoda(250, 53, 30, wall='#E8DCF4', roof='#2A1E4A', trim='#9A5AE0', floors=3, light='#9AE6FF')
    s.blob_tree(150, 105, 10, ['#FFE0F4', '#F4A6E0', '#C06ACE', '#7A3A9A'], trunk='#3A2A4A', seed=4, n=10)
    for k in range(18):
        r = random.Random(k * 7); x, y = r.randint(10, 310), r.randint(20, 150)
        s.disc(x, y, 1.5, hx('#BFF6FF')); s.glow(x, y, 1, 6, '#7ADCF4', .5)
    s.grad([(0, '#3A2A6A'), (.4, '#2A1E52'), (1, '#160E30')], 156, H, tw=.5)
    for x in range(W):
        if (x * 11) % 9 < 3: s.px(x, 156, hx('#7A5AC0'))
    for x in range(0, W, 2):
        if (x * 7) % 13 < 2: s.px(x, 158 + (x % 5), hx('#9AE6FF'))
    return s

def pasvaiste():
    """Šiaurės pašvaistė virš kalnų ežero"""
    s = Scene(81)
    s.grad([(0, '#06101E'), (.4, '#0E2236'), (.75, '#1A3A4E'), (1, '#2A5060')], 0, 112)
    s.stars(110, 100, ['#FFFFFF', '#CFF6FF'], seed=11)
    for band, (c1, c2, ph, yb) in enumerate((('#3AE6A0', '#1E9A7A', 0, 46), ('#7AF0C8', '#2EB48A', 1.7, 58), ('#B47AF0', '#6A3AAE', 3.1, 34))):
        for x in range(W):
            y0 = yb + math.sin(x * .028 + ph) * 12 + math.sin(x * .07 + ph * 2) * 4
            L = 26 + math.sin(x * .05 + ph) * 10
            for y in range(int(y0 - L), int(y0)):
                if 0 <= y < H:
                    t = (y0 - y) / L
                    if (1 - t) * .75 > B4[y % 4, x % 4] and (x + band) % 2 == 0 or (1 - t) * .5 > B4[y % 4, x % 4]:
                        s.im[y, x] = (s.im[y, x] * .3 + np.array(hx(c1 if t < .35 else c2)) * .7).astype(np.uint8)
    s.poly([(0, 112), (30, 84), (54, 92), (88, 70), (118, 88), (150, 78), (184, 96), (220, 74), (256, 88), (290, 70), (320, 82), (320, 112)], '#1A2A3A')
    for a in ((88, 70), (220, 74), (290, 70), (30, 84)): s.poly([a, (a[0] + 8, a[1] + 6), (a[0] - 7, a[1] + 7)], '#CFE6F0')
    s.rect(0, 112, W, 150, hx('#0E1E2E'))
    s.reflect(112, 150, dark=.55, tint='#0A1622', ripple=1)
    s.waves(112, 150, '#3A6A7A', '#24485A', seed=5, dens=.5)
    for x in range(6, W, 9):
        if not 120 < x < 200: s.pine(x, 152 + random.Random(x).randint(-2, 2), random.Random(x).randint(14, 24), '#0A1620', '#08121A')
    s.grad([(0, '#2E4A52'), (.4, '#22383E'), (1, '#121E22')], 150, H, tw=.5)
    for x in range(W):
        if (x * 5) % 7 < 2: s.px(x, 150, hx('#4A7A7A'))
    s.rect(150, 158, 156, 166, hx('#5A3A2A')); s.disc(153, 156, 4, hx('#FF9A3A')); s.disc(153, 155, 2, hx('#FFE07A')); s.glow(153, 156, 4, 26, '#FF8A3A', .45)
    return s

def ruduo():
    """Rudens klevai prie upelio su arkiniu tilteliu"""
    s = Scene(91)
    s.grad([(0, '#6AA6D8'), (.4, '#9AC4E4'), (.75, '#F2D2A6'), (1, '#F6B884')], 0, 108)
    s.poly([(0, 96), (50, 72), (90, 80), (140, 60), (190, 78), (240, 66), (290, 80), (320, 74), (320, 108), (0, 108)], '#B48A8A')
    s.mist(80, 108, '#F4D4AE', .45)
    s.poly([(0, 110), (60, 92), (120, 98), (180, 90), (240, 98), (320, 92), (320, 130), (0, 130)], '#8A5A3A')
    for x in range(0, W, 6):
        r = random.Random(x); s.blob_tree(x, 112 + r.randint(-2, 4), r.randint(6, 9), ['#FFC24A', '#F08A2A', '#C8481E', '#7A2A1A'], trunk='#4A2A1E', seed=x, n=6)
    s.rect(0, 128, W, 148, hx('#4E8EB4'))
    s.reflect(128, 148, dark=.55, tint='#2A5A7A', ripple=1.5)
    s.waves(128, 148, '#9ACCE4', '#6AAAC8', seed=3, dens=.7)
    # tiltelis
    for x in range(120, 202):
        t = (x - 161) / 41; y = int(126 - (1 - t * t) * 16)
        s.rect(x, y, x + 1, y + 4, hx('#C8372D')); s.px(x, y, hx('#E8584A'))
        if x % 8 == 0: s.rect(x, y - 8, x + 2, y, hx('#C8372D'))
    for x in range(120, 202):
        t = (x - 161) / 41; y = int(126 - (1 - t * t) * 16) - 8; s.px(x, y, hx('#8A1E1E'))
    s.grad([(0, '#A88A4A'), (.3, '#8A6A3A'), (1, '#5A3E26')], 148, H, tw=.5)
    s.blob_tree(26, 154, 24, ['#FFD25A', '#FFA03A', '#E05A2A', '#A8341E', '#5E1E16'], trunk='#3A2018', seed=2, n=18)
    s.blob_tree(296, 156, 28, ['#FFD25A', '#FFA03A', '#E05A2A', '#A8341E', '#5E1E16'], trunk='#3A2018', seed=6, n=20)
    s.petals(80, ['#FFA03A', '#E05A2A', '#FFD25A'], 4, 20, 178)
    return s

def vulkanas():
    """Ugnikalnis naktį, lavos upės ir obsidiano uolos"""
    s = Scene(101)
    s.grad([(0, '#120A14'), (.4, '#2A1018'), (.75, '#5A1A1A'), (1, '#9A3A1E')], 0, 120)
    s.stars(40, 50, ['#FFC8A0', '#FFFFFF'], seed=4)
    s.poly([(60, 120), (130, 50), (146, 44), (172, 44), (190, 52), (262, 120)], '#2A1A1E')
    s.poly([(146, 44), (172, 44), (166, 50), (152, 50)], '#FF7A2A'); s.glow(159, 44, 6, 40, '#FF6A2A', .6)
    for k in range(3):
        xs = [(156 + k * 4, 50)]
        for i in range(1, 12): xs.append((xs[-1][0] + random.Random(k * 31 + i).randint(-3, 3) + (k - 1) * 2, 50 + i * 6))
        for (a, b), (c_, d) in zip(xs, xs[1:]): s.line(a, b, c_, d, hx('#FF8A2A')); s.line(a + 1, b, c_ + 1, d, hx('#FFC84A'))
    for k in range(30):
        r = random.Random(k); x, y = r.randint(120, 200), r.randint(10, 46); s.px(x, y, hx('#FFB44A' if r.random() < .5 else '#FF6A2A'))
    s.poly([(0, 120), (0, 100), (40, 92), (70, 106), (110, 98), (150, 112), (200, 100), (250, 110), (290, 96), (320, 102), (320, 120)], '#1A1014')
    s.grad([(0, '#3A2224'), (.3, '#2A181A'), (1, '#140C0E')], 120, H, tw=.5)
    for y in range(124, H, 1):
        x = int(160 + math.sin(y * .12) * 30 + (y - 124) * .8)
        s.rect(x - 4 - (y - 124) // 6, y, x + 4 + (y - 124) // 6, y + 1, hx('#FF6A2A'))
        s.rect(x - 2, y, x + 2, y + 1, hx('#FFC84A'))
    s.glow(170, 150, 10, 60, '#FF5A1E', .35)
    for x, y in ((30, 150), (60, 166), (270, 156), (300, 170), (110, 172)):
        s.poly([(x - 8, y + 6), (x - 3, y - 8), (x + 4, y - 4), (x + 9, y + 6)], '#2E2A3A'); s.line(x - 3, y - 8, x + 4, y - 4, hx('#6A5AA0'))
    return s

def kopos():
    """Kopos sutemose: oazė su palmėmis ir senovinė šventykla"""
    s = Scene(111)
    s.grad([(0, '#2A2A5E'), (.35, '#6A4A86'), (.6, '#D07A7A'), (.8, '#F4B07A'), (1, '#FCD8A0')], 0, 112)
    s.disc(244, 96, 14, hx('#FFE8B0')); s.glow(244, 96, 14, 36, '#FFC07A', .55)
    s.stars(30, 40, ['#FFFFFF'], seed=2)
    s.poly([(0, 112), (40, 100), (100, 108), (160, 96), (220, 106), (280, 98), (320, 104), (320, 130), (0, 130)], '#C88A6A')
    s.poly([(0, 130), (60, 112), (130, 122), (200, 110), (260, 120), (320, 112), (320, 150), (0, 150)], '#E0A47A')
    s.poly([(200, 110), (260, 120), (320, 112), (320, 120), (260, 126)], '#B87A5A')
    # šventykla - griuvėsiai
    for x in (60, 76, 92, 108): s.rect(x, 86, x + 6, 118, hx('#E8C89A')); s.rect(x + 4, 86, x + 6, 118, hx('#B8946A'))
    s.rect(56, 82, 118, 87, hx('#D8B488')); s.rect(56, 86, 118, 87, hx('#A8845E'))
    # oazė
    s.poly([(170, 140), (200, 132), (250, 134), (280, 142), (250, 148), (190, 148)], '#3A8AA8')
    s.waves(133, 148, '#7AD0E4', '#5AB0CC', seed=4, dens=1.2, x0=170, x1=280, only=hx('#3A8AA8'))
    def palm(x, yb, h):
        for i in range(h): s.rect(x + int(math.sin(i * .08) * 3), yb - i, x + int(math.sin(i * .08) * 3) + 2, yb - i + 1, hx('#7A5A3A' if i % 4 else '#5A3E28'))
        tx = x + int(math.sin(h * .08) * 3); ty = yb - h
        for ang in (-150, -120, -60, -30, -90, 200, -10):
            a = math.radians(ang)
            for k in range(14):
                px_, py_ = tx + math.cos(a) * k, ty + math.sin(a) * k * .6 + k * k * .04
                s.px(px_, py_, hx('#3E8A4A' if k < 10 else '#2E6A3A')); s.px(px_, py_ + 1, hx('#2E6A3A'))
    palm(186, 140, 30); palm(268, 142, 24)
    s.grad([(0, '#F0BC8A'), (.35, '#DCA070'), (1, '#B07A52')], 150, H, tw=.5)
    for x in range(W):
        y = 156 + int(math.sin(x * .05) * 3)
        if x % 2: s.px(x, y, hx('#FCD4A4'))
    return s

SC = {'sakura': sakura, 'jura': jura, 'kalnai': kalnai, 'gamta': gamta, 'ziema': ziema, 'kosmosas': kosmosas, 'pasvaiste': pasvaiste, 'ruduo': ruduo, 'vulkanas': vulkanas, 'kopos': kopos}
if __name__ == '__main__':
    for n in (sys.argv[2:] or SC):
        SC[n]().save(f'{OUT}/{n}.png', 3)
        print(n)
