import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

const FONT_FAMILIES_QUERY = {
	_embed: true,
	context: 'edit',
	orderby: 'title',
	order: 'asc',
	per_page: 100,
};

const FONT_COLLECTIONS_QUERY = {
	_fields: 'slug,name,description',
	per_page: 100,
};

function getText( value ) {
	if ( typeof value === 'string' ) {
		return value;
	}

	return value?.rendered || '';
}

function getFontFaces( fontFamily ) {
	return ( fontFamily?._embedded?.font_faces || [] )
		.map( ( fontFace ) => fontFace?.font_face_settings )
		.filter( Boolean );
}

function isVariableFace( face ) {
	return [ face?.fontWeight, face?.fontStretch ]
		.filter( Boolean )
		.some( ( value ) => String( value ).trim().includes( ' ' ) ) || Boolean( face?.fontVariationSettings );
}

function normalizeFontFamily( record ) {
	const settings = record?.font_family_settings || {};
	const faces = getFontFaces( record );

	return {
		faces,
		fontFamily: settings.fontFamily || '',
		id: record?.id,
		isVariable: faces.some( isVariableFace ),
		name: settings.name || getText( record?.name ) || settings.fontFamily || __( 'Untitled font family', 'axismundi' ),
		slug: settings.slug || record?.slug || '',
	};
}

function FontFamilyCard( { active, fontFamily, onSelect } ) {
	const variableLabel = fontFamily.isVariable ? __( 'Variable', 'axismundi' ) : __( 'Static', 'axismundi' );
	const faceLabel = fontFamily.faces.length === 1
		? __( '1 face', 'axismundi' )
		: `${ fontFamily.faces.length } ${ __( 'faces', 'axismundi' ) }`;

	return (
		<button
			aria-pressed={ active }
			className="ax-admin-font-inventory__family"
			onClick={ onSelect }
			type="button"
		>
			<span className="ax-admin-font-inventory__family-name">{ fontFamily.name }</span>
			<span className="ax-admin-font-inventory__family-meta">{ variableLabel }</span>
			<span className="ax-admin-font-inventory__family-meta">{ faceLabel }</span>
		</button>
	);
}

function FaceDetail( { face, index } ) {
	const name = face.fontStyle || __( 'Normal', 'axismundi' );
	const source = Array.isArray( face.src ) ? face.src.join( ', ' ) : face.src;
	const values = [
		[ __( 'Style', 'axismundi' ), face.fontStyle ],
		[ __( 'Weight', 'axismundi' ), face.fontWeight ],
		[ __( 'Stretch', 'axismundi' ), face.fontStretch ],
		[ __( 'Variation settings', 'axismundi' ), face.fontVariationSettings ],
		[ __( 'Display', 'axismundi' ), face.fontDisplay ],
		[ __( 'Unicode range', 'axismundi' ), face.unicodeRange ],
		[ __( 'Source', 'axismundi' ), source ],
	].filter( ( [ , value ] ) => value );

	return (
		<li className="ax-admin-font-inventory__face">
			<h4>{ `${ name } ${ index + 1 }` }</h4>
			<dl className="ax-admin-font-inventory__definition-list">
				{ values.map( ( [ label, value ] ) => (
					<div key={ label }>
						<dt>{ label }</dt>
						<dd>{ value }</dd>
					</div>
				) ) }
			</dl>
		</li>
	);
}

function FontFamilyDetail( { fontFamily } ) {
	if ( ! fontFamily ) {
		return null;
	}

	return (
		<section aria-label={ fontFamily.name } className="ax-admin-font-inventory__detail">
			<p className="ax-admin-font-inventory__eyebrow">{ __( 'Registered family', 'axismundi' ) }</p>
			<h3>{ fontFamily.name }</h3>
			<dl className="ax-admin-font-inventory__definition-list ax-admin-font-inventory__definition-list--family">
				<div>
					<dt>{ __( 'Slug', 'axismundi' ) }</dt>
					<dd>{ fontFamily.slug || __( 'Unavailable', 'axismundi' ) }</dd>
				</div>
				<div>
					<dt>{ __( 'CSS family', 'axismundi' ) }</dt>
					<dd>{ fontFamily.fontFamily || __( 'Unavailable', 'axismundi' ) }</dd>
				</div>
				<div>
					<dt>{ __( 'Capabilities', 'axismundi' ) }</dt>
					<dd>{ fontFamily.isVariable ? __( 'Variable', 'axismundi' ) : __( 'Static', 'axismundi' ) }</dd>
				</div>
			</dl>

			<h4 className="ax-admin-font-inventory__section-title">{ __( 'Faces', 'axismundi' ) }</h4>
			{ fontFamily.faces.length ? (
				<ul className="ax-admin-font-inventory__faces">
					{ fontFamily.faces.map( ( face, index ) => (
						<FaceDetail face={ face } index={ index } key={ `${ face.fontStyle || 'normal' }-${ face.fontWeight || index }-${ index }` } />
					) ) }
				</ul>
			) : (
				<p>{ __( 'This registered family has no font face records.', 'axismundi' ) }</p>
			) }
		</section>
	);
}

