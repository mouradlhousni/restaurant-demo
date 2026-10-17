/**
 * Menu Section – front-end filters.
 *
 * One filter bar drives every Menu Section block on the page. Dishes carry their
 * allergens and diets in data attributes; filtering only toggles `hidden`, so it works
 * on the static export as well. The state is mirrored in the URL (?diet=vegan&allergen=nuts)
 * so a filtered menu can be shared.
 */
const form = document.querySelector( '[data-oe-menu-filters]' );

if ( form ) {
	const dishes = [ ...document.querySelectorAll( '.oe-menu .oe-dish' ) ];
	const sections = [ ...document.querySelectorAll( '.oe-menu' ) ];
	const count = form.querySelector( '.oe-menu-filters__count' );
	const total = dishes.length;
	const format = form.dataset.countFormat || '%1$d / %2$d';

	const selected = ( name ) =>
		[ ...form.querySelectorAll( `input[name="${ name }"]:checked` ) ].map(
			( input ) => input.value
		);
	const tokens = ( element, key ) =>
		( element.dataset[ key ] || '' ).split( ' ' ).filter( Boolean );

	const apply = ( announce = true ) => {
		const diets = selected( 'diet' );
		const allergens = selected( 'allergen' );
		let visible = 0;

		dishes.forEach( ( dish ) => {
			const dishDiets = tokens( dish, 'diets' );
			const dishAllergens = tokens( dish, 'allergens' );
			const show =
				diets.every( ( diet ) => dishDiets.includes( diet ) ) &&
				! allergens.some( ( allergen ) =>
					dishAllergens.includes( allergen )
				);
			dish.hidden = ! show;
			visible += show ? 1 : 0;
		} );

		sections.forEach( ( section ) => {
			const empty = section.querySelector( '.oe-menu__empty' );
			if ( empty ) {
				empty.hidden = !! section.querySelector(
					'.oe-dish:not([hidden])'
				);
			}
		} );

		const filtering = diets.length + allergens.length > 0;
		form.classList.toggle( 'is-filtering', filtering );
		if ( announce ) {
			count.textContent = filtering
				? format.replace( '%1$d', visible ).replace( '%2$d', total )
				: '';
		}

		const url = new URL( window.location.href );
		url.searchParams.delete( 'diet' );
		url.searchParams.delete( 'allergen' );
		diets.forEach( ( diet ) => url.searchParams.append( 'diet', diet ) );
		allergens.forEach( ( allergen ) =>
			url.searchParams.append( 'allergen', allergen )
		);
		window.history.replaceState( null, '', url );
	};

	// Restore filters from the URL.
	const params = new URLSearchParams( window.location.search );
	[ 'diet', 'allergen' ].forEach( ( name ) => {
		params.getAll( name ).forEach( ( value ) => {
			const input = form.querySelector(
				`input[name="${ name }"][value="${ window.CSS.escape(
					value
				) }"]`
			);
			if ( input ) {
				input.checked = true;
			}
		} );
	} );

	form.addEventListener( 'change', () => apply() );
	form.addEventListener( 'reset', () =>
		window.setTimeout( () => apply(), 0 )
	);
	form.addEventListener( 'submit', ( event ) => event.preventDefault() );
	apply( params.has( 'diet' ) || params.has( 'allergen' ) );
}
