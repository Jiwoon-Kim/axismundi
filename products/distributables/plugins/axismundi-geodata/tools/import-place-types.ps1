param(
	[Parameter(Mandatory = $true)]
	[string] $Source
)

$ErrorActionPreference = 'Stop'
$pluginDir = Split-Path -Parent $PSScriptRoot
$target = Join-Path $pluginDir 'data/geotag-place-types.tsv'

$groupSpecs = @'
15|Nature · Natural features
40|Nature · Natural parks & ecology
52|Facilities · Parks · Urban & community parks
67|Facilities · Gardens & wildlife
80|Facilities · Culture & heritage · Museums & art
90|Facilities · Culture & heritage · Landmarks & historic sites
100|Facilities · Culture & heritage · Places of worship
110|Facilities · Events & assembly
119|Facilities · Public amenities
132|Facilities · Attractions & public spaces
142|Facilities · Performance & screen
156|Facilities · Entertainment · Indoor
166|Facilities · Entertainment · Theme parks & rides
177|Facilities · Sports & recreation · General
193|Facilities · Sports & recreation · Sport venues
204|Facilities · Sports & recreation · Outdoor adventure
211|Facilities · Sports & recreation · Fishing & marine
221|Facilities · Outdoor recreation
239|Business · Accommodation
270|Business · Shopping · General
272|Business · Shopping · Large retail
280|Business · Shopping · Everyday retail
287|Business · Shopping · Markets
291|Business · Shopping · Fashion & beauty
297|Business · Shopping · Culture & gifts
305|Business · Shopping · Sports
309|Business · Shopping · Food
317|Business · Shopping · Home & interiors
320|Business · Shopping · Electronics & pets
324|Business · Shopping · Trade & home improvement
337|Business · Food & drink · Cafes & drinks
347|Business · Food & drink · Restaurants
365|Business · Food & drink · Bars & pubs
382|Business · Food & drink · Desserts & bakery
395|Business · Food & drink · Fast food & snacks
408|Business · Food & drink · Specialty dishes
426|Business · Food & drink · Korean
430|Business · Food & drink · Japanese
440|Business · Food & drink · Chinese
447|Business · Food & drink · Other Asian
453|Business · Food & drink · Southeast Asian
462|Business · Food & drink · South Asian
470|Business · Food & drink · Middle Eastern & West Asian
479|Business · Food & drink · European
482|Business · Food & drink · Western European
493|Business · Food & drink · Central European
499|Business · Food & drink · Eastern European
509|Business · Food & drink · Northern European
513|Business · Food & drink · Mediterranean
520|Business · Food & drink · United States
528|Business · Food & drink · Mexican
532|Business · Food & drink · Latin American
543|Business · Food & drink · African
548|Business · Food & drink · Oceanian
556|Business · Beauty & wellness · Bath & spa
564|Business · Beauty & wellness · Skin & body
571|Business · Beauty & wellness · Personal care
581|Business · Beauty & wellness · Retail
585|Business · Health & medical
601|Business · Services · Organizations
605|Business · Services · Pet care
609|Business · Services · Travel
615|Business · Services · Everyday
624|Business · Services · Professional
629|Business · Services · Contractors
634|Business · Services · Delivery & communications
639|Business · Services · Funeral & spiritual
645|Business · Finance
651|Business · Offices & industry
660|Business · Agriculture & production
670|Business · Government
685|Business · Education
698|Business · Transportation
715|Business · Automotive
'@

$fallbackSpecs = @'
billiard_hall|sports_activity_location
bungalow|cottage
canyon|scenic_spot
cave|scenic_spot
cemetery_park|park
children_park|playground
cliff|scenic_spot
complex_cultural_space|cultural_center
condominium|lodging
cultural_park|city_park
desert|scenic_spot
ecological_park|park
estuary|scenic_spot
experience_village|tourist_attraction
fancy_goods_store|gift_shop
flagship_store|store
folk_pub|pub
forest|woods
geopark|nature_preserve
glacier|scenic_spot
historic_park|city_park
homestay|private_guest_room
hot_spring|spa
information_desk|tourist_information_center
interior_accessories_store|home_goods_store
jjimjilbang|public_bath
locker|storage
marsh|nature_preserve
mini_park|city_park
mountain|mountain_peak
mountain_cabin|cottage
municipal_park|city_park
neighborhood_park|city_park
nursing_room|service
outlet_mall|shopping_mall
pension|bed_and_breakfast
photo_studio|service
reed_field|scenic_spot
rental_cottage|cottage
rest_area|rest_stop
science_museum|museum
scuba_diving_center|adventure_sports_center
select_shop|store
serviced_residence|extended_stay_hotel
shooting_range|sports_activity_location
smoking_area|service
specialized_street|tourist_attraction
sports_park|sports_complex
stationery_store|store
templestay|private_guest_room
ticket_office|service
tidal_flat|scenic_spot
urban_farm|city_park
valley|scenic_spot
volcano|scenic_spot
waterfall|scenic_spot
waterfront_park|city_park
wetland|nature_preserve
yacht_rental|service
yacht_tour|tour_agency
'@

$groups = @{}
foreach ( $spec in $groupSpecs.Trim() -split "`n" ) {
	$parts = $spec.Trim() -split '\|', 2
	$groups[ [int] $parts[0] ] = $parts[1]
}

