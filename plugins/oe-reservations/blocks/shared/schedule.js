/**
 * Opening schedule helpers shared by the front-end scripts.
 *
 * Mirrors AvailabilityService::periods_for() / status_at() in PHP, so the "Open now" badge
 * and closed days can be computed in the browser, including on the static export.
 * All dates are in the restaurant timezone, never the visitor's.
 */

export const DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

/**
 * Wall-clock parts of "now" in a timezone ("Europe/Paris" or an offset like "+02:00").
 *
 * @param {string} timezone Timezone.
 * @param {Date}   [date]   Moment (default now).
 * @return {{date: string, minutes: number}} Local date (Y-m-d) and minutes since midnight.
 */
export function localNow( timezone, date = new Date() ) {
	const offset = /^([+-])(\d{2}):(\d{2})$/.exec( timezone || '' );
	if ( offset ) {
		const sign = '-' === offset[ 1 ] ? -1 : 1;
		const shifted = new Date(
			date.getTime() +
				sign *
					( Number( offset[ 2 ] ) * 60 + Number( offset[ 3 ] ) ) *
					60000
		);
		return {
			date: shifted.toISOString().slice( 0, 10 ),
			minutes: shifted.getUTCHours() * 60 + shifted.getUTCMinutes(),
		};
	}

	const parts = {};
	new Intl.DateTimeFormat( 'en-GB', {
		timeZone: timezone || 'UTC',
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		hourCycle: 'h23',
	} )
		.formatToParts( date )
		.forEach( ( part ) => ( parts[ part.type ] = part.value ) );

	return {
		date: `${ parts.year }-${ parts.month }-${ parts.day }`,
		minutes: Number( parts.hour ) * 60 + Number( parts.minute ),
	};
}

/**
 * Adds days to a Y-m-d date.
 *
 * @param {string} date Date.
 * @param {number} days Days to add.
 * @return {string} Y-m-d.
 */
export function addDays( date, days ) {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m - 1, d + days ) )
		.toISOString()
		.slice( 0, 10 );
}

/**
 * Weekday key of a Y-m-d date.
 *
 * @param {string} date Date.
 * @return {string} mon…sun.
 */
export function dayKey( date ) {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return DAYS[ ( new Date( Date.UTC( y, m - 1, d ) ).getUTCDay() + 6 ) % 7 ];
}

/**
 * Opening periods of a date ([open, close] minutes), empty when closed.
 *
 * @param {Object} schedule {hours, closures}.
 * @param {string} date     Y-m-d.
 * @return {Array<Array<number>>} Periods.
 */
export function periodsFor( schedule, date ) {
	if ( ( schedule.closures || [] ).includes( date ) ) {
		return [];
	}
	return ( schedule.hours && schedule.hours[ dayKey( date ) ] ) || [];
}

/**
 * Open / closed now, and when that changes.
 *
 * @param {Object} schedule {timezone, hours, closures}.
 * @param {Date}   [date]   Moment.
 * @return {{open: boolean, change: ?{date: string, minutes: number}}} Status.
 */
export function statusAt( schedule, date = new Date() ) {
	const now = localNow( schedule.timezone, date );

	for ( let offset = 0; offset <= 14; offset++ ) {
		const day = addDays( now.date, offset );
		const from = 0 === offset ? now.minutes : -1;

		for ( const [ open, close ] of periodsFor( schedule, day ) ) {
			if ( 0 === offset && now.minutes >= open && now.minutes < close ) {
				return { open: true, change: { date: day, minutes: close } };
			}
			if ( open > from ) {
				return { open: false, change: { date: day, minutes: open } };
			}
		}
	}

	return { open: false, change: null };
}

/**
 * Minutes to "HH:MM".
 *
 * @param {number} minutes Minutes since midnight.
 * @return {string} Time.
 */
export function formatMinutes( minutes ) {
	const h = Math.floor( minutes / 60 ) % 24;
	return `${ String( h ).padStart( 2, '0' ) }:${ String(
		minutes % 60
	).padStart( 2, '0' ) }`;
}

/**
 * Replaces %s / %d / %1$s placeholders.
 *
 * @param {string} format Format.
 * @param {...*}   args   Values.
 * @return {string} Text.
 */
export function interpolate( format, ...args ) {
	let index = 0;
	return format.replace( /%(?:(\d)\$)?[sd]/g, ( match, position ) =>
		String( args[ position ? Number( position ) - 1 : index++ ] )
	);
}
