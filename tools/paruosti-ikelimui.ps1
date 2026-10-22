# Paruošia aplanką "ikelimui":
#   svetaine-vX.Y.zip  - visi svetainės failai (be config.php ir įkeltų nuotraukų); X.Y - versija iš public_html\app\VERSION
#   duomenu-baze.sql   - visa duomenų bazė naujai DB (001-... failai iš db/ viename)
# config.php ir demo/ šis skriptas nekeičia.
#
# Paleidimas: dešiniu pelės klavišu -> "Run with PowerShell". Langas lieka atidarytas, kol paspausite Enter.

param([switch]$NoPause)   # -NoPause: kai paleidžia tools\atnaujinti-is-github.ps1 (jis pats palaukia Enter)

$ErrorActionPreference = 'Stop'

# Klaida - parodoma, o ne iškart uždaromas langas
trap {
    Write-Host "`nKLAIDA: $_" -ForegroundColor Red
    if (-not $NoPause) { Read-Host 'Spauskite Enter' }
    exit 1
}

$root  = Split-Path -Parent $PSScriptRoot
$out   = Join-Path $root 'ikelimui'
$stage = Join-Path $env:TEMP 'karateka-svetaine'

New-Item -ItemType Directory -Force $out | Out-Null
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
Copy-Item -Recurse (Join-Path $root 'public_html') $stage

# Ko nekelti į serverį
Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $stage 'config.php')                 # serveryje savas
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue (Join-Path $stage 'app\cli')         # tik kompiuteriui
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue (Join-Path $stage 'uploads\news')    # įkeltos nuotraukos lieka serveryje
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue (Join-Path $stage 'uploads\quiz')    # testų klausimų paveikslėliai - irgi
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue (Join-Path $stage 'uploads\lessons') # pamokų nuotraukos - irgi

# Versija (rodoma prie logotipo test svetainėje) - ir ZIP pavadinime
$version = (Get-Content -Raw (Join-Path $root 'public_html\app\VERSION')).Trim()

# tar.exe rašo ZIP su "/" - serveris teisingai sukuria aplankus
Get-ChildItem $out -Filter 'svetaine*.zip' | Remove-Item -Force    # seni paketai
$zip = Join-Path $out "svetaine-v$version.zip"
Push-Location $stage
& "$env:WINDIR\System32\tar.exe" -a -c -f $zip *
Pop-Location
Remove-Item -Recurse -Force $stage

Get-ChildItem (Join-Path $root 'db') -Filter '0*.sql' | Sort-Object Name |
    ForEach-Object { Get-Content -Raw -Encoding UTF8 $_.FullName } |
    Set-Content -Encoding UTF8 (Join-Path $out 'duomenu-baze.sql')

Write-Host "OK: $out  (versija v$version)"
Write-Host "Paruošta: ikelimui\svetaine-v$version.zip" -ForegroundColor Green
if (-not $NoPause) { Read-Host 'Spauskite Enter' }
