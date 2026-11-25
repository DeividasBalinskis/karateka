# Pikselinio meno įrankiai koncepcijai: formos -> sluoksniai su šešėliais, vienas kontūras (navy), detalės iš ASCII.
import math
import numpy as np
from PIL import Image, ImageDraw

NAVY = (44, 46, 86)


def hx(s):
    s = s.lstrip('#')
    return tuple(int(s[i:i + 2], 16) for i in (0, 2, 4))


def ramp(*cols):
    return [hx(c) for c in cols]


class Spr:
    """Spritas: sluoksniai piešiami iš galo į priekį; kiekvienas turi kaukę ir 4 tonų rampą (šviesus, bazinis, šešėlis, gilus)."""

    def __init__(s, w, h):
        s.w, s.h = w, h
        s.X, s.Y = np.meshgrid(np.arange(w) + 0.5, np.arange(h) + 0.5)
        s.rgb = np.zeros((h, w, 3), np.uint8)
        s.lay = np.full((h, w), -1, np.int32)
        s.info = []
        s.stamps = []

    # ---- formos (grąžina bool kaukę) ----
    def ell(s, cx, cy, rx, ry, ang=0.0):
        dx, dy = s.X - cx, s.Y - cy
        if ang:
            ca, sa = math.cos(ang), math.sin(ang)
            dx, dy = dx * ca + dy * sa, -dx * sa + dy * ca
        return (dx / rx) ** 2 + (dy / ry) ** 2 <= 1.0

    def cap(s, pts, radii):
        m = np.zeros((s.h, s.w), bool)
        for (p0, p1), (r0, r1) in zip(zip(pts, pts[1:]), zip(radii, radii[1:])):
            vx, vy = p1[0] - p0[0], p1[1] - p0[1]
            L2 = vx * vx + vy * vy or 1e-6
            t = np.clip(((s.X - p0[0]) * vx + (s.Y - p0[1]) * vy) / L2, 0, 1)
            d = np.hypot(s.X - (p0[0] + t * vx), s.Y - (p0[1] + t * vy))
            m |= d <= r0 + (r1 - r0) * t
        return m

    def poly(s, pts):
        im = Image.new('L', (s.w, s.h), 0)
        ImageDraw.Draw(im).polygon([(x, y) for x, y in pts], fill=1)
        return np.array(im, bool)

    def rect(s, x0, y0, x1, y1):
        return (s.X >= x0) & (s.X < x1) & (s.Y >= y0) & (s.Y < y1)

    # ---- sluoksniai ----
    def add(s, mask, rp, sep='navy', blur=2, vert=0.35, light=(-0.6, -0.8), flat=False, dither=True):
        """sep: kaip atskirti nuo už jo esančių sluoksnių ('navy', 'dark' arba None)."""
        idx = len(s.info)
        s.info.append({'rp': rp, 'sep': sep})
        if not mask.any():
            return idx
        if flat:
            tone = np.ones((s.h, s.w), np.int32)
        else:
            m = mask.astype(float)
            k = blur
            pad = np.pad(m, k, mode='constant')
            b = np.zeros_like(m)
            for dy in range(-k, k + 1):
                for dx in range(-k, k + 1):
                    b += pad[k + dy:k + dy + s.h, k + dx:k + dx + s.w]
            b /= (2 * k + 1) ** 2
            gy, gx = np.gradient(b)
            n = np.hypot(gx, gy) + 1e-6
            lx, ly = light
            ln = math.hypot(lx, ly)
            L = (-gx * lx - gy * ly) / n / ln * np.clip(n * 6, 0, 1)
            ys = s.Y[mask]
            y0, y1 = ys.min(), ys.max()
            L = L - vert * ((s.Y - y0) / max(1.0, y1 - y0) - 0.45) * 2
            bayer = np.array([[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]]) / 16.0 - 0.5
            jit = bayer[(s.Y - 0.5).astype(int) % 4, (s.X - 0.5).astype(int) % 4] * (0.18 if dither else 0)
            Lj = L + jit
            tone = np.where(Lj > 0.42, 0, np.where(Lj > -0.28, 1, np.where(Lj > -0.75, 2, 3)))
        cols = np.array(rp, np.uint8)
        s.rgb[mask] = cols[tone[mask]]
        s.lay[mask] = idx
        return idx

    def stamp(s, x, y, rows, pal, flip=False):
        """ASCII detalė (nupiešiama po kontūrų). pal: raidė -> spalva (tuple) arba None (permatoma)."""
        s.stamps.append((int(round(x)), int(round(y)), rows, pal))

    def line(s, x0, y0, x1, y1, col):
        """Plona linija (detalė po kontūrų)."""
        n = int(max(abs(x1 - x0), abs(y1 - y0))) or 1
        for i in range(n + 1):
            s.stamps.append((int(round(x0 + (x1 - x0) * i / n)), int(round(y0 + (y1 - y0) * i / n)), ['#'], {'#': col}))

    def dot(s, x, y, col):
        s.stamps.append((int(x), int(y), ['#'], {'#': col}))

    def render(s, outline=NAVY):
        rgb = s.rgb.copy()
        lay = s.lay
        a = lay >= 0
        # vidinės linijos: galinio sluoksnio pikselis šalia priekinio su sep
        for dy, dx in ((0, 1), (0, -1), (1, 0), (-1, 0)):
            nb = np.full_like(lay, -1)
            ys = slice(max(0, dy), s.h + min(0, dy))
            yd = slice(max(0, -dy), s.h + min(0, -dy))
            xs = slice(max(0, dx), s.w + min(0, dx))
            xd = slice(max(0, -dx), s.w + min(0, -dx))
            nb[yd, xd] = lay[ys, xs]
            front = (nb > lay) & a
            for j, inf in enumerate(s.info):
                if not inf['sep']:
                    continue
                sel = front & (nb == j)
                if not sel.any():
                    continue
                if inf['sep'] == 'navy':
                    rgb[sel] = outline
                else:
                    # tamsesnis galinio sluoksnio tonas
                    for i, inf2 in enumerate(s.info):
                        sel2 = sel & (lay == i)
                        if sel2.any():
                            rgb[sel2] = inf2['rp'][3]
        alpha = a.copy()
        # išorinis kontūras
        out = np.zeros_like(a)
        out[:, 1:] |= a[:, :-1]
        out[:, :-1] |= a[:, 1:]
        out[1:, :] |= a[:-1, :]
        out[:-1, :] |= a[1:, :]
        out &= ~a
        rgb[out] = outline
        alpha |= out
        im = np.dstack([rgb, (alpha * 255).astype(np.uint8)])
        for (x0, y0, rows, pal) in s.stamps:
            for j, row in enumerate(rows):
                for i, ch in enumerate(row):
                    if ch in pal and pal[ch] is not None:
                        xx, yy = x0 + i, y0 + j
                        if 0 <= xx < s.w and 0 <= yy < s.h:
                            c = pal[ch]
                            im[yy, xx] = (c[0], c[1], c[2], 255 if len(c) == 3 else c[3])
        return Image.fromarray(im, 'RGBA')


def flip(im):
    return im.transpose(Image.FLIP_LEFT_RIGHT)


def up(im, k):
    return im.resize((im.width * k, im.height * k), Image.NEAREST)


def sheet(frames, k=1, gap=0, bg=None):
    w = sum(f.width for f in frames) + gap * (len(frames) - 1)
    h = max(f.height for f in frames)
    sh = Image.new('RGBA', (w, h), bg or (0, 0, 0, 0))
    x = 0
    for f in frames:
        sh.alpha_composite(f, (x, 0))
        x += f.width + gap
    return up(sh, k) if k > 1 else sh


def whiten(im, col=(255, 255, 255)):
    a = np.array(im)
    m = a[..., 3] > 0
    a[m, :3] = col
    return Image.fromarray(a, 'RGBA')
