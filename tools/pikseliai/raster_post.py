# Rastrinės ikonos → pikselinės: aiškus kraštas (be pusiau permatomų taškų), ribota paletė, plonas tamsus kontūras.
from PIL import Image
import numpy as np, glob, os
os.makedirs('ras-px', exist_ok=True)
NAVY = (44, 46, 86, 255)
def proc(f):
    im = Image.open(f).convert('RGBA'); a = np.array(im)
    opaque = a[..., 3] >= 110
    if opaque.sum() < 6: return None
    rgb = Image.fromarray(a[..., :3]).quantize(colors=20, method=Image.MEDIANCUT, dither=Image.NONE).convert('RGB')
    o = np.zeros_like(a); o[..., :3] = np.array(rgb); o[..., 3] = np.where(opaque, 255, 0)
    H, W = opaque.shape; out = o.copy()
    for y in range(H):
        for x in range(W):
            if not opaque[y, x] and any(0 <= y + dy < H and 0 <= x + dx < W and opaque[y + dy, x + dx] for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1))): out[y, x] = NAVY
    ys, xs = np.where(out[..., 3] > 0)
    return Image.fromarray(out[ys.min():ys.max() + 1, xs.min():xs.max() + 1], 'RGBA')
names = []
for f in sorted(glob.glob('ras/*.png')):
    r = proc(f)
    if r: r.save('ras-px/' + os.path.basename(f), optimize=True); names.append(os.path.basename(f)[:-4])
print(len(names))
sheet = Image.new('RGBA', (16 * 92, ((len(names) + 15) // 16) * 92), (104, 126, 166, 255))
for i, n in enumerate(names):
    im = Image.open(f'ras-px/{n}.png'); s = 2 if im.width <= 44 else 1
    im = im.resize((im.width * s, im.height * s), Image.NEAREST); sheet.alpha_composite(im, ((i % 16) * 92 + 2, (i // 16) * 92 + 2))
sheet.save('ras-sheet.png')
open('ras-names.txt', 'w').write('\n'.join(names))
