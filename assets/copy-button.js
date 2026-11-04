/* Vágólap-ikon (.szeducate-copy) viselkedése. Delegált eseménykezelő a document-en, hogy az
   utólag betöltődő tartalommal (Elementor szerkesztő előnézet, popup) is működjön. */
( function () {
	'use strict';

	var SELECTOR = '.szeducate-copy';
	var RESET_MS = 1600;

	function fallbackCopy( text ) {
		// Nem biztonságos kontextus (http) / régi böngésző: rejtett textarea + execCommand.
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0;';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( ta );
		return ok ? Promise.resolve() : Promise.reject( new Error( 'copy failed' ) );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return fallbackCopy( text );
			} );
		}
		return fallbackCopy( text );
	}

	function flash( el ) {
		el.classList.add( 'is-copied' );
		el.setAttribute( 'title', 'Másolva!' );
		clearTimeout( el._szCopyTimer );
		el._szCopyTimer = setTimeout( function () {
			el.classList.remove( 'is-copied' );
			el.setAttribute( 'title', 'Másolás a vágólapra' );
		}, RESET_MS );
	}

	function activate( el, event ) {
		// Az ikon gyakran hivatkozáson (mailto: / tel:) belül van - ne induljon el a levelező / tárcsázó.
		event.preventDefault();
		event.stopPropagation();
		var text = el.getAttribute( 'data-szeducate-copy' );
		if ( ! text ) {
			return;
		}
		copyText( text ).then( function () {
			flash( el );
		}, function () {} );
	}

	document.addEventListener( 'click', function ( event ) {
		var el = event.target.closest && event.target.closest( SELECTOR );
		if ( el ) {
			activate( el, event );
		}
	}, true );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Enter' && event.key !== ' ' ) {
			return;
		}
		var el = event.target.closest && event.target.closest( SELECTOR );
		if ( el ) {
			activate( el, event );
		}
	}, true );

	// A tabindex-et nem a PHP írja ki (a wp_kses_post kidobná), hanem itt pótoljuk.
	function enhance( root ) {
		var nodes = ( root || document ).querySelectorAll( SELECTOR + ':not([tabindex])' );
		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].setAttribute( 'tabindex', '0' );
		}
	}

	function init() {
		enhance( document );
		if ( window.MutationObserver ) {
			new MutationObserver( function () {
				enhance( document );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
