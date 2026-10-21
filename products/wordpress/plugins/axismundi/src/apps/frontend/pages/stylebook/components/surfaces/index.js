import { useState } from '@wordpress/element';
import { Button } from '../../../../components/buttons/button';
import { CarouselItem } from '../../../../components/carousels/carousel-item';
import { UncontainedCarousel } from '../../../../components/carousels/uncontained-carousel';
import { Icon } from '../../../../components/material/icon';
import { List, ListItem } from '../../../../components/lists';
import { BottomSheet, Dialog, SideSheet } from '../../../../components/surfaces';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './surfaces.css';

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

/**
 * One trigger and one surface, which is the pair the contract describes.
 *
 * The trigger carries `aria-haspopup="dialog"` and an `aria-expanded` that
 * follows the open state, as `surface.md`'s accessibility table asks of every
 * row. The open state is the caller's, because closing is one path: the surface
 * asks, the caller lowers `open`, and the exit animation runs before `close()`.
 *
 * @param {Object} props Component props.
 * @return {import('@wordpress/element').ReactNode} Trigger and surface.
 */
function Specimen( { children, label, render } ) {
	const [ open, setOpen ] = useState( false );

	return (
		<div className="ax-stylebook-surfaces__specimen">
			<Button
				variant="outlined"
				aria-haspopup="dialog"
				aria-expanded={ open }
				onClick={ () => setOpen( true ) }
			>
				{ label }
			</Button>
			{ render( { open, onClose: () => setOpen( false ) } ) }
			{ children }
		</div>
	);
}

const LOREM = [
	'A dialog holds one task. The headline is pinned above this text and the actions below it, so the body is the only part that scrolls.',
	'That is why the scrolling box is a div rather than the dialog: the two pinned rows are outside it.',
	'Which raises the keyboard question this page exists to answer. A scroll container that nothing inside can focus is unreachable by keyboard, so it needs a tab stop -- but adding one unconditionally would make it the first focusable descendant, and the platform would focus it on open instead of the first interactive element.',
	'So the tab stop is conditional: the region has to overflow, and hold nothing focusable. Open this specimen and press Tab to see where focus starts.',
	'Both conditions are runtime facts, measured per open and on resize.',
	'This paragraph is here to make sure the body overflows at any reasonable window height.',
	'And so is this one.',
];

/**
 * A surface closed by the platform rather than by the caller.
 *
 * `<form method="dialog">` is how the contract reads a confirmation back, and
 * the browser closes the dialog itself when it submits. Nothing asks first, so
 * unless the `close` event is reported the caller's `open` stays true, the
 * trigger stops working, and the surface can never be opened again. Reopening
 * this specimen after confirming is the whole test.
 *
 * @return {import('@wordpress/element').ReactNode} Trigger, surface and result.
 */
function FormCloseSpecimen() {
	const [ open, setOpen ] = useState( false );
	const [ result, setResult ] = useState( null );

	return (
		<div className="ax-stylebook-surfaces__specimen">
			<Button
				variant="outlined"
				aria-haspopup="dialog"
				aria-expanded={ open }
				onClick={ () => setOpen( true ) }
			>
				Open form dialog
			</Button>
			<output>{ result ? `returned "${ result }"` : 'not answered yet' }</output>
			<Dialog
				open={ open }
				onClose={ () => setOpen( false ) }
				headline="Leave the group?"
				supportingText="Members will no longer see your posts in this group."
				actions={
					<form
						className="ax-dialog__form-actions"
						method="dialog"
						onSubmit={ ( event ) => setResult( event.nativeEvent.submitter?.value ?? '' ) }
					>
					<Button type="submit" value="cancel" variant="text">
						Cancel
					</Button>
					<Button type="submit" value="leave" variant="text">
						Leave
					</Button>
				</form>
				}
			/>
		</div>
	);
}

/**
 * A surface whose body changes while it is open.
 *
 * The scroll affordance is two runtime facts -- does the region overflow, and
 * can anything inside it take focus -- and both change without the region's own
 * box changing. Appending paragraphs grows `scrollHeight` inside a fixed
 * `max-block-size`; adding a link changes no box at all. Neither reaches a
 * ResizeObserver watching the region.
 *
 * @return {import('@wordpress/element').ReactNode} Trigger and surface.
 */
