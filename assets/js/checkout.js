/* global admbikeCheckout */
( function( $, window ) {
	'use strict';

	var config = window.admbikeCheckout || {};
	var restUrl = typeof config.restUrl === 'string' ? config.restUrl : '';
	var nonce = typeof config.nonce === 'string' ? config.nonce : '';
	var i18n = config.i18n || {};
	var states = [];
	var municipalities = [];
	var postcodes = [];
	var refreshTimer = null;

	function parseLocationData() {
		var raw = document.getElementById( 'admbike-location-data' );
		var data;

		if ( ! raw ) {
			return;
		}

		try {
			data = JSON.parse( raw.textContent || '{}' );
		} catch ( error ) {
			return;
		}

		states = Array.isArray( data.states ) ? data.states : [];
		municipalities = Array.isArray( data.municipalities ) ? data.municipalities : [];
		postcodes = Array.isArray( data.postcodes ) ? data.postcodes : [];
	}

	function getField( selector ) {
		return $( selector ).first();
	}

	function setFieldValue( $field, value ) {
		if ( ! $field.length ) {
			return;
		}

		if ( String( $field.val() || '' ) === String( value || '' ) ) {
			return;
		}

		$field.val( value ).trigger( 'change' );
	}

	function scheduleCheckoutRefresh() {
		if ( refreshTimer ) {
			window.clearTimeout( refreshTimer );
		}

		refreshTimer = window.setTimeout(
			function() {
				$( document.body ).trigger( 'update_checkout' );
			},
			120
		);
	}

	function replaceWooShippingMessage( root ) {
		var message = ( i18n.noCoverage || '' ).trim();
		var walker;
		var node;
		var phrases;

		if ( ! message || ! root ) {
			return;
		}

		phrases = [
			'No shipping options are available for this address. Please verify the address is correct or try a different address.',
			'No shipping options are available for this address.',
			'No shipping options were found for this address. Please verify the address is correct or try a different address.',
			'No shipping options were found for this address.',
			'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.',
			'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.'
		];

		walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT, null, false );
		while ( ( node = walker.nextNode() ) ) {
			var text = String( node.nodeValue || '' );
			for ( var i = 0; i < phrases.length; i += 1 ) {
				if ( text.indexOf( phrases[ i ] ) !== -1 ) {
					node.nodeValue = text.replace( phrases[ i ], message );
					break;
				}
			}
		}
	}

	function persistCheckoutLocation( payload ) {
		if ( ! restUrl || ! window.fetch ) {
			return;
		}

		window.fetch(
			restUrl + 'checkout-location',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-ADMBIKE-NONCE': nonce
				},
				credentials: 'same-origin',
				body: JSON.stringify( payload )
			}
		).catch(
			function() {
				return null;
			}
		);
	}

	function getStateById( stateId ) {
		var target = parseInt( stateId, 10 ) || 0;

		return states.find(
			function( state ) {
				return ( parseInt( state.id, 10 ) || 0 ) === target;
			}
		) || null;
	}

	function getStateByCode( stateCode ) {
		var target = String( stateCode || '' ).trim().toUpperCase();

		return states.find(
			function( state ) {
				return String( state.code || '' ).trim().toUpperCase() === target;
			}
		) || null;
	}

	function getMunicipalitiesByStateId( stateId ) {
		var target = parseInt( stateId, 10 ) || 0;

		return municipalities.filter(
			function( municipality ) {
				return ( parseInt( municipality.state_id, 10 ) || 0 ) === target;
			}
		);
	}

	function getPostcodesByMunicipalityId( municipalityId ) {
		var target = parseInt( municipalityId, 10 ) || 0;

		return postcodes.filter(
			function( postcode ) {
				return ( parseInt( postcode.municipality_id, 10 ) || 0 ) === target;
			}
		);
	}

	function toggleFieldRow( $field, isVisible ) {
		var $row = $field.closest( '.form-row' );

		if ( ! $row.length ) {
			return;
		}

		$row.toggleClass( 'admbike-hidden', ! isVisible );
	}

	function fillSelect( $select, items, placeholder, valueKey, labelKey, selectedValue ) {
		$select.empty();
		$select.append( $( '<option />' ).val( '' ).text( placeholder || '' ) );

		items.forEach(
			function( item ) {
				$select.append(
					$( '<option />' )
						.val( item[ valueKey ] || '' )
						.text( item[ labelKey ] || '' )
				);
			}
		);

		$select.val( selectedValue || '' );
	}

	function syncNativeCheckoutFields() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var $municipality = getField( '#orpot_woo_locations_municipality_id' );
		var $postcode = getField( '#orpot_woo_locations_postcode_select' );
		var state = getStateById( $state.val() );
		var cityName = $municipality.find( 'option:selected' ).text() || '';

		if ( ! $municipality.val() ) {
			cityName = '';
		}

		setFieldValue( getField( '#billing_country' ), 'MX' );
		setFieldValue( getField( '#shipping_country' ), 'MX' );
		setFieldValue( getField( '#billing_state' ), state ? state.code : '' );
		setFieldValue( getField( '#shipping_state' ), state ? state.code : '' );
		setFieldValue( getField( '#billing_city' ), cityName );
		setFieldValue( getField( '#shipping_city' ), cityName );
		setFieldValue( getField( '#billing_postcode' ), $postcode.val() || '' );
		setFieldValue( getField( '#shipping_postcode' ), $postcode.val() || '' );
	}

	function populateStateSelect() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var nativeState = getField( '#billing_state' ).val() || getField( '#shipping_state' ).val() || '';
		var selectedState = $state.val() || '';
		var matchedState = selectedState ? getStateById( selectedState ) : getStateByCode( nativeState );

		if ( ! $state.length ) {
			return;
		}

		fillSelect( $state, states, i18n.selectState, 'id', 'name', matchedState ? String( matchedState.id ) : '' );
		toggleFieldRow( $state, true );
	}

	function populateMunicipalitySelect() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var $municipality = getField( '#orpot_woo_locations_municipality_id' );
		var nativeCity = getField( '#billing_city' ).val() || getField( '#shipping_city' ).val() || '';
		var selectedValue = $municipality.val() || '';
		var items = getMunicipalitiesByStateId( $state.val() );

		if ( ! $municipality.length ) {
			return;
		}

		if ( ! selectedValue && nativeCity ) {
			items.some(
				function( municipality ) {
					if ( String( municipality.name ) === String( nativeCity ) ) {
						selectedValue = String( municipality.id );
						return true;
					}
					return false;
				}
			);
		}

		fillSelect( $municipality, items, i18n.selectMunicipality, 'id', 'name', selectedValue );
		toggleFieldRow( $municipality, items.length > 0 );
	}

	function populatePostcodeSelect() {
		var $municipality = getField( '#orpot_woo_locations_municipality_id' );
		var $postcode = getField( '#orpot_woo_locations_postcode_select' );
		var nativePostcode = getField( '#billing_postcode' ).val() || getField( '#shipping_postcode' ).val() || '';
		var selectedValue = $postcode.val() || nativePostcode;
		var items = getPostcodesByMunicipalityId( $municipality.val() );

		if ( ! $postcode.length ) {
			return;
		}

		fillSelect( $postcode, items, i18n.selectPostcode, 'postcode', 'postcode', selectedValue );
		toggleFieldRow( $postcode, items.length > 0 );
	}

	function handleStateChange() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var state = getStateById( $state.val() );

		populateMunicipalitySelect();
		getField( '#orpot_woo_locations_municipality_id' ).val( '' );
		populatePostcodeSelect();
		getField( '#orpot_woo_locations_postcode_select' ).val( '' );
		setFieldValue( getField( '#billing_city' ), '' );
		setFieldValue( getField( '#shipping_city' ), '' );
		setFieldValue( getField( '#billing_postcode' ), '' );
		setFieldValue( getField( '#shipping_postcode' ), '' );
		setFieldValue( getField( '#billing_state' ), state ? state.code : '' );
		setFieldValue( getField( '#shipping_state' ), state ? state.code : '' );

		persistCheckoutLocation(
			{
				country: 'MX',
				state: state ? state.code : '',
				state_id: parseInt( $state.val(), 10 ) || 0,
				municipality_id: 0,
				city: '',
				postcode: ''
			}
		);

		scheduleCheckoutRefresh();
	}

	function handleNativeStateChange( event ) {
		var nativeState = event && event.target ? String( $( event.target ).val() || '' ) : '';

		if ( ! nativeState ) {
			nativeState = getField( '#billing_state' ).val() || getField( '#shipping_state' ).val() || '';
		}

		var matchedState = nativeState ? getStateByCode( nativeState ) : null;
		var $state = getField( '#orpot_woo_locations_state_id' );

		if ( $state.length && matchedState && String( $state.val() || '' ) !== String( matchedState.id ) ) {
			$state.val( String( matchedState.id ) ).trigger( 'change' );
			return;
		}

		handleStateChange();
	}

	function handleMunicipalityChange() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var $municipality = getField( '#orpot_woo_locations_municipality_id' );
		var state = getStateById( $state.val() );
		var cityName = $municipality.find( 'option:selected' ).text() || '';

		if ( ! $municipality.val() ) {
			cityName = '';
		}

		populatePostcodeSelect();
		getField( '#orpot_woo_locations_postcode_select' ).val( '' );
		setFieldValue( getField( '#billing_city' ), cityName );
		setFieldValue( getField( '#shipping_city' ), cityName );
		setFieldValue( getField( '#billing_postcode' ), '' );
		setFieldValue( getField( '#shipping_postcode' ), '' );

		persistCheckoutLocation(
			{
				country: 'MX',
				state: state ? state.code : '',
				state_id: parseInt( $state.val(), 10 ) || 0,
				municipality_id: parseInt( $municipality.val(), 10 ) || 0,
				city: cityName,
				postcode: ''
			}
		);

		scheduleCheckoutRefresh();
	}

	function handlePostcodeChange() {
		var $state = getField( '#orpot_woo_locations_state_id' );
		var $municipality = getField( '#orpot_woo_locations_municipality_id' );
		var $postcode = getField( '#orpot_woo_locations_postcode_select' );
		var state = getStateById( $state.val() );
		var cityName = $municipality.find( 'option:selected' ).text() || '';

		if ( ! $municipality.val() ) {
			cityName = '';
		}

		syncNativeCheckoutFields();
		persistCheckoutLocation(
			{
				country: 'MX',
				state: state ? state.code : '',
				state_id: parseInt( $state.val(), 10 ) || 0,
				municipality_id: parseInt( $municipality.val(), 10 ) || 0,
				city: cityName,
				postcode: String( $postcode.val() || '' )
			}
		);

		scheduleCheckoutRefresh();
	}

	function bindEvents() {
		getField( '#orpot_woo_locations_state_id' ).on( 'change', handleStateChange );
		getField( '#billing_state' ).on( 'change', handleNativeStateChange );
		getField( '#shipping_state' ).on( 'change', handleNativeStateChange );
		getField( '#orpot_woo_locations_municipality_id' ).on( 'change', handleMunicipalityChange );
		getField( '#orpot_woo_locations_postcode_select' ).on( 'change', handlePostcodeChange );
	}

	function observeShippingMessages() {
		var observer;

		replaceWooShippingMessage( document.body );

		if ( ! window.MutationObserver ) {
			return;
		}

		observer = new MutationObserver( function() {
			replaceWooShippingMessage( document.body );
		} );

		observer.observe( document.body, { childList: true, subtree: true, characterData: true } );
	}

	function init() {
		if ( ! getField( '#orpot_woo_locations_state_id' ).length ) {
			return;
		}

		parseLocationData();
		populateStateSelect();
		populateMunicipalitySelect();
		populatePostcodeSelect();
		syncNativeCheckoutFields();
		bindEvents();
		observeShippingMessages();
	}

	$( init );
}( jQuery, window ) );
