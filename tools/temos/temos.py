#!/usr/bin/env python3
"""Svetainės temų fonai (public_html/img/temos/*.svg) - švelnūs, su gylio rūku ir šviesa (v1.49).

Paleidimas:  python3 tools/temos/temos.py            (perrašo visus žemiau aprašytus fonus)
             python3 tools/temos/temos.py kalnai      (tik vieną)
Visi kraštai - lygios kreivės (Catmull-Rom → kubinės Bezier), jokių SVG filtrų (greita ir telefone).
Atsitiktinumas su pastoviu „seed“ - kiekvieną kartą tas pats paveikslas.
"""
import math
import os
import random
import sys

W, H = 1600, 1000
OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'public_html', 'img', 'temos')


# ---------- pagalbinės ----------
def f(n):
    return ('%.1f' % n).rstrip('0').rstrip('.')


def smooth(pts, close_to=None):
    """Taškai → lygi kreivė (Catmull-Rom). close_to = y: uždaroma iki apačios."""
    d = 'M%s %s' % (f(pts[0][0]), f(pts[0][1]))
    for i in range(len(pts) - 1):
        p0 = pts[i - 1] if i > 0 else pts[i]
        p1, p2 = pts[i], pts[i + 1]
        p3 = pts[i + 2] if i + 2 < len(pts) else p2
        c1 = (p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6)
        d += 'C%s %s %s %s %s %s' % (f(c1[0]), f(c1[1]), f(c2[0]), f(c2[1]), f(p2[0]), f(p2[1]))
    if close_to is not None:
        d += 'L%s %sL%s %sZ' % (f(pts[-1][0]), f(close_to), f(pts[0][0]), f(close_to))
    return d


def ridge(rnd, base, amp, n=14, peaks=(), x0=-120, x1=W + 120, jitter=0.35):
    """Kalvų ar kalnų linija: banguojantys sinusai + keli švelnūs smailumai."""
    ph = [rnd.uniform(0, 6.28) for _ in range(3)]
    pts = []
    for i in range(n + 1):
        x = x0 + (x1 - x0) * i / n
        y = base - amp * (0.55 * math.sin(x / 260 + ph[0]) + 0.3 * math.sin(x / 120 + ph[1]) + 0.15 * math.sin(x / 57 + ph[2]))
        y += rnd.uniform(-jitter, jitter) * amp * 0.4
        for px, ph_, pw in peaks:   # (x, aukštis, plotis)
            y -= ph_ * math.exp(-((x - px) / pw) ** 2)
        pts.append((x, y))
    return pts


def y_at(pts, x):
    for (ax, ay), (bx, by) in zip(pts, pts[1:]):
        if ax <= x <= bx:
            t = (x - ax) / (bx - ax) if bx != ax else 0
            t = t * t * (3 - 2 * t)
            return ay + (by - ay) * t
    return pts[-1][1]


class Svg:
    def __init__(self):
        self.defs, self.body, self.n = [], [], 0

    def gid(self, p='g'):
        self.n += 1
        return '%s%d' % (p, self.n)

    def lin(self, stops, x2=0, y2=1, x1=0, y1=0):
        i = self.gid()
        self.defs.append('<linearGradient id="%s" x1="%s" y1="%s" x2="%s" y2="%s">%s</linearGradient>' % (
            i, x1, y1, x2, y2, ''.join('<stop offset="%s" stop-color="%s"%s/>' % (o, c, '' if a == 1 else ' stop-opacity="%s"' % a) for o, c, a in stops)))
        return 'url(#%s)' % i

    def rad(self, stops, cx=.5, cy=.5, r=.5):
        i = self.gid()
        self.defs.append('<radialGradient id="%s" cx="%s" cy="%s" r="%s">%s</radialGradient>' % (
            i, cx, cy, r, ''.join('<stop offset="%s" stop-color="%s"%s/>' % (o, c, '' if a == 1 else ' stop-opacity="%s"' % a) for o, c, a in stops)))
        return 'url(#%s)' % i

    def add(self, s):
        self.body.append(s)

    def out(self, name, bg):
        svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" preserveAspectRatio="xMidYMax slice"><defs>%s</defs>'
               '<rect width="%d" height="%d" fill="%s"/>%s</svg>') % (W, H, ''.join(self.defs), W, H, bg, ''.join(self.body))
        with open(os.path.join(OUT, name + '.svg'), 'w', encoding='utf-8') as fh:
            fh.write(svg)
        return len(svg)


