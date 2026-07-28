/**
 * Gift Cards, storefront enhancements (vanilla JS, no jQuery).
 *
 * Progressive enhancement only:
 *  - Copy buttons on issued gift-card codes use the async Clipboard API with a
 *    selection fallback, and announce success via an aria-live region.
 *  - The checkout redeem input normalises to uppercase for a cleaner read; the
 *    server still re-normalises, so this is purely cosmetic.
 *
 * Uses event delegation (works for codes rendered after AJAX checkout updates).
 * Enqueued deferred in the footer.
 */
( function () {
	'use strict';

	var live = null;
	function announce( msg ) {
		if ( ! live ) {
			live = document.createElement( 'div' );
			live.className = 'screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			live.setAttribute( 'role', 'status' );
			document.body.appendChild( live );
		}
		live.textContent = '';
		// Force re-announcement.
		window.requestAnimationFrame( function () {
			live.textContent = msg;
		} );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			try {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( e ) {
				reject( e );
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.giftcards-copy' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var code = btn.getAttribute( 'data-code' ) || '';
		if ( ! code ) {
			return;
		}
		copyText( code ).then(
			function () {
				btn.setAttribute( 'data-copied', 'true' );
				announce(
					( btn.getAttribute( 'data-copied-label' ) ||
						'Copied' ) +
						': ' +
						code
				);
				window.setTimeout( function () {
					btn.removeAttribute( 'data-copied' );
				}, 1500 );
			},
			function () {
				announce(
					btn.getAttribute( 'data-error-label' ) ||
						'Copy failed'
				);
			}
		);
	} );

	/* Cosmetic uppercase on the redeem input. */
	var input = document.querySelector( '.giftcards-redeem__input' );
	if ( input ) {
		input.addEventListener( 'input', function () {
			var pos = input.selectionStart;
			input.value = input.value.toUpperCase();
			if ( typeof pos === 'number' ) {
				input.setSelectionRange( pos, pos );
			}
		} );
	}

	/*
	 * "Apply" triggers a WooCommerce checkout recalculation so the engine reads
	 * the new code via woocommerce_checkout_update_order_review. WooCommerce
	 * binds that to jQuery's "update_checkout" body event; we prefer it when
	 * jQuery is present, otherwise fall back to a native input event. The button
	 * is type="button" so it never submits/places the order.
	 */
	function triggerCheckoutUpdate() {
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		} else if ( input ) {
			input.dispatchEvent(
				new Event( 'change', { bubbles: true } )
			);
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var apply =
			e.target.closest && e.target.closest( '[data-giftcards-apply]' );
		if ( ! apply ) {
			return;
		}
		e.preventDefault();
		triggerCheckoutUpdate();
	} );

	if ( input ) {
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				triggerCheckoutUpdate();
			}
		} );
	}
} )();