function FontCollections( { collections } ) {
	return (
		<section aria-labelledby="ax-admin-font-collections-title" className="ax-admin-font-inventory__collections">
			<div className="ax-admin-font-inventory__section-heading">
				<h3 id="ax-admin-font-collections-title">{ __( 'Collections', 'axismundi' ) }</h3>
				<p>{ __( 'Available sources registered with WordPress.', 'axismundi' ) }</p>
			</div>
			{ collections.length ? (
				<ul className="ax-admin-font-inventory__collection-list">
					{ collections.map( ( collection ) => (
						<li key={ collection.slug }>
							<strong>{ collection.name || collection.slug }</strong>
							<span>{ collection.description || collection.slug }</span>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No font collections are registered.', 'axismundi' ) }</p>
			) }
		</section>
	);
}

/**
 * Read-only projection of WordPress native font records.
 * It intentionally does not interpret installation as Frontend activation.
 */
export default function FontsInventory() {
	const fontFamiliesQuery = useEntityRecords( 'postType', 'wp_font_family', FONT_FAMILIES_QUERY );
	const fontCollectionsQuery = useEntityRecords( 'root', 'fontCollection', FONT_COLLECTIONS_QUERY );
	const [ selectedSlug, setSelectedSlug ] = useState( '' );
	const fontFamilies = useMemo(
		() => ( fontFamiliesQuery.records || [] ).map( normalizeFontFamily ),
		[ fontFamiliesQuery.records ]
	);
	const selectedFontFamily = fontFamilies.find( ( fontFamily ) => fontFamily.slug === selectedSlug ) || fontFamilies[ 0 ];
	const isLoading = ( fontFamiliesQuery.isResolving && ! fontFamiliesQuery.records ) ||
		( fontCollectionsQuery.isResolving && ! fontCollectionsQuery.records );
	const hasError = fontFamiliesQuery.status === 'ERROR' || fontCollectionsQuery.status === 'ERROR';

	if ( isLoading ) {
		return <p role="status">{ __( 'Loading font resources…', 'axismundi' ) }</p>;
	}

	if ( hasError ) {
		return (
			<p className="ax-admin-font-inventory__notice" role="alert">
				{ __( 'WordPress font resources could not be loaded.', 'axismundi' ) }
			</p>
		);
	}

	return (
		<div className="ax-admin-font-inventory">
			<section aria-labelledby="ax-admin-installed-fonts-title" className="ax-admin-font-inventory__installed">
				<div className="ax-admin-font-inventory__section-heading">
					<h3 id="ax-admin-installed-fonts-title">{ __( 'Installed', 'axismundi' ) }</h3>
					<p>{ __( 'Font families registered in WordPress. This does not indicate Frontend use.', 'axismundi' ) }</p>
				</div>
				{ fontFamilies.length ? (
					<div className="ax-admin-font-inventory__workspace">
						<div aria-label={ __( 'Installed font families', 'axismundi' ) } className="ax-admin-font-inventory__family-list">
							{ fontFamilies.map( ( fontFamily ) => (
								<FontFamilyCard
									active={ selectedFontFamily?.slug === fontFamily.slug }
									fontFamily={ fontFamily }
									key={ fontFamily.id || fontFamily.slug }
									onSelect={ () => setSelectedSlug( fontFamily.slug ) }
								/>
							) ) }
						</div>
						<FontFamilyDetail fontFamily={ selectedFontFamily } />
					</div>
				) : (
					<p>{ __( 'No font families are installed in WordPress.', 'axismundi' ) }</p>
				) }
			</section>
			<FontCollections collections={ fontCollectionsQuery.records || [] } />
		</div>
	);
}
