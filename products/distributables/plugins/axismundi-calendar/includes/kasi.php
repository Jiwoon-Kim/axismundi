<?php
/**
 * The KASI 음양력 provider: where the Korean lunisolar months actually come from.
 *
 * 한국천문연구원's 음양력 정보, reached through 공공데이터포털. This is the only part of the lunar
 * work that can fail for reasons outside the plugin, which is why everything under it was built and
 * proved first: the arithmetic over a materialised store is already audited, so the one new question
 * here is whether a fetch was parsed correctly.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The service. Fixed, so no request here is ever built from something a user typed. */
const AXISMUNDI_CAL_KASI_ENDPOINT = 'https://apis.data.go.kr/B090041/openapi/service/LrsrCldInfoService/getLunCalInfo';

/** Where the key lives when the site has not put it in `wp-config.php`. */
const AXISMUNDI_CAL_KASI_KEY_OPTION = 'ax_cal_kasi_service_key';

/**
 * The service key, or '' when the site has none.
 *
 * A constant wins over the stored one. A site that can edit `wp-config.php` should be able to keep
 * the key out of the database entirely, and once it has, the settings screen has nothing to offer.
 *
 * @return string
 */
function axismundi_cal_kasi_key() : string {
	if ( defined( 'AXISMUNDI_CAL_KASI_KEY' ) && '' !== (string) constant( 'AXISMUNDI_CAL_KASI_KEY' ) ) {
		return (string) constant( 'AXISMUNDI_CAL_KASI_KEY' );
	}
	$stored = (string) get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, '' );
	if ( '' === $stored ) {
		return '';
	}
	$plain = axismundi_cal_kasi_decrypt( $stored );
	return is_string( $plain ) ? $plain : '';
}

/** @return bool Whether the key comes from `wp-config.php` rather than the database. */
function axismundi_cal_kasi_key_is_constant() : bool {
	return defined( 'AXISMUNDI_CAL_KASI_KEY' ) && '' !== (string) constant( 'AXISMUNDI_CAL_KASI_KEY' );
}

/**
 * The secret this site encrypts the stored key with.
 *
 * Derived from the salts in `wp-config.php`, so the ciphertext in the database cannot be read from
 * the database alone. That is the threat this addresses and the only one: a leaked backup, an
 * exported table, a plugin reading options. It does nothing against somebody who already has the
 * filesystem, and it is not meant to -- at that point they can read the key the same way this does.
 *
 * @return string|null
 */
function axismundi_cal_kasi_secret() : ?string {
	if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! defined( 'AUTH_SALT' ) || '' === (string) AUTH_SALT ) {
		return null;
	}
	return substr( hash( 'sha256', 'ax-cal-kasi|' . AUTH_SALT, true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

/**
 * Encrypt a key for storage, or null when this install cannot.
 *
 * @param string $plain Service key.
 * @return string|null
 */
function axismundi_cal_kasi_encrypt( string $plain ) : ?string {
	$secret = axismundi_cal_kasi_secret();
	if ( null === $secret ) {
		return null;
	}
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $secret ) );
}

/**
 * Read a stored key back, or null when it cannot be read.
 *
 * Null after the salts are rotated, which is correct rather than unfortunate: the stored bytes are
 * no longer a key this site can use, and pretending otherwise would send a corrupt string to the
 * service and blame the service for the answer.
 *
 * @param string $stored Stored ciphertext.
 * @return string|null
 */
function axismundi_cal_kasi_decrypt( string $stored ) : ?string {
	$secret = axismundi_cal_kasi_secret();
	$raw    = base64_decode( $stored, true );
	if ( null === $secret || false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return null;
	}
	$plain = sodium_crypto_secretbox_open(
		substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
		substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
		$secret
	);
	return is_string( $plain ) ? $plain : null;
}

/**
 * Store, or clear, the service key.
 *
 * @param string $plain Service key, or '' to forget it.
 * @return true|WP_Error
 */
function axismundi_cal_kasi_key_set( string $plain ) {
	$plain = trim( $plain );
	if ( '' === $plain ) {
		delete_option( AXISMUNDI_CAL_KASI_KEY_OPTION );
		return true;
	}
	$cipher = axismundi_cal_kasi_encrypt( $plain );
	if ( null === $cipher ) {
		// Refused rather than stored in the clear. Somebody who asked for the key to be kept safely
		// and got plaintext instead has been told something untrue about their own site.
		return new WP_Error(
			'ax_cal_kasi_no_crypto',
			__( 'This site cannot store the key safely. Define AXISMUNDI_CAL_KASI_KEY in wp-config.php instead.', 'axismundi-calendar' )
		);
	}
	update_option( AXISMUNDI_CAL_KASI_KEY_OPTION, $cipher, false );
	return true;
}

