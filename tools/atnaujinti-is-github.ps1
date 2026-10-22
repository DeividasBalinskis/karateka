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

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Stop-WithMessage 'Nerastas git. Įdiekite „Git for Windows“ ir paleiskite dar kartą.' }

$branch = (git rev-parse --abbrev-ref HEAD).Trim()
if ($branch -ne 'main') { Stop-WithMessage "Aplankas ne „main“ šakoje (dabar: $branch). Parašykite Deividui." }

$changes = git status --porcelain
if ($changes) {
    Write-Host 'Šiame aplanke yra pakeitimų, kurių nėra GitHub:' -ForegroundColor Yellow
    $changes | ForEach-Object { Write-Host "  $_" }
    Stop-WithMessage "Pirma juos įkelkite į GitHub:`n  git add -A`n  git commit -m `"Pakeitimai`"`n  git push origin main`narba parašykite Deividui."
}

Write-Host '1/2 Atsisiunčiama iš GitHub...' -ForegroundColor Cyan
$before = (git rev-parse HEAD).Trim()
git pull --ff-only origin main
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'Nepavyko atsisiųsti (git pull). Nukopijuokite klaidą ir parašykite Deividui.' }
$after = (git rev-parse HEAD).Trim()

Write-Host "`n2/2 Ruošiamas įkėlimo paketas..." -ForegroundColor Cyan
& (Join-Path $PSScriptRoot 'paruosti-ikelimui.ps1') -NoPause
if (-not $? -or $LASTEXITCODE -ne 0) { Stop-WithMessage 'Nepavyko paruošti zip (klaida parašyta aukščiau).' }

if ($before -eq $after) {
    Write-Host "`nNaujų pakeitimų GitHub'e nebuvo - zip ta pati versija." -ForegroundColor Yellow
} else {
    # Ką importuoti: nauji db\0xx failai nuo praeito atnaujinimo
    $newSql = @(git diff --name-only --diff-filter=A $before $after -- db | Where-Object { $_ -match '^db/\d{3}_.*\.sql$' })
    if ($newSql.Count) {
        Write-Host "`nDuomenų bazė: phpMyAdmin -> aus15792_test -> Import, po vieną iš eilės:" -ForegroundColor Green
        $newSql | Sort-Object | ForEach-Object { Write-Host ('  ' + ($_ -replace '/', '\')) -ForegroundColor Green }
    } else {
        Write-Host "`nDuomenų bazės keisti nereikia." -ForegroundColor Green
    }
}

# Kas nauja - viršutinis įrašas iš docs\pakeitimai.md
$log = Get-Content -Encoding UTF8 (Join-Path $root 'docs\pakeitimai.md')
$start = ($log | Select-String -Pattern '^## v' | Select-Object -First 1).LineNumber
if ($start) {
    $next = ($log | Select-Object -Skip $start | Select-String -Pattern '^## v' | Select-Object -First 1).LineNumber
    $end = if ($next) { $start + $next - 2 } else { $log.Count - 1 }
    Write-Host "`nKas nauja (docs\pakeitimai.md):" -ForegroundColor Cyan
    $log[($start - 1)..$end] | ForEach-Object { Write-Host "  $_" }
}

Write-Host "`nToliau: įkelkite ikelimui\svetaine-v*.zip į test serverį ir išarchyvuokite." -ForegroundColor Cyan
Read-Host 'Spauskite Enter'
