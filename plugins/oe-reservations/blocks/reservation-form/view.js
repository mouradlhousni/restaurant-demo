/**
 * Reservation Form – front end.
 *
 * Steps: date → guests → time → details → confirmation. Times come live from
 * GET oe/v1/availability; the booking is sent to POST oe/v1/reservations.
 *
 * Accessibility: each step is a fieldset whose legend receives focus when shown, the
 * progress list exposes aria-current="step", choices are native radio buttons, and
 * errors are linked to their fields (aria-describedby + aria-invalid) and announced.
 */
import { addDays, periodsFor, interpolate } from '../shared/schedule';

const STEPS = [ 'date', 'guests', 'time', 'details', 'confirm' ];

function init( root ) {
	let config;
	try {
		config = JSON.parse( root.dataset.oeBooking );
	} catch ( error ) {
		return;
	}

	const { i18n } = config;
	const form = root.querySelector( '.oe-booking__form' );
	const alertBox = root.querySelector( '.oe-booking__alert' );
	const fieldsets = STEPS.map( ( name ) =>
		form.querySelector( `[data-step="${ name }"]` )
	);
	const progress = [ ...root.querySelectorAll( '.oe-progress__step' ) ];
	const nextButton = form.querySelector( '[data-action="next"]' );
	const backButton = form.querySelector( '[data-action="back"]' );
	const datesBox = form.querySelector( '.oe-dates' );
	const otherDate = form.querySelector( '#oe-date-other' );
	const slotsBox = form.querySelector( '.oe-slots' );
	const timeContext = form.querySelector( '.oe-step__context' );
	const done = root.querySelector( '.oe-booking__done' );
	const nextLabel = nextButton.textContent;
	const cache = new Map();

	const state = { step: 0, date: '', time: '', timeLabel: '', busy: false };

	const formatDate = ( date, options ) =>
		new Intl.DateTimeFormat( config.locale, {
			timeZone: 'UTC',
			...options,
		} ).format( new Date( date + 'T12:00:00Z' ) );
	const longDate = ( date ) =>
		formatDate( date, { weekday: 'long', day: 'numeric', month: 'long' } );
	const guests = () =>
		Number(
			form.querySelector( 'input[name="guests"]:checked' )?.value || 0
		);
	const guestsLabel = ( n ) =>
		1 === n ? i18n.guest : interpolate( i18n.guests, n );

	/* ------------------------------------------------------------------ *
	 * Messages
	 * ------------------------------------------------------------------ */

	function showAlert( message ) {
		alertBox.textContent = message;
		alertBox.hidden = ! message;
	}

	function fieldError( name, message ) {
		const error = form.querySelector( `#oe-${ name }-error` );
		const input =
			form.querySelector( `[name="${ name }"]` ) ||
			( 'date' === name ? otherDate : null );
		if ( error ) {
			error.textContent = message || '';
			error.hidden = ! message;
		}
		if ( input && 'radio' !== input.type ) {
			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Step 1: dates (next 14 days, closed days disabled)
	 * ------------------------------------------------------------------ */

	function renderDates() {
		const items = [];
		for ( let i = 0; i < 14; i++ ) {
			const date = addDays( config.minDate, i );
			if ( date > config.maxDate ) {
				break;
			}
			const closed = ! periodsFor( config.schedule, date ).length;
			let top = formatDate( date, { weekday: 'short' } );
			if ( 0 === i ) {
				top = i18n.today;
			} else if ( 1 === i ) {
				top = i18n.tomorrow;
			}
			items.push( `
				<label class="oe-date${ closed ? ' is-closed' : '' }">
					<input type="radio" name="date" value="${ date }"${ closed ? ' disabled' : '' }>
					<span class="oe-date__top">${ top }</span>
					<span class="oe-date__day">${ formatDate( date, { day: 'numeric' } ) }</span>
					<span class="oe-date__month">${
						closed
							? i18n.closed
							: formatDate( date, { month: 'short' } )
					}</span>
				</label>` );
		}
		datesBox.innerHTML = items.join( '' );
	}

	datesBox.addEventListener( 'change', ( event ) => {
		state.date = event.target.value;
		otherDate.value = '';
		fieldError( 'date', '' );
	} );

	otherDate.addEventListener( 'change', () => {
		datesBox.querySelectorAll( 'input' ).forEach( ( input ) => {
			input.checked = input.value === otherDate.value;
		} );
		state.date = otherDate.value;
		fieldError( 'date', '' );
	} );

	function validateDate() {
		if ( ! state.date ) {
			fieldError( 'date', i18n.pickDate );
			return false;
		}
		if (
			state.date < config.minDate ||
			state.date > config.maxDate ||
			! periodsFor( config.schedule, state.date ).length
		) {
			fieldError(
				'date',
				`${ longDate( state.date ) }: ${ i18n.closed }.`
			);
			return false;
		}
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Step 3: live availability
	 * ------------------------------------------------------------------ */

	async function loadSlots( force = false ) {
		const key = `${ state.date }|${ guests() }`;
		timeContext.textContent = `${ longDate( state.date ) } · ${ guestsLabel(
			guests()
		) }`;
		fieldError( 'time', '' );

		if ( force ) {
			cache.delete( key );
		}

		slotsBox.setAttribute( 'aria-busy', 'true' );
		slotsBox.innerHTML = `<p class="oe-loading">${ i18n.loading }</p>`;

		try {
			if ( ! cache.has( key ) ) {
				const url = new URL(
					config.api + 'availability',
					window.location.href
				);
				url.searchParams.set( 'date', state.date );
				url.searchParams.set( 'guests', String( guests() ) );
				const response = await fetch( url, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				} );
				const body = await response.json();
				if ( ! response.ok ) {
					throw new Error( body.message || i18n.network );
				}
				cache.set( key, body );
			}
			renderSlots( cache.get( key ) );
		} catch ( error ) {
			slotsBox.innerHTML = '';
			fieldError(
				'time',
				error.message && error.message !== 'Failed to fetch'
					? error.message
					: i18n.network
			);
		} finally {
			slotsBox.setAttribute( 'aria-busy', 'false' );
		}
	}

	function renderSlots( day ) {
		if ( ! day.available ) {
			slotsBox.innerHTML = '';
			fieldError( 'time', day.message || i18n.noSlots );
			return;
		}

		const groups = { lunch: [], dinner: [] };
		day.slots.forEach( ( slot ) => groups[ slot.period ].push( slot ) );

		slotsBox.innerHTML = Object.entries( groups )
			.filter( ( [ , slots ] ) => slots.length )
			.map(
				( [ period, slots ] ) => `
				<div class="oe-slot-group" role="radiogroup" aria-labelledby="oe-slots-${ period }">
					<p class="oe-slot-group__title" id="oe-slots-${ period }">${
						i18n[ period ]
					}</p>
					<div class="oe-choices oe-choices--slots">
						${ slots
							.map( ( slot ) => {
								let note = '';
								if ( ! slot.available ) {
									note = `<small>${
										'too_soon' === slot.reason
											? i18n.tooSoon
											: i18n.full
									}</small>`;
								}
								return `<label class="oe-choice oe-choice--slot${
									slot.available ? '' : ' is-unavailable'
								}">
									<input type="radio" name="time" value="${ slot.time }" data-label="${
										slot.label
									}"${ slot.available ? '' : ' disabled' }${
										slot.time === state.time
											? ' checked'
											: ''
									}>
									<span>${ slot.label }${ note }</span>
								</label>`;
							} )
							.join( '' ) }
					</div>
				</div>`
			)
			.join( '' );
	}

	slotsBox.addEventListener( 'change', ( event ) => {
		state.time = event.target.value;
		state.timeLabel = event.target.dataset.label;
		fieldError( 'time', '' );
	} );

	/* ------------------------------------------------------------------ *
	 * Step 4: details
	 * ------------------------------------------------------------------ */

	function validateDetails() {
		const value = ( name ) =>
			form.querySelector( `[name="${ name }"]` ).value.trim();
		const errors = {};

		if ( value( 'name' ).length < 2 ) {
			errors.name = i18n.nameShort;
		}
		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value( 'email' ) ) ) {
			errors.email = i18n.emailInvalid;
		}
		if (
			! /^\+?[0-9 ().-]{6,25}$/.test( value( 'phone' ) ) ||
			( value( 'phone' ).match( /\d/g ) || [] ).length < 6
		) {
			errors.phone = i18n.phoneInvalid;
		}

		[ 'name', 'email', 'phone', 'notes' ].forEach( ( name ) =>
			fieldError( name, errors[ name ] )
		);
		return showFieldErrors( errors );
	}

	function showFieldErrors( errors ) {
		const names = Object.keys( errors );
		names.forEach( ( name ) => fieldError( name, errors[ name ] ) );
		if ( ! names.length ) {
			return true;
		}
		showAlert( interpolate( i18n.errorCount, names.length ) );
		const first = form.querySelector( `[name="${ names[ 0 ] }"]` );
		if ( first ) {
			first.focus();
		}
		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Step 5: summary and submission
	 * ------------------------------------------------------------------ */

	function summaryRows( rows ) {
		return rows
			.map(
				( [ label, value ] ) =>
					`<div><dt>${ label }</dt><dd>${ escapeHtml(
						value
					) }</dd></div>`
			)
			.join( '' );
	}

	function renderSummary() {
		const value = ( name ) =>
			form.querySelector( `[name="${ name }"]` ).value.trim();
		const labels = [
			...form.querySelectorAll( '[data-step="details"] label' ),
		].map( ( label ) => label.firstChild.textContent.trim() );
		const rows = [
			[
				progress[ 0 ].querySelector( '.oe-progress__label' )
					.textContent,
				longDate( state.date ),
			],
			[
				progress[ 2 ].querySelector( '.oe-progress__label' )
					.textContent,
				state.timeLabel,
			],
			[
				progress[ 1 ].querySelector( '.oe-progress__label' )
					.textContent,
				guestsLabel( guests() ),
			],
			[ labels[ 0 ], value( 'name' ) ],
			[ labels[ 1 ], value( 'email' ) ],
			[ labels[ 2 ], value( 'phone' ) ],
		];
		if ( value( 'notes' ) ) {
			rows.push( [ labels[ 3 ], value( 'notes' ) ] );
		}
		fieldsets[ 4 ].querySelector( '.oe-summary' ).innerHTML =
			summaryRows( rows );
	}

	async function submit() {
		const value = ( name ) =>
			form.querySelector( `[name="${ name }"]` ).value.trim();
		state.busy = true;
		nextButton.disabled = true;
		nextButton.textContent = i18n.sending;

		try {
			const response = await fetch( config.api + 'reservations', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( {
					date: state.date,
					time: state.time,
					guests: guests(),
					name: value( 'name' ),
					email: value( 'email' ),
					phone: value( 'phone' ),
					notes: value( 'notes' ),
					website: value( 'website' ),
				} ),
			} );
			const body = await response.json();

			if ( response.ok ) {
				finish( body );
				return;
			}

			if ( 409 === response.status ) {
				// The slot was taken in the meantime: back to the times, freshly loaded.
				state.time = '';
				await go( 2, true );
				fieldError( 'time', body.message );
				showAlert( body.message );
				return;
			}

			if ( body.data && body.data.fields ) {
				const fields = body.data.fields;
				if ( fields.date || fields.time || fields.guests ) {
					await go( fields.date ? 0 : 2 );
					showAlert( fields.date || fields.time || fields.guests );
					return;
				}
				await go( 3 );
				showFieldErrors( fields );
				return;
			}

			showAlert( body.message || i18n.network );
		} catch ( error ) {
			showAlert( i18n.network );
		} finally {
			state.busy = false;
			nextButton.disabled = false;
			nextButton.textContent =
				STEPS.length - 1 === state.step
					? nextButton.dataset.confirmLabel
					: nextLabel;
		}
	}

	function finish( body ) {
		const reservation = body.reservation || {};
		form.hidden = true;
		root.querySelector( '.oe-progress' ).hidden = true;
		done.hidden = false;
		done.classList.toggle( 'is-demo', !! body.demo );
		let title = i18n.doneTitle;
		if ( body.demo ) {
			title = i18n.doneDemo;
		} else if ( 'pending' === reservation.status ) {
			title = i18n.donePending;
		}
		done.querySelector( '.oe-booking__done-title' ).textContent = title;
		done.querySelector( '.oe-booking__done-message' ).textContent =
			body.message || '';
		done.querySelector( '.oe-summary' ).innerHTML = reservation.datetime
			? summaryRows( [
					[ i18n.reference, reservation.reference ],
					[
						progress[ 0 ].querySelector( '.oe-progress__label' )
							.textContent,
						reservation.datetime,
					],
					[
						progress[ 1 ].querySelector( '.oe-progress__label' )
							.textContent,
						guestsLabel( reservation.guests ),
					],
					[
						fieldsets[ 3 ]
							.querySelector( 'label' )
							.firstChild.textContent.trim(),
						reservation.name,
					],
			  ] )
			: '';
		done.focus();
	}

	/* ------------------------------------------------------------------ *
	 * Navigation
	 * ------------------------------------------------------------------ */

	async function go( step, reload = false ) {
		state.step = step;
		showAlert( '' );

		fieldsets.forEach(
			( fieldset, index ) => ( fieldset.hidden = index !== step )
		);
		progress.forEach( ( item, index ) => {
			item.classList.toggle( 'is-done', index < step );
			if ( index === step ) {
				item.setAttribute( 'aria-current', 'step' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );

		backButton.hidden = 0 === step;
		nextButton.textContent =
			STEPS.length - 1 === step
				? nextButton.dataset.confirmLabel
				: nextLabel;

		const legend = fieldsets[ step ].querySelector( 'legend' );
		legend.focus();

		if ( 2 === step ) {
			await loadSlots( reload );
		}
		if ( 4 === step ) {
			renderSummary();
		}
	}

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( state.busy ) {
			return;
		}

		const step = STEPS[ state.step ];
		if ( 'date' === step && ! validateDate() ) {
			otherDate.focus();
			return;
		}
		if ( 'guests' === step && ! guests() ) {
			showAlert( i18n.pickGuests );
			return;
		}
		if ( 'time' === step && ! state.time ) {
			fieldError( 'time', i18n.pickTime );
			slotsBox.querySelector( 'input:not([disabled])' )?.focus();
			return;
		}
		if ( 'details' === step && ! validateDetails() ) {
			return;
		}
		if ( 'confirm' === step ) {
			await submit();
			return;
		}

		await go( state.step + 1 );
	} );

	backButton.addEventListener( 'click', () =>
		go( Math.max( 0, state.step - 1 ) )
	);

	// A different party size invalidates the chosen time.
	form.addEventListener( 'change', ( event ) => {
		if (
			'guests' === event.target.name ||
			'date' === event.target.name ||
			'date_other' === event.target.name
		) {
			state.time = '';
		}
	} );

	done.querySelector( '[data-action="restart"]' ).addEventListener(
		'click',
		() => {
			form.reset();
			cache.clear();
			Object.assign( state, { date: '', time: '', timeLabel: '' } );
			done.hidden = true;
			form.hidden = false;
			root.querySelector( '.oe-progress' ).hidden = false;
			go( 0 );
		}
	);

	// Tiles are server-rendered; build them only if the markup was cached without them.
	if ( ! datesBox.children.length ) {
		renderDates();
	}
	form.hidden = false;
}

function escapeHtml( value ) {
	const div = document.createElement( 'div' );
	div.textContent = String( value );
	return div.innerHTML;
}

// The static export renders a preview instead of the form: never call the API there.
if ( ! document.body.dataset.staticBuild ) {
	document.querySelectorAll( '[data-oe-booking]' ).forEach( init );
}
