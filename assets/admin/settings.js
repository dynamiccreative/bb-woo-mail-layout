/**
 * BB Woo Mail Layout — réglages : médiathèque, aperçu, e-mail de test, import.
 * Vanilla JS ; wp.media pour le sélecteur de logo.
 */
( function () {
	'use strict';

	const cfg = window.bbWml || { i18n: {} };
	const $ = ( selector, context ) => ( context || document ).querySelector( selector );

	const setStatus = ( el, message, type ) => {
		if ( ! el ) {
			return;
		}
		el.textContent = message || '';
		el.className = 'bb-wml-status' + ( type ? ' is-' + type : '' );
	};

	const post = ( action, data ) => {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( ( key ) => body.append( key, data[ key ] ) );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
	};

	/* ---- Sélecteur de logo ---- */

	const media = $( '.bb-wml-media' );
	if ( media ) {
		const url = $( '#bb-wml-logo-url', media );
		const id = $( '#bb-wml-logo-id', media );
		const preview = $( '.bb-wml-media__preview', media );
		let frame = null;

		const show = ( src ) => {
			preview.src = src || '';
			preview.hidden = ! src;
		};

		$( '.bb-wml-media__choose', media ).addEventListener( 'click', () => {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: cfg.i18n.chooseLogo,
					button: { text: cfg.i18n.useImage },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', () => {
					const attachment = frame.state().get( 'selection' ).first().toJSON();
					url.value = attachment.url;
					id.value = attachment.id;
					show( attachment.url );
				} );
			}
			frame.open();
		} );

		$( '.bb-wml-media__remove', media ).addEventListener( 'click', () => {
			url.value = '';
			id.value = '0';
			show( '' );
		} );

		url.addEventListener( 'change', () => {
			id.value = '0';
			show( url.value );
		} );
	}

	/* ---- Aperçu et e-mail de test ---- */

	const tools = $( '.bb-wml-tools' );
	if ( tools ) {
		const status = $( '#bb-wml-tools-status' );
		const frame = $( '#bb-wml-preview-frame' );
		const selection = () => ( {
			email: $( '#bb-wml-tool-email' ).value,
			order: $( '#bb-wml-tool-order' ).value,
		} );

		// Entrée dans ces champs ne doit pas soumettre le formulaire WooCommerce.
		tools.querySelectorAll( 'input, select' ).forEach( ( field ) => {
			field.addEventListener( 'keydown', ( event ) => {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );
		} );

		$( '#bb-wml-preview' ).addEventListener( 'click', async () => {
			setStatus( status, cfg.i18n.loading );
			try {
				const response = await post( 'bb_wml_preview', selection() );
				const html = await response.text();
				if ( ! response.ok ) {
					throw new Error( html || response.statusText );
				}
				frame.srcdoc = html;
				frame.hidden = false;
				setStatus( status, '' );
			} catch ( error ) {
				setStatus( status, error.message || cfg.i18n.error, 'error' );
			}
		} );

		$( '#bb-wml-send-test' ).addEventListener( 'click', async () => {
			setStatus( status, cfg.i18n.sending );
			try {
				const data = Object.assign( selection(), { to: $( '#bb-wml-tool-to' ).value } );
				const response = await post( 'bb_wml_send_test', data );
				const json = await response.json();
				const message = json && json.data && json.data.message ? json.data.message : cfg.i18n.error;
				setStatus( status, message, json && json.success ? 'success' : 'error' );
			} catch ( error ) {
				setStatus( status, error.message || cfg.i18n.error, 'error' );
			}
		} );
	}

	/* ---- Import ---- */

	const importButton = $( '#bb-wml-import' );
	if ( importButton ) {
		const status = $( '#bb-wml-import-status' );

		importButton.addEventListener( 'click', async () => {
			const file = $( '#bb-wml-import-file' ).files[ 0 ];
			if ( ! file ) {
				setStatus( status, cfg.i18n.chooseFile, 'error' );
				return;
			}
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( cfg.i18n.confirmImport ) ) {
				return;
			}
			setStatus( status, cfg.i18n.importing );
			try {
				const response = await post( 'bb_wml_import', { payload: await file.text() } );
				const json = await response.json();
				const message = json && json.data && json.data.message ? json.data.message : cfg.i18n.error;
				setStatus( status, message, json && json.success ? 'success' : 'error' );
				if ( json && json.success ) {
					window.location.reload();
				}
			} catch ( error ) {
				setStatus( status, error.message || cfg.i18n.error, 'error' );
			}
		} );
	}
}() );
