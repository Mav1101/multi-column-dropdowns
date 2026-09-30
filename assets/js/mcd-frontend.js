/**
 * Multi-Column Dropdowns – keeps open multi-column dropdowns inside the viewport and
 * flips third-level flyouts that would leave the screen.
 *
 * No dependencies. It never touches `display`; showing and hiding stays with the theme.
 */
( function () {
	'use strict';

	var ITEM_SELECTOR = '.mcd-enabled';
	var SKIP_SELECTOR = '#mobile-dropdown, #sidr, #mobile-fullscreen, .elementor-nav-menu__container.elementor-nav-menu--dropdown, .mcd-static-menu';
	var SMARTMENUS_SELECTOR = '[data-smartmenus-id]';
	var WATCHED_ATTRIBUTES = [ 'style', 'class', 'aria-hidden', 'aria-expanded' ];

	var items = [];
	var observer = null;
	var frame = 0;

	function directSubMenu( li ) {
		var children = li.children;
		for ( var i = 0; i < children.length; i++ ) {
			if ( children[ i ].classList.contains( 'sub-menu' ) ) {
				return children[ i ];
			}
		}
		return null;
	}

	function isVisible( el ) {
		return !! el && el.getClientRects().length > 0;
	}

	function edgeGutter( el ) {
		var value = parseFloat( window.getComputedStyle( el ).getPropertyValue( '--mcd-edge-gutter' ) );
		return isNaN( value ) ? 16 : value;
	}

	function setClass( el, className, on ) {
		if ( el.classList.contains( className ) !== on ) {
			el.classList.toggle( className, on );
		}
	}

	/**
	 * Flips flyouts inside an open dropdown. SmartMenus positions its own flyouts, so those are left alone.
	 */
	function fitFlyouts( subMenu, viewport, gutter ) {
		if ( subMenu.closest( SMARTMENUS_SELECTOR ) ) {
			return;
		}
		var parents = subMenu.querySelectorAll( 'li' );
		for ( var i = 0; i < parents.length; i++ ) {
			var li = parents[ i ];
			var flyout = directSubMenu( li );
			if ( ! isVisible( flyout ) ) {
				continue;
			}
			var rect = li.getBoundingClientRect();
			var width = flyout.offsetWidth;
			var rtl = 'rtl' === window.getComputedStyle( li ).direction;
			var roomRight = viewport - gutter - rect.right;
			var roomLeft = rect.left - gutter;

			setClass( li, 'mcd-flyout-left', ! rtl && width > roomRight && roomLeft > roomRight );
			setClass( li, 'mcd-flyout-right', rtl && width > roomLeft && roomRight > roomLeft );
		}
	}

	/**
	 * Shifts an open dropdown horizontally so it stays inside the viewport.
	 * The current shift is subtracted first, so no reset (and no flicker) is needed.
	 */
	function fitItem( li ) {
		var subMenu = directSubMenu( li );
		if ( ! isVisible( subMenu ) ) {
			return;
		}

		var viewport = document.documentElement.clientWidth;
		var gutter = edgeGutter( li );
		var current = parseFloat( li.style.getPropertyValue( '--mcd-shift' ) ) || 0;
		var rect = subMenu.getBoundingClientRect();
		var left = rect.left - current;
		var right = rect.right - current;
		var shift = 0;

		if ( right > viewport - gutter ) {
			shift = viewport - gutter - right;
		}
		if ( left + shift < gutter ) {
			shift = gutter - left;
		}
		shift = Math.round( shift );

		if ( shift !== Math.round( current ) ) {
			if ( shift ) {
				li.style.setProperty( '--mcd-shift', shift + 'px' );
			} else {
				li.style.removeProperty( '--mcd-shift' );
			}
		}
		setClass( li, 'mcd-shifted', 0 !== shift );

		fitFlyouts( subMenu, viewport, gutter );
	}

	function fitAll() {
		frame = 0;
		items.forEach( fitItem );
		// Drop the mutation records caused by our own writes so they do not schedule another pass.
		if ( observer ) {
			observer.takeRecords();
		}
	}

	function schedule() {
		if ( ! frame ) {
			frame = window.requestAnimationFrame( fitAll );
		}
	}

	function collect() {
		var found = document.querySelectorAll( ITEM_SELECTOR );
		items = [];
		for ( var i = 0; i < found.length; i++ ) {
			if ( ! found[ i ].closest( SKIP_SELECTOR ) ) {
				items.push( found[ i ] );
			}
		}

		if ( observer ) {
			observer.disconnect();
			items.forEach( function ( li ) {
				observer.observe( li, {
					subtree: true,
					attributes: true,
					attributeFilter: WATCHED_ATTRIBUTES,
				} );
			} );
		}
	}

	function onInteraction( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var li = target.closest( ITEM_SELECTOR );
		if ( ! li || li.closest( SKIP_SELECTOR ) ) {
			return;
		}
		// Menus re-rendered by the Elementor editor are new elements.
		if ( items.indexOf( li ) === -1 ) {
			collect();
		}
		fitAll();
		schedule();
	}

	function init() {
		if ( 'MutationObserver' in window ) {
			// Theme scripts open menus by changing style/class/aria attributes.
			observer = new window.MutationObserver( schedule );
		}
		collect();

		document.addEventListener( 'mouseover', onInteraction, true );
		document.addEventListener( 'focusin', onInteraction, true );
		document.addEventListener( 'click', onInteraction, true );
		window.addEventListener( 'resize', schedule );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