# ---------- bendri elementai ----------
def sky(s, stops):
    s.add('<rect width="%d" height="%d" fill="%s"/>' % (W, H, s.lin(stops)))


def glow(s, x, y, r, color, core=None, core_r=0, a=.55):
    s.add('<circle cx="%s" cy="%s" r="%s" fill="%s"/>' % (x, y, r, s.rad([(0, color, a), (.45, color, a * .35), (1, color, 0)])))
    if core:
        s.add('<circle cx="%s" cy="%s" r="%s" fill="%s"/>' % (x, y, core_r, s.rad([(0, '#FFFFFF', 1), (.7, core, 1), (1, core, .9)])))


def clouds(s, rnd, n, y0, y1, color='#FFFFFF', a=.75, scale=1.0):
    g = s.rad([(0, color, a), (.6, color, a * .7), (1, color, 0)])
    for _ in range(n):
        cx, cy, w = rnd.uniform(-100, W + 100), rnd.uniform(y0, y1), rnd.uniform(160, 340) * scale
        for k in range(rnd.randint(4, 7)):
            s.add('<ellipse cx="%s" cy="%s" rx="%s" ry="%s" fill="%s"/>' % (
                f(cx + rnd.uniform(-w * .5, w * .5)), f(cy + rnd.uniform(-w * .05, w * .06)), f(w * rnd.uniform(.28, .5)), f(w * rnd.uniform(.1, .17)), g))


def layer(s, pts, top, bottom, bottom_y=H):
    s.add('<path d="%s" fill="%s"/>' % (smooth(pts, bottom_y), s.lin([(0, top, 1), (1, bottom, 1)])))


def haze(s, y, h, color, a=.55):
    """Rūko juosta - tolimi sluoksniai šviesėja (gylis)."""
    s.add('<rect x="0" y="%s" width="%d" height="%s" fill="%s"/>' % (f(y - h), W, f(h * 2), s.lin([(0, color, 0), (.5, color, a), (1, color, 0)])))


def round_tree(s, rnd, x, y, size, colors, trunk='#6B4A34'):
    s.add('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"/>' % (f(x - size * .06), f(y - size * .5), f(size * .12), f(size * .55), f(size * .05), trunk))
    for k in range(5):
        c = rnd.choice(colors)
        s.add('<circle cx="%s" cy="%s" r="%s" fill="%s"/>' % (f(x + rnd.uniform(-size * .32, size * .32)), f(y - size * .62 + rnd.uniform(-size * .25, size * .15)), f(size * rnd.uniform(.26, .38)), c))
    s.add('<circle cx="%s" cy="%s" r="%s" fill="#FFFFFF" opacity=".16"/>' % (f(x - size * .14), f(y - size * .78), f(size * .16)))


def pine(s, x, y, h, color, light, snow=None):
    """Eglė iš trijų užapvalintų aukštų (be aštrių kampų)."""
    w = h * .46
    for t in range(3):
        top = y - h + h * t * .27
        bot = y - h * .08 - (2 - t) * h * .2
        ww = w * (.55 + .225 * t)
        d = 'M%s %sQ%s %s %s %sQ%s %s %s %sQ%s %s %s %sZ' % (
            f(x), f(top), f(x + ww * .22), f(bot - (bot - top) * .35), f(x + ww / 2), f(bot),
            f(x), f(bot + h * .07), f(x - ww / 2), f(bot), f(x - ww * .22), f(bot - (bot - top) * .35), f(x), f(top))
        s.add('<path d="%s" fill="%s"/>' % (d, color))
        s.add('<path d="M%s %sQ%s %s %s %sQ%s %s %s %sZ" fill="%s" opacity=".28"/>' % (
            f(x), f(top), f(x + ww * .2), f(bot - (bot - top) * .35), f(x + ww * .42), f(bot - h * .01), f(x + ww * .1), f(bot + h * .02), f(x), f(top), light))
        if snow:
            s.add('<path d="M%s %sQ%s %s %s %sQ%s %s %s %sZ" fill="%s"/>' % (
                f(x - ww * .36), f(bot - (bot - top) * .18), f(x), f(bot - (bot - top) * .42), f(x + ww * .36), f(bot - (bot - top) * .18),
                f(x), f(bot - (bot - top) * .05), f(x - ww * .36), f(bot - (bot - top) * .18), snow))
    s.add('<rect x="%s" y="%s" width="%s" height="%s" rx="1.5" fill="#4A3A2E"/>' % (f(x - h * .025), f(y - h * .1), f(h * .05), f(h * .12)))


