<?php
/** Protocol value and error helpers. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

/** A method failure, not an HTTP/request failure. */
final class MethodError extends \RuntimeException {

	public function __construct( public readonly string $type, string $description = '' ) {
		parent::__construct( $description );
	}

	public function arguments() : \stdClass {
		$result = (object) array( 'type' => $this->type );
		if ( '' !== $this->getMessage() ) {
			$result->description = $this->getMessage();
		}
		return $result;
	}
}

/** A request rejected before any method is executed. */
final class RequestError extends \RuntimeException {

	public function __construct( public readonly string $type, public readonly int $status, string $detail, public readonly ?string $limit = null ) {
		parent::__construct( $detail );
	}

	public function problem() : \stdClass {
		$result = (object) array(
			'type'   => 'urn:ietf:params:jmap:error:' . $this->type,
			'status' => $this->status,
			'detail' => $this->getMessage(),
		);
		if ( null !== $this->limit ) {
			$result->limit = $this->limit;
		}
		return $result;
	}
}

/** RFC 8620 Id; method call IDs are arbitrary strings, not this type. */
function valid_id( mixed $value ) : bool {
	return is_string( $value ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,255}\z/D', $value );
}

/** Keep JSON object/array distinctions and isolate handlers from earlier results. */
function copy_json( mixed $value ) : mixed {
	return json_decode( json_encode( $value, JSON_THROW_ON_ERROR ), false, 512, JSON_THROW_ON_ERROR );
}
