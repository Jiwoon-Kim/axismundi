import { IconButton } from '../../../../components/buttons/icon-button';
import { Icon } from '../../../../components/material/icon';
import { Menu } from '../../../../components/menus';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './menus.css';

const ACTIONS = [
	{ id: 'edit', label: 'Edit', leading: <Icon name="edit" /> },
	{ id: 'duplicate', label: 'Duplicate', leading: <Icon name="content_copy" /> },
	{ divider: true },
	{ id: 'archive', label: 'Archive', leading: <Icon name="archive" /> },
	{ id: 'delete', label: 'Delete', leading: <Icon name="delete" />, disabled: true },
];

const SELECTABLE_ACTIONS = [
	{ id: 'recent', label: 'Most recent', selected: true, trailing: <Icon name="check" /> },
	{ id: 'popular', label: 'Most discussed', trailing: '24' },
	{ id: 'saved', label: 'Saved by me', trailing: '8' },
];

const SUPPORTING_ACTIONS = [
	{ id: 'public', label: 'Public', supporting: 'Anyone can view this post', leading: <Icon name="public" /> },
	{ id: 'followers', label: 'Followers', supporting: 'Only approved followers can view this post', leading: <Icon name="group" /> },
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

function MenuSample( { items, label } ) {
	return (
		<Menu
			items={ items }
			label={ label }
			trigger={ <IconButton icon={ <Icon name="more_vert" /> } label={ label } variant="standard" /> }
		/>
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
							<MenuSample items={ ACTIONS } label="Post actions" />
						</div>
						<p className="ax-stylebook-page__note">
							Open from the overflow icon. The disabled item remains focusable but cannot be selected;
							the divider is outside the menu&rsquo;s focus order.
						</p>
					</Group>

					<Group kicker="Selection is a visual state, not a second action" title="Selected and trailing content">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample items={ SELECTABLE_ACTIONS } label="Sort feed" />
						</div>
						<p className="ax-stylebook-page__note">
							The selected row gets the tertiary container and a check. Trailing values share the
							menu item&rsquo;s one action rather than becoming controls of their own.
						</p>
					</Group>

					<Group kicker="The slot remains 48dp while the painted state layer is 44dp" title="Supporting text">
						<div className="ax-stylebook-menus__trigger-row">
							<MenuSample items={ SUPPORTING_ACTIONS } label="Post audience" />
						</div>
						<p className="ax-stylebook-page__note">
							Supporting text is an optional menu-item slot. Its measured text block expands, while
							the single-line 48/44 geometry remains the baseline contract.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