def water(s, rnd, y, color_top, color_bottom, shine='#FFFFFF', sun_x=None, n=70):
    s.add('<rect x="0" y="%s" width="%d" height="%s" fill="%s"/>' % (f(y), W, f(H - y), s.lin([(0, color_top, 1), (1, color_bottom, 1)])))
    for _ in range(n):
        yy = rnd.uniform(y + 6, H - 4)
        ww = rnd.uniform(30, 140) * (0.5 + (yy - y) / (H - y))
        s.add('<rect x="%s" y="%s" width="%s" height="%s" rx="1.5" fill="%s" opacity="%s"/>' % (
            f(rnd.uniform(-50, W)), f(yy), f(ww), f(1.5 + (yy - y) / (H - y) * 1.6), shine, f(rnd.uniform(.12, .35))))
    if sun_x is not None:
        for k in range(26):
            yy = y + 6 + k * (H - y - 10) / 26
            ww = 40 + k * 7 + rnd.uniform(-15, 15)
            s.add('<rect x="%s" y="%s" width="%s" height="3" rx="1.5" fill="#FFF6D8" opacity="%s"/>' % (f(sun_x - ww / 2 + rnd.uniform(-10, 10)), f(yy), f(ww), f(.55 - k * .015)))


def stars(s, rnd, n, y1, color='#FFFFFF'):
    for _ in range(n):
        r = rnd.choice([.7, .9, 1.1, 1.4, 1.8])
        s.add('<circle cx="%s" cy="%s" r="%s" fill="%s" opacity="%s"/>' % (f(rnd.uniform(0, W)), f(rnd.uniform(0, y1)), r, color, f(rnd.uniform(.35, .95))))
    for _ in range(max(1, n // 40)):
        x, y = rnd.uniform(0, W), rnd.uniform(0, y1 * .8)
        glow(s, f(x), f(y), 14, '#FFFFFF', a=.5)


def flakes(s, rnd, n, color='#FFFFFF'):
    for _ in range(n):
        s.add('<circle cx="%s" cy="%s" r="%s" fill="%s" opacity="%s"/>' % (f(rnd.uniform(0, W)), f(rnd.uniform(0, H)), f(rnd.uniform(1.2, 3.6)), color, f(rnd.uniform(.4, .9))))


def leaves(s, rnd, n, colors, y0=0, y1=H, size=(5, 11)):
    for _ in range(n):
        x, y, r = rnd.uniform(0, W), rnd.uniform(y0, y1), rnd.uniform(*size)
        s.add('<ellipse cx="%s" cy="%s" rx="%s" ry="%s" fill="%s" opacity="%s" transform="rotate(%d %s %s)"/>' % (
            f(x), f(y), f(r), f(r * .5), rnd.choice(colors), f(rnd.uniform(.55, .95)), rnd.randint(0, 179), f(x), f(y)))


# ---------- temos ----------
def kalnai():
    r, s = random.Random(11), Svg()
    sky(s, [(0, '#8DB8E2', 1), (.55, '#CFE1F2', 1), (1, '#F2F6FB', 1)])
    glow(s, 1240, 230, 260, '#FFF4D6', core='#FFF1C9', core_r=46, a=.75)
    clouds(s, r, 7, 120, 330, a=.7)
    for i, (base, amp, peaks, top, bot) in enumerate([
        (560, 40, [(380, 300, 120), (900, 220, 140), (1300, 260, 120)], '#B6C9DE', '#D4E1EE'),
        (640, 50, [(160, 210, 120), (620, 250, 130), (1120, 200, 150), (1520, 170, 110)], '#8FA9C6', '#B4C7DB'),
        (720, 40, [(420, 150, 160), (980, 170, 140)], '#6E8DAE', '#93AECA')]):
        pts = ridge(r, base, amp, 22, peaks, jitter=.15)
        layer(s, pts, top, bot)
        for px, ph_, pw in peaks:   # švelnios sniego kepurės
            py = y_at(pts, px)
            cap = [(px - pw * .55, py + ph_ * .28), (px - pw * .2, py + ph_ * .1), (px, py - 2), (px + pw * .22, py + ph_ * .12), (px + pw * .5, py + ph_ * .3)]
            s.add('<path d="%s" fill="%s"/>' % (smooth(cap) + 'Q%s %s %s %sZ' % (f(px), f(py + ph_ * .2), f(cap[0][0]), f(cap[0][1])), s.lin([(0, '#FFFFFF', 1), (1, '#E6EEF7', .9)])))
        haze(s, base + 30, 70, '#EAF1F8', .5 - i * .12)
    pts = ridge(r, 800, 22, 18, jitter=.2)
    layer(s, pts, '#4F6F8F', '#3E5A76')
    for k in range(70):
        x = -20 + k * 24 + r.uniform(-8, 8)
        pine(s, x, y_at(pts, x) + r.uniform(4, 16), r.uniform(46, 86), '#2F4A5E', '#7FA0B8')
    water(s, r, 860, '#9EC0DE', '#6E95BA', n=90)
    return s.out('kalnai', '#EEF3F8')


def vulkanas():
    r, s = random.Random(5), Svg()
    sky(s, [(0, '#1A0706', 1), (.45, '#4A120B', 1), (.75, '#A2361A', 1), (1, '#E0652A', 1)])
    stars(s, r, 90, 380, '#FFD9B0')
    glow(s, 800, 470, 520, '#FF7A2E', a=.55)
    cone = [(-150, 1000), (300, 860), (560, 640), (700, 500), (760, 470), (840, 470), (900, 505), (1050, 650), (1300, 860), (1750, 1000)]
    s.add('<path d="%sZ" fill="%s"/>' % (smooth(cone), s.lin([(0, '#3A1410', 1), (.5, '#24100D', 1), (1, '#140908', 1)])))
    s.add('<ellipse cx="800" cy="474" rx="70" ry="16" fill="%s"/>' % s.rad([(0, '#FFE08A', 1), (.5, '#FF8A2A', 1), (1, '#C2401A', .2)]))
    for pts, w in [([(780, 480), (760, 560), (735, 650), (720, 760), (690, 900), (670, 1000)], 16),
                   ([(820, 480), (850, 560), (870, 640), (910, 740), (940, 860), (980, 1000)], 12),
                   ([(800, 480), (805, 600), (790, 700), (815, 820), (800, 1000)], 8)]:
        s.add('<path d="%s" fill="none" stroke="%s" stroke-width="%d" stroke-linecap="round"/>' % (smooth(pts), s.lin([(0, '#FFE6A0', 1), (.4, '#FF8C2B', 1), (1, '#B5321A', .8)]), w))
        s.add('<path d="%s" fill="none" stroke="#FF6A1E" stroke-width="%d" stroke-linecap="round" opacity=".25"/>' % (smooth(pts), w * 3))
    clouds(s, r, 8, 220, 430, color='#3A1A16', a=.75, scale=1.4)
    leaves(s, r, 120, ['#FFB45A', '#FF7A2E', '#FFD27A'], 260, 700, (1.2, 3))
    pts = ridge(r, 930, 30, 16)
    layer(s, pts, '#1A0B09', '#0E0605')
    return s.out('vulkanas', '#140806')


def ziema():
    r, s = random.Random(3), Svg()
    sky(s, [(0, '#BFD5EA', 1), (.6, '#E4EEF7', 1), (1, '#F6F9FC', 1)])
    glow(s, 360, 260, 280, '#FFFFFF', core='#FFF8EA', core_r=40, a=.8)
    clouds(s, r, 6, 140, 320, a=.6)
    for i, (base, amp, top, bot) in enumerate([(600, 60, '#D7E4F0', '#E9F0F7'), (690, 50, '#C7D8EA', '#E2ECF5'), (780, 40, '#E8F0F8', '#FFFFFF')]):
        pts = ridge(r, base, amp, 14)
        layer(s, pts, top, bot)
        if i < 2:
            for k in range(46 if i == 0 else 36):
                x = r.uniform(-20, W + 20)
                pine(s, x, y_at(pts, x) + r.uniform(6, 30), r.uniform(34, 54) * (1.3 + i * .7), '#5F8098' if i == 0 else '#3F6178', '#B9CEDF', snow='#F6FAFD')
        haze(s, base + 30, 60, '#F3F7FB', .55)
    pts = ridge(r, 880, 26, 12)
    layer(s, pts, '#FFFFFF', '#EAF1F8')
    for k in range(14):
        x = r.uniform(0, W)
        pine(s, x, y_at(pts, x) + 10, r.uniform(90, 140), '#2E4F66', '#9DB8CC', snow='#FFFFFF')
    flakes(s, r, 260)
    return s.out('ziema', '#F4F8FC')


def ruduo():
    r, s = random.Random(8), Svg()
    sky(s, [(0, '#F6C98C', 1), (.55, '#FBE3BF', 1), (1, '#FDF0DC', 1)])
    glow(s, 1180, 300, 300, '#FFE2A6', core='#FFE0A0', core_r=50, a=.8)
    clouds(s, r, 6, 140, 330, color='#FFF6E6', a=.6)
    palettes = [['#E8A04A', '#D9823A', '#F0BE6A'], ['#D7612F', '#E98A3A', '#C9502A', '#F2B04E'], ['#B8431F', '#D7612F', '#E98A3A', '#8E3A1C']]
    for i, (base, amp, top, bot) in enumerate([(640, 50, '#E9C08E', '#F1D3AA'), (730, 40, '#D9A36A', '#E8BD8A'), (830, 30, '#C47E43', '#B56E36')]):
        pts = ridge(r, base, amp, 14)
        layer(s, pts, top, bot)
        for k in range(26 + i * 6):
            x = r.uniform(-30, W + 30)
            round_tree(s, r, x, y_at(pts, x) + r.uniform(4, 22), r.uniform(28, 46) * (1 + i * .55), palettes[i], trunk='#7A4A2C')
        haze(s, base + 26, 55, '#FBE6C8', .45)
    leaves(s, r, 160, ['#E98A3A', '#D7612F', '#F2B04E', '#B8431F'])
    return s.out('ruduo', '#FCEBD2')


def jura():
    r, s = random.Random(21), Svg()
    sky(s, [(0, '#7FC0E8', 1), (.6, '#CBE8F7', 1), (1, '#F1F9FD', 1)])
    glow(s, 560, 330, 300, '#FFF6D8', core='#FFF2C6', core_r=50, a=.8)
    clouds(s, r, 7, 130, 380, a=.75)
    isl = ridge(r, 600, 18, 10, [(1250, 60, 160), (1420, 40, 90)], x0=1000, x1=1700, jitter=.1)
    s.add('<path d="%s" fill="%s" opacity=".75"/>' % (smooth(isl, 620), s.lin([(0, '#8CB2C9', 1), (1, '#A9C9DA', 1)])))
    water(s, r, 612, '#5FB2D8', '#1E6F9E', sun_x=560, n=140)
    for k in range(5):   # švelnios bangos prie kranto
        yy = 820 + k * 28
        pts = [(x, yy + 8 * math.sin(x / 90 + k)) for x in range(-100, W + 120, 80)]
        s.add('<path d="%s" fill="none" stroke="#FFFFFF" stroke-width="%s" stroke-linecap="round" opacity="%s"/>' % (smooth(pts), 2 + k * .6, f(.18 + k * .06)))
    sand = ridge(r, 930, 12, 10, jitter=.1)
    layer(s, sand, '#F1DDB0', '#E2C38C')
    s.add('<path d="%s" fill="none" stroke="#FFFFFF" stroke-width="5" opacity=".5"/>' % smooth([(x, y - 3) for x, y in sand]))
    for _ in range(5):   # žuvėdros - švelnūs lankai
        x, y, w = r.uniform(200, 1400), r.uniform(150, 330), r.uniform(10, 18)
        s.add('<path d="M%s %sQ%s %s %s %sQ%s %s %s %s" fill="none" stroke="#5A7A8C" stroke-width="2" stroke-linecap="round"/>' % (
            f(x - w), f(y), f(x - w / 2), f(y - w * .6), f(x), f(y), f(x + w / 2), f(y - w * .6), f(x + w), f(y)))
    return s.out('jura', '#E6F6FC')


def kopos():
    r, s = random.Random(17), Svg()
    sky(s, [(0, '#6F7FB6', 1), (.35, '#D98E8E', 1), (.62, '#F6C28C', 1), (1, '#FCE3B8', 1)])
    glow(s, 330, 560, 330, '#FFD9A0', core='#FFE2AE', core_r=58, a=.8)
    clouds(s, r, 6, 160, 380, color='#FFE2CC', a=.55)
    water(s, r, 600, '#B9B2C8', '#C9A894', sun_x=330, n=60)
    shades = [('#F2CF96', '#E2B377'), ('#EBC184', '#D49F63'), ('#E4B373', '#C98C52'), ('#DCA565', '#BD7E46')]
    for i, (top, bot) in enumerate(shades):
        pts = ridge(r, 690 + i * 75, 55 - i * 6, 10, jitter=.15)
        layer(s, pts, top, bot)
        s.add('<path d="%s" fill="none" stroke="#FFF1D6" stroke-width="3" opacity=".45"/>' % smooth([(x, y + 2) for x, y in pts]))
        for k in range(10 + i * 6):   # kopų žolė - švelnūs lankai
            x = r.uniform(0, W)
            y = y_at(pts, x) + r.uniform(6, 40)
            for b in range(5):
                a = r.uniform(-.7, .7)
                h = r.uniform(14, 30) * (1 + i * .25)
                s.add('<path d="M%s %sQ%s %s %s %s" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round"/>' % (
                    f(x), f(y), f(x + a * h * .3), f(y - h * .6), f(x + a * h), f(y - h), r.choice(['#9A9A55', '#7E8446', '#B3A86A']), f(1.6 + i * .3)))
    return s.out('kopos', '#FBE3B8')


def sakura():
    r, s = random.Random(9), Svg()
    sky(s, [(0, '#F6D6E2', 1), (.6, '#FCEAF0', 1), (1, '#FFF6F8', 1)])
    glow(s, 1150, 330, 240, '#FFFFFF', core='#FDE2EA', core_r=70, a=.7)
    for i, (base, amp, top, bot) in enumerate([(650, 40, '#E9C3D2', '#F2D6E0'), (740, 36, '#DDB0C2', '#EAC8D5'), (850, 26, '#E8C6D3', '#F5DDE6')]):
        pts = ridge(r, base, amp, 12)
        layer(s, pts, top, bot)
        for k in range(14 + i * 4):
            x = r.uniform(-30, W + 30)
            round_tree(s, r, x, y_at(pts, x) + r.uniform(6, 20), r.uniform(26, 40) * (1 + i * .5), ['#F4B3C8', '#F7C7D6', '#EE9DB7', '#FAD5E0'], trunk='#8E5B66')
        haze(s, base + 24, 50, '#FDEFF4', .5)
    # didelė šaka iš kairės
    br = [(-40, 140), (140, 190), (330, 210), (520, 260), (690, 250)]
    s.add('<path d="%s" fill="none" stroke="#6E4650" stroke-width="16" stroke-linecap="round"/>' % smooth(br))
    for sub in [[(330, 210), (380, 140), (450, 100)], [(520, 260), (580, 320), (660, 340)], [(140, 190), (200, 260), (260, 290)]]:
        s.add('<path d="%s" fill="none" stroke="#6E4650" stroke-width="8" stroke-linecap="round"/>' % smooth(sub))
    for k in range(140):
        bx, by = r.choice(br[1:] + [(450, 100), (660, 340), (260, 290), (380, 140)])
        x, y = bx + r.uniform(-60, 60), by + r.uniform(-45, 45)
        s.add('<circle cx="%s" cy="%s" r="%s" fill="%s" opacity="%s"/>' % (f(x), f(y), f(r.uniform(6, 13)), r.choice(['#F7B9CB', '#F9CCD9', '#F29AB4', '#FFFFFF']), f(r.uniform(.75, 1))))
    leaves(s, r, 120, ['#F7B9CB', '#F9CCD9', '#F29AB4'], size=(4, 8))
    return s.out('sakura', '#FFF6F8')


def dojo():
    r, s = random.Random(4), Svg()
    sky(s, [(0, '#E9B98A', 1), (.5, '#F6D9B4', 1), (1, '#FBEBD6', 1)])
    glow(s, 400, 420, 300, '#FFE7B8', core='#FFD99A', core_r=64, a=.8)
    clouds(s, r, 5, 170, 360, color='#FFF3E0', a=.55)
    far = ridge(r, 660, 40, 12)
    layer(s, far, '#D9B58E', '#E6C7A3')
    haze(s, 690, 60, '#F8E3C6', .5)
    near = ridge(r, 790, 22, 12, jitter=.1)
    layer(s, near, '#B98F66', '#A47B55')
    # dojo pastatas - lenkti stogai
    bx, by = 1080, 760
    s.add('<rect x="%d" y="%d" width="300" height="120" rx="6" fill="%s"/>' % (bx - 150, by - 120, s.lin([(0, '#F3E3C8', 1), (1, '#E2CBA6', 1)])))
    for k in range(5):
        s.add('<rect x="%d" y="%d" width="44" height="80" rx="3" fill="#F9EFDD" stroke="#9B6B44" stroke-width="3"/>' % (bx - 130 + k * 54, by - 100))
    for (w, y, h) in [(420, by - 120, 60), (300, by - 190, 50)]:
        s.add('<path d="M%s %sQ%s %s %s %sQ%s %s %s %sQ%s %s %s %sQ%s %s %s %sZ" fill="%s"/>' % (
            f(bx - w / 2), f(y), f(bx - w / 2 + 30), f(y - 10), f(bx - w * .32), f(y - h * .55), f(bx - w * .1), f(y - h), f(bx), f(y - h),
            f(bx + w * .1), f(y - h), f(bx + w * .32), f(y - h * .55), f(bx + w / 2 - 30), f(y - 10), f(bx + w / 2), f(y), s.lin([(0, '#5E3B28', 1), (1, '#3E271B', 1)])))
    s.add('<rect x="%d" y="%d" width="210" height="50" rx="4" fill="#E9D5B4"/>' % (bx - 105, by - 190))
    # torii vartai
    tx = 560
    for dx in (-80, 80):
        s.add('<rect x="%d" y="%d" width="18" height="170" rx="6" fill="#C2432C"/>' % (tx + dx - 9, 650))
    s.add('<path d="M%d %dQ%d %d %d %dL%d %dQ%d %d %d %dZ" fill="#B03A26"/>' % (tx - 130, 652, tx, 630, tx + 130, 652, tx + 120, 668, tx, 652, tx - 120, 668))
    s.add('<rect x="%d" y="%d" width="200" height="12" rx="5" fill="#C2432C"/>' % (tx - 100, 682))
    # žibintai
    for lx in (860, 1300):
        glow(s, lx, by - 30, 50, '#FFD27A', a=.6)
        s.add('<rect x="%d" y="%d" width="22" height="30" rx="8" fill="#FFE2A8" stroke="#7A4B2C" stroke-width="3"/>' % (lx - 11, by - 45))
    fg = ridge(r, 900, 18, 10, jitter=.1)
    layer(s, fg, '#8E6A49', '#7A5A3D')
    for k in range(10):
        x = r.uniform(0, W)
        round_tree(s, r, x, y_at(fg, x) + 8, r.uniform(60, 90), ['#7E8B4E', '#6F7C45', '#93A05C'], trunk='#5E3B28')
    leaves(s, r, 60, ['#F3B9C6', '#F7CCD6'], 0, 700, (3, 6))
    return s.out('dojo', '#F1E7D2')


def kosmosas():
    """#112: rafinuotas kosmosas - ryškūs ūkai, žiedinė planeta su šviesa ir juodoji skylė su švytinčiu disku."""
    r, s = random.Random(42), Svg()
    sky(s, [(0, '#05060F', 1), (.5, '#0B0B22', 1), (1, '#140A26', 1)])
    for cx, cy, rx, ry, col, a in [(380, 320, 520, 300, '#4B2C8F', .55), (1180, 650, 600, 320, '#8A2A6E', .45), (900, 220, 420, 220, '#1E5A8F', .4), (250, 820, 460, 220, '#2C6F8F', .3)]:
        s.add('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="%s"/>' % (cx, cy, rx, ry, s.rad([(0, col, a), (.55, col, a * .4), (1, col, 0)])))
    stars(s, r, 340, H)
    # juodoji skylė: šviesos diskas, tamsus centras, lenktas šviesos žiedas
    bx, by = 470, 330
    glow(s, bx, by, 260, '#FFB86B', a=.35)
    s.add('<ellipse cx="%d" cy="%d" rx="230" ry="46" fill="none" stroke="%s" stroke-width="26" transform="rotate(-14 %d %d)"/>' % (
        bx, by, s.lin([(0, '#FF7A3D', .2), (.3, '#FFD48A', 1), (.55, '#FFF3D6', 1), (.8, '#FF9A4D', .9), (1, '#FF7A3D', .2)], x2=1, y2=0), bx, by))
    s.add('<circle cx="%d" cy="%d" r="78" fill="%s"/>' % (bx, by, s.rad([(0, '#000000', 1), (.82, '#000000', 1), (.93, '#FFCF8A', .9), (1, '#FFCF8A', 0)])))
    s.add('<path d="M%d %dA230 46 0 0 0 %d %d" fill="none" stroke="#FFE7B8" stroke-width="10" opacity=".85" transform="rotate(-14 %d %d)"/>' % (bx - 225, by + 6, bx + 225, by + 6, bx, by))
    # žiedinė planeta su šviesa iš kairės
    px, py, pr = 1260, 270, 110
    s.add('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="none" stroke="%s" stroke-width="16" transform="rotate(-18 %d %d)" opacity=".9"/>' % (
        px, py, pr * 2.1, pr * .48, s.lin([(0, '#C9A86A', .2), (.5, '#F2DDB0', 1), (1, '#C9A86A', .2)], x2=1, y2=0), px, py))
    s.add('<circle cx="%d" cy="%d" r="%d" fill="%s"/>' % (px, py, pr, s.rad([(0, '#F7D9A8', 1), (.5, '#D98A4E', 1), (1, '#6B2E2A', 1)], cx=.32, cy=.3, r=.8)))
    for k in range(5):   # juostos
        yy = py - pr * .6 + k * pr * .3
        s.add('<ellipse cx="%d" cy="%s" rx="%s" ry="%s" fill="#FFFFFF" opacity=".08"/>' % (px, f(yy), f(pr * .95), f(pr * .06)))
    s.add('<path d="M%d %dA%d %d 0 0 0 %d %d" fill="none" stroke="%s" stroke-width="16" transform="rotate(-18 %d %d)"/>' % (
        px - pr * 2.1, py, pr * 2.1, pr * .48, px + pr * 2.1, py, s.lin([(0, '#C9A86A', .2), (.5, '#FFF0CC', 1), (1, '#C9A86A', .2)], x2=1, y2=0), px, py))
    # mažas mėnulis ir krentanti žvaigždė
    s.add('<circle cx="1460" cy="560" r="26" fill="%s"/>' % s.rad([(0, '#DCE6F7', 1), (1, '#4E5F86', 1)], cx=.3, cy=.3, r=.9))
    s.add('<path d="M980 110 L1120 160" stroke="%s" stroke-width="3" stroke-linecap="round"/>' % s.lin([(0, '#FFFFFF', 0), (1, '#FFFFFF', .9)], x2=1, y2=0))
    return s.out('kosmosas', '#050816')


THEMES = {'kalnai': kalnai, 'vulkanas': vulkanas, 'ziema': ziema, 'ruduo': ruduo, 'jura': jura, 'kopos': kopos, 'sakura': sakura, 'dojo': dojo, 'kosmosas': kosmosas}

if __name__ == '__main__':
    pick = sys.argv[1:] or list(THEMES)
    for name in pick:
        print('%-10s %6d B' % (name, THEMES[name]()))
