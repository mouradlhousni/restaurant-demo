/**
 * Olive & Ember – small progressive enhancements.
 *
 * - Header shadow once the page scrolls; header height exposed as --oe-header-height.
 * - Review carousel: arrow buttons over a native scroll-snap track (usable without JS).
 * - Sections fade in as they enter the viewport (skipped with prefers-reduced-motion).
 */
( function () {
	const header = document.querySelector( '.oe-header' );
	const root = document.documentElement;

	if ( header ) {
		const measure = () => {
			root.style.setProperty(
				'--oe-header-height',
				`${ header.offsetHeight }px`
			);
			root.style.setProperty(
				'--oe-sticky-offset',
				`${ header.getBoundingClientRect().bottom + 8 }px`
			);
		};
		const onScroll = () =>
			header.classList.toggle( 'is-scrolled', window.scrollY > 8 );

		// Read layout after the first paint, never while parsing (no forced reflow).
		window.requestAnimationFrame( () => {
			measure();
			onScroll();
		} );
		window.addEventListener( 'resize', measure, { passive: true } );
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	document.querySelectorAll( '[data-oe-carousel]' ).forEach( ( carousel ) => {
		const track = carousel.querySelector( '.oe-carousel__track' );
		const nav = carousel.querySelector( '.oe-carousel__nav' );
		if ( ! track || ! nav || track.children.length < 2 ) {
			return;
		}
		const [ prev, next ] = nav.querySelectorAll( 'button' );
		const update = () => {
			const max = track.scrollWidth - track.clientWidth - 1;
			// aria-disabled, not disabled: a focused button must keep its focus.
			prev.setAttribute(
				'aria-disabled',
				String( track.scrollLeft <= 0 )
			);
			next.setAttribute(
				'aria-disabled',
				String( track.scrollLeft >= max )
			);
		};

		nav.hidden = false;
		nav.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( 'button[data-dir]' );
			if ( button && button.getAttribute( 'aria-disabled' ) !== 'true' ) {
				track.scrollBy( {
					left: Number( button.dataset.dir ) * track.clientWidth,
					behavior: window.matchMedia(
						'(prefers-reduced-motion: reduce)'
					).matches
						? 'auto'
						: 'smooth',
				} );
			}
		} );
		track.addEventListener( 'scroll', update, { passive: true } );
		window.requestAnimationFrame( update );
	} );

	if (
		! ( 'IntersectionObserver' in window ) ||
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	) {
		return;
	}

	const targets = document.querySelectorAll(
		'.oe-sections li, .oe-story__grid, .oe-facts, .oe-signature .oe-dish, .oe-suppliers__grid, .oe-reviews__grid, .oe-cta'
	);
	const observer = new window.IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ rootMargin: '0px 0px -10% 0px' }
	);

	targets.forEach( ( target, index ) => {
		target.classList.add( 'oe-reveal' );
		target.style.transitionDelay = `${ ( index % 4 ) * 80 }ms`;
		observer.observe( target );
	} );
} )();
