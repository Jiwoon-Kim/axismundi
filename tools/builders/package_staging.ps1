# Package plugin directories into _dist/ as upload-ready ZIPs.
#
# Staging deploys are manual uploads, so a ZIP must hold exactly what the mount
# holds minus development artefacts. The filtered copy is staged in a temp dir
# and the resulting archive is listed back and checked, because shipping a
# working directory wholesale has leaked a lockfile before.
#
# Usage: pwsh tools/builders/package_staging.ps1 actors object-projections ...
#        (slugs may be given with or without the axismundi- prefix)

param([Parameter(Mandatory = $true, ValueFromRemainingArguments = $true)][string[]]$Slugs)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$src = Join-Path $root 'products/wordpress/plugins'
$out = Join-Path $root '_dist'
New-Item -ItemType Directory -Force -Path $out | Out-Null

$excludeDirs = @('tests', 'node_modules', '.git')
$excludeFiles = @(
    'package-lock.json', 'package.json', '.gitignore', 'webpack.config.js',
    '.wp-env.json', '.eslintrc', '.eslintrc.js', '.eslintrc.json', '.stylelintrc',
    '.stylelintrc.json'
)

$results = @()

foreach ($slug in $Slugs) {
    $name = if ($slug.StartsWith('axismundi-')) { $slug } else { "axismundi-$slug" }
    $dir = Join-Path $src $name
    if (-not (Test-Path $dir)) { throw "missing plugin directory: $name" }

    $stage = Join-Path ([System.IO.Path]::GetTempPath()) ("axdist-" + [guid]::NewGuid().ToString('N'))
    $target = Join-Path $stage $name
    New-Item -ItemType Directory -Force -Path $target | Out-Null

    Get-ChildItem -Path $dir -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($dir.Length).TrimStart('\', '/')
        $parts = $rel -split '[\\/]'
        if ($parts | Where-Object { $excludeDirs -contains $_ }) { return }
        if ($excludeFiles -contains $_.Name) { return }
        if ($_.Extension -in @('.orig', '.rej', '.map')) { return }
        $dest = Join-Path $target $rel
        New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
        Copy-Item $_.FullName $dest
    }

    $zip = Join-Path $out "$name.zip"
    if (Test-Path $zip) { Remove-Item $zip }
    Compress-Archive -Path $target -DestinationPath $zip -CompressionLevel Optimal

    $entries = [System.IO.Compression.ZipFile]::OpenRead($zip)
    $names = $entries.Entries | ForEach-Object { $_.FullName }
    $entries.Dispose()

    $leaked = $names | Where-Object {
        $_ -match '/tests/' -or $_ -match 'node_modules' -or
        $_ -match 'package-lock' -or $_ -match '\.orig$'
    }
    Remove-Item -Recurse -Force $stage

    $results += [pscustomobject]@{
        Zip    = "$name.zip"
        KB     = [math]::Round((Get-Item $zip).Length / 1KB)
        Files  = $names.Count
        Status = if ($leaked) { "LEAK: " + ($leaked -join ', ') } else { 'clean' }
    }
}

$results | Format-Table -AutoSize
if ($results | Where-Object { $_.Status -ne 'clean' }) { throw 'development artefacts leaked into a package' }
