# LibreSprite + Claude: tiesioginis ryšys (būdas B)

Jungtis: [`libresprite-mcp`](https://github.com/Snehil-Shah/libresprite-mcp) (Snehil Shah, PyPI v0.1.4). Ji veikia **jūsų kompiuteryje**:
Claude programa kompiuteryje ↔ jungtis ↔ LibreSprite. Debesies sesija (claude.ai/code) prie jos neprisijungia - piešiama vietinėje sesijoje.

Pastabos iš autoriaus aprašymo: jungtis ankstyva („hacky and brittle“), vienu metu veikia tik viena, reikia laisvo prievado `64823`;
paleidus kitą LibreSprite scenarijų ryšys nutrūksta (tada vėl paleisti `mcp.js`).

## Vieną kartą (Windows)

1. **uv** (paleidžia jungtį): PowerShell **kaip administratorius** →
   ```
   powershell -ExecutionPolicy ByPass -c "irm https://astral.sh/uv/install.ps1 | iex"
   ```
   Reikia Python 3.11 ar naujesnio (uv jį paprastai parsisiunčia pats).
2. **Claude jungtis** - per Claude programos **Code** skirtuką (vietinė sesija projekto aplanke).
   Claude programos naujose versijose *Settings → Developer* nebėra.
   - Jei veikia `claude --version` (PowerShell):
     `claude mcp add --transport stdio --scope user libresprite -- C:\Users\deivi\.local\bin\uvx.exe libresprite-mcp`
   - Jei ne - failas `.mcp.json` projekto aplanke (į GitHub nekeliamas):
     ```
     cd "C:\Users\deivi\Documents\IT Projektai\Karateka"
     $json = '{ "mcpServers": { "libresprite": { "command": "C:\\Users\\deivi\\.local\\bin\\uvx.exe", "args": ["libresprite-mcp"] } } }'
     [IO.File]::WriteAllText("$PWD\.mcp.json", $json)
     Add-Content .git\info\exclude ".mcp.json"
     ```
   - Tada Claude programoje → **Code** → nauja sesija aplanke `Karateka` → leisti jungtį „libresprite“, jei paklaus.
3. **LibreSprite pusė:** atsisiųsti `mcp.js` iš https://github.com/Snehil-Shah/libresprite-mcp/releases/latest
   ir įdėti į LibreSprite scenarijų (*scripts*) aplanką. Tikslus aplankas aprašyme nenurodytas: LibreSprite meniu *File → Scripts* -
   jei yra „Open Scripts Folder“, jis atidarys tą aplanką. Jei nerandate - nuotrauka to meniu, pasakysiu.

## Kiekvieną kartą piešiant

1. Atidaryti Claude (Desktop arba Claude Code) - jungtis pasileidžia pati.
2. LibreSprite → *File → Scripts* → **mcp.js** → mygtukas **Connect**.
3. Claude lange: pvz. „D8: nupiešk 3 pavyzdinius personažus pagal docs/darbo-planas.md“.
4. Gatavus PNG išsaugoti projekto aplanke (`tools/pixel-art/` arba kur nurodysiu) - tada jie pateks į žaidimą.
