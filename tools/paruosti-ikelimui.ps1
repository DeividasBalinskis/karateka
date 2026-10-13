# Paruošia aplanką "ikelimui":
#   svetaine.zip       - visi svetainės failai (be config.php ir įkeltų nuotraukų)
#   duomenu-baze.sql   - visa duomenų bazė naujai DB (001-... failai iš db/ viename)
# config.php ir demo/ šis skriptas nekeičia.
#
# Paleidimas: dešiniu pelės klavišu -> "Run with PowerShell".

$ErrorActionPreference = 'Stop'
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

# tar.exe rašo ZIP su "/" - serveris teisingai sukuria aplankus
$zip = Join-Path $out 'svetaine.zip'
if (Test-Path $zip) { Remove-Item -Force $zip }
Push-Location $stage
& "$env:WINDIR\System32\tar.exe" -a -c -f $zip *
Pop-Location
Remove-Item -Recurse -Force $stage

Get-ChildItem (Join-Path $root 'db') -Filter '0*.sql' | Sort-Object Name |
    ForEach-Object { Get-Content -Raw -Encoding UTF8 $_.FullName } |
    Set-Content -Encoding UTF8 (Join-Path $out 'duomenu-baze.sql')

Write-Host "OK: $out"
