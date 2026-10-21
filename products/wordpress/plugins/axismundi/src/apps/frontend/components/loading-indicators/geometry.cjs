// The exporter has already matched cubic topology for each directed pair.
function pathForCubics( cubics ) {
	return `M${ cubics[ 0 ][ 0 ] } ${ cubics[ 0 ][ 1 ] } ${ cubics.map( ( cubic ) => `C${ cubic.slice( 2 ).join( ' ' ) }` ).join( ' ' ) } Z`;
}

module.exports = { pathForCubics };
