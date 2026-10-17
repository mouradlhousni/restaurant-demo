/**
 * Restaurant Map – front end.
 *
 * Leaflet (self-hosted, ~150 KB) is only fetched when the map approaches the viewport.
 */

/**
 * Loads Leaflet once.
 *
 * @param {{js: string, css: string}} assets Leaflet file URLs.
 * @return {Promise<Object>} The Leaflet namespace.
 */
function loadLeaflet( assets ) {
	if ( window.L ) {
		return Promise.resolve( window.L );
	}

	const css = document.createElement( 'link' );
	css.rel = 'stylesheet';
	css.href = assets.css;
	document.head.appendChild( css );

	return new Promise( ( resolve, reject ) => {
		const script = document.createElement( 'script' );
		script.src = assets.js;
		script.async = true;
		script.onload = () => resolve( window.L );
		script.onerror = reject;
		document.head.appendChild( script );
	} );
}

async function mount( canvas ) {
	const config = JSON.parse( canvas.dataset.oeMap );

	try {
		const L = await loadLeaflet( config.leaflet );
		L.Icon.Default.imagePath = config.leaflet.images;

		canvas.innerHTML = '';
		const map = L.map( canvas, { scrollWheelZoom: false } ).setView(
			[ config.lat, config.lng ],
			config.zoom
		);
		L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		} ).addTo( map );

		const marker = L.marker( [ config.lat, config.lng ], {
			title: config.title,
			alt: config.title,
		} ).addTo( map );
		const popup = document.createElement( 'div' );
		const name = document.createElement( 'strong' );
		name.textContent = config.title;
		popup.append( name, document.createElement( 'br' ), config.address );
		marker.bindPopup( popup );
	} catch ( error ) {
		canvas.classList.add( 'is-unavailable' );
	}
}

const maps = document.querySelectorAll( '[data-oe-map]' );

if ( 'IntersectionObserver' in window ) {
	const observer = new window.IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					observer.unobserve( entry.target );
					mount( entry.target );
				}
			} );
		},
		{ rootMargin: '300px' }
	);
	maps.forEach( ( canvas ) => observer.observe( canvas ) );
} else {
	maps.forEach( mount );
}
