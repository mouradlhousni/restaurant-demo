/**
 * Opening Hours – front end.
 *
 * Keeps the "Open now / Closed" badge, today's row and the timeline ember up to date
 * from the embedded schedule. No network request: works on the static export too.
 */
import {
	DAYS,
	localNow,
	statusAt,
	formatMinutes,
	interpolate,
} from '../shared/schedule';

function update( block, config ) {
	const { schedule, i18n } = config;
	const now = localNow( schedule.timezone );
	const status = statusAt( schedule );

	const badge = block.querySelector( '.oe-open-badge' );
	if ( badge ) {
		let detail = '';
		if ( status.change ) {
			const time = formatMinutes( status.change.minutes );
			if ( status.open ) {
				detail = interpolate( i18n.until, time );
			} else if ( status.change.date === now.date ) {
				detail = interpolate( i18n.opensAt, time );
			} else {
				const key =
					DAYS[
						( new Date(
							status.change.date + 'T12:00:00Z'
						).getUTCDay() +
							6 ) %
							7
					];
				const tomorrow = new Date(
					Date.parse( now.date + 'T12:00:00Z' ) + 86400000
				)
					.toISOString()
					.slice( 0, 10 );
				detail = interpolate(
					i18n.opensDay,
					status.change.date === tomorrow
						? i18n.tomorrow
						: config.days[ key ],
					time
				);
			}
		}
		badge.dataset.state = status.open ? 'open' : 'closed';
		badge.querySelector( '.oe-open-badge__state' ).textContent = status.open
			? i18n.open
			: i18n.closed;
		badge.querySelector( '.oe-open-badge__detail' ).textContent = detail
			? `· ${ detail }`
			: '';
	}

	const todayKey =
		DAYS[ ( new Date( now.date + 'T12:00:00Z' ).getUTCDay() + 6 ) % 7 ];
	block.querySelectorAll( 'tr[data-day]' ).forEach( ( row ) => {
		row.classList.toggle( 'is-today', row.dataset.day === todayKey );
	} );

	const ember = block.querySelector( '.oe-strip__ember' );
	if ( ember ) {
		const position =
			( now.minutes - config.from ) / ( config.to - config.from );
		ember.style.left = `${ Math.min(
			100,
			Math.max( 0, position * 100 )
		) }%`;
		ember.dataset.state = status.open ? 'open' : 'closed';
	}
}

document.querySelectorAll( '[data-oe-hours]' ).forEach( ( block ) => {
	let config;
	try {
		config = JSON.parse( block.dataset.oeHours );
	} catch ( error ) {
		return;
	}
	update( block, config );
	window.setInterval( () => update( block, config ), 60000 );
} );
