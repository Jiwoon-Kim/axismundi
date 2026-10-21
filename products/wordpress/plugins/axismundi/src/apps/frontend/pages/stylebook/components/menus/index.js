import { useState } from '@wordpress/element';
import { Button } from '../../../../components/buttons/button';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Icon } from '../../../../components/material/icon';
import { Menu } from '../../../../components/menus';
import { Dialog } from '../../../../components/surfaces';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './menus.css';

const LONG_ACTIONS = [
	'About this post',
	'Add to list',
	'Bookmark',
	'Copy link',
	'Download media',
	'Follow conversation',
	'Hide author',
	'Mute thread',
	'Open in new tab',
	'Pin to profile',
	'Report post',
	'Share externally',
	'Translate',
	'View activity',
];

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

function MenuSample( { children, label } ) {
	return (
		<Menu
			label={ label }
			trigger={ <IconButton icon={ <Icon name="more_vert" /> } label={ label } variant="standard" /> }
		>
			{ children }
		</Menu>
	);
}

function DialogSubmenuSample() {
	const [ open, setOpen ] = useState( false );

	return (
		<>
			<Button
				aria-expanded={ open }
				aria-haspopup="dialog"
				onClick={ () => setOpen( true ) }
				variant="outlined"
			>
				Open dialog menu
			</Button>
			<Dialog headline="Post settings" onClose={ () => setOpen( false ) } open={ open }>
				<MenuSample label="Dialog actions">
					<Menu.Group>
						<Menu.Submenu label="Move to" leading={ <Icon name="drive_file_move" /> }>
							<Menu.Group>
								<Menu.Item id="dialog-inbox" label="Inbox" />
								<Menu.Item id="dialog-archive" label="Archive" />
							</Menu.Group>
						</Menu.Submenu>
					</Menu.Group>
				</MenuSample>
			</Dialog>
		</>
	);
}

export function StylebookMenusPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--menus">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/stylebook">Stylebook</a>
					<a href="/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/stylebook/components/surfaces">Surfaces</a>
				</nav>

				<section className="ax-stylebook-page__section" id="menus" aria-labelledby="ax-stylebook-menus-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-menus-title">Menus</h1>
					</header>

					<Group kicker="A popover hosts temporary actions; it is not a dialog" title="Standard vertical menu">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="Post actions">
								<Menu.Group>
									<Menu.Item id="edit" label="Edit" leading={ <Icon name="edit" /> } />
									<Menu.Item id="duplicate" label="Duplicate" leading={ <Icon name="content_copy" /> } />
								</Menu.Group>
								<Menu.Separator />
								<Menu.Group>
									<Menu.Item id="archive" label="Archive" leading={ <Icon name="archive" /> } />
									<Menu.Item disabled id="delete" label="Delete" leading={ <Icon name="delete" /> } />
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							Open from the overflow icon. The disabled item remains focusable but cannot be selected;
							the separator is not a focus stop.
						</p>
					</Group>

					<Group kicker="Flip before clamping so the trigger keeps an aligned menu edge" title="Trailing-edge placement">
						<div className="ax-stylebook-menus__trigger-row ax-stylebook-menus__trigger-row--end">
							<MenuSample label="Trailing-edge actions">
								<Menu.Group>
									<Menu.Item id="edge-edit" label="Edit" leading={ <Icon name="edit" /> } />
									<Menu.Item id="edge-copy" label="Copy link" leading={ <Icon name="content_copy" /> } />
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							At the viewport&rsquo;s inline end, the menu shifts its trailing edge to the trigger instead
							of freezing against the window edge.
						</p>
					</Group>

					<Group kicker="Selection is scoped to a group, not to the whole menu" title="Selected and trailing content">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="Sort feed">
								<Menu.Group selectionMode="single">
									<Menu.Item id="recent" label="Most recent" selected trailing={ <Icon name="check" /> } />
									<Menu.Item id="popular" label="Most discussed" trailing="24" />
									<Menu.Item id="saved" label="Saved by me" trailing="8" />
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							The selected row gets the tertiary container and a check. Trailing values share the
							menu item&rsquo;s one action rather than becoming controls of their own.
						</p>
					</Group>

					<Group kicker="The slot remains 48dp while the painted state layer is 44dp" title="Supporting text">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="Post audience">
								<Menu.Group label="Label">
									<Menu.Item
										id="supporting"
										label="Label"
										leading={ <Icon name="star" /> }
										supporting="Supporting text"
										trailing={ <Icon name="info" /> }
									/>
									<Menu.Item
										id="plain"
										label="Label"
										leading={ <Icon name="star" /> }
										trailing={ <Icon name="info" /> }
									/>
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							Supporting text is an optional menu-item slot. Its measured text block expands, while
							the single-line 48/44 geometry remains the baseline contract. The trailing icon is
							here because the 208dp width only resolves to a 120dp text block with both icons
							present; only Menu.Submenu owns the submenu affordance.
						</p>
					</Group>

					<Group kicker="The viewport space left by the trigger bounds the list" title="Scrollable menu">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="More post actions">
								<Menu.Group>
									{ LONG_ACTIONS.map( ( label ) => 'Follow conversation' === label
										? (
											<Menu.Submenu key={ label } label={ label }>
												<Menu.Group>
													<Menu.Item id="following" label="Following" />
													<Menu.Item id="muted" label="Muted" />
												</Menu.Group>
											</Menu.Submenu>
										)
										: <Menu.Item id={ label } key={ label } label={ label } />
									) }
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							The list scrolls only when its trigger leaves less space than its rows need; no item-count
							cap is imposed. Follow conversation is a nested menu inside that same scrollable list.
						</p>
					</Group>

					<Group kicker="The nested row owns arrow_right; ordinary rows cannot imply a submenu" title="Submenu">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="Post options">
								<Menu.Group>
									<Menu.Item id="copy" label="Copy link" leading={ <Icon name="content_copy" /> } />
									<Menu.Submenu label="Move to" leading={ <Icon name="drive_file_move" /> }>
										<Menu.Group>
											<Menu.Item id="inbox" label="Inbox" />
											<Menu.Item id="archive" label="Archive" />
										</Menu.Group>
									</Menu.Submenu>
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							Open with the row or Right Arrow; Left Arrow returns focus to the parent row. This sample is also
							the runtime measurement for nested placement and top-layer dismissal.
						</p>
					</Group>

					<Group kicker="A child popover must join the top layer after the dialog that contains its trigger" title="Dialog host">
						<div className="ax-stylebook-menus__trigger-row">
							<DialogSubmenuSample />
						</div>
						<p className="ax-stylebook-page__note">
							Open the dialog, then Move to. The child menu must remain above the dialog and return focus to its
							parent row with Left Arrow.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
