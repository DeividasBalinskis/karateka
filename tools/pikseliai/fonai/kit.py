# Pikselinių fonų įrankiai: dithered gradientai, kalnai, medžiai, vanduo, šventyklos. Raiška 320×180.
import numpy as np, math, random
from PIL import Image
W, H = 320, 180
B4 = np.array([[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]]) / 16.0 + 1 / 32
def hx(s): s = s.lstrip('#'); return (int(s[0:2], 16), int(s[2:4], 16), int(s[4:6], 16))
class Scene:
    def __init__(s, seed=1):
        s.im = np.zeros((H, W, 3), np.uint8); s.rnd = random.Random(seed)
    def px(s, x, y, c):
        x, y = int(x), int(y)
        if 0 <= x < W and 0 <= y < H: s.im[y, x] = c
    def get(s, x, y): return tuple(s.im[int(y), int(x)])
    def rect(s, x0, y0, x1, y1, c): s.im[max(0, int(y0)):max(0, int(y1)), max(0, int(x0)):max(0, int(x1))] = c
    def grad(s, stops, y0=0, y1=H, x0=0, x1=W, tw=0.4):
        """Vertikalus gradientas per spalvų juostas su Bayer perėjimais. stops: [(t, '#hex'), ...]"""
        cols = [hx(c) for _, c in stops]; ts = [t for t, _ in stops]
        for y in range(int(y0), int(y1)):
            t = (y - y0) / max(1, y1 - y0 - 1)
            i = max(0, min(len(ts) - 2, sum(1 for v in ts if v <= t) - 1))
            f = (t - ts[i]) / max(1e-6, ts[i + 1] - ts[i])
            g = (f - (1 - tw)) / tw            # aiškios juostos, „dither“ tik siaurame perėjime
            for x in range(int(x0), int(x1)): s.im[y, x] = cols[i + 1] if g > B4[y % 4, x % 4] else cols[i]
    def disc(s, cx, cy, r, c, mask=None):
        for y in range(int(cy - r - 1), int(cy + r + 2)):
            for x in range(int(cx - r - 1), int(cx + r + 2)):
                if (x - cx) ** 2 + (y - cy) ** 2 <= r * r and (mask is None or mask(x, y)): s.px(x, y, c)
    def glow(s, cx, cy, r0, r1, c, strength=0.6, mask=None):
        """Švytėjimas: dithered pereinamasis žiedas (paveikia tik esamas spalvas link c)"""
        c = np.array(hx(c) if isinstance(c, str) else c, float)
        for y in range(int(cy - r1), int(cy + r1 + 1)):
            for x in range(int(cx - r1), int(cx + r1 + 1)):
                if not (0 <= x < W and 0 <= y < H): continue
                d = math.hypot(x - cx, y - cy)
                if d < r0 or d > r1 or (mask and not mask(x, y)): continue
                t = (1 - (d - r0) / (r1 - r0)) * strength
                if t > B4[y % 4, x % 4]: s.im[y, x] = (s.im[y, x] * 0.45 + c * 0.55).astype(np.uint8)
    def poly(s, pts, c):
        """Užpildytas daugiakampis (scanline)"""
        c = hx(c) if isinstance(c, str) else c
        ys = [p[1] for p in pts]
        for y in range(max(0, int(min(ys))), min(H, int(max(ys)) + 1)):
            xs = []
            for (x0, y0), (x1, y1) in zip(pts, pts[1:] + pts[:1]):
                if (y0 <= y < y1) or (y1 <= y < y0): xs.append(x0 + (y - y0) * (x1 - x0) / (y1 - y0))
            xs.sort()
            for a, b in zip(xs[::2], xs[1::2]): s.rect(round(a), y, round(b), y + 1, c)
    def ridge(s, base, amp, rough, c, seed=0, top=None, shade=None, step=1.0, x0=0, x1=W):
        """Kalnų ketera (vidurinio taško poslinkis); užpildo žemyn. top - šviesus kraštas, shade - šešėlinės nuolydžio pusės."""
        r = random.Random(seed); n = 512; h = [0.0] * (n + 1); h[0] = r.uniform(-1, 1); h[n] = r.uniform(-1, 1)
        st, sc = n, 1.0
        while st > 1:
            for i in range(0, n, st):
                m = i + st // 2; h[m] = (h[i] + h[i + st]) / 2 + r.uniform(-1, 1) * sc
            st //= 2; sc *= rough
        hs = []
        for x in range(W): hs.append(base - (h[int(x / W * n)]) * amp)
        c = hx(c) if isinstance(c, str) else c
        for x in range(int(x0), int(x1)):
            yt = int(round(hs[x]))
            for y in range(max(0, yt), H): s.im[y, x] = c
            if top: s.px(x, yt, hx(top))
            if shade and x > 0 and hs[x] > hs[x - 1] + 0.3:
                for y in range(max(0, yt + 1), min(H, yt + 1 + int(amp * 0.25))):
                    if B4[y % 4, x % 4] < 0.55 - (y - yt) * 0.03: s.im[y, x] = hx(shade)
        return hs
    def mist(s, y0, y1, c, maxa=0.5, invert=False):
        c = np.array(hx(c), float)
        for y in range(int(y0), int(y1)):
            t = (y - y0) / max(1, y1 - y0); a = (t if not invert else 1 - t) * maxa
            for x in range(W):
                if a > B4[y % 4, x % 4]: s.im[y, x] = (s.im[y, x] * 0.4 + c * 0.6).astype(np.uint8)
    def reflect(s, ywater, y1=H, dark=0.72, tint='#000000', ripple=1.5):
        tint = np.array(hx(tint), float)
        for y in range(int(ywater), int(y1)):
            sy = int(2 * ywater - y - 1)
            if sy < 0: continue
            off = int(round(math.sin(y * 0.9) * ripple * ((y - ywater) / (y1 - ywater) + 0.3)))
            for x in range(W):
                sx = min(W - 1, max(0, x + off)); c = s.im[sy, sx].astype(float)
                s.im[y, x] = (c * dark + tint * (1 - dark)).astype(np.uint8)
    def waves(s, y0, y1, c, c2=None, seed=4, dens=0.5, x0=0, x1=W, only=None):
        """Ramaus vandens bangelės: trumpos horizontalios linijos, tankėja link kranto"""
        r = random.Random(seed); c = hx(c); c2 = hx(c2) if c2 else c
        for y in range(int(y0) + 1, int(y1), 2):
            t = (y - y0) / max(1, y1 - y0)
            for _ in range(int(W / 14 * dens * (0.4 + t))):
                x = r.randint(int(x0), int(x1) - 1); L = r.randint(3, 6 + int(t * 8))
                for i in range(L):
                    if x + i < x1 and (only is None or (0 <= x + i < W and tuple(s.im[y, x + i]) == only)): s.px(x + i, y, c if i < L - 2 else c2)
    def streaks(s, ywater, y1, c, n=40, seed=3):
        r = random.Random(seed); c = hx(c)
        for _ in range(n):
            y = r.randint(int(ywater) + 1, int(y1) - 1); x = r.randint(0, W - 1); L = r.randint(2, 9)
            for i in range(L): s.px(x + i, y, c)
    def stars(s, n, y1, cols, seed=5, mask=None):
        r = random.Random(seed)
        for _ in range(n):
            x, y = r.randint(0, W - 1), r.randint(0, int(y1)); c = hx(r.choice(cols))
            if mask and not mask(x, y): continue
            s.px(x, y, c)
            if r.random() < 0.12: s.px(x + 1, y, c); s.px(x - 1, y, c); s.px(x, y + 1, c); s.px(x, y - 1, c)
    def line(s, x0, y0, x1, y1, c):
        n = int(max(abs(x1 - x0), abs(y1 - y0))) or 1
        for i in range(n + 1): s.px(round(x0 + (x1 - x0) * i / n), round(y0 + (y1 - y0) * i / n), c)
    # ---- objektai ----
    def pine(s, x, yb, h, c, c2=None):
        c = hx(c); c2 = hx(c2) if c2 else c
        s.rect(x, yb - h * 0.25, x + 1, yb, c)
        for i in range(int(h)):
            w = (i / h) * h * 0.38 * (0.75 + 0.25 * ((int(h) - i) % 5) / 5)
            y = yb - h + i
            for xx in range(int(x - w), int(x + w) + 1): s.px(xx, y, c2 if xx > x + w * 0.35 and B4[y % 4, xx % 4] < 0.5 else c)
    def blob_tree(s, x, yb, r, cols, trunk='#3A2420', seed=0, n=14):
        """Lapuotis medis (sakura, klevas): kamienas + gumulų debesis su 3-4 tonais (šviesa iš viršaus kairės)"""
        rr = random.Random(seed); cols = [hx(c) for c in cols]; tr = hx(trunk)
        for i in range(int(r * 1.2)): s.rect(x - 1 + (i % 3 == 0), yb - i, x + 2, yb - i + 1, tr)
        s.line(x, yb - r * 0.9, x - r * 0.5, yb - r * 1.4, tr); s.line(x + 1, yb - r * 0.8, x + r * 0.6, yb - r * 1.3, tr)
        cx, cy = x, yb - r * 1.6
        pts = [(cx + rr.uniform(-r, r), cy + rr.uniform(-r * 0.55, r * 0.5), rr.uniform(r * 0.35, r * 0.6)) for _ in range(n)]
        for (bx, by, br) in pts:
            for yy in range(int(by - br), int(by + br) + 1):
                for xx in range(int(bx - br), int(bx + br) + 1):
                    d = math.hypot(xx - bx, yy - by)
                    if d > br: continue
                    l = ((xx - bx) * -0.6 + (yy - by) * -0.8) / br + (cy - yy) / (r * 2.2)
                    k = 1 if l > 0.45 else (2 if l > -0.1 else 3)
                    if k == 2 and B4[yy % 4, xx % 4] < 0.25 and l > 0.2: k = 1
                    if d > br - 1.2 and l < 0: k = min(len(cols) - 1, k + 1)
                    s.px(xx, yy, cols[min(k, len(cols) - 1)] if k < len(cols) else cols[-1])
        return pts
    def torii(s, x, yb, h, c='#C8372D', cd='#7A1E1E', top='#2A1A1A'):
        c, cd, top = hx(c), hx(cd), hx(top); w = int(h * 1.05)
        for px_ in (x - w // 2 + 3, x + w // 2 - 5):
            s.rect(px_, yb - h, px_ + 3, yb, c); s.rect(px_ + 2, yb - h, px_ + 3, yb, cd)
            s.rect(px_ - 1, yb - 2, px_ + 4, yb, top)
        s.rect(x - w // 2 - 4, yb - h - 4, x + w // 2 + 4, yb - h, top)
        s.rect(x - w // 2 - 6, yb - h - 5, x - w // 2 - 3, yb - h - 3, top); s.rect(x + w // 2 + 3, yb - h - 5, x + w // 2 + 6, yb - h - 3, top)
        s.rect(x - w // 2 - 2, yb - h, x + w // 2 + 2, yb - h + 3, c); s.rect(x - w // 2 - 2, yb - h + 2, x + w // 2 + 2, yb - h + 3, cd)
        s.rect(x - w // 2, yb - h + int(h * 0.3), x + w // 2, yb - h + int(h * 0.3) + 3, c)
        s.rect(x - 2, yb - h + 3, x + 2, yb - h + int(h * 0.3), c)
    def pagoda(s, x, yb, h, wall='#E9D8B8', roof='#2E2A3A', trim='#C8372D', floors=3, light='#FFD27A'):
        wall, roof, trim, light = hx(wall), hx(roof), hx(trim), hx(light)
        fh = h / (floors + 0.6); y = yb
        for f in range(floors):
            w = h * 0.42 * (1 - f * 0.18)
            s.rect(x - w * 0.62, y - fh * 0.62, x + w * 0.62, y, wall)
            s.rect(x - w * 0.62, y - fh * 0.62, x - w * 0.5, y, trim); s.rect(x + w * 0.5, y - fh * 0.62, x + w * 0.62, y, trim)
            s.rect(x - 2, y - fh * 0.45, x + 2, y - fh * 0.12, light)
            ry = y - fh * 0.62
            for i in range(int(fh * 0.38)):
                ww = w + 4 - i * (w * 0.9 / (fh * 0.38))
                s.rect(x - ww, ry - i, x + ww, ry - i + 1, roof)
            s.px(x - w - 4, ry + 1, roof); s.px(x + w + 4, ry + 1, roof); s.px(x - w - 5, ry, roof); s.px(x + w + 4, ry, roof)
            y = ry - fh * 0.38 + 1
        s.rect(x, y - h * 0.18, x + 1, y, roof)
    def lantern(s, x, yb, c='#A8A29A', cd='#6E6A66', light='#FFD27A'):
        c, cd, light = hx(c), hx(cd), hx(light)
        s.rect(x - 4, yb - 2, x + 5, yb, cd); s.rect(x - 1, yb - 9, x + 2, yb - 2, c)
        s.rect(x - 4, yb - 14, x + 5, yb - 9, c); s.rect(x - 2, yb - 13, x + 3, yb - 10, light)
        s.rect(x - 6, yb - 16, x + 7, yb - 14, cd); s.rect(x - 1, yb - 18, x + 2, yb - 16, cd)
    def petals(s, n, cols, seed=9, y0=0, y1=H):
        r = random.Random(seed); cols = [hx(c) for c in cols]
        for _ in range(n):
            x, y = r.randint(0, W - 1), r.randint(int(y0), int(y1) - 1); c = r.choice(cols)
            s.px(x, y, c)
            if r.random() < 0.5: s.px(x + 1, y, c)
    def save(s, path, scale=1):
        Image.fromarray(s.im).save(path, optimize=True)
        if scale > 1: Image.fromarray(s.im).resize((W * scale, H * scale), Image.NEAREST).save(path.replace('.png', f'-x{scale}.png'))
