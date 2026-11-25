# Įdiegia varžybų sistemos ryšį į karateka.lt projektą: python3 pritaikyti.py <karateka aplankas>
# Nauji failai nukopijuojami, esami papildomi keliomis eilutėmis (jei jau papildyta - praleidžiama).
import sys, os, shutil

root = sys.argv[1]
here = os.path.dirname(os.path.abspath(__file__))
for rel in ['db/012_varzybos.sql', 'public_html/app/varzybos.php', 'public_html/varzybos.php', 'public_html/admin/varzybu-rezultatai.php']:
    shutil.copyfile(os.path.join(here, rel), os.path.join(root, rel))
    print('nukopijuota', rel)

def patch(rel, anchor, add, after=True):
    p = os.path.join(root, rel)
    s = open(p, encoding='utf-8', newline='').read()
    if add.strip() in s:
        print('jau yra', rel); return
    assert s.count(anchor) == 1, f'{rel}: nerasta vieta'
    s = s.replace(anchor, anchor + add if after else add + anchor)
    open(p, 'w', encoding='utf-8', newline='').write(s)
    print('papildyta', rel)

nl = '\r\n' if '\r\n' in open(os.path.join(root, 'public_html/app/bootstrap.php'), encoding='utf-8', newline='').read() else '\n'
patch('public_html/app/bootstrap.php', "require APP_DIR . '/belts.php';", nl + "require APP_DIR . '/varzybos.php';")
patch('public_html/paskyra.php', "<?php if ($isStaff): ?>" + nl + "  <!-- Trenerio paskyra",
      "<?php if ($active && varzybos_enabled()): ?>" + nl
      + "  <a class=\"flash flash-info\" style=\"display:block;\" href=\"<?= url('varzybos.php') ?>\">🥋 Registracija į varžybas: <strong>pasirinkti varžybas ir kategorijas →</strong></a>" + nl
      + "<?php endif; ?>" + nl + nl, after=False)
patch('public_html/admin/renginio-rezultatai.php',
      "Prizinė vieta = dalyvavimo + vietos taškai.</div><?php endif; ?>" + nl + "</div>",
      nl + nl + "<?php if ($event['type'] === 'competition' && varzybos_enabled()): ?>" + nl
      + "  <div class=\"panel card small row between\" style=\"padding:16px 20px; gap:10px;\">" + nl
      + "    <span>Varžybos vyko varžybų sistemoje? Vietas galima įkelti automatiškai.</span>" + nl
      + "    <a class=\"btn btn-primary btn-sm\" href=\"<?= url('admin/varzybu-rezultatai.php?id=' . $id) ?>\">Įkelti iš varžybų sistemos →</a>" + nl
      + "  </div>" + nl + "<?php endif; ?>")
