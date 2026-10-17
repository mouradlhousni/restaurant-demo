#!/usr/bin/env node
/**
 * Static export helper (called by bin/export-static.sh). No dependencies, Node 20+.
 *
 * Usage:
 *   node bin/static-export.js crawl    --site=<url> --out=<dir>   Crawl the site into <dir> (wget-free fallback).
 *   node bin/static-export.js finalize --site=<url> --out=<dir> [--public=<url>]
 *                                                                 Fetch missing assets, make URLs relative, strip dynamic
 *                                                                 endpoints, write _headers, validate. --public sets
 *                                                                 absolute canonical URLs for the deployed site.
 *
 * "finalize" also works on a folder produced by wget or by the Simply Static plugin.
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const args = Object.fromEntries(
	process.argv.slice( 3 ).map( ( arg ) => {
		const [ key, ...value ] = arg.replace( /^--/, '' ).split( '=' );
		return [ key, value.join( '=' ) ];
	} )
);
const command = process.argv[ 2 ];
const site = ( args.site || '' ).replace( /\/+$/, '' ) + '/';
const out = path.resolve( args.out || 'dist' );
const publicUrl = args.public ? args.public.replace( /\/+$/, '' ) + '/' : '';
const sitePath = new URL( site ).pathname; // e.g. "/olive-ember-dev/"

// Never export (or link to) the dynamic parts of WordPress.
const FORBIDDEN = /(wp-admin|wp-login\.php|xmlrpc\.php|wp-json|admin-ajax\.php|wp-cron\.php|\/feed\/|\/comments\/|\?p=\d)/;
const TEXT_EXT = /\.(html|css|js|json|svg|txt|xml)$/i;

/* ------------------------------------------------------------------ *
 * Helpers
 * ------------------------------------------------------------------ */

function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

// Every absolute reference to the site, plain or JSON-escaped ("http:\/\/…").
const siteRefPattern = new RegExp(
	'(' + escapeRegExp( site ) + '|' + escapeRegExp( site.replace( /\//g, '\\/' ) ) + ')([^"\'\\s)<>,]*)',
	'g'
);

/**
 * Local file for a site URL ("…/property/x/" → property/x/index.html, queries dropped).
 *
 * @param {string}  url    Absolute URL.
 * @param {boolean} isHtml Whether the resource is an HTML page.
 * @return {string|null} Absolute file path, or null when outside the site.
 */
function fileFor( url, isHtml ) {
	const parsed = new URL( url );
	if ( ! parsed.href.startsWith( site ) ) {
		return null;
	}
	let rel = decodeURIComponent( parsed.pathname.slice( sitePath.length ) );
	if ( rel === '' || rel.endsWith( '/' ) ) {
		rel += 'index.html';
	} else if ( isHtml && ! path.extname( rel ) ) {
		rel += '/index.html';
	}
	return path.join( out, rel );
}

function walk( dir ) {
	if ( ! fs.existsSync( dir ) ) {
		return [];
	}
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		return entry.isDirectory() ? walk( full ) : [ full ];
	} );
}

async function download( url ) {
	const response = await fetch( url, { redirect: 'follow' } );
	const type = response.headers.get( 'content-type' ) || '';
	const body = Buffer.from( await response.arrayBuffer() );
	return { status: response.status, type, body, finalUrl: response.url };
}

function save( file, body ) {
	fs.mkdirSync( path.dirname( file ), { recursive: true } );
	fs.writeFileSync( file, body );
}

