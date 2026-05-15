param(
    [string]$DistDir = "dist"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$distPath = Join-Path $root $DistDir
$errors = New-Object System.Collections.Generic.List[string]
$warnings = New-Object System.Collections.Generic.List[string]

function Add-Err([string]$Message) { $script:errors.Add($Message) | Out-Null }
function Add-Warn([string]$Message) { $script:warnings.Add($Message) | Out-Null }

if (-not (Test-Path $distPath)) {
    Add-Err "Dossier dist introuvable : $distPath"
} else {
    $zips = Get-ChildItem -Path $distPath -Filter "ouinpo-suite-*.zip" -File | Sort-Object LastWriteTime -Descending
    if (-not $zips) {
        Add-Err "Aucune archive ouinpo-suite-*.zip trouvee."
    }
}

if ($errors.Count -eq 0) {
    $zip = $zips[0]
    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("ouinpo-dist-test-" + [System.Guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory -Path $tmp | Out-Null

    try {
        Expand-Archive -Path $zip.FullName -DestinationPath $tmp -Force
        $main = Get-ChildItem -Path $tmp -Recurse -Filter "ouinpo-suite.php" -File | Select-Object -First 1
        if (-not $main) {
            Add-Err "Fichier principal ouinpo-suite.php introuvable dans l'archive."
        }

        $forbiddenNames = @(".env", "wp-config.php", "secrets.php", "auth.json")
        $forbiddenExt = @(".sql", ".log", ".tmp", ".bak", ".swp")
        Get-ChildItem -Path $tmp -Recurse -Force -File | ForEach-Object {
            if ($forbiddenNames -contains $_.Name -or $forbiddenExt -contains $_.Extension.ToLowerInvariant()) {
                Add-Err "Fichier interdit dans le zip : $($_.FullName.Substring($tmp.Length + 1))"
            }
        }

        $phpFiles = Get-ChildItem -Path $tmp -Recurse -Filter "*.php" -File
        foreach ($file in $phpFiles) {
            $result = & php -l $file.FullName 2>&1
            if ($LASTEXITCODE -ne 0) {
                Add-Err "PHP lint KO : $($file.FullName.Substring($tmp.Length + 1)) :: $result"
            }
        }

        $critical = @(
            ("ouinpo_salt_2025" + "_change_me"),
            ("badge_id" + "_42"),
            ("completion_badge_id" + " = 86"),
            ("wp_" + "ouinpo")
        )
        $textFiles = Get-ChildItem -Path $tmp -Recurse -File -Include *.php,*.js,*.css,*.md,*.txt,*.json,*.ps1
        foreach ($pattern in $critical) {
            $hits = $textFiles | Select-String -SimpleMatch -Pattern $pattern -ErrorAction SilentlyContinue
            if ($hits) {
                Add-Err "Chaine critique detectee : $pattern"
            }
        }
    } finally {
        Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "=== Test distribution OuInPo Suite ==="
if ($warnings.Count -gt 0) {
    Write-Host "Avertissements :"
    $warnings | ForEach-Object { Write-Host " - $_" }
}

if ($errors.Count -gt 0) {
    Write-Host "Erreurs :"
    $errors | ForEach-Object { Write-Host " - $_" }
    exit 1
}

Write-Host "OK : archive verifiee."
exit 0
