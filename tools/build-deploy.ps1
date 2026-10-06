# Sukuria deploy\karateka-test.zip (svetainės failai serveriui) ir deploy\karateka-db.sql (visa DB schema).
# Paleidimas: dešiniu pelės klavišu ant šio failo -> "Run with PowerShell"
# arba terminale:  powershell -ExecutionPolicy Bypass -File tools\build-deploy.ps1
#
# ZIP'e NĖRA config.php (serveryje jis savas), testinių duomenų skripto ir įkeltų nuotraukų,
# todėl išskleidus ZIP serveryje jie nepasikeičia.

$ErrorActionPreference = 'Stop'
$root   = Split-Path -Parent $PSScriptRoot
$deploy = Join-Path $root 'deploy'
$stage  = Join-Path $deploy 'stage'

New-Item -ItemType Directory -Force $deploy | Out-Null
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
Copy-Item -Recurse (Join-Path $root 'public_html') $stage

Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $stage 'config.php')
Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $stage 'app\cli\dev_seed.php')
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue (Join-Path $stage 'uploads\news')

# tar.exe (Windows 10+) rašo ZIP su "/" skirtukais - Linux serveris teisingai sukuria aplankus
$zip = Join-Path $deploy 'karateka-test.zip'
if (Test-Path $zip) { Remove-Item -Force $zip }
Push-Location $stage
& "$env:WINDIR\System32\tar.exe" -a -c -f $zip *
Pop-Location
Remove-Item -Recurse -Force $stage

# Visa DB schema vienu failu (naujai duomenų bazei)
Get-ChildItem (Join-Path $root 'db') -Filter '0*.sql' | Sort-Object Name |
    ForEach-Object { Get-Content -Raw -Encoding UTF8 $_.FullName } |
    Set-Content -Encoding UTF8 (Join-Path $deploy 'karateka-db.sql')

Write-Host "OK: $zip"
