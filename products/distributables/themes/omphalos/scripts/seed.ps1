<#
  Omphalos dev seed — import placeholder media and build the VQA pages.

  Run from the omphalos theme directory AFTER `npm run start` (wp-env up):
      pwsh ./scripts/seed.ps1
      pwsh ./scripts/seed.ps1 -SetDemoLogo   # also set a demo Site Logo

  Media is imported from the theme's bundled assets (mounted into the container
  at wp-content/themes/omphalos/assets). Re-running creates duplicate
  attachments; destroy/reset the env first if you need a clean slate.

  Site Logo is NOT set by default. site_logo / custom_logo are SITE-OWNER data
  (the installation's identity), not a theme fixture — a distributable theme must
  not silently overwrite them on activate/seed. A distributable theme does
  not bundle a raster Site Logo; if a demo really needs a Site Logo slot, pass
  -SetDemoLogo to opt in explicitly (dev/VQA only).

  Parameters:
    -SetDemoLogo  Opt in to setting a demo Site Logo (default: off). Uses the
                  imported placeholder image, because core blocks SVG uploads
                  and the distributable theme does not bundle a raster logo.
#>
param(
    [switch] $SetDemoLogo
)
$ErrorActionPreference = "Stop"
$themePath = "wp-content/themes/omphalos"

# Pin UTF-8 for native-command (wp-cli) capture + file writes. wp-cli emits
# UTF-8, but PowerShell decodes a native command's stdout via
# [Console]::OutputEncoding, which on Windows defaults to a non-UTF-8 code page.
# Without this, capturing the vqa-media pattern body (which contains Korean +
# em dashes) mojibakes multibyte chars — and inside the wp:video `tracks` JSON
# attribute that corruption eats a quote and breaks the block (invalid content).
# Pinning here makes the seed deterministic regardless of the caller's terminal.
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)
[Console]::InputEncoding  = [System.Text.UTF8Encoding]::new($false)
$OutputEncoding           = [System.Text.UTF8Encoding]::new($false)

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

# Site Logo — OFF by default. site_logo / custom_logo are site-owner identity,
# not a theme fixture, so the seed must not overwrite them unless asked. Opt in
# with -SetDemoLogo for a dev/VQA demo. Core blocks SVG uploads for the site-logo
# slot, so the demo logo is the brand RASTER: assets/brand/axismundi-logo.png.
# That raster lives in the repo brand source (NOT bundled in the distributable
# theme), so it is STAGED into the mounted theme dir, imported, and removed
# (try/finally so a mid-run error never leaves the staged copy behind). The
# bundled placeholder image is the fallback if the brand raster is absent.
if ($SetDemoLogo) {
    Write-Host "== Setting demo Site Logo (-SetDemoLogo) =="
    $logoSrc   = Join-Path $PSScriptRoot "../../../../../assets/brand/axismundi-logo.png"
    $logoStageDir = Join-Path $PSScriptRoot "../.seed-tmp"           # under the mounted theme dir
    $logoStage    = Join-Path $logoStageDir "axismundi-logo.png"     # keep the real filename → clean attachment name
    $logo = $null
    try {
        if (Test-Path $logoSrc) {
            New-Item -ItemType Directory -Force -Path $logoStageDir | Out-Null
            Copy-Item $logoSrc $logoStage -Force
            $logo = Import-Media ".seed-tmp/axismundi-logo.png"      # imported via the container mount
        } else {
            Write-Warning "Brand raster not found at $logoSrc; falling back to placeholder image."
            $logo = $image
        }
    } finally {
        if (Test-Path $logoStageDir) { Remove-Item $logoStageDir -Recurse -Force }
    }
    if ($logo) {
        npx wp-env run cli wp option update site_logo $logo
        npx wp-env run cli wp theme mod set custom_logo $logo
        Write-Host "Demo Site Logo set to attachment $logo (axismundi-logo.png)"
    } else {
        Write-Warning "Demo logo import failed; Site Logo was not changed."
    }
} else {
    Write-Host "== Skipping Site Logo (site-owner data; pass -SetDemoLogo to opt in) =="
}

# Prose VQA page — embeds the omphalos/prose-vqa pattern (Custom HTML specimen).
# Clear the theme's block-pattern cache first: WP caches the patterns/ file scan
# per theme, so a freshly added pattern file is otherwise invisible until the
# cache expires.
Write-Host "== Clearing theme pattern cache =="
npx wp-env run cli wp eval 'wp_clean_themes_cache(); wp_get_theme()->cache_delete();' 2>&1 | Out-Null

# VQA demo posts — core/latest-posts is dynamic, so the list/grid VQA needs real
# posts to be meaningful (a fresh install has only "Hello World"). Idempotent
# update-or-create by deterministic slug; the Korean titles/content live inside
# the PHP (wp eval-file) so they never cross the PowerShell/console text boundary.
Write-Host "== Ensuring VQA demo posts =="
npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-posts.php"

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

# Design block VQA page — pure core "Design" category blocks (buttons, columns,
# group + Row/Stack/Grid, separator, spacer). Like vqa-text it is non-media, so
# the page just embeds the omphalos/vqa-design pattern by slug; the specimen
# markup (incl. Korean labels) lives in the pattern and renders server-side.
Write-Host "== Creating Design Block VQA page =="
$designVqa = npx wp-env run cli wp post list --post_type=page --name=vqa-design --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
$designContent = '<!-- wp:pattern {"slug":"omphalos/vqa-design"} /-->'

