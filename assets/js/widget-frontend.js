/**
 * MTMT Sync — widget frontend interakció (Fázis 5).
 * Vanilla JS, nincs build lépés / bundler (CLAUDE.md §2). Több widget-példány
 * is lehet egy oldalon — mindent a `.mtmt-widget` konténer `data-*` attribútumai
 * és a hozzá tartozó, deleguált eseménykezelők vezérelnek.
 */
( function () {
	'use strict';

	/** @type {WeakMap<Element, {year:number, areaId:number, project:string, search:string, sort:string, paged:number}>} */
	var state = new WeakMap();

	/**
	 * @param {Element} widget
	 * @return {{year:number, areaId:number, project:string, search:string, sort:string, paged:number}}
	 */
	function getState( widget ) {
		if ( ! state.has( widget ) ) {
			state.set( widget, {
				year: parseInt( widget.getAttribute( 'data-year' ) || '0', 10 ) || 0,
				areaId: parseInt( widget.getAttribute( 'data-area-filter' ) || '0', 10 ) || 0,
				project: '',
				search: '',
				// Widget-beállítás (nincs hozzá frontend kapcsoló), de minden
				// AJAX-kérésnél elküldjük, hogy a beállított sorrend keresés/
				// év-váltás/lapozás után is érvényben maradjon.
				sort: widget.getAttribute( 'data-sort-order' ) || 'newest',
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
		body.set( 'sort', s.sort );
		body.set( 'paged', String( s.paged ) );
		body.set( 'per_page', widget.getAttribute( 'data-per-page' ) || '20' );
		body.set( 'citation_style', widget.getAttribute( 'data-citation-style' ) || 'full' );
		body.set( 'show_doi_link', widget.getAttribute( 'data-show-doi-link' ) === '1' ? '1' : '0' );
		body.set( 'show_sjr_badge', widget.getAttribute( 'data-show-sjr-badge' ) === '1' ? '1' : '0' );
		body.set( 'ext_id_badge_mode', widget.getAttribute( 'data-ext-id-badge-mode' ) || 'both' );
		body.set( 'show_topic_area', widget.getAttribute( 'data-show-topic-area' ) === '1' ? '1' : '0' );
		body.set( 'empty_state_text', widget.getAttribute( 'data-empty-text' ) || '' );
		body.set( 'pagination_prev_label', widget.getAttribute( 'data-prev-label' ) || '' );
		body.set( 'pagination_next_label', widget.getAttribute( 'data-next-label' ) || '' );
		body.set( 'year_tab_all_label', widget.getAttribute( 'data-year-all-label' ) || '' );
		body.set( 'show_funding', widget.getAttribute( 'data-show-funding' ) === '1' ? '1' : '0' );
		body.set( 'funding_label', widget.getAttribute( 'data-funding-label' ) || '' );
		body.set( 'show_project_ids', widget.getAttribute( 'data-show-project-ids' ) === '1' ? '1' : '0' );
		body.set( 'project_label', widget.getAttribute( 'data-project-label' ) || '' );
		body.set( 'project_clickable', widget.getAttribute( 'data-project-clickable' ) === '1' ? '1' : '0' );
		body.set( 'show_dimensions', widget.getAttribute( 'data-dimensions' ) === '1' ? '1' : '0' );
		body.set( 'dimensions_style', widget.getAttribute( 'data-dimensions-style' ) || '' );
		body.set( 'dimensions_legend', widget.getAttribute( 'data-dimensions-legend' ) || '' );
		body.set( 'dimensions_hide_zero', widget.getAttribute( 'data-dimensions-hide-zero' ) === '1' ? '1' : '0' );

		if ( 'topic' === widget.getAttribute( 'data-widget-type' ) ) {
			body.set( 'area_id', widget.getAttribute( 'data-area-id' ) || '0' );
			body.set( 'profile_id', widget.getAttribute( 'data-profile-id' ) || '0' );
			body.set( 'featured_only', '1' );
		} else {
			body.set( 'area_id', String( s.areaId ) );
			body.set( 'project', s.project );
			// Van terület-szűrő lenyíló -> a szerver keresés után szűkítse a
			// listáját a találatot adó területekre.
			if ( widget.querySelector( '.mtmt-area-filter' ) ) {
				body.set( 'area_filter', '1' );
				body.set( 'area_filter_all_label', widget.getAttribute( 'data-area-all-label' ) || '' );
			}
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
				var yearTabs = widget.querySelector( '.mtmt-year-tabs' );
				if ( list ) {
					list.innerHTML = json.data.html;
					refreshDimensionsBadges( widget );
				}
				if ( pagination ) {
					pagination.innerHTML = json.data.pagination;
				}
				// Az év-fülek a kereséssel/terület-szűréssel újraszámolódnak a
				// szerveren — csak a találatot adó évek maradnak. A szerver az
				// aktív évet is visszaadhatja módosítva (ha a kiválasztott évre
				// már nincs találat, "Összes"-re esik vissza).
				if ( yearTabs && typeof json.data.year_tabs === 'string' ) {
					yearTabs.innerHTML = json.data.year_tabs;
				}
				if ( typeof json.data.active_year !== 'undefined' ) {
					s.year = parseInt( json.data.active_year, 10 ) || 0;
				}
				// A terület-szűrő lenyíló is a kereséssel újraszámolódik: csak a
				// találatot adó területek maradnak benne. A szerver a helyes
				// <option>-t jelöli kiválasztottnak (ha a kiválasztott terület
				// kiesett, "minden terület"-re esik vissza).
				var areaFilter = widget.querySelector( '.mtmt-area-filter' );
				if ( areaFilter && typeof json.data.area_options === 'string' ) {
					areaFilter.innerHTML = json.data.area_options;
					// Ha csak a "minden terület" opció maradt (a keresésnek egyik
					// terület sem felel meg), a szűrőnek nincs értelme -> elrejtjük.
					areaFilter.hidden = areaFilter.options.length <= 1;
					if ( typeof json.data.active_area_id !== 'undefined' ) {
						s.areaId = parseInt( json.data.active_area_id, 10 ) || 0;
					}
				}
			} )
			['catch']( function () {
				// Csendes hiba — a widget a korábbi, még érvényes tartalmát mutatja tovább.
			} )
			.then( function () {
				widget.classList.remove( 'is-loading' );
			} );
	}

	// --- Dimensions idézettség-badge (docs/decisions.md #101) -----------------
	// A badge.dimensions.ai script CSAK akkor töltődik be, ha az oldalon van
	// olyan widget, amelyiken a badge be van kapcsolva, ÉS az a widget a
	// látótérbe kerül (lazy-load). AJAX-csere után az új badge-eket a
	// Dimensions saját API-jával (`__dimensions_embed.addBadges()`) kell
	// "felébreszteni", különben üresek maradnak.
	var DIMENSIONS_SRC = 'https://badge.dimensions.ai/badge.js';
	var dimensionsState = 'idle'; // idle | loading | ready

	function loadDimensionsScript() {
		if ( 'idle' !== dimensionsState ) {
			return;
		}
		dimensionsState = 'loading';
		var script = document.createElement( 'script' );
		script.src = DIMENSIONS_SRC;
		script.async = true;
		script.charset = 'utf-8';
		script.onload = function () {
			dimensionsState = 'ready';
			if ( window.__dimensions_embed && typeof window.__dimensions_embed.addBadges === 'function' ) {
				window.__dimensions_embed.addBadges();
			}
		};
		script.onerror = function () {
			dimensionsState = 'idle';
		};
		document.head.appendChild( script );
	}

	/**
	 * @param {Element} widget
	 */
	function refreshDimensionsBadges( widget ) {
		if ( '1' !== widget.getAttribute( 'data-dimensions' ) ) {
			return;
		}
		if ( 'ready' === dimensionsState && window.__dimensions_embed && typeof window.__dimensions_embed.addBadges === 'function' ) {
			window.__dimensions_embed.addBadges();
		} else {
			loadDimensionsScript();
		}
	}

	/**
	 * @param {Element} widget
	 */
	function initDimensions( widget ) {
		if ( '1' !== widget.getAttribute( 'data-dimensions' ) || widget.getAttribute( 'data-dimensions-init' ) ) {
			return;
		}
		widget.setAttribute( 'data-dimensions-init', '1' );

		if ( ! ( 'IntersectionObserver' in window ) ) {
			refreshDimensionsBadges( widget );
			return;
		}
		var observer = new IntersectionObserver( function ( entries ) {
			for ( var i = 0; i < entries.length; i++ ) {
				if ( entries[ i ].isIntersecting ) {
					observer.disconnect();
					refreshDimensionsBadges( widget );
					return;
				}
			}
		}, { rootMargin: '200px 0px' } );
		observer.observe( widget );
	}

	function initAllDimensions() {
		var widgets = document.querySelectorAll( '.mtmt-widget[data-dimensions="1"]' );
		for ( var i = 0; i < widgets.length; i++ ) {
			initDimensions( widgets[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAllDimensions );
	} else {
		initAllDimensions();
	}

	// Elementor szerkesztő-előnézet: a widget újrarenderelésekor (beállítás-
	// váltás) új DOM-elem jön létre, azt is inicializálni kell.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		var onReady = function ( $scope ) {
			var el = $scope && $scope[ 0 ] ? $scope[ 0 ].querySelector( '.mtmt-widget' ) : null;
			if ( el ) {
				initDimensions( el );
			}
		};
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/mtmt_all_publications.default', onReady );
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/mtmt_topic_publications.default', onReady );
	} );

	/**
	 * A képernyő tetején lévő fix/ragadós sávok (admin-bar, ragadós fejléc)
	 * együttes magassága — ennyivel a widget teteje alá kell görgetni, hogy a
	 * fejléc ne takarja ki.
	 *
	 * @return {number}
	 */
	function topOverlayHeight() {
		if ( ! document.elementsFromPoint ) {
			return 0;
		}
		var bottom = 0;
		var x = Math.round( window.innerWidth / 2 );
		var maxY = Math.min( 240, window.innerHeight / 3 );
		// A felső sávot végigmintavételezzük, mert a "lebegő" fejléc nem mindig
		// tapad a 0. pixelhez (pl. admin-bar alatt, kis réssel).
		for ( var y = 1; y < maxY; y += 8 ) {
			var stack = document.elementsFromPoint( x, y );
			for ( var i = 0; i < stack.length; i++ ) {
				for ( var el = stack[ i ]; el && el !== document.body && el !== document.documentElement; el = el.parentElement ) {
					var pos = window.getComputedStyle( el ).position;
					if ( 'fixed' !== pos && 'sticky' !== pos ) {
						continue;
					}
					var r = el.getBoundingClientRect();
					if ( r.top < maxY && r.height < window.innerHeight / 2 && r.width > window.innerWidth * 0.3 ) {
						bottom = Math.max( bottom, r.bottom );
					}
				}
			}
		}
		return bottom;
	}

	/**
	 * Lapozás / projekt-chip után a widget tetejére görget, de CSAK az ablakot
	 * (window.scrollTo). Az Element.scrollIntoView() korábban egy overflow:hidden
	 * Elementor-konténert is "elgörgetett" — ettől csúszott fel a widget fölötti
	 * tartalom és tolódott el az oldal (docs/decisions.md #104). Ha a widget
	 * teteje már látszik, nem görget.
	 *
	 * @param {Element} widget
	 */
	function scrollWidgetIntoView( widget ) {
		var offset = topOverlayHeight() + 16;
		var top = widget.getBoundingClientRect().top;
		if ( top >= offset && top < window.innerHeight * 0.6 ) {
			return;
		}
		var target = Math.max( 0, window.pageYOffset + top - offset );
		try {
			window.scrollTo( { top: target, behavior: 'smooth' } );
		} catch ( err ) {
			window.scrollTo( 0, target );
		}
	}

	/**
	 * Projekt-szűrő beállítása (lenyílóból vagy egy kártya projekt-chipjéről).
	 *
	 * @param {Element} widget
	 * @param {string} project
	 */
	function setProjectFilter( widget, project ) {
		var s = getState( widget );
		s.project = project;
		s.paged = 1;
		var select = widget.querySelector( '.mtmt-project-filter' );
		if ( select ) {
			select.value = project;
		}
		fetchAndRender( widget );
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
			scrollWidgetIntoView( pagerWidget );
			return;
		}

		var projectChip = e.target.closest( '.mtmt-project-chip' );
		if ( projectChip ) {
			var chipWidget = projectChip.closest( '.mtmt-widget' );
			if ( chipWidget && chipWidget.querySelector( '.mtmt-project-filter' ) ) {
				setProjectFilter( chipWidget, projectChip.getAttribute( 'data-project' ) || '' );
				scrollWidgetIntoView( chipWidget );
			}
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
		if ( e.target.classList && e.target.classList.contains( 'mtmt-project-filter' ) ) {
			var projectWidget = e.target.closest( '.mtmt-widget' );
			if ( projectWidget ) {
				setProjectFilter( projectWidget, e.target.value || '' );
			}
			return;
		}
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
