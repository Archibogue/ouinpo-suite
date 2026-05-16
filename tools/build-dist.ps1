$ErrorActionPreference = "Stop"

# ============================================================
# Build distribution package for OuInPo Suite
# ============================================================
# Objectif :
# - créer un zip installable par WordPress ;
# - garantir la structure : ouinpo-suite/ouinpo-suite.php ;
# - éviter les chemins Windows avec antislashs dans le zip ;
# - exclure dumps SQL, XML, logs, secrets, archives, fichiers locaux.
# ============================================================

$pluginSlug = "ouinpo-suite"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Resolve-Path (Join-Path $scriptDir "..")

Set-Location $root

$mainPluginFile = Join-Path $root "ouinpo-suite.php"

if (!(Test-Path $mainPluginFile)) {
    throw "Fichier principal introuvable : ouinpo-suite.php"
}

$mainContent = Get-Content $mainPluginFile -Raw
$version = "dev"

if ($mainContent -match "Version:\s*([^\r\n]+)") {
    $version = $Matches[1].Trim()
}

if ($mainContent -notmatch "Plugin Name:") {
    throw "Le fichier ouinpo-suite.php ne contient pas l'en-tête WordPress 'Plugin Name:'."
}

$distDir = Join-Path $root "dist"
$buildDir = Join-Path $distDir "build"
$packageDir = Join-Path $buildDir $pluginSlug
$zipPath = Join-Path $distDir "$pluginSlug-$version.zip"

Write-Host ""
Write-Host "========================================"
Write-Host " Build OuInPo Suite"
Write-Host "========================================"
Write-Host "Racine        : $root"
Write-Host "Plugin        : $pluginSlug"
Write-Host "Version       : $version"
Write-Host "Zip final     : $zipPath"
Write-Host ""

# ============================================================
# Nettoyage ancien build
# ============================================================

if (Test-Path $buildDir) {
    Remove-Item $buildDir -Recurse -Force
}

if (!(Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

New-Item -ItemType Directory -Path $packageDir -Force | Out-Null

# ============================================================
# Copie des fichiers
# ============================================================

Write-Host "Copie des fichiers..."

$excludedRootItems = @(
    "vendor",
    "dist",
    "scripts",
    "tools",
    ".git",
    ".github",
    ".idea",
    ".vscode",
    "node_modules",
    "cache",
    "tmp",
    "temp",
    "logs",
    "dumps",
    "notes-privees",
    "ouinpo-suite-prod",
    "CSS_additionnels"
)

Get-ChildItem -Path $root -Force | Where-Object {
    $excludedRootItems -notcontains $_.Name
} | ForEach-Object {
    Copy-Item $_.FullName -Destination $packageDir -Recurse -Force
}

# ============================================================
# Nettoyage des fichiers interdits
# ============================================================

Write-Host "Nettoyage des fichiers interdits..."

$forbiddenPatterns = @(
    ".distignore",
    "ouinpo-pack-test-*.json",
    "*.sql",
    "*.sqlite",
    "*.db",
    "*.log",
    "*.bak",
    "*.backup",
    "*.old",
    "*.tmp",
    "*.zip",
    "*.tar",
    "*.tgz",
    "*.tar.gz",
    "*.rar",
    "*.7z",
    "*.wxr",
    "*.xml",
    ".env",
    ".env.*",
    "wp-config.php",
    "config.php",
    "secrets.php",
    "auth.json",
    "site-segfault-clean.xml",
    "phpunit.xml",
    "phpstan.neon",
    "composer.lock",
    "package-lock.json",
    "yarn.lock",
    ".DS_Store",
    "Thumbs.db"
)

foreach ($pattern in $forbiddenPatterns) {
    Get-ChildItem -Path $packageDir -Recurse -Force -Filter $pattern -ErrorAction SilentlyContinue | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force
    }
}

$forbiddenDirs = @(
    "vendor",
    ".git",
    ".github",
    ".idea",
    ".vscode",
    "node_modules",
    "cache",
    "tmp",
    "temp",
    "logs",
    "dumps",
    "notes-privees",
    "ouinpo-suite-prod",
    "CSS_additionnels",
    "tools",
    "scripts",
    "__MACOSX"
)

foreach ($dirName in $forbiddenDirs) {
    Get-ChildItem -Path $packageDir -Recurse -Force -Directory -ErrorAction SilentlyContinue | Where-Object {
        $_.Name -eq $dirName
    } | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force
    }
}

# Nettoyage final explicite des éléments de build / test
Remove-Item (Join-Path $packageDir ".distignore") -Force -ErrorAction SilentlyContinue
Remove-Item (Join-Path $packageDir "packs\ouinpo-pack-test-*.json") -Force -ErrorAction SilentlyContinue
Remove-Item (Join-Path $packageDir "tools") -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item (Join-Path $packageDir "scripts") -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item (Join-Path $packageDir "dist") -Recurse -Force -ErrorAction SilentlyContinue

# ============================================================
# Contrôle structure WordPress
# ============================================================

Write-Host "Contrôle de structure WordPress..."

$packagedMainFile = Join-Path $packageDir "ouinpo-suite.php"

if (!(Test-Path $packagedMainFile)) {
    throw "Paquet refusé : le fichier ouinpo-suite/ouinpo-suite.php est absent."
}

$packagedMainContent = Get-Content $packagedMainFile -Raw

if ($packagedMainContent -notmatch "Plugin Name:") {
    throw "Paquet refusé : ouinpo-suite.php ne contient pas d'en-tête WordPress Plugin Name."
}

if (!(Test-Path (Join-Path $packageDir "src"))) {
    throw "Paquet refusé : le dossier ouinpo-suite/src est absent."
}

# ============================================================
# Contrôle anti-fichiers sensibles
# ============================================================

