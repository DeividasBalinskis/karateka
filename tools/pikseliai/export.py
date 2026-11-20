# Surenka pikselinius duomenis į svetainę: lapė + gyvūnėliai → assets/game-px-data.js; ikonos ir scenos → img/
import json, os, glob, shutil
from PIL import Image
ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'public_html') + '/'
fox = json.load(open('fox.json')); pets = json.load(open('pets.json'))
import base64, numpy as np
arr = np.frombuffer(base64.b64decode(fox['data']), np.uint8).reshape(fox['h'], fox['w'])
kidx = [i for i, p in enumerate(fox['pal']) if p and p[0] in ('kim', 'petal', 'hem', 'obi', 'kline')]
ys, xs = np.where(np.isin(arr, kidx)); fox['kbox'] = [int(xs.min()) - 2, int(ys.min()) - 6, int(xs.max()) + 3, int(ys.max()) + 3]
slots = json.load(open('slots.json'))
open(ROOT + 'assets/game-px-data.js', 'w').write('// Pikselinis žaidimo menas (sugeneruota): lapė (kitsunė) su perdažomomis sritimis ir gyvūnėliai - pūkų gumulėliai.\nwindow.GAME_PX=' + json.dumps({'fox': fox, 'pets': pets}, separators=(',', ':')) + ';\n')
os.makedirs(ROOT + 'img/zaidimas', exist_ok=True)
for f in glob.glob('icons/*.png'): shutil.copy(f, ROOT + 'img/zaidimas/' + os.path.basename(f))
n = 0
for f in glob.glob('ras-px/*.png'):
    b = os.path.basename(f)[:-4]
    if b[:2] in ('h-', 'd-') or (b[:2] == 'i-' and slots.get(b[2:]) in ('weapon', 'paint', 'egg')):
        shutil.copy(f, ROOT + 'img/zaidimas/' + b + '.png'); n += 1
print('sprites', n, 'kbox', fox['kbox'])
os.makedirs(ROOT + 'img/temos/px', exist_ok=True)
shutil.copy('arena-dojo.png', ROOT + 'img/temos/px/dojo.png')
for f in sorted(glob.glob('raw-*.png')):
    n = os.path.basename(f)[4:-4]
    if n == 'dojo': continue
    im = Image.open(f).convert('RGB').resize((240, 150), Image.BOX).quantize(colors=28, method=Image.MEDIANCUT, dither=Image.NONE)
    im.save(ROOT + f'img/temos/px/{n}.png', optimize=True)
print(os.path.getsize(ROOT + 'assets/game-px-data.js'), sorted(os.listdir(ROOT + 'img/temos/px')), len(os.listdir(ROOT + 'img/zaidimas')))
