/**
 * MTMT Sync — widget frontend interakció (Fázis 5).
 * Vanilla JS, nincs build lépés / bundler (CLAUDE.md §2). Több widget-példány
 * is lehet egy oldalon — mindent a `.mtmt-widget` konténer `data-*` attribútumai
 * és a hozzá tartozó, deleguált eseménykezelők vezérelnek.
 */
( function () {
	'use strict';

	/** @type {WeakMap<Element, {year:number, areaId:number, search:string, paged:number}>} */
	var state = new WeakMap();

	/**
	 * @param {Element} widget
	 * @return {{year:number, areaId:number, search:string, paged:number}}
	 */
	function getState( widget ) {
		if ( ! state.has( widget ) ) {
			state.set( widget, {
				year: parseInt( widget.getAttribute( 'data-year' ) || '0', 10 ) || 0,
				areaId: parseInt( widget.getAttribute( 'data-area-filter' ) || '0', 10 ) || 0,
				search: '',
				paged: 1,
			} );
		}
		return state.get( widget );
	}

	/**
	 * @param {Element} widget
	 */
	function fetchAndRender( widget ) {
		var s = getState( widget );
		var ajaxUrl = widget.getAttribute( 'data-ajax-url' );
		var nonce = widget.getAttribute( 'data-nonce' );

		var body = new URLSearchParams();
		body.set( 'action', 'mtmt_widget_query' );
		body.set( 'nonce', nonce || '' );
		body.set( 'year', String( s.year ) );
		body.set( 'search', s.search );
		body.set( 'paged', String( s.paged ) );
		body.set( 'per_page', widget.getAttribute( 'data-per-page' ) || '20' );
		body.set( 'citation_style', widget.getAttribute( 'data-citation-style' ) || 'full' );
		body.set( 'show_doi_link', widget.getAttribute( 'data-show-doi-link' ) === '1' ? '1' : '0' );
		body.set( 'show_sjr_badge', widget.getAttribute( 'data-show-sjr-badge' ) === '1' ? '1' : '0' );
		body.set( 'show_topic_area', widget.getAttribute( 'data-show-topic-area' ) === '1' ? '1' : '0' );
		body.set( 'empty_state_text', widget.getAttribute( 'data-empty-text' ) || '' );
		body.set( 'pagination_prev_label', widget.getAttribute( 'data-prev-label' ) || '' );
		body.set( 'pagination_next_label', widget.getAttribute( 'data-next-label' ) || '' );

		if ( 'topic' === widget.getAttribute( 'data-widget-type' ) ) {
			body.set( 'area_id', widget.getAttribute( 'data-area-id' ) || '0' );
			body.set( 'profile_id', widget.getAttribute( 'data-profile-id' ) || '0' );
			body.set( 'featured_only', '1' );
		} else {
			body.set( 'area_id', String( s.areaId ) );
		}

		widget.classList.add( 'is-loading' );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data ) {
					return;
				}
				var list = widget.querySelector( '.mtmt-widget-list' );
				var pagination = widget.querySelector( '.mtmt-widget-pagination' );
				if ( list ) {
					list.innerHTML = json.data.html;
				}
				if ( pagination ) {
					pagination.innerHTML = json.data.pagination;
				}
			} )
			['catch']( function () {
				// Csendes hiba — a widget a korábbi, még érvényes tartalmát mutatja tovább.
			} )
			.then( function () {
				widget.classList.remove( 'is-loading' );
			} );
	}

	/**
	 * @param {Element} widget
	 * @param {number} year
	 */
	function setActiveYearTab( widget, year ) {
		var tabs = widget.querySelectorAll( '.mtmt-year-tab' );
		for ( var i = 0; i < tabs.length; i++ ) {
			var tabYear = parseInt( tabs[ i ].getAttribute( 'data-year' ) || '0', 10 );
			tabs[ i ].classList.toggle( 'is-active', tabYear === year );
		}
	}

	var searchDebounceTimers = new WeakMap();

	document.addEventListener( 'click', function ( e ) {
		var yearTab = e.target.closest( '.mtmt-year-tab' );
		if ( yearTab ) {
			var widget = yearTab.closest( '.mtmt-widget' );
			if ( ! widget ) {
				return;
			}
			var s = getState( widget );
			s.year = parseInt( yearTab.getAttribute( 'data-year' ) || '0', 10 ) || 0;
			s.paged = 1;
			setActiveYearTab( widget, s.year );
			fetchAndRender( widget );
			return;
		}

		var pageBtn = e.target.closest( '.mtmt-page-btn' );
		if ( pageBtn ) {
			var pagerWidget = pageBtn.closest( '.mtmt-widget' );
			if ( ! pagerWidget ) {
				return;
			}
			var ps = getState( pagerWidget );
			ps.paged = parseInt( pageBtn.getAttribute( 'data-page' ) || '1', 10 ) || 1;
			fetchAndRender( pagerWidget );
			pagerWidget.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			return;
		}

		// Teljes kártya kattintható (CLAUDE.md §14/12) — de csak akkor navigálunk
		// JS-ből, ha a kattintás NEM egy beágyazott linken/gombon történt (pl. az
		// egyéb-azonosítós logó-gombok saját célra mutatnak).
		var card = e.target.closest( '.mtmt-pub-card[data-href]' );
		if ( card && ! e.target.closest( 'a, button' ) ) {
			window.open( card.getAttribute( 'data-href' ), '_blank', 'noopener' );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' !== e.key && ' ' !== e.key ) {
			return;
		}
		var card = e.target.closest && e.target.closest( '.mtmt-pub-card[data-href]' );
		if ( card && document.activeElement === card ) {
			e.preventDefault();
			window.open( card.getAttribute( 'data-href' ), '_blank', 'noopener' );
		}
	} );

	document.addEventListener( 'input', function ( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'mtmt-search-input' ) ) {
			return;
		}
		var widget = e.target.closest( '.mtmt-widget' );
		if ( ! widget ) {
			return;
		}
		var s = getState( widget );
		s.search = e.target.value;
		s.paged = 1;

		if ( searchDebounceTimers.has( widget ) ) {
			clearTimeout( searchDebounceTimers.get( widget ) );
		}
		searchDebounceTimers.set(
			widget,
			setTimeout( function () {
				fetchAndRender( widget );
			}, 400 )
		);
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'mtmt-area-filter' ) ) {
			return;
		}
		var widget = e.target.closest( '.mtmt-widget' );
		if ( ! widget ) {
			return;
		}
		var s = getState( widget );
		s.areaId = parseInt( e.target.value || '0', 10 ) || 0;
		s.paged = 1;
		fetchAndRender( widget );
	} );
} )();
