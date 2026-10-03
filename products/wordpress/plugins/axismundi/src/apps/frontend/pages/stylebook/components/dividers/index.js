import { Divider } from '../../../../components/dividers/divider';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './dividers.css';

/*
 * Checked against `products/styleguide/_data/divider.yml`.
 *
 * The whole token surface is two rows: `md.comp.divider.color` references
 * `md.sys.color.outline-variant` and `md.comp.divider.thickness` is 1dp. Three
 * figures published alongside them -- the 4dp gap to supporting text and the 8dp
 * right and bottom margins -- belong to the consumer, so this page spends them
 * and the component does not.
 *
 * The card page keeps its own divider group, which is not a duplicate: it shows
 * a divider separating regions inside a card, which is the use M3 names, and it
 * measures the ownership of spacing in a real consumer. This page measures the
 * component.
 */

const VARIANTS = [ 'full', 'inset', 'middle-inset' ];

const PUBLISHED = {
	full: { start: 0, end: 0 },
	inset: { start: 16, end: 0 },
	'middle-inset': { start: 16, end: 16 },
};

function Group( { children, kicker, title } ) {
	return (
		<section className="ax-stylebook-page__group" aria-label={ title }>
			<header className="ax-stylebook-page__group-header">
				<p className="ax-stylebook-page__group-kicker">{ kicker }</p>
				<h2>{ title }</h2>
			</header>
			{ children }
		</section>
	);
}

/*
 * The colour is read by resolving the role on the element rather than by
 * comparing against a hex, so a scheme change cannot make this specimen lie.
 */
function InsetSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const host = hostRef.current;
		const first = host?.querySelector( '.ax-divider' );
		if ( ! first ) {
			return;
		}

		const probe = document.createElement( 'span' );
		probe.style.color = window
			.getComputedStyle( first )
			.getPropertyValue( '--md-sys-color-outline-variant' )
			.trim();
		host.appendChild( probe );
		const role = window.getComputedStyle( probe ).color;
		probe.remove();

		setMetrics( {
			role,
			rows: VARIANTS.map( ( variant ) => {
				const line = host.querySelector( `.ax-divider[data-variant="${ variant }"]` );
				const computed = window.getComputedStyle( line );
				return {
					variant,
					start: Math.round( parseFloat( computed.marginInlineStart ) ),
					end: Math.round( parseFloat( computed.marginInlineEnd ) ),
					thickness: Math.round( parseFloat( computed.blockSize ) ),
					block: `${ Math.round( parseFloat( computed.marginBlockStart ) ) }/${ Math.round( parseFloat( computed.marginBlockEnd ) ) }`,
					paints: computed.backgroundColor === role,
				};
			} ),
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-dividers__stack">
				{ VARIANTS.map( ( variant ) => (
					<div className="ax-stylebook-dividers__row" key={ variant }>
						<p className="ax-stylebook-page__note">{ variant }</p>
						<Divider variant={ variant } />
					</div>
				) ) }
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? metrics.rows
						.map(
							( row ) =>
								`${ row.variant } ${ row.start }/${ row.end } (published ${ PUBLISHED[ row.variant ].start }/${ PUBLISHED[ row.variant ].end }) ${ row.thickness }px${ row.paints ? '' : ' COLOUR MISMATCH' }`
						)
						.join( ' · ' ) + ` · block margins ${ metrics.rows[ 0 ].block } (the component declares none)`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * Decorative is the default because M3 calls dividers decorative, and a
 * decorative line should not be announced. The announced form is an `<hr>`,
 * which carries `role="separator"` natively rather than having the role put on
 * a span.
 */
function SemanticsSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const lines = [ ...( hostRef.current?.querySelectorAll( '.ax-divider' ) ?? [] ) ];
		setMetrics(
			/*
			 * There is no way to read a computed accessibility role from script, so
			 * this reports what is measurable -- the element and whether it is hidden
			 * -- and leaves `<hr>` mapping to `separator` to the note below, where it
			 * is cited rather than claimed as measured.
			 */
			lines.map( ( line ) => ( {
				tag: line.tagName.toLowerCase(),
				hidden: line.getAttribute( 'aria-hidden' ) ?? 'not set',
				role: line.getAttribute( 'role' ) ?? 'no role attribute',
			} ) )
		);
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-dividers__stack">
				<div className="ax-stylebook-dividers__row">
					<p className="ax-stylebook-page__note">decorative (default)</p>
					<Divider />
				</div>
				<div className="ax-stylebook-dividers__row">
					<p className="ax-stylebook-page__note">decorative={ '{ false }' }</p>
					<Divider decorative={ false } />
				</div>
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? metrics
						.map( ( m ) => `<${ m.tag }> · aria-hidden ${ m.hidden } · ${ m.role }` )
						.join( ' · ' )
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * A vertical divider is published in the prose with no measurements of its own,
 * so it shares the colour and the 1dp thickness and takes its length from the
 * row. The readout reports that length next to the row's height, since a figure
 * this component invented would show up as a mismatch.
 */
function VerticalSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const row = hostRef.current?.querySelector( '.ax-stylebook-dividers__inline' );
		const line = row?.querySelector( '.ax-divider' );
		if ( ! line ) {
			return;
		}
		setMetrics( {
			width: Math.round( line.getBoundingClientRect().width ),
			height: Math.round( line.getBoundingClientRect().height ),
			row: Math.round( row.getBoundingClientRect().height ),
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-dividers__inline">
				<p className="ax-stylebook-page__note">Paragraph text</p>
				<Divider orientation="vertical" />
				<p className="ax-stylebook-page__note">Media beside it</p>
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `${ metrics.width }px wide (published 1dp) · ${ metrics.height }px tall, in a ${ metrics.row }px row — the length is the row's, not the component's`
					: 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookDividersPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--dividers">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/button-groups">Button groups</a>
					<a href="/social/stylebook/components/split-buttons">Split buttons</a>
					<a href="/social/stylebook/components/cards">Cards</a>
				</nav>

				<section className="ax-stylebook-page__section" id="dividers" aria-labelledby="ax-stylebook-dividers-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-dividers-title">Dividers</h1>
					</header>

					<Group kicker="Three, although the prose says two ways" title="Insets">
						<InsetSample />
						<p className="ax-stylebook-page__note">
							M3&rsquo;s text offers full width and inset, while its measurements table publishes
							inset and middle-inset with different figures &mdash; 16dp start and 0dp end against
							16dp on both sides. Three is what the component exposes, and the theme had already
							shipped both as separate <code>core/separator</code> style variations.
						</p>
						<p className="ax-stylebook-page__note">
							Every gap on this page belongs to this page. The divider measurements table also
							publishes a 4dp gap to supporting text and 8dp right and bottom margins, and those
							describe the space around a divider in a layout. A component that shipped them would
							put space into every card and list using one, with no way for a caller to remove it,
							so the readout above reports the component&rsquo;s own block margins as none.
						</p>
					</Group>

					<Group kicker="A line between sections is a separator; a line inside a card is decoration" title="Decorative or announced">
						<SemanticsSample />
						<p className="ax-stylebook-page__note">
							M3 calls dividers decorative, which is a statement about colour contrast &mdash; they
							&ldquo;have no contrast minimums&rdquo;. ARIA has a real <code>separator</code> role
							and an <code>&lt;hr&gt;</code> maps to it natively, so the caller decides which this
							is and the default is the one M3 names. The announced form uses the element the
							platform already defines rather than putting the role on a span.
						</p>
						<p className="ax-stylebook-page__note">
							The readout reports the element and whether it is hidden, because those are
							measurable. That <code>&lt;hr&gt;</code> maps to <code>separator</code> is HTML-AAM,
							read from the spec rather than measured here &mdash; a computed accessibility role
							cannot be read from script.
						</p>
					</Group>

					<Group kicker="Published in the prose, with no measurements of its own" title="Vertical">
						<VerticalSample />
						<p className="ax-stylebook-page__note">
							&ldquo;A vertical divider can be used to arrange content on a larger screen, such as
							separating paragraph text from video or imagery media.&rdquo; It shares the colour and
							the 1dp thickness; its length and the space around it belong to whatever is arranging
							the content, so no height is invented here.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
