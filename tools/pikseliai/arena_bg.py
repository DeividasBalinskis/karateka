# Pikselinė arena: mėnesienos dojo (240×135). Ribota paletė, kietos formos, „dither“ perėjimai.
import numpy as np, math
from PIL import Image
W, H = 240, 135
im = np.zeros((H, W, 3), np.uint8)
B4 = np.array([[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]]) / 16.0
def rect(x0, y0, x1, y1, c): im[max(0, y0):max(0, y1), max(0, x0):max(0, x1)] = c
def px(x, y, c):
    if 0 <= x < W and 0 <= y < H: im[y, x] = c
def dith(x0, y0, x1, y1, c1, c2, vert=True, power=1.0):
    for y in range(y0, y1):
        for x in range(x0, x1):
            t = ((y - y0) / max(1, y1 - y0 - 1)) if vert else ((x - x0) / max(1, x1 - x0 - 1))
            px(x, y, c2 if t ** power > B4[y % 4, x % 4] else c1)
def disc(cx, cy, r, c, test=None):
    for y in range(int(cy - r - 1), int(cy + r + 2)):
        for x in range(int(cx - r - 1), int(cx + r + 2)):
            if (x - cx) ** 2 + (y - cy) ** 2 <= r * r and (test is None or test(x, y)): px(x, y, c)
FLOOR = 90
WOOD_D, WOOD, WOOD_L, WOOD_H = (44, 28, 30), (66, 42, 38), (92, 60, 46), (124, 84, 58)
PAPER_D, PAPER, PAPER_L = (74, 92, 132), (104, 126, 166), (138, 160, 194)
GRID = (52, 62, 98)
# Siena: shoji, apšviestos mėnesienos
dith(0, 0, W, FLOOR, PAPER, PAPER_D, True, 1.6)
dith(0, 8, W, 40, PAPER_L, PAPER, True, 0.8)
# Shoji tinklelis
for x in range(0, W, 13): rect(x, 8, x + 1, FLOOR - 12, GRID)
for y in range(8, FLOOR - 12, 12): rect(0, y, W, y + 1, GRID)
# Apvalus langas su mėnuliu, kalnu ir sakura
cx, cy, r = 120, 43, 27
for y in range(cy - r, cy + r + 1):
    for x in range(cx - r, cx + r + 1):
        d2 = (x - cx) ** 2 + (y - cy) ** 2
        if d2 <= r * r:
            t = (y - (cy - r)) / (2 * r)
            sky1, sky2, sky3 = (26, 30, 72), (58, 52, 116), (140, 84, 132)
            c = sky1 if t < 0.35 + (B4[y % 4, x % 4] - .5) * .25 else (sky2 if t < 0.72 + (B4[y % 4, x % 4] - .5) * .25 else sky3)
            px(x, y, c)
inwin = lambda x, y: (x - cx) ** 2 + (y - cy) ** 2 <= (r - 1) ** 2
for sx, sy in ((104, 28), (111, 22), (138, 30), (131, 24), (100, 40), (142, 42), (118, 26)): px(sx, sy, (230, 236, 255))
disc(128, 33, 7, (250, 244, 214), inwin); disc(128, 33, 5, (255, 252, 236), inwin)
px(126, 31, (232, 222, 190)); px(130, 35, (232, 222, 190)); px(129, 30, (232, 222, 190))
for y in range(cy, cy + r):                                   # kalnas
    half = (y - (cy + 2)) * 1.5
    for x in range(int(112 - half), int(112 + half) + 1):
        if inwin(x, y) and y >= cy + 2: px(x, y, (36, 34, 78) if y > cy + 9 else (88, 84, 146))
for x in range(108, 117):                                     # sniego viršūnė
    for y in range(cy + 2, cy + 6):
        if abs(x - 112) <= (y - (cy + 2)) * 1.5 and inwin(x, y): px(x, y, (214, 222, 246))
