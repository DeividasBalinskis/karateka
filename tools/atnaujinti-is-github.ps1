# Atsisiunčia naujausią versiją iš GitHub (main) ir paruošia įkėlimo failus aplanke "ikelimui":
#   svetaine-vX.Y.zip - įkelti į test serverį ir išarchyvuoti (kaip iki šiol)
#   ir parašo, kurį db\0xx failą importuoti per phpMyAdmin (jei jų atsirado).
#
# Paleidimas: dešiniu pelės klavišu -> "Run with PowerShell".
# Jei šiame aplanke ką nors keitėte patys - skriptas sustos ir nieko nepakeis: pirma įkelkite pakeitimus į GitHub.

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Stop-WithMessage($msg) {
    Write-Host "`n$msg" -ForegroundColor Red
    Read-Host 'Spauskite Enter'
    exit 1
}

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Stop-WithMessage 'Nerastas git. Įdiekite Git for Windows ir paleiskite dar kartą.' }

$changes = git status --porcelain
if ($changes) {
    Write-Host 'Šiame aplanke yra pakeitimų, kurių nėra GitHub:' -ForegroundColor Yellow
    $changes | ForEach-Object { Write-Host "  $_" }
    Stop-WithMessage 'Šiame aplanke kažkas pakeista ranka. Parašykite Deividui (arba: git stash).'
}

# Visada imama oficiali versija - main šaka (jei aplankas kitoje šakoje, persijungiama)
Write-Host '1/2 Atsisiunčiama iš GitHub...' -ForegroundColor Cyan
git fetch origin main
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'Nepavyko prisijungti prie GitHub. Patikrinkite internetą ir paleiskite dar kartą.' }
$before = (git rev-parse HEAD).Trim()
git checkout -B main origin/main    # aplankas = lygiai tokia versija, kaip GitHub main
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'Nepavyko paimti main versijos. Nukopijuokite klaida ir parasykite Deividui.' }
$after = (git rev-parse HEAD).Trim()

Write-Host "`n2/2 Ruošiamas įkėlimo paketas..." -ForegroundColor Cyan
& (Join-Path $PSScriptRoot 'paruosti-ikelimui.ps1') -NoPause
if (-not $? -or $LASTEXITCODE -ne 0) { Stop-WithMessage 'Nepavyko paruošti zip (klaida parašyta aukščiau).' }

if ($before -eq $after) {
    Write-Host "`nNaujų pakeitimų GitHub'e nebuvo - paketas ta pati versija." -ForegroundColor Yellow
}
# Duomenų bazė: kurių db failų trūksta, rodo pati svetainė (Nustatymai -> Atsarginės kopijos ir sistema -> Importuoti db failai)
Write-Host "`nDuomenų bazė (phpMyAdmin -> aus15792_test -> Import): importuokite tuos, kurių dar neimportavote, po vieną iš eilės:" -ForegroundColor Green
Get-ChildItem (Join-Path $root 'db') -Filter '0*.sql' | Where-Object { $_.Name -match '^(\d{3})_' -and [int]$Matches[1] -ge 22 } | Sort-Object Name |
    ForEach-Object { Write-Host ('  db\' + $_.Name) -ForegroundColor Green }
Write-Host '  (svetainėje: Nustatymai -> Atsarginės kopijos ir sistema parodo, kurių trūksta; importuoti antrą kartą nekenkia)'

# Kas nauja - viršutinis įrašas iš docs\pakeitimai.md
$log = Get-Content -Encoding UTF8 (Join-Path $root 'docs\pakeitimai.md')
$start = ($log | Select-String -Pattern '^## v' | Select-Object -First 1).LineNumber
if ($start) {
    $next = ($log | Select-Object -Skip $start | Select-String -Pattern '^## v' | Select-Object -First 1).LineNumber
    $end = if ($next) { $start + $next - 2 } else { $log.Count - 1 }
    Write-Host "`nKas nauja (docs\pakeitimai.md):" -ForegroundColor Cyan
    $log[($start - 1)..$end] | ForEach-Object { Write-Host "  $_" }
}

Write-Host "`nToliau: įkelkite į test serverį ikelimui\svetaine-v*.zip (ir išarchyvuokite) ARBA visą aplanko ikelimui\svetaine turinį (FileZilla - nutempti į serverio aplanką)." -ForegroundColor Cyan
Read-Host 'Spauskite Enter'
