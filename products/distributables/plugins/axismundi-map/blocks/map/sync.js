/**
 * Keep a map outside the Query router region in sync with enhanced pagination.
 * The basemap instance stays mounted; only its GeoJSON overlay is replaced.
 */
import {
	getContext,
	getElement,
	getServerState,
	store,
} from '@wordpress/interactivity';

store( 'axismundi/map', {
	callbacks: {
		syncDataset() {
			const { mapKey } = getContext();
			const { ref } = getElement();
			const datasets = getServerState().datasets || {};
			const dataset = datasets[ mapKey ];
			const canvas = ref && ref.querySelector( '.axismundi-map__canvas' );

			if ( ! dataset || ! canvas || ! canvas.axismundiMapController ) {
				return;
			}

			const serialized = JSON.stringify( dataset );
			if ( canvas.dataset.axismundiMapDataset === serialized ) {
				return;
			}

			canvas.axismundiMapController.setGeojson( JSON.parse( serialized ) );
			canvas.dataset.axismundiMapDataset = serialized;
		},
	},
} );
