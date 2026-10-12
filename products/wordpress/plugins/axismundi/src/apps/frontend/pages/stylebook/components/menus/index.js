import { IconButton } from '../../../../components/buttons/icon-button';
import { Icon } from '../../../../components/material/icon';
import { Menu } from '../../../../components/menus';
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

export function StylebookMenusPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--menus">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/surfaces">Surfaces</a>
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
							present; it is not a submenu affordance, which this slice does not implement.
						</p>
					</Group>

					<Group kicker="The viewport space left by the trigger bounds the list" title="Scrollable menu">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample label="More post actions">
								<Menu.Group>
									{ LONG_ACTIONS.map( ( label ) => <Menu.Item id={ label } key={ label } label={ label } /> ) }
								</Menu.Group>
							</MenuSample>
						</div>
						<p className="ax-stylebook-page__note">
							The list scrolls only when its trigger leaves less space than its rows need; no item-count
							cap is imposed.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
