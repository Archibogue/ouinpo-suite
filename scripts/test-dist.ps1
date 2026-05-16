$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Resolve-Path (Join-Path $scriptDir "..")
$buildScript = Join-Path $root "tools\build-dist.ps1"
$distDir = Join-Path $root "dist"

if (!(Test-Path $buildScript)) {
    throw "Script de build introuvable : tools/build-dist.ps1"
}

& $buildScript

$zip = Get-ChildItem -Path $distDir -Filter "ouinpo-suite-*.zip" -File |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1

if ($null -eq $zip) {
    throw "Aucun zip OuInPo Suite trouve dans dist/."
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip.FullName)

try {
    $entries = $archive.Entries | ForEach-Object { $_.FullName }

    if ($entries -notcontains "ouinpo-suite/ouinpo-suite.php") {
        throw "Zip invalide : ouinpo-suite/ouinpo-suite.php absent."
    }

    $badSeparators = $entries | Where-Object { $_ -like "*\*" }
    if ($badSeparators.Count -gt 0) {
        throw "Zip invalide : chemins internes avec antislash detectes."
    }

    $forbidden = $entries | Where-Object {
        $_ -match '\.(sql|sqlite|db|log|zip|tar|rar|7z|wxr|xml)$' -or
        $_ -match '(^|/)(\.env|wp-config\.php|secrets\.php|auth\.json)$'
    }

    if ($forbidden.Count -gt 0) {
        $forbidden | Select-Object -First 30 | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        throw "Zip invalide : fichiers interdits detectes."
    }
}
finally {
    $archive.Dispose()
}

Write-Host ""
Write-Host "Test distribution OK" -ForegroundColor Green
Write-Host "Zip : $($zip.FullName)"
Write-Host "Entrees : $($entries.Count)"
