/**
 * Accessible modal dialog.
 *
 * Built on the native <dialog> element, which handles the parts that are easy
 * to get wrong and tedious to maintain: it moves focus into the dialog, traps
 * Tab within it, closes on Escape, renders above all other content in the top
 * layer, and returns focus to the trigger on close. This controller adds only
 * what the platform leaves to the author: opening from a trigger, closing on a
 * backdrop click, locking background scroll without a layout shift, and
 * animating open and close while respecting a reduced-motion preference.
 *
 * Markup contract:
 *
 *   <button type="button" data-modal-open="promo">Open</button>
 *
 *   <dialog id="promo" class="modal" aria-labelledby="promo-title">
 *     <div class="modal__content">
 *       <h2 id="promo-title">Title</h2>
 *       ...
 *       <button type="button" data-modal-close>Close</button>
 *     </div>
 *   </dialog>
 */
( function () {
	'use strict';

	var OPEN_CLASS = 'is-open';
	var CLOSING_CLASS = 'is-closing';

	var prefersReducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/**
	 * Lock background scroll and compensate for the scrollbar width so the page
	 * behind the modal does not shift when the scrollbar disappears.
	 */
	function lockScroll() {
		var scrollbar = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty( '--modal-scrollbar', scrollbar + 'px' );
		document.body.classList.add( 'modal-open' );
	}

	function unlockScroll() {
		document.body.classList.remove( 'modal-open' );
	}

	function open( dialog ) {
		if ( typeof dialog.showModal !== 'function' || dialog.open ) {
			return;
		}

		lockScroll();
		dialog.showModal();

		// Toggle the class on the next frame so the transition runs rather than
		// applying the open state instantly.
		requestAnimationFrame( function () {
			dialog.classList.add( OPEN_CLASS );
		} );

		// Honor an author-chosen starting point for focus, if one is marked.
		var initial = dialog.querySelector( '[data-modal-initial-focus]' );
		if ( initial ) {
			initial.focus();
		}
	}

	function finishClose( dialog ) {
		dialog.classList.remove( OPEN_CLASS, CLOSING_CLASS );
		dialog.close();
		unlockScroll();
	}

	function close( dialog ) {
		if ( ! dialog.open ) {
			return;
		}

		if ( prefersReducedMotion.matches ) {
			finishClose( dialog );
			return;
		}

		dialog.classList.add( CLOSING_CLASS );
		dialog.addEventListener( 'transitionend', function handler( event ) {
			if ( event.target === dialog ) {
				dialog.removeEventListener( 'transitionend', handler );
				finishClose( dialog );
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var opener = event.target.closest( '[data-modal-open]' );
		if ( opener ) {
			var target = document.getElementById( opener.getAttribute( 'data-modal-open' ) );
			if ( target ) {
				open( target );
			}
			return;
		}

		if ( event.target.closest( '[data-modal-close]' ) ) {
			var parent = event.target.closest( 'dialog' );
			if ( parent ) {
				close( parent );
			}
			return;
		}

		// A click whose target is the <dialog> itself, rather than its content,
		// landed on the backdrop, so close.
		if ( event.target.matches( 'dialog.modal' ) ) {
			close( event.target );
		}
	} );

	// Escape fires the dialog's "cancel" event. Intercept it so the close
	// animation runs instead of the instant native close.
	document.addEventListener( 'cancel', function ( event ) {
		if ( event.target.matches( 'dialog.modal' ) ) {
			event.preventDefault();
			close( event.target );
		}
	}, true );
} )();
