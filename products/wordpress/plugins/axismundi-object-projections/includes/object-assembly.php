<?php
/** Type-keyed assembly of source facts for the shared Object view contract. */
defined( 'ABSPATH' ) || exit;

$GLOBALS['axismundi_op_object_assemblies'] = array();
$GLOBALS['axismundi_op_object_assemblies_loaded'] = false;

/** Register one type assembly. The callback receives facts, never a source. */
function axismundi_op_register_object_assembly( string $type, callable $assemble ) {
	if ( null === axismundi_op_vocabulary_type( $type ) ) {
		return new WP_Error( 'ax_op_assembly_type', 'Assembly requires a registered vocabulary type.' );
	}
	if ( isset( $GLOBALS['axismundi_op_object_assemblies'][ $type ] ) ) {
		return new WP_Error( 'ax_op_assembly_duplicate', 'An assembly is already registered for this type.' );
	}
	$GLOBALS['axismundi_op_object_assemblies'][ $type ] = array( 'type' => $type, 'assemble' => $assemble );
	return true;
}

/** @return array<string,array<string,mixed>> Type assembly read-back. */
function axismundi_op_object_assemblies() : array {
	if ( ! $GLOBALS['axismundi_op_object_assemblies_loaded'] ) {
		$GLOBALS['axismundi_op_object_assemblies_loaded'] = true;
		axismundi_op_register_object_assembly( 'Object', 'axismundi_op_assemble_object_view_facts' );
		do_action( 'axismundi_op_register_object_assemblies' );
	}
	return $GLOBALS['axismundi_op_object_assemblies'];
}

/**
 * Select the nearest registered ancestor; ties follow vocabulary parent order.
 * Unknown observed extension types retain the base Object presentation used
 * before this split. No extension vocabulary or storage is inferred here.
 *
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_resolve_object_assembly( string $type ) {
	$assemblies = axismundi_op_object_assemblies();
	$pending = array( $type );
	$seen = array();
	while ( $pending ) {
		$current = array_shift( $pending );
		if ( isset( $seen[ $current ] ) ) {
			continue;
		}
		$seen[ $current ] = true;
		if ( isset( $assemblies[ $current ] ) ) {
			return $assemblies[ $current ];
		}
		$definition = axismundi_op_vocabulary_type( $current );
		foreach ( (array) ( $definition['parents'] ?? array() ) as $parent ) {
			$pending[] = (string) $parent;
		}
	}
	return $assemblies['Object'] ?? new WP_Error( 'ax_op_assembly_missing', 'The base Object assembly is unavailable.' );
}

/** Assemble source-free facts and retain the common default-field contract. */
function axismundi_op_assemble_object_facts( array $facts ) {
	$assembly = axismundi_op_resolve_object_assembly( (string) ( $facts['type'] ?? 'Object' ) );
	if ( is_wp_error( $assembly ) ) {
		return $assembly;
	}
	try {
		$result = call_user_func( $assembly['assemble'], $facts );
	} catch ( Throwable $error ) {
		return new WP_Error( 'ax_op_assembly_threw', 'The type assembly raised an error.' );
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( is_array( $result ) ) {
		$result = array_merge( axismundi_op_object_view_model_defaults(), $result );
		return $result;
	}
	return new WP_Error( 'ax_op_assembly_result', 'An assembly must return facts or WP_Error.' );
}