# Sakuros šaka per langą
branch = [(92, 26), (97, 27), (102, 27), (107, 29), (112, 30), (117, 30), (122, 32), (127, 35), (132, 36), (138, 36), (144, 38), (149, 41)]
for (x0, y0), (x1, y1) in zip(branch, branch[1:]):
    n = max(abs(x1 - x0), abs(y1 - y0))
    for i in range(n + 1):
        x, y = round(x0 + (x1 - x0) * i / n), round(y0 + (y1 - y0) * i / n)
        px(x, y, (40, 24, 28)); px(x, y + 1, (62, 38, 36))
for bx, by in ((96, 24), (103, 25), (109, 26), (115, 28), (121, 29), (125, 33), (134, 33), (140, 34), (146, 36), (99, 30), (112, 33), (130, 38), (143, 41), (106, 31), (118, 34), (137, 39), (150, 39)):
    px(bx, by, (255, 170, 196)); px(bx + 1, by, (255, 206, 222)); px(bx, by + 1, (232, 122, 160)); px(bx + 1, by + 1, (255, 170, 196))
for y in range(cy - r - 2, cy + r + 3):                       # lango rėmas
    for x in range(cx - r - 2, cx + r + 3):
        d = math.hypot(x - cx, y - cy)
        if r - 0.5 < d <= r + 2.2: px(x, y, WOOD_D if d > r + 1.2 else WOOD_L)
# Kolonos ir sijos
for x0 in (0, 74, 160, 234):
    rect(x0, 0, x0 + 6, FLOOR, WOOD); rect(x0, 0, x0 + 1, FLOOR, WOOD_L); rect(x0 + 5, 0, x0 + 6, FLOOR, WOOD_D)
rect(0, 0, W, 8, WOOD); rect(0, 7, W, 8, WOOD_D); rect(0, 0, W, 1, WOOD_L)
rect(0, FLOOR - 12, W, FLOOR, WOOD); rect(0, FLOOR - 12, W, FLOOR - 11, WOOD_L); rect(0, FLOOR - 1, W, FLOOR, WOOD_D)
for x in range(8, W, 20): rect(x, FLOOR - 10, x + 1, FLOOR - 1, WOOD_D)
# Kabantis ritinys (kairėje) - teptuko brūkšniai
rect(26, 16, 50, 62, (232, 222, 196)); rect(26, 16, 50, 17, WOOD_D); rect(24, 14, 52, 16, WOOD_L); rect(24, 62, 52, 64, WOOD_L); rect(26, 61, 50, 62, (196, 184, 156))
rect(28, 18, 48, 20, (176, 52, 48)); rect(28, 58, 48, 60, (176, 52, 48))
INK = (36, 30, 44)
for seg in (((33, 25), (43, 25)), ((38, 22), (38, 34)), ((32, 30), (44, 30)), ((34, 34), (31, 40)), ((42, 34), (45, 40)), ((33, 43), (43, 43)), ((38, 43), (38, 54)), ((33, 48), (43, 48)), ((34, 54), (42, 54))):
    (x0, y0), (x1, y1) = seg; n = max(abs(x1 - x0), abs(y1 - y0))
    for i in range(n + 1): px(round(x0 + (x1 - x0) * i / n), round(y0 + (y1 - y0) * i / n), INK)