/**
 * Fetch one Gregorian month from KASI.
 *
 * `solDay` is omitted on purpose: the service answers the whole month, so a 31-day month is one
 * request rather than 31.
 *
 * @param int $year  Gregorian year.
 * @param int $month Gregorian month.
 * @return array<int,array<string,string>>|WP_Error Item rows.
 */
function axismundi_cal_kasi_fetch_month( int $year, int $month ) {
	$key = axismundi_cal_kasi_key();
	if ( '' === $key ) {
		return new WP_Error( 'ax_cal_kasi_no_key', __( 'No KASI service key is configured.', 'axismundi-calendar' ) );
	}
	if ( $month < 1 || $month > 12 ) {
		return new WP_Error( 'ax_cal_kasi_month', __( 'A month is 1 to 12.', 'axismundi-calendar' ) );
	}
	if ( ! axismundi_cal_system_covers( AXISMUNDI_CAL_KOREAN_LUNISOLAR, axismundi_cal_absolute_day( $year, $month, 1 ) ) ) {
		// Not an error the service should be asked to produce. The range is known here, and spending a
		// request to be told no is spending somebody's quota to learn what was already written down.
		return new WP_Error( 'ax_cal_kasi_coverage', __( 'That month is outside the range this provider covers.', 'axismundi-calendar' ) );
	}

	/*
	 * The key is appended rather than passed through `add_query_arg`, which encodes what it is given:
	 * the portal issues the key already percent-encoded, and encoding it again turns every `%2B` into
	 * `%252B` and the request into an authentication failure that looks like a bad key.
	 *
	 * A key pasted in its decoded form is encoded here instead, so both of the things somebody can
	 * copy off that page work.
	 */
	$service_key = false !== strpos( $key, '%' ) ? $key : rawurlencode( $key );
	$url         = add_query_arg(
		array(
			'solYear'   => sprintf( '%04d', $year ),
			'solMonth'  => sprintf( '%02d', $month ),
			'numOfRows' => '40',
		),
		AXISMUNDI_CAL_KASI_ENDPOINT
	) . '&ServiceKey=' . $service_key;

	$response = wp_safe_remote_get(
		$url,
		array(
			/*
			 * Read timeout. Not the one bulk runs fail on: those fail with cURL 28 at ~10s, which is
			 * the separate connect timeout, so the connection is never being established rather than
			 * the service being slow. Raising this would have fixed nothing and looked like it might.
			 */
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => array( 'Accept' => 'application/xml' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'ax_cal_kasi_http', sprintf( /* translators: %d: HTTP status. */ __( 'The service answered %d.', 'axismundi-calendar' ), $code ) );
	}
	return axismundi_cal_kasi_parse( (string) wp_remote_retrieve_body( $response ) );
}

/**
 * Read the day rows out of a KASI response.
 *
 * @param string $body XML body.
 * @return array<int,array<string,string>>|WP_Error
 */
function axismundi_cal_kasi_parse( string $body ) {
	if ( '' === trim( $body ) ) {
		return new WP_Error( 'ax_cal_kasi_empty', __( 'The service returned nothing.', 'axismundi-calendar' ) );
	}
	$previous = libxml_use_internal_errors( true );
	// LIBXML_NONET, because a document that can fetch is a document that can be pointed somewhere.
	$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( false === $xml ) {
		return new WP_Error( 'ax_cal_kasi_parse', __( 'The service returned something that is not a response.', 'axismundi-calendar' ) );
	}

	$header_code = isset( $xml->header->resultCode ) ? trim( (string) $xml->header->resultCode ) : '';
	if ( '' !== $header_code && '00' !== $header_code ) {
		$message = isset( $xml->header->resultMsg ) ? trim( (string) $xml->header->resultMsg ) : $header_code;
		return new WP_Error( 'ax_cal_kasi_service', sanitize_text_field( $message ) );
	}

	$items = array();
	if ( isset( $xml->body->items->item ) ) {
		foreach ( $xml->body->items->item as $item ) {
			$row = array();
			foreach ( $item as $name => $value ) {
				$row[ (string) $name ] = trim( (string) $value );
			}
			$items[] = $row;
		}
	}
	if ( array() === $items ) {
		return new WP_Error( 'ax_cal_kasi_no_items', __( 'The service returned no days for that month.', 'axismundi-calendar' ) );
	}
	return $items;
}

/**
 * Turn day rows into lunar month rows.
 *
 * Every day carries its own month's identity and length, so the month it belongs to follows from any
 * one of them: the month began `lunDay - 1` days before this day, and ran `lunNday` days. Thirty-one
 * days therefore describe the same one or two lunar months over and over, and what is kept is the
 * two.
 *
 * @param array<int,array<string,string>> $items Day rows.
 * @return array<string,array<string,mixed>> Months, keyed by identity.
 */
function axismundi_cal_kasi_months( array $items ) : array {
	$months = array();
	foreach ( $items as $item ) {
		$sol_year  = (int) ( $item['solYear'] ?? 0 );
		$sol_month = (int) ( $item['solMonth'] ?? 0 );
		$sol_day   = (int) ( $item['solDay'] ?? 0 );
		$lun_day   = (int) ( $item['lunDay'] ?? 0 );
		$length    = (int) ( $item['lunNday'] ?? 0 );
		if ( $sol_year < -9999 || $sol_month < 1 || $sol_day < 1 || $lun_day < 1 || $length < 29 ) {
			continue;
		}
		$leap = '윤' === ( $item['lunLeapmonth'] ?? '' );
		$key  = ( $item['lunYear'] ?? '' ) . ':' . ( $item['lunMonth'] ?? '' ) . ':' . ( $leap ? '1' : '0' );
		if ( isset( $months[ $key ] ) ) {
			continue;
		}
		$months[ $key ] = array(
			'start_absolute_day' => axismundi_cal_absolute_day( $sol_year, $sol_month, $sol_day ) - $lun_day + 1,
			'lunar_year'         => (int) ( $item['lunYear'] ?? 0 ),
			'lunar_month'        => (int) ( $item['lunMonth'] ?? 0 ),
			'leap_month'         => $leap,
			'days'               => $length,
		);
	}
	return $months;
}

/**
 * Fetch a Gregorian month and store the lunar months it describes.
 *
 * @param int $year  Gregorian year.
 * @param int $month Gregorian month.
 * @return int|WP_Error Number of lunar months stored.
 */
function axismundi_cal_kasi_materialise_month( int $year, int $month ) {
	$items = axismundi_cal_kasi_fetch_month( $year, $month );
	if ( is_wp_error( $items ) ) {
		return $items;
	}
	$stored = 0;
	foreach ( axismundi_cal_kasi_months( $items ) as $lunar_month ) {
		$saved = axismundi_cal_lunar_month_save( AXISMUNDI_CAL_KOREAN_LUNISOLAR, $lunar_month );
		if ( is_wp_error( $saved ) ) {
			// One unusable month does not discard the rest. The others were read from the same
			// response and are not wrong because this one was.
			continue;
		}
		++$stored;
	}
	if ( 0 === $stored ) {
		return new WP_Error( 'ax_cal_kasi_nothing', __( 'Nothing in that response was a lunar month.', 'axismundi-calendar' ) );
	}
	return $stored;
}

/**
 * Materialise every Gregorian month a range touches, stopping at the first failure.
 *
 * Stops rather than continuing, because the usual failure is the quota or the key, and the rest of
 * the range would fail the same way while making the log say it a hundred times.
 *
 * @param int $from_year Gregorian year, inclusive.
 * @param int $to_year   Gregorian year, inclusive.
 * @return array{months:int,stored:int,error:string}
 */
function axismundi_cal_kasi_materialise_years( int $from_year, int $to_year ) : array {
	$out = array( 'months' => 0, 'stored' => 0, 'error' => '' );
	for ( $year = $from_year; $year <= $to_year; $year++ ) {
		for ( $month = 1; $month <= 12; $month++ ) {
			$result = axismundi_cal_kasi_materialise_month( $year, $month );
			if ( is_wp_error( $result ) ) {
				$out['error'] = sprintf( '%04d-%02d: %s', $year, $month, $result->get_error_message() );
				return $out;
			}
			++$out['months'];
			$out['stored'] += (int) $result;
		}
	}
	return $out;
}
