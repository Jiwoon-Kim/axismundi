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

# Text block VQA page — pure core text blocks only. This replaces the earlier
# ad-hoc /core-vqa/ page so Phase 8 can proceed group-by-group.
Write-Host "== Creating Text Block VQA page =="
$textVqa = npx wp-env run cli wp post list --post_type=page --name=vqa-text --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
$legacyCoreVqa = npx wp-env run cli wp post list --post_type=page --name=core-vqa --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
$textContent = '<!-- wp:pattern {"slug":"omphalos/vqa-text"} /-->'

if ($textVqa) {
    $textVqaId = $textVqa
    npx wp-env run cli wp post update $textVqaId --post_title="VQA Text" --post_name=vqa-text --post_content="$textContent" | Out-Null
    Write-Host "Text VQA page updated at /vqa-text/ (ID $textVqaId)"
} elseif ($legacyCoreVqa) {
    $textVqaId = $legacyCoreVqa
    npx wp-env run cli wp post update $textVqaId --post_title="VQA Text" --post_name=vqa-text --post_content="$textContent" | Out-Null
    Write-Host "Legacy Core VQA page renamed to /vqa-text/ (ID $textVqaId)"
} else {
    $textVqaId = npx wp-env run cli wp post create --post_type=page --post_status=publish `
        --post_title="VQA Text" --post_name=vqa-text --post_content="$textContent" --porcelain 2>&1 |
        Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    Write-Host "Text VQA page created at /vqa-text/ (ID $textVqaId)"
}

# core/footnotes is dynamic: it renders the page's `footnotes` meta keyed by the
# inline <sup data-fn="UUID"> refs in the vqa-text pattern. Seed the matching
# meta so the footnotes list actually renders (otherwise it is empty by design).
# The two UUIDs must stay in sync with patterns/vqa-text.php.
if ($textVqaId) {
    $footnotesMeta = '[{"id":"1e3b8bdd-8cf2-475b-b015-54c86f93e1b1","content":"첫 번째 각주 — Markdown/HTML 본문의 인라인 각주 참조."},{"id":"7873f5ac-f713-4c02-84fa-0356553a6d1a","content":"두 번째 각주 — core/footnotes 동적 블록이 post meta에서 생성."}]'
    npx wp-env run cli wp post meta update $textVqaId footnotes $footnotesMeta | Out-Null
    Write-Host "Footnotes meta seeded on VQA Text page (ID $textVqaId)"
}

# Media VQA page — patterns/vqa-media.php is a seed-bound template with
# __*_ID__ / __*_URL__ placeholders (Inserter:false). Resolve this install's
# real attachment IDs + URLs and substitute them, so the page works on any
# install / after a reset without hard-coded IDs.
Write-Host "== Creating Media VQA page =="

function Attachment-Url([string]$id) {
    if (-not $id) { return $null }
    $u = npx wp-env run cli wp post get $id --field=guid 2>&1 |
        Where-Object { $_ -match '^https?://' } | Select-Object -First 1
    if ($u) { return $u.Trim() }
    return $null
}

function Attachment-Permalink([string]$id) {
    if (-not $id) { return $null }
    $u = npx wp-env run cli wp post url $id 2>&1 |
        Where-Object { $_ -match '^https?://' } | Select-Object -First 1
    if ($u) { return $u.Trim() }
    return $null
}

$imageUrl     = Attachment-Url $image
$audioUrl     = Attachment-Url $audOgg
$audioLink    = Attachment-Permalink $audOgg   # file block first link = attachment page
$videoUrl     = Attachment-Url $video
$vttKoUrl     = Attachment-Url $capKo

# Cover/second-gallery image: reuse the WEBP image so the page is self-contained.
$coverId  = $image
$coverUrl = $imageUrl

if ($image -and $audOgg -and $video -and $capEn -and $capKo) {
    # Read the pattern's block markup (include the PHP, capture output), strip
    # the PHP header, substitute this install's IDs/URLs for the placeholders.
    $raw  = npx wp-env run cli wp eval "ob_start(); include get_stylesheet_directory().'/patterns/vqa-media.php'; echo ob_get_clean();" 2>&1 | Out-String
    $body = $raw.Substring($raw.IndexOf('<!-- wp:'))
    $body = $body.Replace('__IMAGE_ID__',  $image ).Replace('__IMAGE_URL__',  $imageUrl)
    $body = $body.Replace('__COVER_ID__',  $coverId).Replace('__COVER_URL__',  $coverUrl)
    $body = $body.Replace('__AUDIO_ID__',  $audOgg).Replace('__AUDIO_URL__',  $audioUrl)
    $body = $body.Replace('__AUDIO_PERMALINK__', $audioLink)
    $body = $body.Replace('__VIDEO_ID__',  $video ).Replace('__VIDEO_URL__',  $videoUrl)
    $body = $body.Replace('__VTT_KO_URL__', $vttKoUrl).Replace('__VTT_KO_ID__', $capKo)

    # Write the substituted content to a file inside the theme (mounted into the
    # container) and update the page from it — avoids shell-quoting the markup.
    $contentRel  = "scripts/.vqa-media-content.html"
    Set-Content -Path (Join-Path (Get-Location) $contentRel) -Value $body -Encoding UTF8 -NoNewline

    $mediaVqaId = npx wp-env run cli wp post list --post_type=page --name=vqa-media --field=ID 2>&1 |
        Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    if ($mediaVqaId) {
        npx wp-env run cli wp post update $mediaVqaId --post_title="VQA Media" --post_name=vqa-media "$themePath/$contentRel" | Out-Null
    } else {
        $mediaVqaId = npx wp-env run cli wp post create --post_type=page --post_status=publish `
            --post_title="VQA Media" --post_name=vqa-media "$themePath/$contentRel" --porcelain 2>&1 |
            Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    }
    Remove-Item (Join-Path (Get-Location) $contentRel) -Force -ErrorAction SilentlyContinue
    Write-Host "Media VQA page seeded at /vqa-media/ (ID $mediaVqaId)"
} else {
    Write-Warning "Skipping VQA Media page — one or more media imports failed."
}

Write-Host "== Done. Attachment permalinks: =="
npx wp-env run cli wp post list --post_type=attachment --field=guid
