import { AppBar } from '../../../../components/app-bars/app-bar';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Fab } from '../../../../components/fabs';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './tooltips.css';

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

export function StylebookTooltipsPage() {
	return (
		<Scaffold className="ax-stylebook ax-stylebook--tooltips">
			<div className="ax-stylebook-page" aria-labelledby="ax-stylebook-tooltips-title">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/fabs">FABs</a>
				</nav>

				<section className="ax-stylebook-page__section" aria-labelledby="ax-stylebook-tooltips-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-tooltips-title">Tooltips</h1>
					</header>

					<Group kicker="One at a time, above a visible control" title="Plain tooltip">
						<div className="ax-stylebook-tooltips__row">
							<IconButton icon={ <Icon name="edit" /> } label="Edit post" />
							<IconButton icon={ <Icon name="filter_list" /> } label="Filters" tooltip="Filter the feed" variant="tonal" />
							<Fab icon={ <Icon name="edit" /> } label="Compose post" />
						</div>
					</Group>

					<Group kicker="App-bar triggers place the same tooltip below" title="App bar">
						<AppBar
							actions={ <IconButton icon={ <Icon name="more_vert" /> } label="More options" variant="standard" /> }
							title="Inbox"
						/>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
