# Lokali svetainė iš naujo: http://localhost:8088
#   - švari duomenų bazė su VISAIS db\0xx failais (ir naujais, pvz. 011)
#   - tik admino paskyra info@karateka.lt (slaptažodis testas123) - svetainė kaip tikra, be pavyzdžių
#   - testiniai nariai (tėvai, vaikas) - Nustatymai → Testavimas, peržiūra kaip tėvai ar vaikas - viršuje 👁
#
# Kada naudoti: tik pirmą kartą arba kai norite pradėti nuo švarios bazės.
# Atnaujinti NEIŠTRINANT savo duomenų - tools\lokali-atnaujinti.ps1.
#
# DĖMESIO: ištrina lokalios DB duomenis (tik jūsų kompiuteryje; test ir tikra svetainė nepaliečiamos).
# Reikia įjungto Docker Desktop. Paleidimas: dešiniu pelės klavišu -> "Run with PowerShell".

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Fail($msg) {
    Write-Host "`n$msg" -ForegroundColor Red
    Read-Host 'Spauskite Enter'
    exit 1
}

docker info *> $null
if ($LASTEXITCODE -ne 0) { Fail 'Docker neveikia. Įjunkite Docker Desktop, palaukite, kol užsikraus, ir paleiskite šį failą dar kartą.' }
if (-not (Test-Path '.env')) { Copy-Item '.env.example' '.env' }
if (-not (Test-Path 'public_html\config.php')) { Fail 'Nėra public_html\config.php (žr. README.md: „Running locally“).' }

Write-Host "`nDĖMESIO: lokali duomenų bazė bus IŠTRINTA ir sukurta iš naujo (jūsų lokaliai įkeltos nuotraukos, tekstai ir renginiai dings)." -ForegroundColor Yellow
Write-Host 'Atnaujinti neištrinant - tools\lokali-atnaujinti.ps1' -ForegroundColor Yellow
$ans = Read-Host 'Tikrai ištrinti ir pradėti iš naujo? Įrašykite TAIP'
if ($ans -ne 'TAIP') { Write-Host 'Nieko nepakeista.' -ForegroundColor Green; Read-Host 'Spauskite Enter'; exit 0 }

Write-Host "`n1/4 Išvaloma lokali duomenų bazė..." -ForegroundColor Cyan
docker compose down -v

Write-Host "`n2/4 Paleidžiama (pirmą kartą gali užtrukti kelias minutes)..." -ForegroundColor Cyan
docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Fail 'Nepavyko paleisti (docker compose up).' }

Write-Host "`n3/4 Laukiama duomenų bazės..." -ForegroundColor Cyan
# Prisijungimo patikra iš config.php (be dvigubų kabučių - PowerShell jas sugadina)
$check = '$c = require ''config.php''; $d = $c[''db'']; new PDO(''mysql:host='' . $d[''host''] . '';dbname='' . $d[''name''], $d[''user''], $d[''pass'']);'
$ready = $false
for ($i = 0; $i -lt 60; $i++) {
    Start-Sleep -Seconds 3
    docker compose exec -T web php -r $check 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Write-Host '.' -NoNewline
}
if (-not $ready) { Fail 'Duomenų bazė nepasileido per 3 min. Paleiskite šį failą dar kartą.' }
Write-Host ''
docker compose exec -T web php app/cli/dev_seed.php --tik-adminas

Write-Host "`n4/4 Testiniai nariai" -ForegroundColor Cyan
Write-Host 'Prisijunkite kaip adminas: Nustatymai → Testavimas → „Sukurti“. Peržiūra kaip tėvai ar vaikas - viršuje 👁.'

Write-Host "`nParuošta: http://localhost:8088" -ForegroundColor Green
Write-Host '  info@karateka.lt         / testas123  - administratorius'
Write-Host '  Laiškai: http://localhost:8025   Duomenų bazė: http://localhost:8081'
Start-Process 'http://localhost:8088'
Read-Host "`nSpauskite Enter"