function DynamicBodySpecimen() {
	const [ open, setOpen ] = useState( false );
	const [ lines, setLines ] = useState( 1 );
	const [ link, setLink ] = useState( false );

	return (
		<div className="ax-stylebook-surfaces__specimen">
			<Button
				variant="outlined"
				aria-haspopup="dialog"
				aria-expanded={ open }
				onClick={ () => {
					setLines( 1 );
					setLink( false );
					setOpen( true );
				} }
			>
				Open growing dialog
			</Button>
			<Dialog
				open={ open }
				onClose={ () => setOpen( false ) }
				headline="Growing body"
				actions={
					<>
						<Button variant="text" onClick={ () => setLines( ( n ) => n + 6 ) }>
							Add text
						</Button>
						<Button variant="text" onClick={ () => setLink( ( on ) => ! on ) }>
							{ link ? 'Remove link' : 'Add link' }
						</Button>
						<Button variant="text" onClick={ () => setOpen( false ) }>
							Close
						</Button>
					</>
				}
			>
				{ Array.from( { length: lines }, ( ignored, index ) => (
					<p key={ index }>
						Paragraph { index + 1 }. The controls below sit outside this region, so
						pressing them changes what is in here without changing its box.
					</p>
				) ) }
				{ link ? (
					<p>
						<a href="#surfaces">A link that appears inside the scrolling body</a>
					</p>
				) : null }
			</Dialog>
		</div>
	);
}

/**
 * A standard side sheet in a real Scaffold, which is the only place it can be
 * shown honestly: the sheet is a column of the body, so without the scaffold
 * there is no column for it to take.
 *
 * Narrow the window below 600px and the same element opens as a modal instead.
 * Nothing moves in the DOM -- only `showModal()` is called where `show()` was.
 *
 * @return {import('@wordpress/element').ReactNode} Scaffold with a sheet slot.
 */
function StandardSheetSpecimen( { edge } ) {
	const [ open, setOpen ] = useState( false );

	return (
		<div className="ax-stylebook-surfaces__frame">
			<Scaffold
				appBar={ <div className="ax-stylebook-surfaces__bar">App bar</div> }
				sheet={
					<SideSheet
						open={ open }
						onClose={ () => setOpen( false ) }
						modality="standard"
						edge={ edge }
						headline="Filters"
						actions={ <Button variant="text" onClick={ () => setOpen( false ) }>Apply</Button> }
						divider
					>
						<p>The page did not move aside. The body was divided again.</p>
						<p>
							This column sits between the app bar above and the navigation bar
							below, so it needs no offsets of its own.
						</p>
					</SideSheet>
				}
			>
				<div className="ax-stylebook-surfaces__page">
					<Button
						variant="outlined"
						aria-haspopup="dialog"
						aria-expanded={ open }
						onClick={ () => setOpen( true ) }
					>
						{ open ? 'Sheet is open' : `Open standard sheet (${ edge })` }
					</Button>
					<p>
						This paragraph is the page. Watch it narrow rather than slide when the
						sheet opens.
					</p>
				</div>
			</Scaffold>
		</div>
	);
}

function BottomSheetSpecimen( { modality = 'modal' } ) {
	const [ open, setOpen ] = useState( false );

	return (
		<div className="ax-stylebook-surfaces__specimen">
			<Button
				variant="outlined"
				aria-haspopup="dialog"
				aria-expanded={ open }
				onClick={ () => setOpen( true ) }
			>
				{ `Open ${ modality } bottom sheet` }
			</Button>
			<BottomSheet
				open={ open }
				onClose={ () => setOpen( false ) }
				modality={ modality }
				headline="Choose a collection"
			>
				<p>
					The handle is a button: Tab reaches it first, and click, Space or
					Enter changes between the initial and full height without a drag.
				</p>
				<UncontainedCarousel
					label="Collections"
					itemWidth={ 144 }
					className="ax-stylebook-surfaces__bottom-carousel"
				>
					{ [ 'Images', 'Events', 'Notes', 'Places' ].map( ( label ) => (
						<CarouselItem
							key={ label }
							label={ label }
							onClick={ () => {} }
						>
							<span className="ax-stylebook-surfaces__carousel-card">{ label }</span>
						</CarouselItem>
					) ) }
				</UncontainedCarousel>
				{ LOREM.slice( 0, 4 ).map( ( line ) => <p key={ line }>{ line }</p> ) }
			</BottomSheet>
		</div>
	);
}

