( function () {
	'use strict';

	var settings = window.mwLocalAiConnectorAdminSettings || {};
	var copiedLabel = settings.copiedLabel || 'Copied!';
	var failedLabel = settings.failedLabel || 'Copy failed';

	function copyText( text ) {
		if ( navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var textarea = document.createElement( 'textarea' );

			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'absolute';
			textarea.style.left = '-9999px';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
					return;
				}

				reject( new Error( 'Copy command failed.' ) );
			} catch ( error ) {
				reject( error );
			} finally {
				document.body.removeChild( textarea );
			}
		} );
	}

	function flashLabel( btn, text ) {
		var orig = btn.textContent;
		btn.textContent = text;
		setTimeout( function () {
			btn.textContent = orig;
		}, 2000 );
	}

	document.querySelectorAll( '.mw-local-ai-copy' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var code = btn.parentNode.querySelector( 'code' );
			if ( ! code ) {
				return;
			}
			copyText( code.textContent )
				.then( function () {
					flashLabel( btn, copiedLabel );
				} )
				.catch( function () {
					flashLabel( btn, failedLabel );
				} );
		} );
	} );
} )();
