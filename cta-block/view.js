/**
 * Front-end controller for the "Call to Action (A/B)" block.
 *
 * Variant assignment happens here, on the client, so the server response stays
 * cacheable for every visitor. The chosen variant is stored in a cookie so a
 * returning visitor keeps seeing the same one, and an exposure event is sent
 * once per render for whatever analytics layer is present.
 */
( function () {
	'use strict';

	var COOKIE_PREFIX = 'lm_cta_';
	var COOKIE_DAYS = 30;

	/**
	 * Pick an index from a list of relative weights.
	 * For example [ 1, 1 ] is a 50/50 split and [ 3, 1 ] is 75/25.
	 *
	 * @param {number[]} weights Relative, non-negative weights.
	 * @return {number} The chosen index.
	 */
	function weightedChoice( weights ) {
		var total = weights.reduce( function ( sum, weight ) {
			return sum + weight;
		}, 0 );

		if ( total <= 0 ) {
			return 0;
		}

		var threshold = Math.random() * total;
		for ( var i = 0; i < weights.length; i++ ) {
			threshold -= weights[ i ];
			if ( threshold < 0 ) {
				return i;
			}
		}

		return weights.length - 1;
	}

	function readCookie( name ) {
		var match = document.cookie.match(
			new RegExp( '(?:^|; )' + name + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[ 1 ] ) : null;
	}

	function writeCookie( name, value ) {
		var expires = new Date( Date.now() + COOKIE_DAYS * 864e5 ).toUTCString();
		var secure = 'https:' === window.location.protocol ? '; Secure' : '';
		document.cookie = name + '=' + encodeURIComponent( value ) +
			'; expires=' + expires + '; path=/; SameSite=Lax' + secure;
	}

	/**
	 * Report an event without assuming a specific analytics vendor: push to a
	 * data layer if one exists, and always dispatch a DOM event teams can hook.
	 */
	function track( action, experiment, variant ) {
		var payload = {
			event: 'cta_' + action,
			experiment: experiment,
			variant: variant,
		};

		if ( Array.isArray( window.dataLayer ) ) {
			window.dataLayer.push( payload );
		}

		document.dispatchEvent(
			new CustomEvent( 'lm-cta:' + action, { detail: payload } )
		);
	}

	function activate( root ) {
		var config;
		try {
			config = JSON.parse( root.getAttribute( 'data-lm-cta' ) );
		} catch ( error ) {
			return; // Malformed config: leave the control variant visible.
		}

		var buttons = Array.prototype.slice.call(
			root.querySelectorAll( '.lm-cta__button' )
		);
		if ( ! buttons.length ) {
			return;
		}

		// Editor preview: show every variant, and do not assign or track.
		if ( root.hasAttribute( 'data-preview' ) ) {
			buttons.forEach( function ( button ) {
				button.hidden = false;
			} );
			return;
		}

		// Reuse a prior assignment when one exists, otherwise assign and store.
		var cookie = COOKIE_PREFIX + config.experiment;
		var chosen = parseInt( readCookie( cookie ), 10 );
		if ( isNaN( chosen ) || chosen < 0 || chosen >= buttons.length ) {
			chosen = weightedChoice( config.weights || [] );
			writeCookie( cookie, String( chosen ) );
		}

		buttons.forEach( function ( button, index ) {
			button.hidden = index !== chosen;
		} );

		var variant = buttons[ chosen ].getAttribute( 'data-variant' );
		track( 'view', config.experiment, variant );
		buttons[ chosen ].addEventListener( 'click', function () {
			track( 'click', config.experiment, variant );
		} );
	}

	function init() {
		document.querySelectorAll( '.lm-cta' ).forEach( activate );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