px(45, 56, (200, 48, 44)); px(46, 56, (200, 48, 44)); px(45, 57, (200, 48, 44)); px(46, 57, (200, 48, 44))
# Ginklų stovas (dešinėje): bo, bokken, nunčakai
rect(184, 24, 222, 26, WOOD_L); rect(184, 26, 222, 27, WOOD_D); rect(184, 46, 222, 48, WOOD_L); rect(184, 48, 222, 49, WOOD_D)
rect(186, 20, 188, 56, WOOD); rect(218, 20, 220, 56, WOOD)
rect(190, 30, 216, 32, (196, 150, 96)); rect(190, 32, 216, 33, (146, 104, 64))
rect(190, 38, 212, 40, (120, 80, 56)); rect(212, 38, 216, 40, (56, 40, 44)); rect(190, 40, 216, 41, (80, 52, 40))
rect(192, 52, 202, 54, (176, 60, 52)); rect(206, 52, 216, 54, (176, 60, 52)); px(203, 53, (190, 190, 200)); px(204, 53, (150, 150, 164)); px(205, 53, (190, 190, 200))
# Žibintai su švytėjimu
def lantern(cx, top):
    for y in range(top - 6, top + 26):
        for x in range(cx - 16, cx + 17):
            d = math.hypot((x - cx) * 0.9, y - (top + 9))
            if d < 15 and B4[y % 4, x % 4] > d / 15 + 0.12 and tuple(im[y, x]) in (PAPER, PAPER_D, PAPER_L, GRID): px(x, y, (190, 150, 150) if d > 9 else (232, 176, 140))
    rect(cx, 8, cx + 1, top, WOOD_D)
    rect(cx - 3, top, cx + 4, top + 2, (40, 26, 30))
    for y in range(top + 2, top + 15):
        t = (y - (top + 2)) / 12; hw = round(3 + 3.2 * math.sin(math.pi * min(1, max(0, t * 0.92 + 0.04))))
        for x in range(cx - hw, cx + hw + 1):
            c = (224, 68, 52)
            if x <= cx - hw + 1: c = (150, 38, 44)
            elif x >= cx + hw - 1: c = (176, 46, 46)
            if abs(x - cx) <= 1 and 4 <= y - top <= 11: c = (255, 214, 120)
            elif abs(x - cx) <= 2 and 3 <= y - top <= 12: c = (250, 140, 76)
            px(x, y, c)
        if (y - top) % 4 == 1:
            for x in range(cx - hw, cx + hw + 1):
                if abs(x - cx) > 2: px(x, y, (150, 38, 44))
    rect(cx - 3, top + 15, cx + 4, top + 17, (40, 26, 30)); rect(cx, top + 17, cx + 1, top + 21, (232, 190, 80))
lantern(64, 14); lantern(176, 14)
# Grindys: lentos su perspektyva
F1, F2, F3, F4 = (88, 54, 44), (122, 78, 54), (154, 104, 66), (188, 136, 86)
dith(0, FLOOR, W, H, F2, F3, True, 1.2)
rows = [FLOOR, 94, 99, 105, 113, 123, H]
for i, (ya, yb) in enumerate(zip(rows, rows[1:])):
    rect(0, ya, W, ya + 1, F1)
    step = 26 + i * 9; off = (i % 2) * step // 2
    for x in range(-off, W, step):
        for y in range(ya + 1, yb): px(x, y, F1)
        for y in range(ya + 1, yb):
            if 0 <= x + 1 < W: px(x + 1, y, F3 if y % 2 else F2)
# Mėnesienos atspindys ir žibintų šviesa ant grindų
for y in range(FLOOR + 1, H):
    for x in range(W):
        if tuple(im[y, x]) == F1: continue
        d = abs(x - 120) / (18 + (y - FLOOR) * 0.9)
        if d < 1 and B4[y % 4, x % 4] > d * 0.9 + 0.25: px(x, y, F4)
        for lx in (64, 176):
            d2 = abs(x - lx) / (10 + (y - FLOOR) * 0.5)
            if d2 < 1 and B4[y % 4, x % 4] > d2 + 0.45: px(x, y, (206, 128, 80))
dith(0, FLOOR, W, FLOOR + 3, (56, 36, 38), F2, True, 1.0)
rect(0, FLOOR, W, FLOOR + 1, (40, 26, 30))
Image.fromarray(im).save('arena-dojo.png')
Image.fromarray(im).resize((W * 4, H * 4), Image.NEAREST).save('arena-dojo-x4.png')
print('ok')
