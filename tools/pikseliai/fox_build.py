# Lapės (kitsunės) pikselinis herojus: iš originalo 59×71 → sritys (kailis, kimono, obi, liepsnos, kontūrai) → 2× tinklelis su plonais kontūrais
# → indeksuotas paveikslas (paletės įrašas = sritis + tonas), kurį naršyklė perdažo.
import numpy as np, colorsys, json, base64
from collections import deque
from PIL import Image
nat = np.load('nat.npy').astype(float)
H, W, _ = nat.shape
lum = 0.3*nat[...,0] + 0.59*nat[...,1] + 0.11*nat[...,2]
def hsv(c): return colorsys.rgb_to_hsv(*(c/255))
SAT = np.array([[hsv(nat[y, x])[1] for x in range(W)] for y in range(H)])
reddish = (nat[...,0] > nat[...,1] + 35) & (nat[...,0] > nat[...,2] + 25)
dark = (lum < 95) & ~reddish
bg = np.zeros((H, W), bool); q = deque([(x, y) for x in range(W) for y in (0, H-1)] + [(x, y) for y in range(H) for x in (0, W-1)])
while q:
    x, y = q.popleft()
    if not (0 <= x < W and 0 <= y < H) or bg[y, x] or dark[y, x] or nat[y, x].min() < 240: continue
    bg[y, x] = True; q.extend([(x+1, y), (x-1, y), (x, y+1), (x, y-1)])
kim = np.load('mask.npy') & (SAT < 0.3) & ~dark
R = np.full((H, W), '', object)   # sritis
for y in range(H):
    for x in range(W):
        c = nat[y, x]
        if bg[y, x]: R[y, x] = 'bg'
        elif dark[y, x]: R[y, x] = 'line'
        elif kim[y, x]: R[y, x] = 'kim'
        elif c[0] > 195 and 130 < c[1] < 200 and c[2] < 110: R[y, x] = 'bell'
        elif reddish[y, x]:
            if 41 <= y <= 58 and 19 <= x <= 36: R[y, x] = 'obi'
            elif 25 <= y <= 31 and 21 <= x <= 34: R[y, x] = 'collar'
            elif c[1] < 100 and c[0] > 140: R[y, x] = 'mark'
            else: R[y, x] = 'pink'
        elif c[2] > c[0] + 25 and SAT[y, x] >= 0.2: R[y, x] = 'acc'
        else: R[y, x] = 'fur'
# Atskiros liepsnos (nesusijungusios su kūnu) - visos „acc“, net šviesūs blyksniai
comp = np.zeros((H, W), int); n = 0
for y in range(H):
    for x in range(W):
        if R[y, x] != 'bg' and not comp[y, x]:
            n += 1; q = deque([(x, y)])
            while q:
                a, b = q.popleft()
                if not (0 <= a < W and 0 <= b < H) or comp[b, a] or R[b, a] == 'bg': continue
                comp[b, a] = n; q.extend([(a+1, b), (a-1, b), (a, b+1), (a, b-1)])
sizes = {i: (comp == i).sum() for i in range(1, n+1)}; body = max(sizes, key=sizes.get)
for y in range(H):
    for x in range(W):
        if comp[y, x] and comp[y, x] != body and R[y, x] not in ('line',): R[y, x] = 'acc'
print('components', sizes)

# Tonai: kiekvienoje srityje - keli tonai pagal šviesumą (k-vidurkiai)
def kmeans(cols, k):
    cols = np.array(cols, float)
    order = np.argsort(0.3*cols[:,0]+0.59*cols[:,1]+0.11*cols[:,2])[::-1]
    cen = cols[order[np.linspace(0, len(cols)-1, k).astype(int)]]
    for _ in range(25):
        d = ((cols[:, None] - cen[None]) ** 2).sum(2); a = d.argmin(1)
        for i in range(k):
            if (a == i).any(): cen[i] = cols[a == i].mean(0)
    cen = cen[np.argsort(-(0.3*cen[:,0]+0.59*cen[:,1]+0.11*cen[:,2]))]
    return cen
K = {'fur': 7, 'acc': 6, 'obi': 3, 'collar': 2, 'bell': 2, 'pink': 3, 'mark': 2, 'kim': 5}
CEN = {r: kmeans([nat[y, x] for y in range(H) for x in range(W) if R[y, x] == r], k) for r, k in K.items()}
TONE = np.zeros((H, W), int)
for y in range(H):
    for x in range(W):
        r = R[y, x]
        if r in CEN: TONE[y, x] = ((CEN[r] - nat[y, x]) ** 2).sum(1).argmin()
