( function () {
	'use strict';
	if ( ! window.wc || ! window.wc.wcSettings || ! window.wc.wcBlocksRegistry ) {
		return;
	}
	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = window.wc.wcSettings.getSetting;
	var decodeEntities = window.wp && window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities
		? window.wp.htmlEntities.decodeEntities
		: function ( s ) { return s; };
	var el = window.wp && window.wp.element;
	if ( ! el || ! el.createElement || ! el.useEffect ) {
		return;
	}
	var ce = el.createElement;
	var useEffect = el.useEffect;

	if ( ! document.getElementById( 'gtaw-fleeca-blocks-base-css' ) ) {
		var style = document.createElement( 'style' );
		style.id = 'gtaw-fleeca-blocks-base-css';
		style.textContent =
			'.gtaw-fleeca-blocks__label{display:inline-flex;align-items:center;gap:0.5rem;flex-wrap:wrap;max-width:100%}' +
			'.gtaw-fleeca-blocks__logo{display:block;max-height:28px;max-width:100px;width:auto;height:auto;object-fit:contain;flex-shrink:0}' +
			'.gtaw-fleeca-blocks--edit .gtaw-fleeca-blocks__label .gtaw-fleeca-blocks__logo{max-height:24px;max-width:88px}';
		document.head.appendChild( style );
	}

	var name = 'fleeca';
	var defaults = { title: 'Fleeca Bank', description: '', enabled: true, logo_url: '' };
	var settings = getSetting( name + '_data', defaults ) || defaults;

	var logoUrl = settings.logo_url ? String( settings.logo_url ).trim() : '';

	var Label = function ( props ) {
		var t = settings.title ? String( settings.title ) : 'Fleeca Bank';
		var PL = props && props.components && props.components.PaymentMethodLabel;
		var textNode = PL ? ce( PL, { text: decodeEntities( t ) } ) : ce( 'span', null, decodeEntities( t ) );
		if ( ! logoUrl ) {
			return textNode;
		}
		return ce(
			'span',
			{ className: 'gtaw-fleeca-blocks__label' },
			ce( 'img', {
				src: logoUrl,
				alt: '',
				className: 'gtaw-fleeca-blocks__logo',
				loading: 'lazy',
				decoding: 'async',
				draggable: false
			} ),
			textNode
		);
	};

	var fallbackText = 'Pay securely with your Fleeca Bank account. You will be redirected to complete payment.';

	var FleecaContent = function ( props ) {
		var onPaymentSetup = props && props.eventRegistration && props.eventRegistration.onPaymentSetup;
		var responseTypes = props && props.emitResponse && props.emitResponse.responseTypes;

		useEffect(
			function () {
				if ( ! onPaymentSetup || ! responseTypes ) {
					return undefined;
				}
				var unsub = onPaymentSetup( function () {
					return {
						type: responseTypes.SUCCESS,
						meta: {
							paymentMethodData: { payment_method: name }
						}
					};
				} );
				return function () {
					if ( typeof unsub === 'function' ) {
						unsub();
					}
				};
			},
			[ onPaymentSetup, responseTypes ]
		);

		var d = settings.description ? String( settings.description ).trim() : '';
		var text = d ? decodeEntities( d ) : fallbackText;
		return ce( 'div', { className: 'gtaw-fleeca-blocks' }, ce( 'p', { className: 'gtaw-fleeca-blocks__text' }, text ) );
	};

	var FleecaEdit = function () {
		var d = settings.description ? String( settings.description ).trim() : '';
		var text = d ? decodeEntities( d ) : fallbackText;
		var p = ce( 'p', { className: 'gtaw-fleeca-blocks__text' }, text );
		if ( ! logoUrl ) {
			return ce( 'div', { className: 'gtaw-fleeca-blocks gtaw-fleeca-blocks--edit' }, p );
		}
		return ce(
			'div',
			{ className: 'gtaw-fleeca-blocks gtaw-fleeca-blocks--edit' },
			ce(
				'div',
				{ className: 'gtaw-fleeca-blocks__label' },
				ce( 'img', {
					src: logoUrl,
					alt: '',
					className: 'gtaw-fleeca-blocks__logo',
					loading: 'lazy',
					decoding: 'async',
					draggable: false
				} ),
				p
			)
		);
	};

	var canMakePayment = function ( args ) {
		if ( settings.enabled === false ) {
			return false;
		}
		var ct = args && args.cartTotals;
		if ( ct && ct.currency_code && ct.currency_code !== 'USD' ) {
			return false;
		}
		return true;
	};

	registerPaymentMethod( {
		name: name,
		label: ce( Label, null ),
		content: ce( FleecaContent, null ),
		edit: ce( FleecaEdit, null ),
		canMakePayment: canMakePayment,
		ariaLabel: settings.title ? String( settings.title ) : 'Fleeca Bank',
		supports: { features: ( settings.supports && settings.supports.length ) ? settings.supports : [ 'products' ] }
	} );
} )();
