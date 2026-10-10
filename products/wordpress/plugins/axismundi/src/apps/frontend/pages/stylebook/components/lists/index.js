import { List, ListItem } from '../../../../components/lists';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './lists.css';

const PUBLISHED_HEIGHTS = [ 56, 72, 88 ];

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

function MetricsSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const host = hostRef.current;
		const actions = [ ...( host?.querySelectorAll( '.ax-list-item__action' ) ?? [] ) ];
		const segmented = host?.querySelector( '.ax-list[data-variant="segmented"]' );
		if ( ! actions.length || ! segmented ) {
			return;
		}

		const computed = window.getComputedStyle( actions[ 0 ] );
		setMetrics( {
			heights: actions.slice( 0, 3 ).map( ( action ) => Math.round( action.getBoundingClientRect().height ) ),
			focusThickness: Math.round( parseFloat( computed.getPropertyValue( '--md-comp-list-focus-indicator-thickness' ) ) ),
			focusOffset: Math.round( parseFloat( computed.outlineOffset ) ),
			segmentedGap: Math.round( parseFloat( window.getComputedStyle( segmented ).gap ) ),
			firstRadius: Math.round( parseFloat( window.getComputedStyle( segmented.querySelector( '.ax-list-item__action' ) ).borderTopLeftRadius ) ),
			middleRadius: Math.round( parseFloat( window.getComputedStyle( segmented.querySelectorAll( '.ax-list-item__action' )[ 1 ] ).borderTopLeftRadius ) ),
			lastRadius: Math.round( parseFloat( window.getComputedStyle( segmented.querySelectorAll( '.ax-list-item__action' )[ 2 ] ).borderBottomLeftRadius ) ),
		} );
	}, [] );

	return (
		<div ref={ hostRef } className="ax-stylebook-lists__samples">
			<List className="ax-stylebook-lists__list" aria-label="Row height specimens">
				<ListItem headline="One line" leading={ <Icon name="inbox" /> } />
				<ListItem
					headline="Two lines"
					supportingText="Supporting text defines the second text line."
					leading={ <Icon name="draft" /> }
				/>
				<ListItem
					overline="Overline"
					headline="Three lines"
					supportingText="The row grows to the published third minimum."
					leading={ <Icon name="topic" /> }
				/>
			</List>
			<List className="ax-stylebook-lists__list" aria-label="Segmented shape specimens" variant="segmented">
				<ListItem headline="First" />
				<ListItem headline="Middle" />
				<ListItem headline="Last" />
			</List>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `row heights ${ metrics.heights.join( ' / ' ) } (published ${ PUBLISHED_HEIGHTS.join( ' / ' ) }) · focus ${ metrics.focusThickness }px / ${ metrics.focusOffset }px (published 3 / -3) · segmented gap ${ metrics.segmentedGap }px (published 2) · first/middle/last ${ metrics.firstRadius }/${ metrics.middleRadius }/${ metrics.lastRadius }px (policy 16/4/16)`
					: 'Measuring...' }
			</p>
		</div>
	);
}

function HostSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const host = hostRef.current;
		if ( ! host ) {
			return;
		}

		setMetrics( {
			list: host.querySelector( '.ax-list' )?.tagName.toLowerCase(),
			items: [ ...host.querySelectorAll( '.ax-list > *' ) ].map( ( item ) => item.tagName.toLowerCase() ),
			actions: [ ...host.querySelectorAll( '.ax-list-item__action' ) ].map( ( action ) => action.tagName.toLowerCase() ),
			listbox: host.querySelectorAll( '[role="listbox"]' ).length,
		} );
	}, [] );

	return (
		<div ref={ hostRef } className="ax-stylebook-lists__samples">
			<List className="ax-stylebook-lists__list" aria-label="Native host specimens">
				<ListItem headline="Static information" trailingSupportingText="Today" />
				<ListItem href="#list-link" headline="One primary link" trailingIcon={ <Icon name="chevron_right" /> } />
				<ListItem onClick={ () => undefined } headline="One primary button" trailingIcon={ <Icon name="more_vert" /> } />
				<ListItem disabled href="#disabled-link" headline="Disabled link" />
				<ListItem disabled onClick={ () => undefined } headline="Disabled button" />
			</List>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `<${ metrics.list }> > ${ metrics.items.map( ( item ) => `<${ item }>` ).join( ', ' ) } · primary hosts ${ metrics.actions.map( ( action ) => `<${ action }>` ).join( ', ' ) } · listbox containers ${ metrics.listbox }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

function SlotSample() {
	return (
		<List className="ax-stylebook-lists__list" aria-label="Leading and trailing slot specimens">
			<ListItem
				headline="Leading icon"
				leading={ <Icon name="bookmark" /> }
				trailingSupportingText="11:42"
			/>
			<ListItem
				headline="Leading avatar"
				leading={ <span className="ax-stylebook-lists__avatar">A</span> }
				leadingType="avatar"
				trailingIcon={ <Icon name="chevron_right" /> }
			/>
			<ListItem
				headline="Leading image"
				supportingText="The content slot remains the widest column."
				leading={ <img alt="" src="https://picsum.photos/seed/ax-list/112/112" /> }
				leadingType="image"
				trailingSupportingText="2d"
			/>
			<ListItem
				headline="Leading video"
				leading={ <img alt="" src="https://picsum.photos/seed/ax-list-video/200/112" /> }
				leadingType="video"
				trailingIcon={ <Icon name="play_arrow" /> }
			/>
		</List>
	);
}

export function StylebookListsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--lists">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/cards">Cards</a>
					<a href="/social/stylebook/components/carousels">Carousels</a>
					<a href="/social/stylebook/components/dividers">Dividers</a>
				</nav>

				<section className="ax-stylebook-page__section" id="lists" aria-labelledby="ax-stylebook-lists-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-lists-title">Lists</h1>
					</header>

					<Group kicker="Expressive rows are current; baseline is recorded, not shipped" title="Row heights and segmented shape">
						<MetricsSample />
						<p className="ax-stylebook-page__note">
							The three published heights are minimums, not a reconstruction from text line boxes.
							The 16dp outer corners come from the List container token; mapping them onto
							first and last rows is the local DOM policy recorded in <code>list.yml</code>.
						</p>
					</Group>

					<Group kicker="No listbox until List owns one selection mode" title="Native single actions">
						<HostSample />
						<p className="ax-stylebook-page__note">
							A static row is a div, a navigational row is an anchor, and a command row is a
							button. Selection, multi-action rows, and their arrow-key controller remain
							closed rather than appearing as independent row props.
						</p>
					</Group>

					<Group kicker="Leading and trailing stay smaller than the readable content slot" title="Slots">
						<SlotSample />
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
