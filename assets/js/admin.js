/**
 * Gift Cards, admin settings enhancements (vanilla JS, no jQuery).
 *
 * Progressive enhancement only; every feature degrades gracefully:
 *  - Help "?" buttons reveal an accessible tooltip via the native Popover API
 *    when supported, falling back to a wired-up inline <span> otherwise. Each
 *    trigger is associated with its help text via aria-describedby.
 *  - Token chips insert/copy their placeholder into the focused (or last-used)
 *    template field, or copy to clipboard as a fallback.
 *  - A live preview mirrors the email subject/body as the merchant types,
 *    interpolating {code}/{amount} so they see the result instantly.
 *
 * Enqueued deferred in the footer; runs after DOM parse.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.giftcards-admin' );
	if ( ! root ) {
		return;
	}

	var supportsPopover =
		typeof HTMLElement !== 'undefined' &&
		HTMLElement.prototype.hasOwnProperty( 'popover' );

	/* ---- Tooltips ---------------------------------------------------- */

	root.querySelectorAll( '.giftcards-help' ).forEach( function ( trigger ) {
		var tip = document.getElementById(
			trigger.getAttribute( 'aria-describedby' ) || ''
		);
		if ( ! tip ) {
			return;
		}

		// Fallback span is always present for no-JS; hide it once JS upgrades.
		var fallback = tip.classList.contains( 'giftcards-help-fallback' );

		if ( supportsPopover && ! fallback ) {
			var show = function () {
				try {
					positionTip( trigger, tip );
					tip.showPopover();
				} catch ( e ) {}
			};
			var hide = function () {
				try {
					tip.hidePopover();
				} catch ( e ) {}
			};
			trigger.addEventListener( 'mouseenter', show );
			trigger.addEventListener( 'focus', show );
			trigger.addEventListener( 'mouseleave', hide );
			trigger.addEventListener( 'blur', hide );
			trigger.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					hide();
				}
			} );
		}
	} );

	function positionTip( trigger, tip ) {
		var r = trigger.getBoundingClientRect();
		tip.style.position = 'fixed';
		tip.style.margin = '0';
		tip.style.insetBlockStart = Math.round( r.bottom + 8 ) + 'px';
		tip.style.insetInlineStart =
			Math.round(
				Math.min(
					r.left,
					document.documentElement.clientWidth - 300
				)
			) + 'px';
	}

	/* ---- Template token chips --------------------------------------- */

	var lastField = null;
	root
		.querySelectorAll(
			'#giftcards_email_subject, #giftcards_email_body'
		)
		.forEach( function ( field ) {
			field.addEventListener( 'focus', function () {
				lastField = field;
			} );
			field.addEventListener( 'input', updatePreview );
		} );

	root.querySelectorAll( '.giftcards-token' ).forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			var token = chip.getAttribute( 'data-token' ) || '';
			if ( ! token ) {
				return;
			}

			var target = lastField || resolveDefaultField( chip );

			if ( target ) {
				insertAtCursor( target, token );
				target.focus();
				updatePreview();
				flash( chip, chip.getAttribute( 'data-inserted' ) );
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( token ).then( function () {
					flash( chip, chip.getAttribute( 'data-copied' ) );
				} );
			}
		} );
	} );

	function resolveDefaultField( chip ) {
		var scope = chip.closest( '.giftcards-admin__card' );
		return scope
			? scope.querySelector( 'input[type="text"], textarea' )
			: null;
	}

	function insertAtCursor( field, text ) {
		var start = field.selectionStart;
		var end = field.selectionEnd;
		if ( typeof start === 'number' && typeof end === 'number' ) {
			field.value =
				field.value.slice( 0, start ) +
				text +
				field.value.slice( end );
			var pos = start + text.length;
			field.setSelectionRange( pos, pos );
		} else {
			field.value += text;
		}
	}

	var flashTimers = new WeakMap();
	function flash( chip, label ) {
		var original = chip.getAttribute( 'data-label' ) || chip.textContent;
		if ( ! chip.hasAttribute( 'data-label' ) ) {
			chip.setAttribute( 'data-label', chip.textContent );
		}
		chip.textContent = label || '✓';
		chip.classList.add( 'giftcards-token__copied' );
		clearTimeout( flashTimers.get( chip ) );
		flashTimers.set(
			chip,
			setTimeout( function () {
				chip.textContent = original;
				chip.classList.remove( 'giftcards-token__copied' );
			}, 1200 )
		);
	}

	/* ---- Live email preview ----------------------------------------- */

	var subjectField = root.querySelector( '#giftcards_email_subject' );
	var bodyField = root.querySelector( '#giftcards_email_body' );
	var previewSubject = root.querySelector(
		'.giftcards-preview__subject'
	);
	var previewBody = root.querySelector( '.giftcards-preview__body' );

	function interpolate( str ) {
		var sample =
			root.getAttribute( 'data-sample-amount' ) || '$50.00';
		var code = root.getAttribute( 'data-sample-code' ) || 'GIFT-AB12CD34';
		return String( str )
			.split( '{amount}' )
			.join( sample )
			.split( '{code}' )
			.join( code );
	}

	function updatePreview() {
		if ( previewSubject && subjectField ) {
			previewSubject.textContent = interpolate( subjectField.value );
		}
		if ( previewBody && bodyField ) {
			previewBody.textContent = interpolate( bodyField.value );
		}
	}

	updatePreview();
} )();
