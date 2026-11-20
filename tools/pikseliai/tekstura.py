# Tekstūros sąsajai (kartojamos): mediena, tamsi mediena, ryžių popierius, akmuo. Pikselinės, mažos, besiūlės.
import numpy as np, random, os
from PIL import Image
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'public_html', 'img', 'zaidimas', 'tex')
def hx(s): s = s.lstrip('#'); return (int(s[0:2], 16), int(s[2:4], 16), int(s[4:6], 16))
def wood(name, base, light, dark, grain, w=64, h=32, seed=1, plank=16):
    r = random.Random(seed); im = np.zeros((h, w, 3), np.uint8); im[:] = hx(base)
    for py in range(0, h, plank):
        # lentos šviesesnis viršus, tamsi siūlė apačioje
        im[py] = hx(light)
        im[min(h - 1, py + plank - 1)] = hx(dark)
        off = r.randint(0, w)
        for y in range(py + 1, py + plank - 1):
            for x in range(w):
                v = np.sin((x + off) * 0.21 + np.sin((y + py) * 0.9) * 1.6)
                if v > 0.86: im[y, x] = hx(grain)
                elif v < -0.93: im[y, x] = hx(light)
        for _ in range(2):                                   # šakelės (mazgai)
            kx, ky = r.randint(2, w - 3), r.randint(py + 3, py + plank - 4)
            for dx, dy in ((0, 0), (1, 0), (-1, 0), (0, 1)): im[ky + dy, (kx + dx) % w] = hx(dark)
        sx = (off * 3) % w                                    # lentų sandūra
        for y in range(py + 1, py + plank - 1): im[y, sx] = hx(dark)
    Image.fromarray(im).save(f'{OUT}/{name}.png')
def paper(name, base, fiber, fiber2, w=32, h=32, seed=2):
    r = random.Random(seed); im = np.zeros((h, w, 3), np.uint8); im[:] = hx(base)
    for _ in range(26):
        x, y = r.randint(0, w - 1), r.randint(0, h - 1); L = r.randint(2, 5); c = hx(r.choice((fiber, fiber2)))
        for i in range(L): im[(y + (i if r.random() < .3 else 0)) % h, (x + i) % w] = c
    Image.fromarray(im).save(f'{OUT}/{name}.png')
def stone(name, base, light, dark, w=32, h=32, seed=3):
    r = random.Random(seed); im = np.zeros((h, w, 3), np.uint8); im[:] = hx(base)
    for _ in range(40):
        x, y = r.randint(0, w - 1), r.randint(0, h - 1); im[y, x] = hx(r.choice((light, dark, dark)))
    Image.fromarray(im).save(f'{OUT}/{name}.png')
os.makedirs(OUT, exist_ok=True)
wood('mediena', '#8A5A36', '#A8744A', '#5A3820', '#74482A', seed=1)
wood('mediena-tamsi', '#5A3A2A', '#74503A', '#3A2418', '#4A2E20', seed=4)
wood('mediena-raudona', '#8E2E28', '#B04236', '#5A1A18', '#76261F', seed=7)
paper('popierius', '#EADBB8', '#D8C59C', '#F4E8CC')
paper('popierius-tamsus', '#D9C49A', '#C4AC80', '#E6D4AE', seed=5)
stone('akmuo', '#6E6A72', '#8A8690', '#545058')
print(sorted(os.listdir(OUT)))
