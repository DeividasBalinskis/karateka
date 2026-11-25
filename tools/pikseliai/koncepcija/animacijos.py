# Animacijų peržiūra: Kitsunė, Meistras Drakonas ir Dvasia (kvėpavimas / plūduriavimas) + gyvūnėliai. Rezultatai -> out/
import os
import numpy as np
from PIL import Image
from pxkit import up, sheet, hx
from veikejai import kitsune, dragon, dvasia, pet_turtle, pet_foxcub, pet_dragonling
from aplinka import dojo_kiemas, contact_shadow

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
IDLE = ((0, 0), (1, 1), (1, 2), (0, 3))


def blit(im, spr, x, y):
    a = np.array(spr)
    h, w = a.shape[:2]
    m = a[..., 3] > 0
    reg = im[y:y + h, x:x + w]
    reg[..., :3][m] = a[..., :3][m]


def gif(frames, path, ms, k):
    fr = [up(Image.fromarray(f), k) for f in frames]
    p0 = fr[0].quantize(colors=256, method=Image.MEDIANCUT, dither=Image.NONE)
    q = [f.quantize(palette=p0, dither=Image.NONE) for f in fr]
    q[0].save(path, save_all=True, append_images=q[1:], duration=ms, loop=0, disposal=1)


# trys veikėjai scenoje (192x72 iškarpa iš kiemo)
frames = []
for i in range(8):
    s = dojo_kiemas(G=50, seed=3)
    for cx in (34, 96, 158):
        contact_shadow(s, cx, 63.5, 15, 2.4)
    im = s.im[0:72, 0:192].copy()
    b, t = IDLE[i % 4]
    blit(im, kitsune(b, t), 10, 17)
    blit(im, dragon(*IDLE[(i + 1) % 4]), 72, 17)
    blit(im, dvasia(i % 4), 134, 13)
    frames.append(im)
gif(frames, os.path.join(OUT, 'animacijos_trys.gif'), [180] * 8, 5)
Image.fromarray(frames[0]).resize((192 * 5, 72 * 5), Image.NEAREST).save(os.path.join(OUT, 'animacijos_trys_x5.png'))

# sprite lapai (šviesus fonas, x6) peržiūrai
bg = (236, 230, 246)
rows = [
    ('kitsune', [kitsune(b, t) for b, t in IDLE] + [kitsune(0, 0, 'punch'), kitsune(0, 0, 'block'), kitsune(0, 0, 'hit')]),
    ('drakonas', [dragon(b, t) for b, t in IDLE] + [dragon(0, 0, 'punch'), dragon(0, 0, 'block'), dragon(0, 0, 'hit')]),
    ('dvasia', [dvasia(t) for t in range(4)] + [dvasia(0, 'punch')]),
]
for name, fr in rows:
    sh = sheet(fr, gap=4)
    canvas = Image.new('RGBA', sh.size, bg + (255,))
    canvas.alpha_composite(sh)
    up(canvas, 6).save(os.path.join(OUT, 'lapas_%s_x6.png' % name))
    sheet(fr).save(os.path.join(OUT, '%s_48.png' % name))
pets = [pet_turtle(0), pet_turtle(1), pet_foxcub(0, 0), pet_foxcub(1, 1), pet_dragonling(0, 0), pet_dragonling(1, 1)]
sh = sheet(pets, gap=4)
canvas = Image.new('RGBA', sh.size, bg + (255,))
canvas.alpha_composite(sh)
up(canvas, 6).save(os.path.join(OUT, 'lapas_gyvunai_x6.png'))
print('ok')
