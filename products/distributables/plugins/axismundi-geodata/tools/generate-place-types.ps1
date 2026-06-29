$ErrorActionPreference = 'Stop'

$pluginDir = Split-Path -Parent $PSScriptRoot
$source = Join-Path $pluginDir 'data/geotag-place-types.tsv'
$target = Join-Path $pluginDir 'includes/place-types.generated.php'

if ( ! ( Test-Path -LiteralPath $source -PathType Leaf ) ) {
	throw "Missing source: $source"
}

$records = @( Import-Csv -LiteralPath $source -Delimiter "`t" -Encoding UTF8 )
if ( 508 -ne $records.Count ) {
	throw "Expected 508 records, found $($records.Count)"
}

$duplicates = @( $records | Group-Object slug | Where-Object Count -gt 1 )
if ( $duplicates.Count ) {
	throw "Duplicate slugs: $($duplicates.Name -join ', ')"
}

foreach ( $record in $records ) {
	if ( $record.slug -cnotmatch '^[a-z][a-z0-9_]*$' ) {
		throw "Invalid slug: $($record.slug)"
	}
	if ( $record.source -notin @( 'google', 'custom' ) ) {
		throw "Invalid source for $($record.slug): $($record.source)"
	}
	if ( 'custom' -eq $record.source -and ! $record.google_fallback ) {
		throw "Missing Google fallback for custom type $($record.slug)"
	}
}

function ConvertTo-PhpString( [string] $Value ) {
	$escaped = $Value.Replace( '\', '\\' ).Replace( "'", "\'" )
	return "'$escaped'"
}

$lines = [System.Collections.Generic.List[string]]::new()
$lines.Add( '<?php' )
$lines.Add( '/**' )
$lines.Add( ' * Generated geotag place-type records. Do not edit directly.' )
$lines.Add( ' * Run: powershell -File tools/generate-place-types.ps1' )
$lines.Add( ' *' )
$lines.Add( ' * @package AxismundiGeodata' )
$lines.Add( ' */' )
$lines.Add( '' )
$lines.Add( "defined( 'ABSPATH' ) || exit;" )
$lines.Add( '' )
$lines.Add( 'return array(' )
foreach ( $record in $records ) {
	$lines.Add( "`tarray(" )
	$lines.Add( "`t`t'division'        => $(ConvertTo-PhpString $record.division)," )
	$lines.Add( "`t`t'group'           => __( $(ConvertTo-PhpString $record.group), 'axismundi-geodata' )," )
	$lines.Add( "`t`t'slug'            => $(ConvertTo-PhpString $record.slug)," )
	$lines.Add( "`t`t'label'           => __( $(ConvertTo-PhpString $record.label_en), 'axismundi-geodata' )," )
	$lines.Add( "`t`t'source'          => $(ConvertTo-PhpString $record.source)," )
	$lines.Add( "`t`t'google_fallback' => $(ConvertTo-PhpString $record.google_fallback)," )
	$lines.Add( "`t)," )
}
$lines.Add( ');' )

[System.IO.File]::WriteAllLines( $target, $lines, [System.Text.UTF8Encoding]::new( $false ) )
$customCount = @( $records | Where-Object source -eq 'custom' ).Count
Write-Output "Generated $($records.Count) place types ($($records.Count - $customCount) Google, $customCount custom)."
