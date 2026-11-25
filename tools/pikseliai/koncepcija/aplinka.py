# Koncepcijos aplinka: dojo kiemas saulėlydyje, 320x180 (G - žemės viršaus aukštis, kad tiktų ir kovai).
import os
import sys
import math
import random

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'fonai'))
from kit import Scene, hx, W, H, B4  # noqa: E402


def dojo_kiemas(G=118, seed=3):
    s = Scene(seed)
    # dangus
    s.grad([(0, '#2B2557'), (0.3, '#59397A'), (0.55, '#A6507C'), (0.74, '#E57A64'), (0.9, '#F7A865'), (1.0, '#FFD58C')], 0, G)
    sx, sy = 226, G - 30
    s.glow(sx, sy, 12, 44, '#FFD08A', 0.6)
    s.disc(sx, sy, 12, hx('#FFE9B4'))
    s.disc(sx - 3, sy - 3, 6, hx('#FFF6D8'))
    r = random.Random(seed)
    for (x, y, L) in [(18, 0.18, 52), (118, 0.12, 70), (238, 0.22, 46), (170, 0.36, 58), (40, 0.42, 40)]:
        yy = int(G * y) + 4
        s.rect(x, yy, x + L, yy + 1, hx('#C76A88'))
        s.rect(x + 7, yy - 1, x + L - 12, yy, hx('#E9908E'))
    s.stars(26, G * 0.3, ['#E8D9FF', '#C9B8F2'], seed=7)
    # kalnai
    s.ridge(G - 18, 15, 0.55, '#7A4D86', seed=11, top='#9A6293')
    s.ridge(G - 8, 10, 0.6, '#59407A', seed=5, top='#6E4D84')
    s.mist(G - 22, G + 2, '#E99A8A', 0.32)
    # tolimi toriai (tarp kovotojų)
    s.torii(160, G - 1, 26, c='#B2404C', cd='#7A2A3E', top='#3A2442')
    # žemė: akmeninis kiemas su perspektyva
    s.rect(0, G, W, H, hx('#6E5A8A'))
    vx, vy = 160, G - 70
    rows = []
    y = G + 1
    step = 3
    while y < H:
        rows.append(y)
        y += step
        step += 1.6
    for i, y in enumerate(rows):
        yi = int(y)
        s.rect(0, yi, W, yi + 1, hx('#5B4A7A'))
        if i == 0:
            s.rect(0, G, W, G + 1, hx('#9A7CA6'))
    for k in range(-14, 15):
        x0 = vx + k * 26
        for yy in range(G + 1, H):
            t = (yy - vy) / (G + 1 - vy)
            x = vx + (x0 - vx) * t
            s.px(round(x), yy, hx('#5B4A7A'))
    # saulėlydžio šviesa ant akmenų (prie horizonto šviesiau)
    for yy in range(G, min(H, G + 26)):
        a = 1 - (yy - G) / 26
        for x in range(W):
            if a * 0.55 > B4[yy % 4, x % 4] and tuple(s.im[yy, x]) == hx('#6E5A8A'):
                s.im[yy, x] = hx('#86689A')
    # dojo pastatas dešinėje
    bx, by = 246, G + 2
    s.rect(bx - 8, by - 6, W, by + 2, hx('#4B4166'))
    s.rect(bx - 8, by - 6, W, by - 5, hx('#6E6088'))
    s.rect(bx, by - 36, W, by - 6, hx('#F2E4CC'))
    for i, x in enumerate(range(bx, W, 18)):
        lit = i in (1, 2)
        s.rect(x + 3, by - 33, x + 16, by - 8, hx('#FFD58C') if lit else hx('#E7D3B2'))
        for gx in range(x + 3, x + 16, 4):
            s.rect(gx, by - 33, gx + 1, by - 8, hx('#C9A97E') if not lit else hx('#E8B267'))
        for gy in range(by - 33, by - 8, 6):
            s.rect(x + 3, gy, x + 16, gy + 1, hx('#C9A97E') if not lit else hx('#E8B267'))
        s.rect(x, by - 36, x + 3, by - 6, hx('#8E3B4C'))
        s.rect(x + 2, by - 36, x + 3, by - 6, hx('#6A2A3E'))
    s.rect(bx - 4, by - 40, W, by - 36, hx('#3A3152'))
    s.poly([(bx - 16, by - 39), (bx - 6, by - 50), (W, by - 64), (W, by - 39)], '#2F2A46')
    s.poly([(bx - 6, by - 50), (W, by - 64), (W, by - 61), (bx - 4, by - 47)], '#4A4468')
    s.poly([(bx - 20, by - 41), (bx - 14, by - 40), (bx - 10, by - 38), (bx - 18, by - 38)], '#2F2A46')
    for lx in (bx + 6, bx + 42):
        s.rect(lx, by - 30, lx + 5, by - 23, hx('#D8434B'))
        s.rect(lx + 1, by - 29, lx + 4, by - 24, hx('#F2A65A'))
        s.rect(lx + 2, by - 31, lx + 3, by - 30, hx('#3A2442'))
    # sakura kairėje
    s.blob_tree(30, G + 4, 17, ['#FFD3E2', '#F59CC3', '#D96CA0', '#A84D84'], trunk='#4A2E48', seed=4, n=16)
    s.blob_tree(-6, G - 2, 12, ['#FFD3E2', '#F59CC3', '#D96CA0', '#A84D84'], trunk='#4A2E48', seed=9, n=10)
    s.lantern(78, G + 6, c='#A59CB6', cd='#6C6386', light='#FFD58C')
    s.glow(79, G - 5, 2, 12, '#FFD58C', 0.35)
    s.petals(55, ['#FFC4D8', '#F59CC3', '#FFE0EA'], seed=12, y0=10, y1=H - 2)
    return s


def contact_shadow(s, cx, cy, rx, ry, col='#3B2D55', strength=0.62):
    c = hx(col)
    for y in range(int(cy - ry - 1), int(cy + ry + 2)):
        for x in range(int(cx - rx - 1), int(cx + rx + 2)):
            if not (0 <= x < W and 0 <= y < H):
                continue
            d = ((x + 0.5 - cx) / rx) ** 2 + ((y + 0.5 - cy) / ry) ** 2
            if d <= 1 and (1 - d * 0.55) * strength > B4[y % 4, x % 4]:
                s.im[y, x] = c
