import { Button } from '../../../../components/buttons/button';
import { ButtonGroup } from '../../../../components/buttons/button-group';
import { ConnectedButtonGroup } from '../../../../components/buttons/connected-button-group';
import { Icon } from '../../../../components/material/icon';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './button-groups.css';

/*
 * Checked against `products/styleguide/_data/button_group.yml`.
 *
 * The group paints nothing, so what there is to verify is the gap per size and
 * that the children keep their own colour, width and shape. The media-player
 * row is M3's own worked example of a group with no selection at all, which is
 * the half of "standard" that is easy to forget.
 */

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

const GAPS = { xsmall: 18, small: 12, medium: 8, large: 8, xlarge: 8 };

/* `min` is `_data/button_group.yml`'s `min_width`, which only XS and S publish. */
const CONNECTED = {
	xsmall: { height: 32, inner: 8, min: 48 },
	small: { height: 40, inner: 8, min: 48 },
	medium: { height: 56, inner: 8, min: null },
	large: { height: 96, inner: 16, min: null },
	xlarge: { height: 136, inner: 20, min: null },
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

function GapSample( { size } ) {
	const hostRef = useRef();
	const [ gap, setGap ] = useState();

	useEffect( () => {
		const group = hostRef.current?.querySelector( '.ax-button-group' );
		if ( group ) {
			setGap( window.getComputedStyle( group ).columnGap );
		}
	}, [] );

	return (
		<div className="ax-stylebook-button-groups__sample" ref={ hostRef }>
			<ButtonGroup size={ size }>
				<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" size={ size } variant="outlined" />
				<Button size={ size }>Play</Button>
				<IconButton icon={ <Icon name="skip_next" /> } label="Next" size={ size } variant="outlined" />
			</ButtonGroup>
			<p className="ax-stylebook-page__note">
				{ gap ? `${ size } — gap ${ gap } (published ${ GAPS[ size ] }dp)` : 'Measuring...' }
			</p>
		</div>
	);
}

function AdjacentSample() {
	const hostRef = useRef();
	const [ selected, setSelected ] = useState( 'one' );
	const [ widths, setWidths ] = useState();

	useEffect( () => {
		const read = ( group ) =>
			[ ...( group?.children ?? [] ) ]
				.map( ( child ) => Math.round( child.getBoundingClientRect().width ) )
				.join( ' / ' );

		const groups = hostRef.current?.querySelectorAll( '.ax-button-group' );
		if ( groups?.length === 2 ) {
			setWidths( { fill: read( groups[ 0 ] ), hug: read( groups[ 1 ] ) } );
		}
	}, [ selected ] );

	const row = ( distribution ) => (
		<ButtonGroup distribution={ distribution } size="small">
			{ [ 'one', 'two', 'three' ].map( ( value ) => (
				<Button
					key={ value }
					onSelectedChange={ () => setSelected( value ) }
					selected={ selected === value }
					toggle
					variant="tonal"
				>
					{ `Option ${ value }` }
				</Button>
			) ) }
		</ButtonGroup>
	);

	return (
		<div className="ax-stylebook-button-groups__adjacent" ref={ hostRef }>
			{ row( 'fill' ) }
			{ row( 'hug' ) }
			<p className="ax-stylebook-page__note">
				{ widths ? `fill ${ widths.fill } · hug ${ widths.hug }` : 'Measuring...' }
			</p>
		</div>
	);
}

function ConnectedSample( {
	label = 'Date range',
	selectionMode = 'single',
	selectionRequired = false,
	shape = 'round',
} ) {
	/* The disabled `notes` request verifies that disabled segments cannot render selected. */
	const [ value, setValue ] = useState(
		'multiple' === selectionMode ? [ 'photos', 'notes', 'links' ] : 'week'
	);

	const reading = Array.isArray( value )
		? ( value.length ? value.join( ', ' ) : 'none' )
		: ( value ?? 'none' );

	return (
		<div className="ax-stylebook-button-groups__sample">
			<ConnectedButtonGroup
				label={ 'multiple' === selectionMode ? 'Content filters' : label }
				onValueChange={ setValue }
				segments={
					'multiple' === selectionMode
						? [
							{ icon: <Icon name="image" />, label: 'Photos', value: 'photos' },
							{ disabled: true, icon: <Icon name="note" />, label: 'Notes', value: 'notes' },
							{ icon: <Icon name="link" />, label: 'Links', value: 'links' },
						]
						: [
							{ label: 'Day', value: 'day' },
							{ label: 'Week', value: 'week' },
							{ label: 'Month', value: 'month' },
						]
				}
				selectionMode={ selectionMode }
				selectionRequired={ selectionRequired }
				shape={ shape }
				value={ value }
			/>
			<p className="ax-stylebook-page__note">
				{ selectionMode } { selectionRequired ? '· required' : '· optional' } &mdash; value: { reading }
			</p>
		</div>
	);
}

/*
 * A connected group divides its surface, so the interesting width is a narrow
 * one. This specimen is the only place on the page where the published floor is
 * reachable: 48dp a segment at XS and S, with labels longer than the share they
 * get. Two things were wrong here and the readout reports both rather than
 * asserting either -- a group in a 240px surface measured 347px and overflowed
 * it, and a 140px label in a 79px segment put its box 31px outside the segment.
 *
 * What is measurable from script is geometry: the group against its surface, and
 * each label's box against its segment. How much text the ellipsis removes is
 * measurable too (`scrollWidth` against `clientWidth`). Whether the clipped
 * glyphs actually stop painting at the segment edge is `overflow: hidden` doing
 * its job, which is read from the stylesheet and not claimed here as measured.
 */
function ConnectedNarrowSample( { size, width } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const group = hostRef.current?.querySelector( '.ax-connected-button-group' );
		const segments = [ ...( group?.querySelectorAll( '.ax-connected-button-group__segment' ) ?? [] ) ];
		if ( ! segments.length ) {
			return;
		}

		const texts = segments
			.map( ( segment ) => ( {
				box: segment.getBoundingClientRect(),
				text: segment.querySelector( '.ax-connected-button-group__label' ),
			} ) )
			.filter( ( entry ) => entry.text );

		setMetrics( {
			clipped: Math.max(
				...texts.map( ( { text } ) => text.scrollWidth - text.clientWidth )
			),
			escaping: texts.filter( ( { box, text } ) => {
				const label = text.getBoundingClientRect();
				return label.left < box.left - 0.5 || label.right > box.right + 0.5;
			} ).length,
			group: Math.round( group.getBoundingClientRect().width ),
			host: Math.round( hostRef.current.getBoundingClientRect().width ),
			segment: Math.round( segments[ 0 ].getBoundingClientRect().width ),
		} );
	}, [] );

	return (
		<div className="ax-stylebook-button-groups__narrow" ref={ hostRef } style={ { inlineSize: width } }>
			<ConnectedButtonGroup
				defaultValue="recent"
				label={ `Sort order in a ${ width } surface` }
				segments={ [
					{ label: 'Recently updated', value: 'recent' },
					{ label: 'Alphabetical', value: 'alpha' },
					{ label: 'Most replies', value: 'replies' },
				] }
				selectionRequired
				size={ size }
			/>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `surface ${ metrics.host } · group ${ metrics.group } · segment ${ metrics.segment } (published floor ${ CONNECTED[ size ].min ? `${ CONNECTED[ size ].min }dp` : 'none' }) · label boxes outside their segment ${ metrics.escaping } · widest label clipped by ${ metrics.clipped }px`
					: 'Measuring...' }
			</p>
		</div>
	);
}

