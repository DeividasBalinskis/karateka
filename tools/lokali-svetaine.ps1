# Lokali svetainė iš naujo: http://localhost:8088
#   - švari duomenų bazė su VISAIS db\0xx failais (ir naujais, pvz. 011)
#   - testinės paskyros (slaptažodis testas123) + demo nariai, taškai, renginiai, naujienos
#
# Kada naudoti: pirmą kartą arba kai atsiranda naujas db\0xx failas.
# Kitiems pakeitimams nieko daryti nereikia - užtenka F5 naršyklėje.
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
docker compose exec -T web php app/cli/dev_seed.php

Write-Host "`n4/4 Įkeliami demo duomenys..." -ForegroundColor Cyan
New-Item -ItemType Directory -Force 'ikelimui' | Out-Null
$tmp = 'ikelimui\lokali-demo.sql'   # santykinis kelias - "C:" docker'is palaikytų konteinerio vardu
docker compose cp 'tools\demo-duomenys.php' web:/tmp/demo-duomenys.php
docker compose exec -T web sh -c 'php /tmp/demo-duomenys.php > /tmp/demo.sql'
docker compose cp web:/tmp/demo.sql $tmp
docker compose cp $tmp db:/tmp/demo.sql
docker compose exec -T db sh -c 'mariadb -u karateka -p$MARIADB_PASSWORD karateka < /tmp/demo.sql'
if ($LASTEXITCODE -ne 0) { Write-Host 'Demo duomenų įkelti nepavyko - svetainė veikia ir be jų.' -ForegroundColor Yellow }
Remove-Item -Force -ErrorAction SilentlyContinue $tmp

# Demo naujienų nuotraukos (jei yra paruoštas paketas)
$photos = Join-Path $root 'ikelimui\demo\demo-nuotraukos.zip'
if (Test-Path $photos) { Expand-Archive -Force $photos (Join-Path $root 'public_html') }

Write-Host "`nParuošta: http://localhost:8088" -ForegroundColor Green
Write-Host '  info@karateka.lt         / testas123  - administratorius'
Write-Host '  narys@karateka.test      / testas123  - suaugęs narys su 0 tšk.'
Write-Host '  tevai@karateka.test      / testas123  - tėvai su 2 vaikais'
Write-Host '  ...@demo.karateka.lt     / demo12345  - demo nariai'
Write-Host '  Laiškai: http://localhost:8025   Duomenų bazė: http://localhost:8081'
Write-Host '  LTKF svetainė: http://localhost:8089' -ForegroundColor Green
Start-Process 'http://localhost:8088'
Start-Process 'http://localhost:8089'
Read-Host "`nSpauskite Enter"