Write-Host "Contrôle anti-fuite..."

$remainingForbidden = Get-ChildItem -Path $packageDir -Recurse -Force -File | Where-Object {
    $_.Name -match "\.(sql|sqlite|db|log|bak|backup|old|tmp|zip|tar|tgz|rar|7z|wxr|xml)$" -or
    $_.Name -in @(".distignore", ".env", "wp-config.php", "config.php", "secrets.php", "auth.json") -or
    $_.Name -like "ouinpo-pack-test-*.json"
}

if ($remainingForbidden.Count -gt 0) {
    Write-Host ""
    Write-Host "Fichiers interdits encore présents :" -ForegroundColor Red

    $remainingForbidden | ForEach-Object {
        Write-Host $_.FullName -ForegroundColor Red
    }

    throw "Paquet refusé : fichiers interdits détectés."
}

# ============================================================
# Contrôle anti-secrets dans les fichiers texte
# ============================================================

Write-Host "Recherche de secrets possibles..."

$secretRegex = "(sk-[A-Za-z0-9_\-]{20,}|gh[pousr]_[A-Za-z0-9_]{20,}|xox[baprs]-[A-Za-z0-9\-]{20,}|AIza[0-9A-Za-z\-_]{20,})"

$secretHits = Get-ChildItem -Path $packageDir -Recurse -Force -File -Include *.php,*.js,*.css,*.md,*.txt,*.json | Select-String -Pattern $secretRegex -ErrorAction SilentlyContinue

if ($secretHits.Count -gt 0) {
    Write-Host ""
    Write-Host "Secrets possibles détectés :" -ForegroundColor Red

    $secretHits | ForEach-Object {
        Write-Host "$($_.Path):$($_.LineNumber)" -ForegroundColor Red
    }

    throw "Paquet refusé : secret possible détecté."
}

# ============================================================
# Création du zip avec chemins Unix /
# ============================================================

Write-Host "Création du zip avec chemins WordPress compatibles..."

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$tempZip = Join-Path $distDir "temp-$pluginSlug.zip"

if (Test-Path $tempZip) {
    Remove-Item $tempZip -Force
}

$zipStream = [System.IO.File]::Open($tempZip, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    $buildFullPath = (Resolve-Path $buildDir).Path.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar

    Get-ChildItem -Path $packageDir -Recurse -Force -File | ForEach-Object {
        $fileFullPath = $_.FullName

        if (!$fileFullPath.StartsWith($buildFullPath)) {
            throw "Impossible de calculer le chemin relatif pour : $fileFullPath"
        }

        $relative = $fileFullPath.Substring($buildFullPath.Length)

        # Très important :
        # les chemins internes du zip doivent utiliser /
        # et jamais \ sinon WordPress/Linux peut ne pas trouver le fichier.
        $entryName = $relative -replace "\\", "/"

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $fileFullPath,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $archive.Dispose()
    $zipStream.Dispose()
}

Move-Item $tempZip $zipPath -Force

# ============================================================
# Contrôle final du zip
# ============================================================

Write-Host "Contrôle final du zip..."

$checkArchive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)

try {
    $entryNames = $checkArchive.Entries | ForEach-Object { $_.FullName }

    $badSeparators = $entryNames | Where-Object { $_ -like "*\*" }

    if ($badSeparators.Count -gt 0) {
        Write-Host ""
        Write-Host "Chemins invalides détectés dans le zip :" -ForegroundColor Red

        $badSeparators | Select-Object -First 30 | ForEach-Object {
            Write-Host $_ -ForegroundColor Red
        }

        throw "Paquet refusé : le zip contient des antislashs."
    }

    $expectedMainEntry = "$pluginSlug/ouinpo-suite.php"

    if ($entryNames -notcontains $expectedMainEntry) {
        Write-Host ""
        Write-Host "Entrées présentes dans le zip :" -ForegroundColor Yellow

        $entryNames | Select-Object -First 50 | ForEach-Object {
            Write-Host $_
        }

        throw "Paquet refusé : $expectedMainEntry est absent du zip."
    }

    $mainEntry = $checkArchive.GetEntry($expectedMainEntry)

    if ($null -eq $mainEntry) {
        throw "Paquet refusé : impossible de lire $expectedMainEntry dans le zip."
    }

    $reader = New-Object System.IO.StreamReader($mainEntry.Open())
    $mainText = $reader.ReadToEnd()
    $reader.Close()

    if ($mainText -notmatch "Plugin Name:") {
        throw "Paquet refusé : l'en-tête Plugin Name est absent du fichier principal dans le zip."
    }

    $srcEntries = $entryNames | Where-Object { $_ -like "$pluginSlug/src/*" }

    if ($srcEntries.Count -eq 0) {
        throw "Paquet refusé : aucun fichier src/ n'est présent dans le zip."
    }
}
finally {
    $checkArchive.Dispose()
}

# ============================================================
# Résumé
# ============================================================

Write-Host ""
Write-Host "========================================"
Write-Host " Paquet créé avec succès"
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Fichier zip :" -ForegroundColor Green
Write-Host $zipPath -ForegroundColor Green
Write-Host ""
Write-Host "Structure validée :" -ForegroundColor Cyan
Write-Host "$pluginSlug/ouinpo-suite.php"
Write-Host "$pluginSlug/src/..."
Write-Host ""
Write-Host "Aucun antislash détecté dans les chemins internes du zip."
Write-Host "Aucun fichier interdit détecté."
Write-Host ""
Write-Host "À faire maintenant :"
Write-Host "1. Supprimer toute ancienne installation ratée dans wp-content/plugins/"
Write-Host "2. Réinstaller ce zip dans WordPress"
Write-Host "3. Activer le plugin"
Write-Host ""