function ConnectedSizeSample( { size } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const group = hostRef.current?.querySelector( '.ax-connected-button-group' );
		const segments = group?.querySelectorAll( '.ax-connected-button-group__segment' );
		if ( group && segments?.length ) {
			const selectedSegment = segments[ 0 ];
			const restingSegment = segments[ 1 ] ?? selectedSegment;
			setMetrics( {
				gap: window.getComputedStyle( group ).columnGap,
				height: Math.round( selectedSegment.getBoundingClientRect().height ),
				inner: window.getComputedStyle( restingSegment ).borderTopLeftRadius,
				width: Math.round( selectedSegment.getBoundingClientRect().width ),
			} );
		}
	}, [] );

	return (
		<div className="ax-stylebook-button-groups__sample" ref={ hostRef }>
			<ConnectedButtonGroup
				defaultValue="one"
				label={ `${ size } connected selection` }
				segments={ [ { label: 'One', value: 'one' }, { label: 'Two', value: 'two' } ] }
				selectionRequired
				size={ size }
			/>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `${ size } — h ${ metrics.height } (published ${ CONNECTED[ size ].height }dp) · seam ${ metrics.gap } · inner ${ metrics.inner } (published ${ CONNECTED[ size ].inner }dp) · segment ${ metrics.width }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookButtonGroupsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--button-groups">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
				</nav>

				<section className="ax-stylebook-page__section" id="button-groups" aria-labelledby="ax-stylebook-button-groups-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-button-groups-title">Button groups</h1>
					</header>

					<Group kicker="The gap keeps a 48dp target reachable, so it is per size" title="Standard, by size">
						<div className="ax-stylebook-button-groups__sizes">
							{ SIZES.map( ( size ) => <GapSample key={ size } size={ size } /> ) }
						</div>
						<p className="ax-stylebook-page__note">
							XS and S carry the larger gaps because their controls are the ones that would
							otherwise sit too close to a neighbour.
						</p>
					</Group>

					<Group kicker="M3's own example, and it has no selection in it" title="An action group">
						<ButtonGroup label="Playback" size="medium">
							<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" size="medium" variant="outlined" />
							<Button size="medium" variant="tonal">Play</Button>
							<IconButton icon={ <Icon name="skip_next" /> } label="Next" size="medium" variant="outlined" />
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							Emphasis comes from the buttons&rsquo; own size, colour and width. The container
							arranges and nothing else &mdash; it has no colour axis, and M3 publishes no shape
							tokens for a standard group either.
						</p>
					</Group>

					<Group kicker="Allowed for hero moments; the gap stays the group's" title="Mixed sizes">
						<div className="ax-stylebook-button-groups__sizes">
							<ButtonGroup label="Playback" size="small">
								<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" variant="outlined" />
								<Button size="medium" variant="tonal">Play</Button>
								<IconButton icon={ <Icon name="skip_next" /> } label="Next" variant="outlined" />
							</ButtonGroup>
							<ButtonGroup size="large">
								<Button size="large">Large</Button>
								<Button size="small" variant="outlined">Small inside a large group</Button>
							</ButtonGroup>
						</div>
						<p className="ax-stylebook-page__note">
							The hero is a different size and the group&rsquo;s gap does not follow it. The
							second row is the case worth checking: XS and S carry the wider gaps so a small
							control still clears its neighbour, and a small button placed in a large group
							takes the large group&rsquo;s narrower one instead.
						</p>
					</Group>

					<Group kicker="Children keep what they chose" title="Mixed colours and widths">
						<ButtonGroup size="small">
							<Button>Filled</Button>
							<Button variant="tonal">Tonal</Button>
							<Button variant="outlined">Outlined</Button>
							<IconButton icon={ <Icon name="more_vert" /> } label="More" variant="outlined" />
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							Mixing colour is allowed here and is the point of a standard group. A connected
							group is the one M3 tells you not to mix.
						</p>
						<p className="ax-stylebook-page__note">
							Width is the exception. The group&rsquo;s gap is what delivers the 48dp target
							&mdash; a container plus its gap clears 48 at every size &mdash; and a narrow XS
							or S control is the one thing that leaves it short, by 2 to 6px depending on the
							pair. Narrow is therefore unsupported in a group for now, and the component says
							so in development.
						</p>
					</Group>

					<Group kicker="Opt-in, because it contradicts hugging" title="Adjacent interaction">
						<AdjacentSample />
						<p className="ax-stylebook-page__note">
							Selecting widens the chosen button by 15% and its neighbours compress to pay for
							it. This needs <code>distribution=&quot;fill&quot;</code>: a group sized to its
							content has nothing to redistribute, so the same markup at the default
							<code> hug </code>does not move at all. Both are below.
						</p>
					</Group>

					<Group kicker="Height, seam and the inner corner, against the published figures" title="Connected, by size">
						<div className="ax-stylebook-button-groups__sizes">
							{ SIZES.map( ( size ) => <ConnectedSizeSample key={ size } size={ size } /> ) }
						</div>
						<p className="ax-stylebook-page__note">
							These span the page, so they verify height, the 2dp seam and the resting inner corner,
							and nothing about the published 48dp floor at XS and S &mdash; a 600dp segment cannot
							show a minimum it is nowhere near. The narrow specimens below are where that figure is
							reachable.
						</p>
					</Group>

					<Group kicker="Only XS and S publish a minimum segment width, and only a narrow surface reaches it" title="Connected, in a narrow surface">
						<div className="ax-stylebook-button-groups__connected">
							<ConnectedNarrowSample size="small" width="360px" />
							<ConnectedNarrowSample size="xsmall" width="240px" />
						</div>
						<p className="ax-stylebook-page__note">
							The labels here are longer than the share each segment gets, which is the ordinary case
							for a sort control in a feed. M3&rsquo;s published answer to a surface this narrow is the
							Segment&rsquo;s own <code>Show label text</code>; short of that, the label is clipped
							rather than drawn over its neighbour, and the accessible name stays whole either way.
						</p>
					</Group>

					<Group kicker="Segments are parts, so the group owns their geometry and fixed Filled-toggle colours" title="Connected selection">
						<div className="ax-stylebook-button-groups__connected">
							<ConnectedSample selectionRequired />
							<ConnectedSample label="Date range, optional" />
							<ConnectedSample label="Date range with square outer corners" selectionRequired shape="square" />
							<ConnectedSample selectionMode="multiple" />
						</div>
						<p className="ax-stylebook-page__note">
							Connected replaces segmented buttons. It spans its surface, keeps a 2dp seam at every
							size, and changes only the selected segment&rsquo;s inner corners; neighbouring segments
							do not redistribute. The first row requires one choice, so pressing the selected segment
							leaves it selected; the second is the same control without that requirement, where
							pressing it clears to none. The third verifies square outer corners, and the fourth
							allows any number of filters while keeping a disabled segment, which cannot render
							selected even though the initial value asks for it.
						</p>
						<p className="ax-stylebook-page__note">
							All three published keyboard rows hold here. Tab steps through every segment, because
							each one keeps its own tab stop; the left and right arrows move focus within a group
							without changing the selection; Space or Enter activates. That is not a radiogroup, and
							the reason is the tab stops rather than the arrows &mdash; a radiogroup is one tab stop
							with arrows inside it.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
