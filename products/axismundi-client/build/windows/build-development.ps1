[CmdletBinding()]
param(
    [string]$OutputDirectory,
    [string]$Target
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) {
    throw 'This installer build requires Windows.'
}
foreach ($command in @('cargo', 'npm.cmd')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Required build command is missing: $command"
    }
}
$project = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$gui = Join-Path $project 'gui'
$configPath = Join-Path $PSScriptRoot 'tauri.development.conf.json'
$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
if (-not $OutputDirectory) {
    $OutputDirectory = Join-Path $project 'artifacts/windows/development'
}
if (-not $Target -and $env:CARGO_BUILD_TARGET) { $Target = $env:CARGO_BUILD_TARGET }
$started = [DateTime]::UtcNow
$commit = $null
$dirty = $null
if (Get-Command git -ErrorAction SilentlyContinue) {
    $revision = & git -C $project rev-parse HEAD 2>$null
    if ($LASTEXITCODE -eq 0) {
        $commit = $revision.Trim()
        $dirty = [bool](& git -C $project status --porcelain)
    }
}
Push-Location $gui
try {
    $arguments = @('run', 'tauri', '--', 'build', '--debug', '--bundles', 'nsis',
        '--config', $configPath, '--ci', '--no-sign')
    if ($Target) { $arguments += @('--target', $Target) }
    & npm.cmd @arguments
    if ($LASTEXITCODE -ne 0) { throw "Tauri build failed: exit $LASTEXITCODE" }
    $metadataJson = & cargo metadata --manifest-path src-tauri/Cargo.toml --format-version 1 --no-deps
    if ($LASTEXITCODE -ne 0) { throw 'Unable to determine Cargo output directory.' }
    $metadata = $metadataJson | ConvertFrom-Json
    $profileDirectory = $metadata.target_directory
    if ($Target) { $profileDirectory = Join-Path $profileDirectory $Target }
    $profileDirectory = Join-Path $profileDirectory 'debug'
    $bundleDirectory = Join-Path $profileDirectory 'bundle/nsis'
    $pattern = "$($config.productName)_$($config.version)_*-setup.exe"
    $installers = @(Get-ChildItem -LiteralPath $bundleDirectory -Filter $pattern -File |
        Where-Object { $_.LastWriteTimeUtc -ge $started.AddSeconds(-2) })
    if ($installers.Count -ne 1) {
        throw "Expected one fresh Development installer, found $($installers.Count)."
    }
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    $destination = Join-Path $OutputDirectory $installers[0].Name
    Copy-Item -LiteralPath $installers[0].FullName -Destination $destination -Force
    $hash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash
    $info = [ordered]@{
        product = $config.productName
        version = $config.version
        identifier = $config.identifier
        profile = 'debug'
        target = $Target
        sourceCommit = $commit
        sourceModified = $dirty
        builtAtUtc = [DateTime]::UtcNow.ToString('o')
        installer = $installers[0].Name
        sha256 = $hash
        signed = $false
    }
    $info | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $OutputDirectory 'build-info.json') -Encoding UTF8
    Write-Output "Installer: $destination"
    Write-Output "SHA256: $hash"
}
finally {
    Pop-Location
}