# Kimono raštas ir kraštas
for y in range(H):
    for x in range(W):
        if kim[y, x] and (x + 2*y) % 7 == 0 and y % 3 == 0 and TONE[y, x] <= 2 and 33 <= y <= 58 and kim[y-1, x] and kim[y+1, x] and kim[y, x-1] and kim[y, x+1]:
            R[y, x] = 'petal'; TONE[y, x] = 0
for x in range(W):
    col = np.where(kim[:, x])[0]
    if len(col) and col.max() >= 55: R[col.max(), x] = 'hem'; TONE[col.max(), x] = 0

def kind(x, y):
    if not (0 <= x < W and 0 <= y < H) or R[y, x] == 'bg': return 'bg'
    return 'out' if R[y, x] == 'line' else 'in'
face = np.zeros((H, W), bool); face[10:25, 19:35] = True
# 2× tinklelis: (sritis, tonas)
UP = [[None]*(2*W) for _ in range(2*H)]
for y in range(H):
    for x in range(W):
        if R[y, x] == 'bg': continue
        if R[y, x] != 'line':
            for j in (0, 1):
                for i in (0, 1): UP[2*y+j][2*x+i] = (R[y, x], int(TONE[y, x]))
            continue
        L_, R_, T_, B_ = kind(x-1, y), kind(x+1, y), kind(x, y-1), kind(x, y+1)
        def keep(a, b):
            if a == 'out' or b == 'out' or (a == 'bg' and b == 'bg') or (a == 'in' and b == 'in' and face[y, x]): return {0, 1}
            if a == 'bg': return {0}
            if b == 'bg': return {1}
            return {0}
        kh, kv = keep(L_, R_), keep(T_, B_)
        if len(kh) == 1 and len(kv) == 1 and 'out' in [kind(x+dx, y+dy) for dx, dy in ((-1,-1),(1,-1),(-1,1),(1,1))]: kv = {0, 1}
        neigh = [R[yy, xx] for xx, yy in ((x-1, y), (x+1, y), (x, y-1), (x, y+1)) if kind(xx, yy) == 'in']
        if 'bg' in (L_, R_, T_, B_) or not neigh: lr = ('out', 0)
        elif face[y, x]: lr = ('face', 0)
        elif any(r in ('kim', 'petal', 'hem') for r in neigh): lr = ('kline', 0)
        elif all(r == 'acc' for r in neigh): lr = ('aline', 0)
        elif any(r == 'obi' for r in neigh): lr = ('oline', 0)
        else: lr = ('fline', 0)
        for j in (0, 1):
            for i in (0, 1):
                if i in kh and j in kv: v = lr
                else:
                    src = ((x-1, y) if i == 0 else (x+1, y)) if i not in kh else ((x, y-1) if j == 0 else (x, y+1))
                    v = (R[src[1]][src[0]], int(TONE[src[1], src[0]])) if kind(*src) == 'in' else lr
                UP[2*y+j][2*x+i] = v
# Paletė
FIX = {'out': [(44, 46, 86)], 'face': [(30, 38, 76)], 'fline': [(122, 110, 150)], 'kline': [(20, 60, 56)], 'aline': [(52, 86, 128)], 'oline': [(92, 26, 34)],
       'petal': [(255, 196, 214)], 'hem': [(240, 186, 64)]}
pal = [None]; idx = {}
def pi(r, t):
    if (r, t) not in idx:
        c = FIX[r][0] if r in FIX else tuple(int(v) for v in CEN[r][t])
        idx[(r, t)] = len(pal); pal.append([r, t, *c])
    return idx[(r, t)]
data = bytearray()
for row in UP:
    for v in row: data.append(pi(*v) if v else 0)
# Apkarpyti tuščius kraštus
arr = np.frombuffer(bytes(data), np.uint8).reshape(2*H, 2*W)
ys, xs = np.where(arr > 0); y0, y1, x0, x1 = ys.min(), ys.max()+1, xs.min(), xs.max()+1
arr = arr[y0:y1, x0:x1]
out = {'w': int(arr.shape[1]), 'h': int(arr.shape[0]), 'pal': pal, 'tones': {r: int(k) for r, k in K.items()}, 'data': base64.b64encode(arr.tobytes()).decode()}
json.dump(out, open('fox.json', 'w'))
print(out['w'], out['h'], len(pal), 'palette entries')
# Peržiūra
rgb = np.zeros((*arr.shape, 4), np.uint8)
for i, p in enumerate(pal):
    if p: rgb[arr == i] = (*p[2:], 255)
Image.fromarray(rgb, 'RGBA').resize((arr.shape[1]*4, arr.shape[0]*4), Image.NEAREST).save('fox-preview.png')
