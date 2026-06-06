<#
.SYNOPSIS
  Build the distributable Omphalos theme ZIP for WordPress.org submission.

.DESCRIPTION
  Stages the theme into products/distributables/_dist/omphalos/ excluding every
  pattern listed in .distignore, then compresses it to
  products/distributables/_dist/omphalos.zip with the theme folder at the ZIP
  root (the form WordPress.org expects).

  Dir patterns in .distignore (existing directories) -> robocopy /XD.
  File patterns (everything else, incl. wildcards) -> robocopy /XF.

.NOTES
  Output dir (_dist) is gitignored; the ZIP is a transient artifact, never committed.
#>
$ErrorActionPreference = 'Stop'

$themeDir = Split-Path -Parent $PSScriptRoot            # scripts/ -> theme root
$slug     = 'axismundi'
$distRoot = Join-Path $themeDir '..\..\_dist'
$staging  = Join-Path $distRoot $slug
$zipPath  = Join-Path $distRoot "$slug.zip"

# --- parse .distignore into robocopy exclude lists ---
$excludeDirs  = New-Object System.Collections.Generic.List[string]
$excludeFiles = New-Object System.Collections.Generic.List[string]
foreach ($raw in Get-Content (Join-Path $themeDir '.distignore')) {
    $line = $raw.Trim()
    if ($line -eq '' -or $line.StartsWith('#')) { continue }
    $rel  = $line.TrimStart('/')
    $full = Join-Path $themeDir $rel
    if (Test-Path -LiteralPath $full -PathType Container) {
        $excludeDirs.Add((Resolve-Path -LiteralPath $full).Path)
    } else {
        # filename or wildcard (matched anywhere by robocopy /XF)
        $excludeFiles.Add((Split-Path $rel -Leaf))
    }
}

# --- clean + recreate output ---
if (Test-Path -LiteralPath $staging) { Remove-Item -LiteralPath $staging -Recurse -Force }
if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

# --- stage with robocopy (exit codes < 8 are success) ---
$rcArgs = @($themeDir, $staging, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
if ($excludeDirs.Count)  { $rcArgs += '/XD'; $rcArgs += $excludeDirs }
if ($excludeFiles.Count) { $rcArgs += '/XF'; $rcArgs += $excludeFiles }
& robocopy @rcArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

# --- compress (theme folder at ZIP root) ---
Compress-Archive -Path $staging -DestinationPath $zipPath -Force

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
$fileCount = (Get-ChildItem -Recurse -File -LiteralPath $staging).Count
Write-Host "Built $zipPath  ($sizeMB MB, $fileCount files)"