export function StylebookSurfacesPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--surfaces">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/stylebook">Stylebook</a>
					<a href="/stylebook/components/buttons">Buttons</a>
					<a href="/stylebook/components/tabs">Tabs</a>
				</nav>

				<section
					className="ax-stylebook-page__section"
					id="surfaces"
					aria-labelledby="ax-stylebook-surfaces-title"
				>
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-surfaces-title">Surfaces</h1>
					</header>

					<Group
						kicker="Two rows of one contract, three components in the app"
						title="Basic dialog"
					>
						<div className="ax-stylebook-surfaces__row">
							<Specimen
								label="Open basic dialog"
								render={ ( state ) => (
									<Dialog
										{ ...state }
										divider
										headline="Reset settings?"
										supportingText="This will restore every setting on this device to its default. It cannot be undone."
										actions={
											<>
												<Button variant="text" onClick={ state.onClose }>
													Cancel
												</Button>
												<Button variant="text" onClick={ state.onClose }>
													Reset
												</Button>
											</>
										}
									/>
								) }
							/>

							<Specimen
								label="Open dialog with icon"
								render={ ( state ) => (
									<Dialog
										{ ...state }
										icon={ <Icon name="delete" /> }
										headline="Delete this draft?"
										supportingText="The draft will not be recoverable."
										actions={
											<>
												<Button variant="text" onClick={ state.onClose }>
													Cancel
												</Button>
												<Button variant="text" onClick={ state.onClose }>
													Delete
												</Button>
											</>
										}
									/>
								) }
							/>

							<Specimen
								label="Open list dialog"
								render={ ( state ) => (
									<Dialog
										{ ...state }
										contentKind="list"
										icon={ <Icon name="delete" /> }
										headline="Delete drafts?"
										supportingText="Choose the drafts to remove."
										actions={
											<>
												<Button variant="text" onClick={ state.onClose }>
													Cancel
												</Button>
												<Button variant="text" onClick={ state.onClose }>
													Delete
												</Button>
											</>
										}
									>
										<List variant="standard" aria-label="Drafts to delete">
											<ListItem headline="First draft" />
											<ListItem headline="Second draft" />
										</List>
									</Dialog>
								) }
							/>
						</div>
						<p className="ax-stylebook-page__note">
							Container is surface-container-high at elevation level 3, shape 28,
							between 280 and 560dp wide with a 24dp window margin. An icon centres
							the heading; without one it starts. The confirming action sits nearest
							the trailing edge.
						</p>
					</Group>

					<Group kicker="Per instance, not per row" title="alertdialog">
						<Specimen
							label="Open alertdialog"
							render={ ( state ) => (
								<Dialog
									{ ...state }
									role="alertdialog"
									closedBy="closerequest"
									headline="Discard 3 unsent messages?"
									actions={
										<>
											<Button variant="text" onClick={ state.onClose }>
												Keep editing
											</Button>
											<Button variant="text" onClick={ state.onClose }>
												Discard
											</Button>
										</>
									}
								>
									<p>Leaving now loses the drafts.</p>
								</Dialog>
							) }
						/>
						<p className="ax-stylebook-page__note">
							M3&apos;s accessibility page calls every basic dialog an alertdialog;
							material-web makes it opt-in. A dialog holding a list or a date picker
							is not an urgent interruption, so the role is an instance setting. This
							one also takes <code>closedby=&quot;closerequest&quot;</code>: it has a
							required action, so the scrim does not dismiss it. Escape still does.
						</p>
					</Group>

					<Group kicker="The conditional tab stop" title="Scrolling body">
						<div className="ax-stylebook-surfaces__row">
							<Specimen
								label="Scrolls, nothing focusable"
								render={ ( state ) => (
									<Dialog
										{ ...state }
										headline="Terms"
										actions={
											<Button variant="text" onClick={ state.onClose }>
												Close
											</Button>
										}
									>
										{ LOREM.map( ( line ) => (
											<p key={ line }>{ line }</p>
										) ) }
									</Dialog>
								) }
							/>

							<Specimen
								label="Scrolls, holds a link"
								render={ ( state ) => (
									<Dialog
										{ ...state }
										headline="Terms with a link"
										actions={
											<Button variant="text" onClick={ state.onClose }>
												Close
											</Button>
										}
									>
										{ LOREM.map( ( line ) => (
											<p key={ line }>{ line }</p>
										) ) }
										<p>
											<a href="#surfaces">A link inside the scrolling body</a>
										</p>
									</Dialog>
								) }
							/>
						</div>
						<p className="ax-stylebook-page__note">
							The first body takes <code>tabindex=&quot;0&quot;</code>, a group role
							and the headline as its name. The second does not: focus can already
							reach inside it, so the nearest scrollable ancestor answers the arrow
							keys and a tab stop would add nothing but a stop. Section 7 of the
							escape record.
						</p>
					</Group>

					<Group kicker="Closed by the platform, not by the caller" title="Form close">
						<FormCloseSpecimen />
						<p className="ax-stylebook-page__note">
							The buttons are inside a <code>&lt;form method=&quot;dialog&quot;&gt;</code>,
							so the browser closes the surface and reports the pressed button&apos;s
							value. Confirm, then press the trigger again: a native close that is not
							reported back leaves <code>open</code> true and the trigger dead.
						</p>
					</Group>

					<Group kicker="Both conditions are runtime facts" title="Body that changes while open">
						<DynamicBodySpecimen />
						<p className="ax-stylebook-page__note">
							Add text until the body overflows and it takes a tab stop; add a link and
							the tab stop goes, because focus can already reach inside. Neither change
							resizes the region, so neither is visible to a ResizeObserver watching it.
						</p>
					</Group>

					<Group kicker="The page stays usable, so the body divides" title="Standard side sheet">
						<StandardSheetSpecimen edge="end" />
						<StandardSheetSpecimen edge="start" />
						<p className="ax-stylebook-page__note">
							256dp by default, between 256 and 400. The column is the Scaffold&apos;s,
							which is why the sheet spans its region rather than the window &mdash;
							M3&apos;s &ldquo;set by how the layout is divided into regions&rdquo;. The
							leading specimen swaps the grid areas through <code>:has()</code>, because
							the scaffold receives a node and cannot read its props.
						</p>
					</Group>

					<Group kicker="Preferred on a compact window" title="Modal side sheet">
						<div className="ax-stylebook-surfaces__row">
							<Specimen
								label="Open modal sheet"
								render={ ( state ) => (
									<SideSheet
										{ ...state }
										headline="Details"
										onBack={ state.onClose }
										actions={
											<>
												<Button variant="text" onClick={ state.onClose }>
													Cancel
												</Button>
												<Button variant="text" onClick={ state.onClose }>
													Save
												</Button>
											</>
										}
										divider
									>
										<p>Corners face the page: 16dp on this side, 0 on the edge.</p>
										<p>The scrim dims the page and the page does not scroll.</p>
									</SideSheet>
								) }
							/>
							<Specimen
								label="Open detached sheet"
								render={ ( state ) => (
									<SideSheet
										{ ...state }
										attachment="detached"
										width={ 400 }
										headline="Detached, 400dp"
									>
										<p>
											Inset 16dp and rounded all round. M3 allows this inset and
											no more: further in, it hides the page and blurs whether
											the sheet is what scrolls.
										</p>
									</SideSheet>
								) }
							/>
						</div>
						<p className="ax-stylebook-page__note">
							The close button is always rendered, never optional: M3&apos;s anatomy calls
							it recommended and its accessibility page calls it always present, and the
							stricter page is the one this project follows. A standard sheet also has no
							Escape to fall back on, because a non-modal dialog fires no cancel.
						</p>
					</Group>

					<Group kicker="Reachable without a drag gesture" title="Bottom sheet">
						<div className="ax-stylebook-surfaces__row">
							<BottomSheetSpecimen />
							<BottomSheetSpecimen modality="standard" />
						</div>
						<p className="ax-stylebook-page__note">
							The first open is capped at half the window. The real handle is the
							first tab stop and cycles initial/full height with click, Space or Enter.
							At full height, the modal specimen exposes Close; the standard specimen
							exposes Collapse sheet and remains open at its initial height.
							The Carousel intentionally scrolls horizontally inside the vertically
							scrolling sheet; that is allowed for bottom sheets and forbidden for side
							sheets.
						</p>
					</Group>

					<Group kicker="Fills a compact window, basic from medium" title="Full-screen dialog">
						<Specimen
							label="Open full-screen dialog"
							render={ ( state ) => (
								<Dialog
									{ ...state }
									presentation="full-screen"
									headline="New message"
									actions={
										<Button variant="text" onClick={ state.onClose }>
											Send
										</Button>
									}
								>
									<p>
										The close icon button is the only navigation in the header,
										and the confirming action names what happens next -- Send,
										not Done or OK. It is never disabled on this row.
									</p>
									<p>
										Narrow the window below 600px and this fills it at shape 0
										with no shadow. From medium it takes the basic dialog&apos;s
										container, corner and elevation while the 56dp header and
										the content stay as they are.
									</p>
								</Dialog>
							) }
						/>
						<p className="ax-stylebook-page__note">
							No scrim on this row, which is why the page behind stays visible through
							a transparent backdrop rather than being dimmed. The on-scroll container
							and header elevation changes are published and not implemented yet; they
							need the same scroll observation the app bar defers to its route.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
