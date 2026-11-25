# Varžybų sistema lokaliai: http://localhost:8090
#   - švari duomenų bazė su visais db\0xx failais
#   - demo varžybos: "Rudens taurė" (vyksta šiandien) ir "Žiemos taurė" (registracija atvira)
#
# Kada naudoti: pirmą kartą arba kai atsiranda naujas db\0xx failas.
# Kitiems pakeitimams nieko daryti nereikia - užtenka F5 naršyklėje.
#
# DĖMESIO: ištrina lokalios varžybų DB duomenis (tik šiame kompiuteryje).
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
if (-not (Test-Path 'public_html\config.php')) { Copy-Item 'config.example.php' 'public_html\config.php' }

Write-Host "`n1/3 Išvaloma lokali duomenų bazė..." -ForegroundColor Cyan
docker compose down -v

Write-Host "`n2/3 Paleidžiama (pirmą kartą gali užtrukti kelias minutes)..." -ForegroundColor Cyan
docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Fail 'Nepavyko paleisti (docker compose up). Gal 8090 ar 3308 prievadas užimtas?' }

Write-Host "`n3/3 Laukiama duomenų bazės ir įkeliami demo duomenys..." -ForegroundColor Cyan
# Prisijungimo patikra iš config.php (be dvigubų kabučių - PowerShell jas sugadina)
$check = '$c = require ''config.php''; $d = $c[''db'']; $p = new PDO(''mysql:host='' . $d[''host''] . '';dbname='' . $d[''name''], $d[''user''], $d[''pass'']); $p->query(''SELECT 1 FROM tournaments'');'
$ready = $false
for ($i = 0; $i -lt 60; $i++) {
    Start-Sleep -Seconds 3
    docker compose exec -T web php -r $check 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Write-Host '.' -NoNewline
}
if (-not $ready) { Fail 'Duomenų bazė nepasileido per 3 min. Paleiskite šį failą dar kartą.' }
Write-Host ''
docker compose exec -T web php app/cli/demo.php
if ($LASTEXITCODE -ne 0) { Write-Host 'Demo duomenų įkelti nepavyko - svetainė veikia ir be jų (prisijungimas: sukurkite administratorių).' -ForegroundColor Yellow }

Write-Host "`nParuošta: http://localhost:8090" -ForegroundColor Green
Write-Host '  admin@varzybos.test        / testas123  - organizatorius'
Write-Host '  sekretorius@varzybos.test  / testas123  - tatami sekretorius'
Write-Host '  Salės ekranas:  http://localhost:8090/#/t/1/ekranas'
Write-Host '  Duomenų bazė:   http://localhost:8083'
Write-Host '  karateka.lt lokali versija (http://localhost:8088) prisijungia automatiškai.'
Start-Process 'http://localhost:8090'
Read-Host "`nSpauskite Enter"
