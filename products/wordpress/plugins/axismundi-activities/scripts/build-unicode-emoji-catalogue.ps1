param(
    [string]$InputPath = (Join-Path $PSScriptRoot '..\..\..\..\..\tmp\emoji-test-17.0.txt'),
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\assets\unicode-rgi-17.0.json')
)

$ErrorActionPreference = 'Stop'
$SourceUrl = 'https://www.unicode.org/Public/17.0.0/emoji/emoji-test.txt'

if (-not (Test-Path -LiteralPath $InputPath)) {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $InputPath) | Out-Null
    Invoke-WebRequest -Uri $SourceUrl -OutFile $InputPath
}

$group = ''
$subgroup = ''
# `emoji-test.txt` is UTF-8 without a BOM. Windows PowerShell otherwise guesses the
# active code page, which silently corrupts many variation-selector and ZWJ sequences.
$items = foreach ($line in Get-Content -LiteralPath $InputPath -Encoding utf8) {
    if ($line -match '^# group: (.+)$') {
        $group = $Matches[1]
        continue
    }
    if ($line -match '^# subgroup: (.+)$') {
        $subgroup = $Matches[1]
        continue
    }
    if ($line -notmatch '^([0-9A-F ]+)\s*;\s*fully-qualified\s*#\s*(\S+)\s+E([0-9.]+)\s+(.+)$') {
        continue
    }

    $codepoints = $Matches[1].Trim().Split(' ', [System.StringSplitOptions]::RemoveEmptyEntries)
    $keypoints = $codepoints | Where-Object { $_ -notin @('FE0E', 'FE0F') }
    [pscustomobject]@{
        emoji = $Matches[2]
        key = 'unicode:U+' + ($keypoints -join '-U+')
        group = $group
        subgroup = $subgroup
        name = $Matches[4]
        keywords = @($Matches[4].ToLowerInvariant().Split(' ', [System.StringSplitOptions]::RemoveEmptyEntries))
        emojiVersion = $Matches[3]
    }
}

$catalogue = [ordered]@{
    schema = 1
    unicodeVersion = '17.0'
    source = $SourceUrl
    sourceSha256 = (Get-FileHash -LiteralPath $InputPath -Algorithm SHA256).Hash.ToLowerInvariant()
    items = @($items)
}

function Write-Utf8Json([string]$Path, $Value) {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Path) | Out-Null
    [System.IO.File]::WriteAllText(
        $Path,
        ($Value | ConvertTo-Json -Depth 6 -Compress),
        (New-Object System.Text.UTF8Encoding($false))
    )
}

function Get-GroupSlug([string]$Group) {
    return (($Group.ToLowerInvariant() -replace '&', 'and' -replace '[^a-z0-9]+', '-').Trim('-'))
}

Write-Utf8Json $OutputPath $catalogue

# The picker opens groups on demand. Keep the full file for REST/search consumers, but
# split the render path so opening one category never downloads or renders the other eight.
$groupRoot = Join-Path (Split-Path -Parent $OutputPath) 'unicode-rgi-17.0'
if (Test-Path -LiteralPath $groupRoot) {
    Remove-Item -LiteralPath $groupRoot -Recurse -Force
}
$manifestGroups = foreach ($groupItems in ($items | Group-Object group)) {
    $slug = Get-GroupSlug $groupItems.Name
    $file = Join-Path $groupRoot "$slug.json"
    Write-Utf8Json $file ([ordered]@{
        schema = 1
        unicodeVersion = '17.0'
        group = $groupItems.Name
        items = @($groupItems.Group)
    })
    [pscustomobject]@{ group = $groupItems.Name; file = "$slug.json"; count = @($groupItems.Group).Count }
}
Write-Utf8Json (Join-Path (Split-Path -Parent $OutputPath) 'unicode-rgi-17.0.manifest.json') ([ordered]@{
    schema = 1
    unicodeVersion = '17.0'
    groups = @($manifestGroups)
})

Write-Host "Wrote $(@($items).Count) Unicode Emoji 17.0 RGI entries to $OutputPath"
