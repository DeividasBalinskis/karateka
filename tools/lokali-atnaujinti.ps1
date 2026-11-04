# Lokali svetainė: atnaujinti NEIŠTRINANT duomenų (jūsų įkeltos nuotraukos, tekstai, renginiai lieka).
#   1. Atsisiunčia naujausią versiją iš GitHub (ta pačia šaka, kurioje esate)
#   2. Į lokalią duomenų bazę importuoja tik dar neimportuotus db\0xx failus (jie tik prideda lenteles ir stulpelius)
#   3. Seni pavyzdiniai (demo) duomenys išvalomi automatiškai, kai atidarote „Šiandien“; testiniai nariai - Nustatymai → Testavimas
#
# Svetainė iš naujo su švaria baze - tools\lokali-svetaine.ps1 (tas IŠTRINA lokalius duomenis).
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

$changes = git status --porcelain
if ($changes) {
    Write-Host 'Šiame aplanke yra pakeitimų, kurių nėra GitHub:' -ForegroundColor Yellow
    $changes | ForEach-Object { Write-Host "  $_" }
    Fail 'Pirma juos išsaugokite (git commit) arba parašykite Deividui.'
}

Write-Host "`n1/3 Atsisiunčiama iš GitHub (main)..." -ForegroundColor Cyan
git fetch origin main
if ($LASTEXITCODE -ne 0) { Fail 'Nepavyko prisijungti prie GitHub. Patikrinkite internetą ir paleiskite dar kartą.' }
git checkout -B main origin/main    # aplankas = lygiai tokia versija, kaip GitHub main
if ($LASTEXITCODE -ne 0) { Fail 'Nepavyko paimti main versijos. Nukopijuokite klaida ir parasykite Deividui.' }

docker compose up -d
if ($LASTEXITCODE -ne 0) { Fail 'Nepavyko paleisti (docker compose up).' }

Write-Host "`n2/3 Duomenų bazė..." -ForegroundColor Cyan
# Kurie db failai jau importuoti - lentelėje db_versija (nuo 022; kiekvienas failas įsirašo pats). Senesni (iki 021) jau yra kiekvienoje lokalioje bazėje.
$applied = @()
for ($i = 0; $i -lt 20; $i++) {
    docker compose exec -T db sh -c 'mariadb -N -u karateka -p$MARIADB_PASSWORD karateka -e ''SELECT 1''' *> $null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 3
}
# (sh komandoje - tik viengubos kabutės: PowerShell dvigubas kabutes perduodamas sugadina)
$out = docker compose exec -T db sh -c 'mariadb -N -u karateka -p$MARIADB_PASSWORD karateka -e ''SELECT failas FROM db_versija'' 2>/dev/null'
if ($LASTEXITCODE -eq 0 -and $out) { $applied = @($out | ForEach-Object { $_.Trim() }) }
$newSql = @(Get-ChildItem 'db' -Filter '*.sql' | Where-Object { $_.Name -match '^(\d{3})_' -and [int]$Matches[1] -ge 22 -and $applied -notcontains $_.Name } | Sort-Object Name)
if (-not $newSql.Count) {
    Write-Host '  Visi db failai jau importuoti - duomenų bazės keisti nereikia.' -ForegroundColor Green
}
foreach ($f in $newSql) {
    Write-Host "  importuojama: db\$($f.Name)"
    docker compose cp "db/$($f.Name)" db:/tmp/atnaujinimas.sql
    docker compose exec -T db sh -c 'mariadb -u karateka -p$MARIADB_PASSWORD karateka < /tmp/atnaujinimas.sql'
    if ($LASTEXITCODE -ne 0) { Fail "Nepavyko importuoti db\$($f.Name). Nukopijuokite klaidą ir parašykite Deividui." }
}

Write-Host "`n3/3 Testiniai nariai" -ForegroundColor Cyan
Write-Host 'Prisijunkite kaip adminas: Nustatymai → Testavimas → „Sukurti“. Peržiūra kaip tėvai ar vaikas - viršuje 👁.'

Write-Host "`nParuošta: http://localhost:8088 (jūsų duomenys liko)" -ForegroundColor Green
Start-Process 'http://localhost:8088'
Read-Host "`nSpauskite Enter"
