$ErrorActionPreference = 'Stop'

$pluginDir = Split-Path -Parent $PSScriptRoot
$source = Join-Path $pluginDir 'data/iso-3166-1.tsv'
$target = Join-Path $pluginDir 'includes/iso-3166-1.generated.php'

if ( ! ( Test-Path -LiteralPath $source -PathType Leaf ) ) {
	throw "Missing source: $source"
}

$records = @( Import-Csv -LiteralPath $source -Delimiter "`t" -Encoding UTF8 )
if ( 249 -ne $records.Count ) {
	throw "Expected 249 officially assigned codes, found $($records.Count)"
}

$seenAlpha2 = @{}
$seenAlpha3 = @{}
$seenNumeric = @{}
foreach ( $record in $records ) {
	if ( $record.alpha_2 -cnotmatch '^[A-Z]{2}$' ) {
		throw "Invalid alpha-2: $($record.alpha_2)"
	}
	if ( $record.alpha_3 -cnotmatch '^[A-Z]{3}$' ) {
		throw "Invalid alpha-3 for $($record.alpha_2): $($record.alpha_3)"
	}
	if ( $record.numeric -cnotmatch '^[0-9]{3}$' ) {
		throw "Invalid numeric for $($record.alpha_2): $($record.numeric)"
	}
	if ( ! $record.name ) {
		throw "Missing name for $($record.alpha_2)"
	}
	foreach ( $pair in @( @{ Seen = $seenAlpha2; Value = $record.alpha_2; What = 'alpha-2' },
		@{ Seen = $seenAlpha3; Value = $record.alpha_3; What = 'alpha-3' },
		@{ Seen = $seenNumeric; Value = $record.numeric; What = 'numeric' } ) ) {
		if ( $pair.Seen.ContainsKey( $pair.Value ) ) {
			throw "Duplicate $($pair.What): $($pair.Value)"
		}
		$pair.Seen[ $pair.Value ] = $true
	}
}

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add( '<?php' )
$lines.Add( '/**' )
$lines.Add( ' * Generated ISO 3166-1 country records. Do not edit directly.' )
$lines.Add( ' * Run: powershell -File tools/generate-iso-3166-1.ps1' )
$lines.Add( ' *' )
$lines.Add( ' * @package AxismundiGeodata' )
$lines.Add( ' */' )
$lines.Add( '' )
$lines.Add( "defined( 'ABSPATH' ) || exit;" )
$lines.Add( '' )
$lines.Add( 'return array(' )
foreach ( $record in ( $records | Sort-Object alpha_2 ) ) {
	$name = $record.name -replace "'", "\'"
	$lines.Add( "`t'$($record.alpha_2)' => array( 'alpha_3' => '$($record.alpha_3)', 'numeric' => '$($record.numeric)', 'name' => '$name' )," )
}
$lines.Add( ');' )

# Written without a byte order mark: three bytes before `<?php` are three bytes of output, sent
# before any header this plugin has not yet set.
$utf8 = New-Object System.Text.UTF8Encoding( $false )
[System.IO.File]::WriteAllText( $target, ( ( $lines -join "`n" ) + "`n" ), $utf8 )
Write-Output "Wrote $($records.Count) countries to $target"
