<?php
/** Execute the same policy assertions through known-positive controls and in-memory mutants. */
defined( 'ABSPATH' ) || exit( 1 );
$reflection = new ReflectionFunction( 'axismundi_op_article_response' );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
$mutations = array(
	'body' => array( "unset( \$document['content'], \$document['contentMap'] );", '', 'List omits content' ),
	'language' => array( "unset( \$document['content'], \$document['contentMap'] );", "unset( \$document['content'] );", 'List omits contentMap' ),
	'identity' => array( "return \$document;\n}", "\$document['id'] .= '/list'; return \$document;\n}", 'List retains identity' ),
	'nested' => array( "unset( \$document['attachment'] );", "unset( \$document['attachment'], \$document['preview']['attachment'] );", 'Nested preview is untouched' ),
	'coupled' => array( "unset( \$document['content'], \$document['contentMap'] );", "if ( 'omit' === \$attachments ) { unset( \$document['content'], \$document['contentMap'] ); }", 'Attachments never enable body' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $needle, $replacement, $expected ] ) {
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Ambiguous mutation anchor: ' . $name ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxArticleList_' . $name . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$callback = $namespace . '\\axismundi_op_article_response';
		$error = null;
		ob_start();
		try {
			(static function ( $callback ) { $article_response = $callback; require __DIR__ . '/audit-article-list.php'; })( $callback );
		} catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( ! $mutated && $error ) { throw $error; }
		if ( $mutated ) {
			if ( ! $error || $expected !== $error->getMessage() ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught;
			echo 'CAUGHT ' . $name . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
