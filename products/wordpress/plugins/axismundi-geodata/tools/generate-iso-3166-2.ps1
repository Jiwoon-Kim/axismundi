$ErrorActionPreference = 'Stop'

$pluginDir = Split-Path -Parent $PSScriptRoot
$source = Join-Path $pluginDir 'data/iso-3166-2.tsv'
$countriesSource = Join-Path $pluginDir 'data/iso-3166-1.tsv'
$target = Join-Path $pluginDir 'includes/iso-3166-2.generated.php'

foreach ( $path in @( $source, $countriesSource ) ) {
	if ( ! ( Test-Path -LiteralPath $path -PathType Leaf ) ) {
		throw "Missing source: $path"
	}
}

$records = @( Import-Csv -LiteralPath $source -Delimiter "`t" -Encoding UTF8 )
if ( 5046 -ne $records.Count ) {
	throw "Expected 5046 CLDR 48.2 subdivision codes, found $($records.Count)"
}

$countries = @{}
foreach ( $country in @( Import-Csv -LiteralPath $countriesSource -Delimiter "`t" -Encoding UTF8 ) ) {
	$countries[ $country.alpha_2 ] = $true
}

$seen = @{}
foreach ( $record in $records ) {
	if ( $record.country -cnotmatch '^[A-Z]{2}$' -or ! $countries.ContainsKey( $record.country ) ) {
		throw "Unknown country: $($record.country)"
	}
	if ( $record.code -cnotmatch '^[A-Z]{2}-[A-Z0-9]{1,4}$' ) {
		throw "Invalid ISO 3166-2 code: $($record.code)"
	}
	if ( ! $record.code.StartsWith( "$($record.country)-" ) ) {
		throw "Code is not under its country: $($record.code)"
	}
	if ( $seen.ContainsKey( $record.code ) ) {
		throw "Duplicate code: $($record.code)"
	}
	$seen[ $record.code ] = $true
}

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add( '<?php' )
$lines.Add( '/**' )
$lines.Add( ' * Generated ISO 3166-2 subdivision codes. Do not edit directly.' )
$lines.Add( ' * Source: Unicode CLDR 48.2 subdivision containment (Unicode-3.0).' )
$lines.Add( ' * Run: powershell -File tools/generate-iso-3166-2.ps1' )
$lines.Add( ' *' )
$lines.Add( ' * @package AxismundiGeodata' )
$lines.Add( ' */' )
$lines.Add( '' )
$lines.Add( "defined( 'ABSPATH' ) || exit;" )
$lines.Add( '' )
$lines.Add( 'return array(' )
foreach ( $record in ( $records | Sort-Object code ) ) {
	$lines.Add( "`t'$($record.code)' => '$($record.country)'," )
}
$lines.Add( ');' )

# Written without a byte order mark: bytes before `<?php` are output before WordPress can send headers.
$utf8 = New-Object System.Text.UTF8Encoding( $false )
[System.IO.File]::WriteAllText( $target, ( ( $lines -join "`n" ) + "`n" ), $utf8 )
Write-Output "Wrote $($records.Count) subdivision codes to $target"
