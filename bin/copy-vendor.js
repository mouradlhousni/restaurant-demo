/**
 * Copies self-hosted fonts (into the theme) and Leaflet (into the plugin) from node_modules.
 * Run with `npm run vendor`. The copied files are committed so nothing needs a build step to run.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const theme = path.join( root, 'themes/olive-ember/assets' );
const plugin = path.join( root, 'plugins/oe-reservations/assets' );

const copies = [
	[ '@fontsource-variable/fraunces/files/fraunces-latin-soft-italic.woff2', theme, 'fonts/fraunces-latin-italic.woff2' ],
	[ '@fontsource-variable/manrope/files/manrope-latin-wght-normal.woff2', theme, 'fonts/manrope-latin.woff2' ],
	[ '@fontsource-variable/outfit/files/outfit-latin-wght-normal.woff2', theme, 'fonts/outfit-latin.woff2' ],
	[ '@fontsource-variable/fraunces/LICENSE', theme, 'fonts/LICENSE-fraunces.txt' ],
	[ '@fontsource-variable/manrope/LICENSE', theme, 'fonts/LICENSE-manrope.txt' ],
	[ '@fontsource-variable/outfit/LICENSE', theme, 'fonts/LICENSE-outfit.txt' ],
	[ 'leaflet/dist/leaflet.js', plugin, 'vendor/leaflet/leaflet.js' ],
	[ 'leaflet/dist/leaflet.css', plugin, 'vendor/leaflet/leaflet.css' ],
	[ 'leaflet/LICENSE', plugin, 'vendor/leaflet/LICENSE' ],
	...[ 'layers.png', 'layers-2x.png', 'marker-icon.png', 'marker-icon-2x.png', 'marker-shadow.png' ].map( ( image ) => [
		`leaflet/dist/images/${ image }`,
		plugin,
		`vendor/leaflet/images/${ image }`,
	] ),
];

for ( const [ from, base, to ] of copies ) {
	const source = path.join( root, 'node_modules', from );
	if ( ! fs.existsSync( source ) ) {
		console.warn( `missing ${ from }` );
		continue;
	}
	const target = path.join( base, to );
	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.copyFileSync( source, target );
	console.log( `copied ${ path.relative( root, target ) }` );
}
