<#
  Omphalos dev seed — import placeholder media and set the Site Logo.

  Run from the omphalos theme directory AFTER `npm run start` (wp-env up):
      pwsh ./scripts/seed.ps1

  Media is imported from the theme's bundled assets (mounted into the container
  at wp-content/themes/omphalos/assets). Re-running creates duplicate
  attachments; destroy/reset the env first if you need a clean slate.
#>
$ErrorActionPreference = "Stop"
$themePath = "wp-content/themes/omphalos"

function Import-Media([string]$relPath) {
    # --porcelain prints just the new attachment ID; grab the last numeric line.
    $out = npx wp-env run cli wp media import "$themePath/$relPath" --porcelain 2>&1
    $id  = ($out | Where-Object { $_ -match '^\d+\s*$' } | Select-Object -Last 1)
    if (-not $id) { Write-Warning "import may have failed for $relPath`n$out"; return $null }
    return $id.Trim()
}

Write-Host "== Importing placeholder media =="
$image   = Import-Media "assets/media/image/image-placeholder-mogu-1024.webp"
$audOgg  = Import-Media "assets/media/audio/audio-placeholder-jazzy-lofi.ogg"
$video   = Import-Media "assets/media/video/video-placeholder-gwangan-720p.webm"
$capEn   = Import-Media "assets/media/video/video-placeholder-gwangan-720p.en.vtt"
$capKo   = Import-Media "assets/media/video/video-placeholder-gwangan-720p.ko.vtt"

Write-Host "image=$image audioOgg=$audOgg video=$video capEn=$capEn capKo=$capKo"

# Attach WebVTT tracks to the video attachment (consumed by the video partial).
if ($video -and $capEn) {
    $tracks = @(
        @{ src = "/wp-content/uploads/PLACEHOLDER-en.vtt"; kind = "subtitles"; srclang = "en"; label = "English"; default = $true },
        @{ src = "/wp-content/uploads/PLACEHOLDER-ko.vtt"; kind = "subtitles"; srclang = "ko"; label = "Korean" }
    ) | ConvertTo-Json -Compress
    Write-Host "NOTE: set omphalos_video_tracks meta on attachment $video with real uploaded .vtt URLs:"
    Write-Host "  npx wp-env run cli wp post meta update $video omphalos_video_tracks '<json>'"
}

# Site Logo from the Axismundi brand mark. Core blocks SVG uploads by default;
# if the SVG import fails, fall back to the raster placeholder image.
Write-Host "== Setting Site Logo =="
$logo = Import-Media "assets/brand/axismundi-symbol.svg"
if (-not $logo) {
    Write-Warning "SVG import blocked by core (expected). Falling back to the placeholder image as Site Logo."
    $logo = $image
}
if ($logo) {
    npx wp-env run cli wp option update site_logo $logo
    npx wp-env run cli wp theme mod set custom_logo $logo
    Write-Host "Site Logo set to attachment $logo"
}

# Prose VQA page — embeds the omphalos/prose-vqa pattern (Custom HTML specimen).
# Clear the theme's block-pattern cache first: WP caches the patterns/ file scan
# per theme, so a freshly added pattern file is otherwise invisible until the
# cache expires.
Write-Host "== Clearing theme pattern cache =="
npx wp-env run cli wp eval 'wp_clean_themes_cache(); wp_get_theme()->cache_delete();' 2>&1 | Out-Null

Write-Host "== Creating Prose VQA page =="
$existing = npx wp-env run cli wp post list --post_type=page --name=prose-vqa --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
if (-not $existing) {
    $content = '<!-- wp:pattern {"slug":"omphalos/prose-vqa"} /-->'
    npx wp-env run cli wp post create --post_type=page --post_status=publish `
        --post_title="Prose VQA" --post_name=prose-vqa --post_content="$content"
    Write-Host "Prose VQA page created at /prose-vqa/"
} else {
    Write-Host "Prose VQA page already exists (ID $existing)"
}

Write-Host "== Done. Attachment permalinks: =="
npx wp-env run cli wp post list --post_type=attachment --field=guid