$fallbacks = @{}
foreach ( $spec in $fallbackSpecs.Trim() -split "`n" ) {
	$parts = $spec.Trim() -split '\|', 2
	$fallbacks[ $parts[0] ] = $parts[1]
}

$html = ( Invoke-WebRequest -UseBasicParsing 'https://developers.google.com/maps/documentation/places/web-service/place-types?hl=en' ).Content
$google = @{}
[regex]::Matches( $html, '<code[^>]*>([a-z][a-z0-9_]*)</code>' ) | ForEach-Object {
	$google[ $_.Groups[1].Value ] = $true
}

function Get-EnglishLabel( [string] $Slug ) {
	$label = $Slug -replace '_', ' '
	$label = $label.Substring( 0, 1 ).ToUpperInvariant() + $label.Substring( 1 )
	return $label -replace '^Atm$', 'ATM' -replace '^Rv ', 'RV ' -replace ' us ', ' US ' -replace '^Ebike ', 'E-bike '
}

$records = [System.Collections.Generic.List[object]]::new()
$group = ''
$lines = Get-Content -LiteralPath $Source -Encoding UTF8
for ( $index = 0; $index -lt $lines.Count; $index++ ) {
	$lineNumber = $index + 1
	if ( $groups.ContainsKey( $lineNumber ) ) {
		$group = $groups[ $lineNumber ]
	}

	$text = $lines[ $index ]
	$slug = $null
	$labelKo = $null
	if ( $text -match '^\s*\|\s*`?([a-z][a-z0-9_]*)`?\s*\|\s*([^|]+?)\s*\|' ) {
		$slug = $matches[1]
		$labelKo = $matches[2].Trim()
	} elseif ( $text -cmatch '^\s*([a-z][a-z0-9_]*)\s{2,}(.+?)\s*$' ) {
		$slug = $matches[1]
		$labelKo = $matches[2].Trim()
	}
	if ( ! $slug ) {
		continue
	}
	if ( ! $group ) {
		throw "No group for source line $lineNumber"
	}

	$division = if ( $group.StartsWith( 'Nature' ) ) { 'nature' } elseif ( $group.StartsWith( 'Facilities' ) ) { 'facility' } else { 'business' }
	$sourceName = if ( $google.ContainsKey( $slug ) ) { 'google' } else { 'custom' }
	$googleFallback = if ( 'google' -eq $sourceName ) { $slug } elseif ( $fallbacks.ContainsKey( $slug ) ) { $fallbacks[ $slug ] } else { throw "Missing Google fallback for $slug" }

	$records.Add( [pscustomobject] @{
		Order = [double] $lineNumber
		division = $division
		group = $group
		slug = $slug
		label_en = Get-EnglishLabel $slug
		label_ko = $labelKo
		source = $sourceName
		google_fallback = $googleFallback
	} )
}

$customRows = @(
	@( 55.1, 'facility', 'Facilities · Parks · Urban & community parks', 'historic_park', 'Historic park', '역사공원', 'city_park' ),
	@( 56.1, 'facility', 'Facilities · Parks · Urban & community parks', 'cultural_park', 'Cultural park', '문화공원', 'city_park' ),
	@( 57.1, 'facility', 'Facilities · Parks · Urban & community parks', 'waterfront_park', 'Waterfront park', '수변공원', 'city_park' ),
	@( 58.1, 'facility', 'Facilities · Parks · Urban & community parks', 'cemetery_park', 'Cemetery park', '묘지공원', 'park' ),
	@( 59.1, 'facility', 'Facilities · Parks · Urban & community parks', 'urban_farm', 'Urban farm', '도시농업공원', 'city_park' ),
	@( 208.1, 'facility', 'Facilities · Sports & recreation · Outdoor adventure', 'sports_park', 'Sports park', '체육공원', 'sports_complex' ),
	@( 379.1, 'business', 'Business · Food & drink · Bars & pubs', 'folk_pub', 'Folk pub', '토속주점', 'pub' )
)
foreach ( $row in $customRows ) {
	$records.Add( [pscustomobject] @{
		Order = [double] $row[0]
		division = $row[1]
		group = $row[2]
		slug = $row[3]
		label_en = $row[4]
		label_ko = $row[5]
		source = 'custom'
		google_fallback = $row[6]
	} )
}

$records = @( $records | Sort-Object Order )
if ( 508 -ne $records.Count ) {
	throw "Expected 508 records, found $($records.Count)"
}
$duplicates = @( $records | Group-Object slug | Where-Object Count -gt 1 )
if ( $duplicates.Count ) {
	throw "Duplicate slugs: $($duplicates.Name -join ', ')"
}

$rows = @( "division`tgroup`tslug`tlabel_en`tlabel_ko`tsource`tgoogle_fallback" )
$rows += $records | ForEach-Object {
	@( $_.division, $_.group, $_.slug, $_.label_en, $_.label_ko, $_.source, $_.google_fallback ) -join "`t"
}
[System.IO.File]::WriteAllLines( $target, $rows, [System.Text.UTF8Encoding]::new( $false ) )

$customCount = @( $records | Where-Object source -eq 'custom' ).Count
Write-Output "Imported $($records.Count) place types ($($records.Count - $customCount) Google, $customCount custom)."
