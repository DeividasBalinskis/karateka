# Įkelia svetainės failus į test.karateka.lt per FTP (WinSCP sinchronizacija).
# Įkeliami tik pasikeitę failai; seni perrašomi - dublikatų nebūna.
# NIEKADA neliečia: config.php (serverio nustatymai), uploads/news/ (įkeltos nuotraukos), dev_seed.php.
#
# Vienkartinis paruošimas (žr. docs/deploy-test.md, skyrius „Įkėlimas su WinSCP“):
#   1) įdiekite WinSCP, 2) WinSCP'e išsaugokite prisijungimą vardu  karateka-test  (su slaptažodžiu).
# Paleidimas: dešiniu pelės klavišu ant šio failo -> "Run with PowerShell".

$ErrorActionPreference = 'Stop'
$Session    = 'karateka-test'                       # WinSCP'e išsaugoto prisijungimo pavadinimas
$RemoteDir  = '/'                                    # FTP vartotojas sukurtas tik test aplankui; jei jungiatės pagrindiniu (aus15792) - '/domains/test.karateka.lt/public_html'
$Exclude    = '|config.php; dev_seed.php; news/'    # ko nekelti ir nekeisti serveryje

$root  = Split-Path -Parent $PSScriptRoot
$local = Join-Path $root 'public_html'
$winscp = @("${env:ProgramFiles(x86)}\WinSCP\WinSCP.com", "$env:ProgramFiles\WinSCP\WinSCP.com", "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com") |
    Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $winscp) {
    Write-Host 'Nerastas WinSCP. Įdiekite jį iš https://winscp.net ir paleiskite iš naujo.' -ForegroundColor Red
    Read-Host 'Spauskite Enter'
    exit 1
}

function Invoke-Sync([switch]$Preview) {
    $previewFlag = if ($Preview) { '-preview' } else { '' }
    & $winscp /log="$env:TEMP\karateka-deploy.log" /command `
        "open $Session" `
        "synchronize remote $previewFlag -criteria=time,size -filemask=""$Exclude"" ""$local"" ""$RemoteDir""" `
        "exit"
    if ($LASTEXITCODE -ne 0) { throw "WinSCP baigė su klaida (žr. $env:TEMP\karateka-deploy.log)" }
}

Write-Host "`n=== Kas bus įkelta į test.karateka.lt (peržiūra) ===`n" -ForegroundColor Cyan
Invoke-Sync -Preview

$answer = Read-Host "`nĮkelti šiuos failus? (t/n)"
if ($answer -notin @('t', 'T', 'taip', 'y', 'Y')) { Write-Host 'Atšaukta.'; exit 0 }

Invoke-Sync
Write-Host "`nĮkelta. Atidarykite https://test.karateka.lt (Ctrl+F5)." -ForegroundColor Green
Write-Host 'Jei pasikeitė duomenų bazė - importuokite naują db\0xx_*.sql failą per phpMyAdmin.'
Read-Host 'Spauskite Enter'