if ($designVqa) {
    npx wp-env run cli wp post update $designVqa --post_title="VQA Design" --post_name=vqa-design --post_content="$designContent" | Out-Null
    Write-Host "Design VQA page updated at /vqa-design/ (ID $designVqa)"
} else {
    $designVqaId = npx wp-env run cli wp post create --post_type=page --post_status=publish `
        --post_title="VQA Design" --post_name=vqa-design --post_content="$designContent" --porcelain 2>&1 |
        Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    Write-Host "Design VQA page created at /vqa-design/ (ID $designVqaId)"
}

# Widgets block VQA page — core "Widgets" category blocks (search, social icons,
# tag cloud, the widget lists, calendar). Non-media: embeds the omphalos/vqa-widgets
# pattern by slug. The dynamic widgets render this install's real content, so the
# lists look sparse on a fresh install (and tag-cloud is empty until tags exist).
Write-Host "== Creating Widgets Block VQA page =="
$widgetsVqa = npx wp-env run cli wp post list --post_type=page --name=vqa-widgets --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
$widgetsContent = '<!-- wp:pattern {"slug":"omphalos/vqa-widgets"} /-->'

if ($widgetsVqa) {
    npx wp-env run cli wp post update $widgetsVqa --post_title="VQA Widgets" --post_name=vqa-widgets --post_content="$widgetsContent" | Out-Null
    Write-Host "Widgets VQA page updated at /vqa-widgets/ (ID $widgetsVqa)"
} else {
    $widgetsVqaId = npx wp-env run cli wp post create --post_type=page --post_status=publish `
        --post_title="VQA Widgets" --post_name=vqa-widgets --post_content="$widgetsContent" --porcelain 2>&1 |
        Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    Write-Host "Widgets VQA page created at /vqa-widgets/ (ID $widgetsVqaId)"
}

# Embeds block VQA page — core "Embeds" category (core/embed providers). Non-media:
# embeds the omphalos/vqa-embeds pattern by slug. The providers resolve via oEmbed
# (external fetch) on first render, so the page may be slow the first time and the
# fragile providers (X/Threads/Instagram) are EXPECTED to fall back to a link.
Write-Host "== Creating Embeds Block VQA page =="
$embedsVqa = npx wp-env run cli wp post list --post_type=page --name=vqa-embeds --field=ID 2>&1 |
    Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
$embedsContent = '<!-- wp:pattern {"slug":"omphalos/vqa-embeds"} /-->'

if ($embedsVqa) {
    npx wp-env run cli wp post update $embedsVqa --post_title="VQA Embeds" --post_name=vqa-embeds --post_content="$embedsContent" | Out-Null
    Write-Host "Embeds VQA page updated at /vqa-embeds/ (ID $embedsVqa)"
} else {
    $embedsVqaId = npx wp-env run cli wp post create --post_type=page --post_status=publish `
        --post_title="VQA Embeds" --post_name=vqa-embeds --post_content="$embedsContent" --porcelain 2>&1 |
        Where-Object { $_ -match '^\d+\s*$' } | Select-Object -First 1
    Write-Host "Embeds VQA page created at /vqa-embeds/ (ID $embedsVqaId)"
}

# Media VQA page — patterns/vqa-media.php is a seed-bound template with
# __*_ID__ / __*_URL__ placeholders (Inserter:false). The include + placeholder
# substitution + page write all happen server-side in scripts/seed-vqa-media.php
# (run via `wp eval-file`), so the pattern body — Korean text + em dashes, incl.
# inside the wp:video tracks JSON — never crosses the PowerShell/console text
# boundary, which is code-page dependent and would mojibake multibyte chars.
# PowerShell only resolves attachment IDs and hands them to PHP; the PHP derives
# URLs/permalinks itself and self-validates (no leftover placeholders + block
# round-trip stable), failing the seed if either check fails.
Write-Host "== Creating Media VQA page =="

if ($image -and $audOgg -and $video -and $capEn -and $capKo) {
    npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-media.php" $image $audOgg $video $capKo
} else {
    Write-Warning "Skipping VQA Media page — one or more media imports failed."
}

# Embed Template VQA page — the /embed/ Object Card our own post/page renders as when
# embedded elsewhere. Self-embeds 5 demo specimens (post/page × featured-image shape)
# so each renders the real wp-embedded-content iframe → /embed/ card. Builds its own
# demo content + a wide GD image (rectangular branch); Korean stays inside the PHP.
Write-Host "== Creating Embed Template VQA page =="
npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-embed-template.php"

# Core Theme VQA page — site identity / navigation / Query Loop + post-context blocks
# / post navigation / template infra. Builds its own demo posts (featured + none),
# category/tag, comments, and an author bio so the dynamic Query Loop + post-meta
# blocks render meaningfully. Korean stays inside the PHP.
Write-Host "== Creating Theme VQA page =="
npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-theme.php"

# Comments VQA page (THEME-VQA-ROUTE §3 Phase 2) — core/comments family. The page has
# comments OPEN + seeded comments (incl. a threaded reply) so core/comments renders a real
# comment context. Korean stays inside the PHP.
Write-Host "== Creating Theme Comments VQA page =="
npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-theme-comments.php"

# Archive / Terms VQA page (THEME-VQA-ROUTE §3 Phase 3) — term blocks,
# terms-query, and links to live archive/search template contexts.
Write-Host "== Creating Theme Archive VQA page =="
npx wp-env run cli wp eval-file "$themePath/scripts/seed-vqa-theme-archive.php"

Write-Host "== Done. Attachment permalinks: =="
npx wp-env run cli wp post list --post_type=attachment --field=guid
