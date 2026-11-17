# Gyvūnėliai - pūkų gumulėliai: skirtingos formos, 4 kailio tonai, plonas kontūras, akys su blizgesiu. Indeksuoti (perdažomi).
import numpy as np, math, json, base64
from PIL import Image
S = 44
def blob(shape):
    R = np.zeros((S, S), int)   # 0 tuščia, 1.. tonai: 1 šviesiausias ... 4 tamsiausias; 5 kontūras; 6 akis; 7 blizgesys; 8 skruostas; 9 detalė (ragai/sparnai tamsesni); 10 pilvas
    cx, cy = S / 2 - .5, S / 2 + 3
    def inside(x, y):
        dx, dy = x - cx, y - cy; th = math.atan2(dy, dx); d = math.hypot(dx, dy)
        fl = 0.9 * math.sin(th * 9 + 1.3) + 0.5 * math.sin(th * 15)          # pūkuotas kraštas
        if shape == 'round': return d < 13 + fl
        if shape == 'tall': return math.hypot(dx / 10.5, dy / 14) < 1 + fl / 14
        if shape == 'wide': return math.hypot(dx / 16, (dy - 2) / 10.5) < 1 + fl / 14
        if shape == 'spiky': return d < 10.5 + 3.6 * abs(math.cos(th * 3.5 + .4)) ** 1.5 + fl * .3
        if shape == 'cloud': return any(math.hypot(dx - ox, dy - oy) < rr + fl * .4 for ox, oy, rr in ((-8, 3, 8), (0, -3, 10), (8, 3, 8), (0, 5, 9)))
        if shape == 'drop': return (d < 12 + fl * .7 and dy > -6) or (dy <= -6 and abs(dx) < (dy + 17) * 0.75 and dy > -17)
        if shape == 'eared': return d < 12 + fl or any(abs(dx - s * 7) < (dy + 20) * 0.42 and -20 < dy < -6 for s in (-1, 1))
        if shape == 'bunny': return d < 12 + fl or any(math.hypot((dx - s * 5) / 3.2, (dy + 15) / 9) < 1 for s in (-1, 1))
        if shape == 'horn': return d < 12.5 + fl
        if shape == 'wing': return math.hypot(dx / 11.5, dy / 12.5) < 1 + fl / 14
        if shape == 'flame': return (d < 11.5 + fl * .6 and dy > -5) or (dy <= -5 and abs(dx + 2 * math.sin(dy * .7)) < (dy + 19) * 0.6 and dy > -19)
        return d < 13
    body = np.array([[inside(x, y) for x in range(S)] for y in range(S)])
    extra = np.zeros((S, S), bool)
    if shape == 'horn':
        for s in (-1, 1):
            for i in range(6):
                for w in range(max(1, 3 - i // 2)):
                    x, y = int(round(cx + s * (6 + i * .6) + (w if s < 0 else -w))), int(round(cy - 11 - i))
                    if 0 <= x < S and 0 <= y < S: extra[y, x] = True
    if shape in ('wing', 'flame'):
        for s in (-1, 1):
            for y in range(S):
                for x in range(S):
                    dx, dy = x - (cx + s * 13), y - (cy - 1)
                    if math.hypot(dx / 5.5, dy / 7.5) < 1 and (s * dx) > -4: extra[y, x] = True
    full = body | extra
    for y in range(S):
        for x in range(S):
            if not full[y, x]: continue
            if extra[y, x] and not body[y, x]: R[y, x] = 9; continue
            dx, dy = (x - cx) / 13, (y - cy) / 13
            l = -dx * 0.55 - dy * 0.75                       # šviesa iš viršaus kairės
            # atstumas iki krašto
            e = min([k for k in range(1, 6) if not all(0 <= y + oy * k < S and 0 <= x + ox * k < S and full[y + oy * k, x + ox * k] for ox, oy in ((1, 0), (0, 1), (1, 1)))] or [6])
            t = 2
            if l > 0.55: t = 1
            if l < -0.25 or e <= 2: t = 3
            if l < -0.62 and e <= 3: t = 4
            R[y, x] = t
    # pilvas (šviesesnė dėmė)
    for y in range(S):
        for x in range(S):
            if body[y, x] and math.hypot((x - cx) / 6.2, (y - (cy + 5)) / 5.2) < 1 and R[y, x] in (2, 3): R[y, x] = 10
    # kontūras: 1 px išorėje
    out = R.copy()
    for y in range(S):
        for x in range(S):
            if not full[y, x] and any(0 <= y + oy < S and 0 <= x + ox < S and full[y + oy, x + ox] for ox, oy in ((1, 0), (-1, 0), (0, 1), (0, -1))): out[y, x] = 5
    R = out
    # veidas
    ex, ey = 5, int(cy - 1)
    for s in (-1, 1):
        x0 = int(round(cx + s * ex)) - (1 if s < 0 else 0)
        for yy in range(ey, ey + 4):
            for xx in (x0, x0 + 1): R[yy, xx] = 6
        R[ey, x0] = 7
        for xx in (x0 + (-3 if s < 0 else 2), x0 + (-2 if s < 0 else 3)): R[ey + 5, xx] = 8
    mx = int(round(cx)); R[ey + 5, mx] = 6; R[ey + 5, mx + 1] = 6; R[ey + 4, mx - 1] = 6; R[ey + 4, mx + 2] = 6
    # kojytės
    for s in (-1, 1):
        fx = int(round(cx + s * 5))
        ys = [y for y in range(S) if R[y, fx] in (1, 2, 3, 4, 10)]
        if ys:
            yb = max(ys)
            for xx in (fx - 1, fx, fx + 1): R[yb, xx] = 4
    ys, xs = np.where(R > 0)
    return R[ys.min():ys.max() + 1, xs.min():xs.max() + 1]
SHAPES = ['round', 'tall', 'wide', 'spiky', 'cloud', 'drop', 'eared', 'bunny', 'horn', 'wing', 'flame']
out = {}
def col(R, base):
    b = np.array(base, float); lav = np.array((120, 104, 168.0))
    pal = {1: b + (255 - b) * .42, 2: b, 3: b * .8 + lav * .12, 4: b * .58 + lav * .2, 5: (44, 46, 86), 6: (34, 30, 52), 7: (255, 255, 255), 8: (255, 150, 170), 9: b * .68 + lav * .1, 10: b + (255 - b) * .62}
    a = np.zeros((*R.shape, 4), np.uint8)
    for k, c in pal.items(): a[R == k] = (*[int(max(0, min(255, v))) for v in c], 255)
    return Image.fromarray(a, 'RGBA')
sheet = Image.new('RGBA', (len(SHAPES) * 50 * 3, 60 * 3), (104, 126, 166, 255))
COLS = [(246, 236, 222), (240, 170, 110), (196, 150, 110), (250, 170, 196), (150, 210, 230), (120, 200, 140), (236, 236, 244), (232, 222, 210), (110, 196, 150), (176, 140, 110), (255, 150, 70)]
for i, sh in enumerate(SHAPES):
    R = blob(sh)
    out[sh] = {'w': int(R.shape[1]), 'h': int(R.shape[0]), 'data': base64.b64encode(R.astype(np.uint8).tobytes()).decode()}
    im = col(R, COLS[i]); im = im.resize((im.width * 3, im.height * 3), Image.NEAREST)
    sheet.alpha_composite(im, (i * 150 + (150 - im.width) // 2, 180 - im.height - 6))
json.dump(out, open('pets.json', 'w')); sheet.save('pets-sheet.png'); print({k: (v['w'], v['h']) for k, v in out.items()})
