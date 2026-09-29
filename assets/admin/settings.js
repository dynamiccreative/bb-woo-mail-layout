/**
 * BB Woo Mail Layout — réglages : sous-onglets, champs dépendants, aperçu en direct, e-mail de test, import.
 * Vanilla JS ; wp.media pour le sélecteur de logo, jQuery seulement pour écouter selectWoo et TinyMCE.
 */
( function () {
	'use strict';

	const root = document.getElementById( 'bb-wml' );
	if ( ! root ) {
		return;
	}

	const cfg = window.bbWml || { i18n: {}, defaults: {} };
	const i18n = cfg.i18n || {};
	const $ = ( selector, context ) => ( context || root ).querySelector( selector );
	const $$ = ( selector, context ) => Array.from( ( context || root ).querySelectorAll( selector ) );
	const form = root.closest( 'form' );
	const jq = window.jQuery;
	const fieldName = ( key ) => cfg.option + '[' + key + ']';
	const field = ( key ) => form.elements.namedItem( fieldName( key ) );
	const sprintf = ( text, ...args ) => {
		let i = 0;
		return String( text )
			.replace( /%(\d)\$[sd]/g, ( m, n ) => args[ n - 1 ] )
			.replace( /%[sd]/g, () => args[ i++ ] );
	};

	const setStatus = ( el, message, type ) => {
		if ( ! el ) {
			return;
		}
		el.textContent = message || '';
		el.className = 'bb-wml-status' + ( type ? ' is-' + type : '' );
	};

	const post = ( action, data, body, signal ) => {
		body = body || new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( ( key ) => body.append( key, data[ key ] ) );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal } );
	};

	// État courant du formulaire (non enregistré) : utilisé par l'aperçu et l'e-mail de test.
	const draft = () => {
		if ( window.tinyMCE ) {
			window.tinyMCE.triggerSave();
		}
		const body = new FormData();
		new FormData( form ).forEach( ( value, key ) => {
			if ( 0 === key.indexOf( cfg.option + '[' ) ) {
				body.append( key, value );
			}
		} );
		body.append( 'draft', '1' );
		return body;
	};

	// Entrée dans un champ sans name (recherche, outils) ne doit pas soumettre le formulaire WooCommerce.
	root.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key && 'INPUT' === event.target.tagName && ! event.target.name ) {
			event.preventDefault();
		}
	} );

	/* ---- Sous-onglets ---- */

	const work = $( '.bb-wml-work' );
	const tabs = $$( '.bb-wml-tab' );

	const openTab = ( name ) => {
		if ( ! $( '#bb-wml-panel-' + name ) ) {
			return;
		}
		tabs.forEach( ( tab ) => tab.setAttribute( 'aria-selected', String( tab.dataset.bbTab === name ) ) );
		$$( '.bb-wml-panel' ).forEach( ( panel ) => {
			panel.hidden = panel.id !== 'bb-wml-panel-' + name;
		} );
		work.classList.toggle( 'is-wide', 'outils' === name );
		try {
			window.localStorage.setItem( 'bb-wml-tab', name );
		} catch ( e ) {}
	};

	tabs.forEach( ( tab ) => tab.addEventListener( 'click', () => openTab( tab.dataset.bbTab ) ) );
	$( '.bb-wml-tabs' ).addEventListener( 'keydown', ( event ) => {
		if ( 'ArrowRight' !== event.key && 'ArrowLeft' !== event.key ) {
			return;
		}
		const current = tabs.findIndex( ( tab ) => 'true' === tab.getAttribute( 'aria-selected' ) );
		const next = tabs[ ( current + ( 'ArrowRight' === event.key ? 1 : -1 ) + tabs.length ) % tabs.length ];
		openTab( next.dataset.bbTab );
		next.focus();
	} );
	$$( '[data-bb-goto]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			openTab( button.dataset.bbGoto );
			$( '.bb-wml-tabs' ).scrollIntoView( { block: 'start' } );
		} )
	);
	try {
		openTab( window.localStorage.getItem( 'bb-wml-tab' ) || 'apparence' );
	} catch ( e ) {}

	/* ---- Champs dépendants, résumés ---- */

	const value = ( key ) => {
		const el = field( key );
		if ( ! el ) {
			return '';
		}
		if ( el instanceof window.RadioNodeList ) {
			return el.value;
		}
		return 'checkbox' === el.type ? ( el.checked ? 'yes' : '' ) : el.value;
	};

	const syncUi = () => {
		// « clé » (non vide), « clé=valeur » ou « clé!=valeur ».
		$$( '[data-bb-show-if]' ).forEach( ( el ) => {
			const [ , key, not, expected ] = el.dataset.bbShowIf.match( /^([^!=]+)(!?)=?(.*)$/ );
			const shown = '' === expected && ! el.dataset.bbShowIf.includes( '=' ) ? !! value( key ) : value( key ) === expected;
			el.hidden = not ? shown : ! shown;
		} );
		$$( '[data-bb-block]' ).forEach( ( block ) => block.classList.toggle( 'is-off', ! value( block.dataset.bbBlock ) ) );

		const layout = value( 'layout' );
		$$( '.bb-wml-layout' ).forEach( ( card ) => {
			const radio = $( 'input', card );
			card.classList.toggle( 'is-checked', radio.checked );
			if ( radio.checked ) {
				$( '#bb-wml-sum-layout' ).textContent = radio.dataset.label;
				$$( '[data-bb-general]' ).forEach( ( option ) => {
					option.textContent = sprintf( i18n.generalLayout, radio.dataset.label );
				} );
			}
		} );
		$$( '.bb-wml-thumb' ).forEach( ( thumb ) => thumb.style.setProperty( '--bb-thumb', value( 'color_primary' ) || '#1f4e79' ) );
		const background = $( '[data-bb-swatch="color_background"]' );
		if ( background ) {
			background.classList.toggle( 'is-muted', 'classique' !== layout );
		}

		const help = [ 'contact_phone', 'contact_email', 'contact_hours' ].filter( ( key ) => value( key ).trim() ).length;
		const summary = ( key, text ) => {
			const el = $( '[data-bb-summary="' + key + '"]' );
			if ( el ) {
				el.textContent = text;
			}
		};
		summary( 'show_help', help ? sprintf( i18n.helpFilled, help ) : i18n.helpEmpty );
		summary( 'show_social', sprintf( i18n.socialCount, $$( '[data-bb-social]' ).filter( ( el ) => el.value.trim() ).length ) );
		const products = $( '#bb-wml-featured-products' );
		summary(
			'show_featured',
			'manual' === value( 'featured_source' ) ? sprintf( i18n.featuredCount, products ? products.selectedOptions.length : 0 ) : i18n.featuredAuto
		);

		const toggles = $$( '[data-bb-mail-toggle]' );
		$( '#bb-wml-sum-mails' ).textContent = toggles.filter( ( el ) => el.checked ).length + ' / ' + toggles.length;
	};

	/* ---- Couleurs ---- */

	const HEX = /^#[0-9a-f]{6}$/i;

	$$( '[data-bb-swatch]' ).forEach( ( swatch ) => {
		const pick = $( '.bb-wml-swatch__pick', swatch );
		const hex = $( '.bb-wml-swatch__hex', swatch );
		const sync = () => swatch.classList.toggle( 'is-auto', '' === hex.value.trim() );
		pick.addEventListener( 'input', () => {
			hex.value = pick.value;
			sync();
			hex.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		hex.addEventListener( 'input', () => {
			const raw = hex.value.trim();
			const full = /^#?[0-9a-f]{3}$/i.test( raw ) ? '#' + raw.replace( '#', '' ).replace( /./g, '$&$&' ) : raw;
			if ( HEX.test( full ) ) {
				pick.value = full;
			}
			sync();
		} );
		sync();
	} );

	const resetColors = $( '#bb-wml-reset-colors' );
	if ( resetColors ) {
		resetColors.addEventListener( 'click', () => {
			Object.keys( cfg.defaults ).forEach( ( key ) => {
				const hex = field( key );
				if ( hex ) {
					hex.value = cfg.defaults[ key ];
					hex.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			} );
		} );
	}

	/* ---- Logo ---- */

	const media = $( '.bb-wml-media' );
	const logoUrl = $( '#bb-wml-logo-url' );
	const logoId = $( '#bb-wml-logo-id' );
	const logoPreview = $( '.bb-wml-media__preview' );
	const logoSize = $( '#bb-wml-logo-size' );

	const showLogoSize = () => {
		if ( ! logoSize ) {
			return;
		}
		const w = logoPreview.naturalWidth;
		const h = logoPreview.naturalHeight;
		if ( logoPreview.hidden || ! w || ! h ) {
			logoSize.textContent = '';
			return;
		}
		const scale = Math.min( ( +value( 'logo_max_width' ) || w ) / w, ( +value( 'logo_max_height' ) || h ) / h, 1 );
		logoSize.textContent = sprintf( i18n.logoSize, Math.round( w * scale ), Math.round( h * scale ) );
	};

	const showLogo = ( src ) => {
		logoPreview.src = src || '';
		logoPreview.hidden = ! src;
		$( '.bb-wml-media__empty' ).hidden = !! src;
		showLogoSize();
	};

	if ( media ) {
		let frame = null;
		logoPreview.addEventListener( 'load', showLogoSize );
		if ( logoPreview.complete ) {
			showLogoSize();
		}

		$( '.bb-wml-media__choose', media ).addEventListener( 'click', () => {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: i18n.chooseLogo,
					button: { text: i18n.useImage },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', () => {
					const attachment = frame.state().get( 'selection' ).first().toJSON();
					logoUrl.value = attachment.url;
					logoId.value = attachment.id;
					showLogo( attachment.url );
					logoUrl.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} );
			}
			frame.open();
		} );

		$( '.bb-wml-media__remove', media ).addEventListener( 'click', () => {
			logoUrl.value = '';
			logoId.value = '0';
			showLogo( '' );
			logoUrl.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

		logoUrl.addEventListener( 'change', () => {
			logoId.value = '0';
			showLogo( logoUrl.value );
		} );
	}

	/* ---- E-mails ---- */

	const mails = $$( '[data-bb-mail]' );
	let filter = 'all';
	let query = '';

	const applyFilter = () => {
		let visible = 0;
		mails.forEach( ( row ) => {
			const client = '1' === row.dataset.bbClient;
			const show = ( 'all' === filter || ( 'client' === filter ) === client ) && row.dataset.bbSearch.includes( query );
			row.hidden = ! show;
			visible += show ? 1 : 0;
		} );
		$( '#bb-wml-mails-empty' ).hidden = visible > 0;
	};

	const syncMail = ( row ) => {
		const on = $( '[data-bb-mail-toggle]', row ).checked;
		row.classList.toggle( 'is-off', ! on );
		$$( '[data-bb-email-select] option[value="' + row.dataset.bbMail + '"]' ).forEach( ( option ) => {
			option.textContent = option.dataset.title + ( on ? '' : ' — ' + i18n.nativeSuffix );
		} );
	};

	$$( '[data-bb-filter]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			filter = button.dataset.bbFilter;
			$$( '[data-bb-filter]' ).forEach( ( el ) => el.setAttribute( 'aria-pressed', String( el === button ) ) );
			applyFilter();
		} )
	);

	const search = $( '#bb-wml-mail-search' );
	if ( search ) {
		search.addEventListener( 'input', () => {
			query = search.value.trim().toLowerCase();
			applyFilter();
		} );
	}

	$$( '[data-bb-bulk]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			mails
				.filter( ( row ) => ! row.hidden )
				.forEach( ( row ) => {
					$( '[data-bb-mail-toggle]', row ).checked = 'on' === button.dataset.bbBulk;
					syncMail( row );
				} );
			form.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} )
	);

	mails.forEach( ( row ) => {
		const id = row.dataset.bbMail;
		const button = $( '.bb-wml-intro-btn', row );
		const box = $( '.bb-wml-mail__intro', row );
		// Intro, pré-en-tête, libellé et lien du bouton.
		const texts = $$( '[data-bb-intro]', row );

		$( '[data-bb-mail-toggle]', row ).addEventListener( 'change', () => syncMail( row ) );

		const layout = $( '[data-bb-mail-layout]', row );
		const syncLayout = () => layout.classList.toggle( 'is-custom', '' !== layout.value );
		layout.addEventListener( 'change', () => {
			syncLayout();
			showInPreview( id );
		} );
		syncLayout();
		button.addEventListener( 'click', () => {
			box.hidden = ! box.hidden;
			button.setAttribute( 'aria-expanded', String( ! box.hidden ) );
			if ( ! box.hidden ) {
				texts[ 0 ].focus();
				showInPreview( id );
			}
		} );
		texts.forEach( ( text ) =>
			text.addEventListener( 'input', () => {
				const custom = texts.some( ( el ) => '' !== el.value.trim() );
				button.classList.toggle( 'is-custom', custom );
				$( '.bb-wml-intro-btn__label', button ).textContent = custom ? i18n.introCustom : i18n.introDefault;
				showInPreview( id, true );
			} )
		);
	} );

	/* ---- Jetons et placeholders : insertion au curseur ---- */

	let lastIntro = null;
	root.addEventListener( 'focusin', ( event ) => {
		if ( event.target.matches( '[data-bb-intro]' ) ) {
			lastIntro = event.target;
		}
	} );

	const insert = ( target, text ) => {
		if ( ! target ) {
			return;
		}
		const start = target.selectionStart ?? target.value.length;
		const end = target.selectionEnd ?? start;
		target.value = target.value.slice( 0, start ) + text + target.value.slice( end );
		target.focus();
		target.setSelectionRange( start + text.length, start + text.length );
		target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	};

	$$( '.bb-wml-tokens' ).forEach( ( group ) =>
		group.addEventListener( 'mousedown', ( event ) => {
			const token = event.target.closest( '.bb-wml-token' );
			if ( ! token ) {
				return;
			}
			event.preventDefault(); // Garde le focus (et la sélection) dans le champ.
			const target = 'intro' === group.dataset.bbTarget ? lastIntro : document.getElementById( group.dataset.bbTarget );
			insert( target, token.textContent.trim() );
		} )
	);

	/* ---- Aperçu en direct ---- */

	const stage = $( '#bb-wml-stage' );
	const frame = $( '#bb-wml-preview-frame' );
	const previewStatus = $( '#bb-wml-preview-status' );
	const previewError = $( '#bb-wml-preview-error' );
	const previewEmail = $( '#bb-wml-preview-email' );
	const previewOrder = $( '#bb-wml-preview-order' );
	let controller = null;
	let timer = null;

	const setPreviewState = ( state, message ) => {
		previewStatus.className = 'bb-wml-live' + ( state ? ' is-' + state : '' );
		previewStatus.textContent = message;
		stage.classList.toggle( 'is-loading', 'loading' === state );
	};

	const refreshPreview = async () => {
		if ( work.classList.contains( 'is-wide' ) ) {
			return;
		}
		if ( controller ) {
			controller.abort();
		}
		controller = new window.AbortController();
		setPreviewState( 'loading', i18n.loading );
		try {
			const response = await post( 'bb_wml_preview', { email: previewEmail.value, order: previewOrder.value }, draft(), controller.signal );
			const html = await response.text();
			if ( ! response.ok ) {
				throw new Error( html.replace( /<[^>]+>/g, '' ) || response.statusText );
			}
			frame.srcdoc = html;
			frame.hidden = false;
			previewError.hidden = true;
			setPreviewState( '', i18n.live );
		} catch ( error ) {
			if ( 'AbortError' === error.name ) {
				return;
			}
			frame.hidden = true;
			previewError.textContent = error.message || i18n.error;
			previewError.hidden = false;
			setPreviewState( 'error', i18n.error );
		}
	};

	const schedulePreview = () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( refreshPreview, 600 );
	};

	function showInPreview( id, debounced ) {
		if ( previewEmail.value !== id ) {
			previewEmail.value = id;
			debounced = false;
		}
		if ( debounced ) {
			schedulePreview();
		} else {
			refreshPreview();
		}
	}

	previewEmail.addEventListener( 'change', refreshPreview );
	previewOrder.addEventListener( 'change', refreshPreview );
	$$( '[data-bb-device]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			$$( '[data-bb-device]' ).forEach( ( el ) => el.setAttribute( 'aria-pressed', String( el === button ) ) );
			stage.classList.toggle( 'is-mobile', 'mobile' === button.dataset.bbDevice );
		} )
	);
	tabs.forEach( ( tab ) => tab.addEventListener( 'click', () => 'outils' !== tab.dataset.bbTab && ! frame.srcdoc && refreshPreview() ) );

	/* ---- Modifications : état, aperçu ---- */

	const dirty = $( '#bb-wml-dirty' );
	const onChange = ( event ) => {
		const target = event && event.target;
		// Les champs sans name (recherche, outils, sélecteurs d'aperçu) ne sont pas des réglages.
		if ( target && target !== form && target.name !== undefined && 0 !== String( target.name ).indexOf( cfg.option + '[' ) ) {
			return;
		}
		dirty.hidden = false;
		syncUi();
		schedulePreview();
	};

	form.addEventListener( 'input', onChange );
	form.addEventListener( 'change', onChange );
	if ( jq ) {
		// selectWoo (produits) déclenche un « change » jQuery, invisible pour addEventListener.
		jq( '#bb-wml-featured-products' ).on( 'change', () => onChange() );
		// Éditeur des mentions (TinyMCE).
		jq( document ).on( 'tinymce-editor-init', ( event, editor ) => {
			if ( editor && 'bb_wml_footer_text' === editor.id ) {
				editor.on( 'input change undo redo', () => onChange() );
			}
		} );
	}

	/* ---- E-mail de test ---- */

	const sendTest = $( '#bb-wml-send-test' );
	if ( sendTest ) {
		const status = $( '#bb-wml-tools-status' );
		sendTest.addEventListener( 'click', async () => {
			setStatus( status, i18n.sending );
			sendTest.disabled = true;
			try {
				const response = await post(
					'bb_wml_send_test',
					{ email: $( '#bb-wml-tool-email' ).value, order: $( '#bb-wml-tool-order' ).value, to: $( '#bb-wml-tool-to' ).value },
					draft()
				);
				const json = await response.json();
				const message = json && json.data && json.data.message ? json.data.message : i18n.error;
				setStatus( status, message, json && json.success ? 'success' : 'error' );
			} catch ( error ) {
				setStatus( status, error.message || i18n.error, 'error' );
			} finally {
				sendTest.disabled = false;
			}
		} );
	}

	/* ---- Import (confirmation dans la page) ---- */

	const importButton = $( '#bb-wml-import' );
	if ( importButton ) {
		const status = $( '#bb-wml-import-status' );
		const confirmBox = $( '#bb-wml-import-confirm' );
		const file = () => $( '#bb-wml-import-file' ).files[ 0 ];

		importButton.addEventListener( 'click', () => {
			if ( ! file() ) {
				setStatus( status, i18n.chooseFile, 'error' );
				return;
			}
			setStatus( status, '' );
			confirmBox.hidden = false;
		} );
		$( '#bb-wml-import-no' ).addEventListener( 'click', () => {
			confirmBox.hidden = true;
		} );
		$( '#bb-wml-import-yes' ).addEventListener( 'click', async () => {
			confirmBox.hidden = true;
			setStatus( status, i18n.importing );
			try {
				const response = await post( 'bb_wml_import', { payload: await file().text() } );
				const json = await response.json();
				const message = json && json.data && json.data.message ? json.data.message : i18n.error;
				setStatus( status, message, json && json.success ? 'success' : 'error' );
				if ( json && json.success ) {
					window.onbeforeunload = null;
					window.location.reload();
				}
			} catch ( error ) {
				setStatus( status, error.message || i18n.error, 'error' );
			}
		} );
	}

	syncUi();
	refreshPreview();
}() );
