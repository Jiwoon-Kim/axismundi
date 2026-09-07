$ErrorActionPreference = 'Stop'

# Build the distributable plugin ZIP, excluding dev-only directories (tests, scripts)
# and dot-files. The plugin slug is derived from the directory, so this file is
# identical across plugins; keep them in sync.

$pluginRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$pluginSlug = Split-Path $pluginRoot -Leaf
$distributablesRoot = Resolve-Path (Join-Path $pluginRoot '..\..')
$distRoot = Join-Path $distributablesRoot '_dist'
$stageRoot = Join-Path $distRoot "_stage-$pluginSlug"
$stagePlugin = Join-Path $stageRoot $pluginSlug
$zipPath = Join-Path $distRoot "$pluginSlug.zip"

if (Test-Path $stageRoot) {
	Remove-Item -LiteralPath $stageRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $stagePlugin | Out-Null
New-Item -ItemType Directory -Path $distRoot -Force | Out-Null

$excludeDirs = @('scripts', 'tests', 'node_modules', '.git', '.github')
$excludeFiles = @('.distignore')

Get-ChildItem -LiteralPath $pluginRoot -Force | ForEach-Object {
	if ($_.PSIsContainer -and $excludeDirs -contains $_.Name) {
		return
	}
	if (-not $_.PSIsContainer -and $excludeFiles -contains $_.Name) {
		return
	}
	Copy-Item -LiteralPath $_.FullName -Destination $stagePlugin -Recurse -Force
}

if (Test-Path $zipPath) {
	Remove-Item -LiteralPath $zipPath -Force
}
# Write portable forward-slash ZIP entry names; PowerShell 7 Compress-Archive uses backslashes.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
	Get-ChildItem -LiteralPath $stagePlugin -File -Recurse | ForEach-Object {
		$relativePath = $_.FullName.Substring($stageRoot.Length).TrimStart([char] 92, [char] 47).Replace([string] [char] 92, '/')
		$entry = $archive.CreateEntry($relativePath, [System.IO.Compression.CompressionLevel]::Optimal)
		$sourceStream = [System.IO.File]::OpenRead($_.FullName); $destinationStream = $entry.Open()
		try { $sourceStream.CopyTo($destinationStream) } finally { $destinationStream.Dispose(); $sourceStream.Dispose() }
	}
} finally { $archive.Dispose() }
Remove-Item -LiteralPath $stageRoot -Recurse -Force

$sizeMb = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host "Built $zipPath ($sizeMb MB)"
