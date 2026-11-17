# Personažai iš paveikslo (5×5 ant juodo fono) → atskiri PNG/WebP su permatomu fonu. Piešinys nekeičiamas.
import numpy as np, sys
from PIL import Image
from collections import deque
SRC = sys.argv[1]
im = np.array(Image.open(SRC).convert('RGB')).astype(float)
H, W, _ = im.shape
B = np.array([11, 12, 16.0])
d = np.abs(im - B).max(2)
def shift_or(m, r):
    out = m.copy()
    for dy in range(-r, r + 1):
        for dx in range(-r, r + 1):
            if dx * dx + dy * dy > r * r: continue
            s = np.zeros_like(m)
            ys, yd = (slice(dy, None), slice(0, -dy if dy else None)) if dy >= 0 else (slice(0, dy), slice(-dy, None))
            xs, xd = (slice(dx, None), slice(0, -dx if dx else None)) if dx >= 0 else (slice(0, dx), slice(-dx, None))
            s[ys, xs] = m[yd, xd]; out |= s
    return out
dark = d < 20
# „Platus“ fonas: taškai, aplink kuriuos 3 px spinduliu viskas tamsu - pro ploną šešėlį į personažo vidų nepratekama
wide = ~shift_or(~dark, 3)
# užpildymas nuo kraštų per „platų“ foną
bg = np.zeros((H, W), bool)
seed = np.zeros((H, W), bool); seed[0, :] = seed[-1, :] = seed[:, 0] = seed[:, -1] = True
# Uždari tamsūs „langai“ viduje (aura, uodegos kilpa): didelės vienodai tamsios vietos - irgi fonas
seed |= ~shift_or(~(d < 13), 6)
cur = seed & wide
while True:
    nxt = shift_or(cur, 1) & wide
    if (nxt == cur).all(): break
    cur = nxt
bg = cur
# priartėti prie krašto: 3 px išplėsti per tamsius taškus
for _ in range(3): bg = shift_or(bg, 1) & dark | bg
fg = ~bg
# komponentai → langeliai pagal centrą
lab = np.zeros((H, W), np.int32); comps = []
ys, xs = np.nonzero(fg)
for y0, x0 in zip(ys, xs):
    if lab[y0, x0]: continue
    n = len(comps) + 1; q = deque([(y0, x0)]); lab[y0, x0] = n; pts = []
    while q:
        y, x = q.popleft(); pts.append((y, x))
        for yy, xx in ((y+1, x), (y-1, x), (y, x+1), (y, x-1)):
            if 0 <= yy < H and 0 <= xx < W and fg[yy, xx] and not lab[yy, xx]:
                lab[yy, xx] = n; q.append((yy, xx))
    p = np.array(pts); comps.append((len(pts), p[:, 0].mean(), p[:, 1].mean(), p))
cells = {}
for size, cy, cx, p in comps:
    if size < 4: continue
    r, c = min(4, int(cy / (H / 5))), min(4, int(cx / (W / 5)))
    cells.setdefault((r, c), []).append(p)
# minkštas kraštas: 2 px žiedas prie fono - spalva → permatomumas
ring = fg & shift_or(bg, 2)
alpha = np.where(fg, 1.0, 0.0)
c2a = np.clip(((im - B) / (255 - B)).max(2), 0, 1)
soft = ring & (d < 48)
alpha[soft] = np.clip(c2a[soft] * 2.2, 0.15, 1)
rgb = im.copy()
k = alpha[..., None]
rgb = np.where(k > 0, np.clip(B + (im - B) / np.maximum(k, 1e-3), 0, 255), 0)
rgb[~soft] = im[~soft]
out = {}
for (r, c), parts in sorted(cells.items()):
    allp = np.concatenate(parts)
    y0, x0 = allp.min(0); y1, x1 = allp.max(0) + 1
    m = np.zeros((y1 - y0, x1 - x0), bool)
    for p in parts: m[p[:, 0] - y0, p[:, 1] - x0] = True
    m = shift_or(m, 2)
    a = (alpha[y0:y1, x0:x1] * m * 255).astype(np.uint8)
    rgba = np.dstack([rgb[y0:y1, x0:x1].astype(np.uint8), a])
    Image.fromarray(rgba, 'RGBA').save(f'raw-{r}{c}.png')
    out[f'{r}{c}'] = (int(x1 - x0), int(y1 - y0))
print(out)
