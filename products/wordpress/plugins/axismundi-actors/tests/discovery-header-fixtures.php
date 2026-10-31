<?php
/** Execute current response emitters in isolated namespaces, capturing actual header calls. */
defined( 'ABSPATH' ) || exit( 1 );

return static function ( bool $mutate = false, ?string $only = null ) : int {
	$checks = 0;
	foreach ( array( 'webfinger', 'nodeinfo' ) as $kind ) {
		if ( null !== $only && $kind !== $only ) { continue; }
		$reflection = new ReflectionFunction( 'axismundi_actors_serve_' . $kind );
		$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
		if ( $mutate ) {
			$source = str_replace( "header( 'Access-Control-Allow-Origin: *' );", '', $source, $count );
			if ( 1 !== $count ) { throw new RuntimeException( 'MISSED: CORS mutation anchor must match once' ); }
		}
		$source = str_replace( 'exit;', 'return;', $source );
		$namespace = 'AxDiscoveryProbe' . str_replace( '-', '', wp_generate_uuid4() );
		$prelude = <<<'PHP'
function header( $value ) { $GLOBALS['ax_discovery_probe']['headers'][] = $value; }
function status_header( $value ) { $GLOBALS['ax_discovery_probe']['status'] = $value; }
function get_query_var( $key ) { return $GLOBALS['ax_discovery_probe']['query'][$key] ?? ''; }
function axismundi_actors_webfinger_descriptor( $resource ) {
    return 'missing' === $resource ? new \WP_Error( 'not_found' ) : array( 'subject' => $resource );
}
PHP;
		eval( 'namespace ' . $namespace . '; ' . $prelude . $source );
		$serve = $namespace . '\\axismundi_actors_serve_' . $kind;
		$original_get = $_GET;
		try {
			foreach ( 'webfinger' === $kind ? array( 'found', 'missing' ) : array( 'discovery', '2.1' ) as $case ) {
				$GLOBALS['ax_discovery_probe'] = array( 'headers' => array(), 'query' => array( 'ax_webfinger' => 1, 'ax_nodeinfo' => $case ) );
				$_GET['resource'] = $case;
				ob_start();
				try { $serve(); $body = ob_get_contents(); } finally { ob_end_clean(); }
				$headers = $GLOBALS['ax_discovery_probe']['headers'];
				if ( ! in_array( 'Access-Control-Allow-Origin: *', $headers, true ) ) { throw new RuntimeException( $kind . ' CORS response' ); }
				++$checks;
				if ( ! in_array( 'Access-Control-Allow-Methods: GET, HEAD', $headers, true ) ) { throw new RuntimeException( $kind . ' CORS methods' ); }
				++$checks;
				$type = 'webfinger' === $kind ? 'application/jrd+json' : 'application/json';
				if ( ! array_filter( $headers, static fn( $header ) => str_starts_with( $header, 'Content-Type: ' . $type . ';' ) ) ) { throw new RuntimeException( $kind . ' content type' ); }
				++$checks;
				if ( ( 'missing' === $case ? 404 : 200 ) !== $GLOBALS['ax_discovery_probe']['status'] || ! is_array( json_decode( $body, true ) ) ) { throw new RuntimeException( $kind . ' status and JSON body' ); }
				++$checks;
			}
			$GLOBALS['ax_discovery_probe'] = array( 'headers' => array(), 'query' => array() );
			$serve();
			if ( array() !== $GLOBALS['ax_discovery_probe']['headers'] ) { throw new RuntimeException( 'Non-discovery response changed' ); }
			++$checks;
		} finally { $_GET = $original_get; unset( $GLOBALS['ax_discovery_probe'] ); }
	}
	return $checks;
};