function unescapeUrl( raw ) {
	return raw.replace( /\\\//g, '/' ).replace( /&amp;/g, '&' ).replace( /&#038;/g, '&' );
}

/**
 * Site URLs referenced by an HTML/CSS/JS document.
 *
 * @param {string} text    Document text.
 * @param {string} baseUrl URL of the document (for relative CSS url()).
 * @return {string[]} URLs.
 */
function references( text, baseUrl ) {
	const found = new Set();

	// JSON stored in HTML attributes (map markers) is entity-encoded.
	text = text.replace( /&quot;/g, '"' ).replace( /&#0?39;/g, "'" );

	for ( const match of text.matchAll( siteRefPattern ) ) {
		found.add( site + unescapeUrl( match[ 2 ] ) );
	}

	// Relative url(...) in stylesheets (fonts, images).
	for ( const match of text.matchAll( /url\(\s*['"]?([^'")]+)['"]?\s*\)/g ) ) {
		if ( ! /^(data:|https?:|#)/.test( match[ 1 ] ) ) {
			found.add( new URL( match[ 1 ], baseUrl ).href );
		}
	}

	return [ ...found ]
		.map( ( url ) => url.split( '#' )[ 0 ] )
		.filter( ( url ) => url.startsWith( site ) && ! FORBIDDEN.test( url ) );
}

/* ------------------------------------------------------------------ *
 * Crawl
 * ------------------------------------------------------------------ */

async function crawl() {
	fs.mkdirSync( out, { recursive: true } );

	const queue = [ site ];
	const seen = new Set();
	let pages = 0;
	let assets = 0;

	while ( queue.length ) {
		const url = queue.shift();
		const clean = url.split( '?' )[ 0 ];
		const isPageQuery = url.includes( '?' ) && ! path.extname( new URL( clean ).pathname );

		// Pages with a query string (?pg=2, ?city=…) are the same static page: the JavaScript reads the URL.
		if ( seen.has( clean ) || isPageQuery || FORBIDDEN.test( url ) ) {
			continue;
		}
		seen.add( clean );

		const res = await download( clean );
		if ( res.status >= 400 || ! res.finalUrl.startsWith( site ) ) {
			if ( res.status >= 400 ) {
				console.warn( `  skip ${ res.status } ${ clean }` );
			}
			continue;
		}

		const isHtml = res.type.includes( 'text/html' );
		const file = fileFor( clean, isHtml );
		if ( ! file ) {
			continue;
		}
		save( file, res.body );
		isHtml ? pages++ : assets++;

		if ( isHtml || /text\/css|javascript|json/.test( res.type ) ) {
			for ( const ref of references( res.body.toString( 'utf8' ), clean ) ) {
				if ( ! seen.has( ref.split( '?' )[ 0 ] ) ) {
					queue.push( ref );
				}
			}
		}
	}

	// A styled 404 page for static hosts.
	const notFound = await download( site + 'oe-static-404-page/' );
	save( path.join( out, '404.html' ), notFound.body );

	console.log( `  crawled ${ pages } pages and ${ assets } assets` );
}

/* ------------------------------------------------------------------ *
 * Finalize
 * ------------------------------------------------------------------ */

// Fetch assets referenced only from inline JSON (e.g. Leaflet, loaded on scroll by the map block).
async function complete() {
	let added = 0;
	for ( let pass = 0; pass < 3; pass++ ) {
		const missing = new Set();
		for ( const file of walk( out ).filter( ( f ) => TEXT_EXT.test( f ) ) ) {
			const rel = path.relative( out, file ).split( path.sep ).join( '/' );
			const docUrl = site + rel.replace( /index\.html$/, '' );
			for ( const ref of references( fs.readFileSync( file, 'utf8' ), docUrl ) ) {
				const clean = ref.split( '?' )[ 0 ];
				if ( path.extname( new URL( clean ).pathname ) && ! fs.existsSync( fileFor( clean, false ) ) ) {
					missing.add( clean );
				}
			}
		}
		if ( ! missing.size ) {
			break;
		}
		for ( const url of missing ) {
			const res = await download( url );
			if ( res.status < 400 ) {
				save( fileFor( url, false ), res.body );
				added++;
			}
		}
	}
	console.log( `  completed ${ added } assets referenced from scripts` );
}

// wget/Simply Static may keep "?ver=…" in file names: static hosts would never serve them.
function stripQueryFileNames() {
	for ( const file of walk( out ) ) {
		const base = path.basename( file );
		if ( base.includes( '?' ) ) {
			const target = path.join( path.dirname( file ), base.split( '?' )[ 0 ] );
			if ( ! fs.existsSync( target ) ) {
				fs.renameSync( file, target );
			} else {
				fs.unlinkSync( file );
			}
		}
	}
}

function removeDynamicFiles() {
	for ( const file of walk( out ) ) {
		const rel = path.relative( out, file ).split( path.sep ).join( '/' );
		if ( /\.php$/i.test( rel ) || /^(wp-admin|wp-json|feed)\//.test( rel ) || /(^|\/)feed\/index\.html$/.test( rel ) ) {
			fs.unlinkSync( file );
		}
	}
}

function rewrite() {
	for ( const file of walk( out ).filter( ( f ) => /\.(html|css|js)$/i.test( f ) ) ) {
		const rel = path.relative( out, file ).split( path.sep ).join( '/' );
		const depth = rel.split( '/' ).length - 1;
		// 404.html is served for any missing path, at any depth: it needs root-absolute URLs.
		const prefix = rel === '404.html' ? '/' : '../'.repeat( depth );
		let text = fs.readFileSync( file, 'utf8' );

		// Absolute site URLs → relative to this file (plain and JSON-escaped).
		text = text.split( site ).join( prefix || './' );
		text = text.split( site.replace( /\//g, '\\/' ) ).join( ( prefix || './' ).replace( /\//g, '\\/' ) );
		text = text.split( site.replace( /\/$/, '' ) ).join( prefix.replace( /\/$/, '' ) || '.' );

		// Root-relative references ("/olive-ember-dev/…").
		if ( sitePath !== '/' ) {
			text = text.replace( new RegExp( '(["\'(])' + escapeRegExp( sitePath ), 'g' ), '$1' + ( prefix || './' ) );
		}

		if ( rel.endsWith( '.html' ) ) {
			// Canonical URLs must be absolute: use the public URL when known, otherwise drop them.
			text = text.replace( /<link[^>]+rel=["']canonical["'][^>]*>\s*/gi, () => {
				if ( ! publicUrl ) {
					return '';
				}
				const page = rel.replace( /(^|\/)index\.html$/, '$1' ).replace( /^404\.html$/, '' );
				return `<link rel="canonical" href="${ publicUrl + page }">\n`;
			} );

			// Drop discovery links to dynamic endpoints.
			text = text.replace( /<link[^>]+(wp-json|xmlrpc\.php|EditURI|oembed|\/feed\/)[^>]*>\s*/gi, '' );
			// Unwrap any link to the admin or login screens.
			text = text.replace( /<a\b[^>]*href=["'][^"']*(wp-login\.php|wp-admin)[^"']*["'][^>]*>([\s\S]*?)<\/a>/gi, '$2' );
		}

		fs.writeFileSync( file, text );
	}
}

function writeHostFiles() {
	const headers = `# Cloudflare Pages / Netlify headers for the Olive & Ember static showcase.
/*
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()
  Cross-Origin-Opener-Policy: same-origin
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.tile.openstreetmap.org https://tile.openstreetmap.org; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'none'; object-src 'none'
  Cache-Control: public, max-age=0, must-revalidate

/wp-content/*
  Cache-Control: public, max-age=31536000, immutable

/wp-includes/*
  Cache-Control: public, max-age=31536000, immutable
`;
	fs.writeFileSync( path.join( out, '_headers' ), headers );
	fs.writeFileSync( path.join( out, '.nojekyll' ), '' );
	fs.writeFileSync( path.join( out, 'robots.txt' ), 'User-agent: *\nAllow: /\n' );
}

function validate() {
	const problems = [];
	const host = new URL( site ).host;

	for ( const file of walk( out ).filter( ( f ) => /\.(html|js|json|css)$/i.test( f ) && ! f.includes( `${ path.sep }vendor${ path.sep }` ) ) ) {
		const text = fs.readFileSync( file, 'utf8' );
		const rel = path.relative( out, file );
		for ( const needle of [ 'wp-json', 'admin-ajax.php', 'wp-login.php', 'xmlrpc.php', host ] ) {
			if ( text.includes( needle ) ) {
				problems.push( `${ rel }: contains "${ needle }"` );
			}
		}
	}

	if ( problems.length ) {
		console.error( '  validation failed:\n   - ' + problems.slice( 0, 30 ).join( '\n   - ' ) );
		process.exit( 1 );
	}

	const files = walk( out );
	const size = files.reduce( ( sum, f ) => sum + fs.statSync( f ).size, 0 );
	console.log( `  validated ${ files.length } files (${ ( size / 1048576 ).toFixed( 1 ) } MB): no PHP, no REST API, no admin links` );
}

async function finalize() {
	await complete();
	stripQueryFileNames();
	removeDynamicFiles();
	rewrite();
	writeHostFiles();
	validate();
}

( async () => {
	if ( ! args.site ) {
		console.error( 'Missing --site=<url>' );
		process.exit( 1 );
	}
	if ( command === 'crawl' ) {
		await crawl();
	} else if ( command === 'finalize' ) {
		await finalize();
	} else {
		console.error( 'Usage: static-export.js <crawl|finalize> --site=<url> --out=<dir>' );
		process.exit( 1 );
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );
