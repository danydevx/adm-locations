/**
 * ADM Bike Woo Locations - Checkout dependent selectors.
 *
 * @package ADMBike_Woo_Locations
 */

(function ( $ ) {
	'use strict';

	var config = window.admbikeCheckout || {};
	var locationData = null;
	var isLoading = false;

	// ─── Data Loading ────────────────────────────────────────────────────────────

	function loadLocationData( callback ) {
		if ( locationData ) {
			callback( locationData );
			return;
		}

		var $script = $( '#admbike-location-data' );
		if ( $script.length ) {
			try {
				locationData = JSON.parse( $script.text() );
				callback( locationData );
				return;
			} catch ( e ) {
				// Fall through to REST API.
			}
		}

		$.ajax( {
			url: config.restUrl + 'states',
			type: 'GET',
			dataType: 'json',
			cache: true,
			success: function ( states ) {
				locationData = { states: states, municipalities: [], postcodes: [] };

				$.ajax( {
					url: config.restUrl + 'municipalities',
					type: 'GET',
					dataType: 'json',
					cache: true,
					success: function ( municipalities ) {
						locationData.municipalities = municipalities;

						$.ajax( {
							url: config.restUrl + 'postcodes',
							type: 'GET',
							dataType: 'json',
							cache: true,
							success: function ( postcodes ) {
								locationData.postcodes = postcodes;
								callback( locationData );
							},
							error: function () {
								callback( null );
							}
						} );
					},
					error: function () {
						callback( null );
					}
				} );
			},
			error: function () {
				callback( null );
			}
		} );
	}

	// ─── Coverage Check ────────────────────────────────────────────────────────

	function checkCoverage( postcode, $container ) {
		$.ajax( {
			url: config.restUrl + 'coverage',
			type: 'GET',
			data: { postcode: postcode },
			dataType: 'json',
			success: function ( response ) {
				isLoading = false;
				hideOverlay();

				if ( ! response.available ) {
					$container
						.removeClass( 'admbike-coverage-ok admike-coverage-paid' )
						.addClass( 'admbike-coverage-unavailable' )
						.html(
							'<p class="admbike-coverage-message admike-coverage-unavailable">' +
							'<span class="dashicons dashicons-dismiss"></span> ' +
							( response.message || config.i18n.noCoverage ) +
							'</p>'
						)
						.show();

					// Block order review.
					$( '#place_order' ).prop( 'disabled', true ).addClass( 'disabled' );
				} else {
					var msgClass = 'free' === response.rule_type ? 'admbike-coverage-ok' : 'admbike-coverage-paid';
					var icon = 'free' === response.rule_type ? 'dashicons-yes-alt' : 'dashicons-money-alt';

					$container
						.removeClass( 'admbike-coverage-unavailable' )
						.addClass( msgClass )
						.html(
							'<p class="admbike-coverage-message ' + msgClass + '">' +
							'<span class="dashicons ' + icon + '"></span> ' +
							response.message +
							'</p>'
						)
						.show();

					$( '#place_order' ).prop( 'disabled', false ).removeClass( 'disabled' );
				}
			},
			error: function () {
				isLoading = false;
				hideOverlay();
				$container
					.removeClass( 'admbike-coverage-ok admbile-coverage-paid' )
					.addClass( 'admbike-coverage-unavailable' )
					.html(
						'<p class="admbike-coverage-message admike-coverage-unavailable">' +
						config.i18n.noCoverage +
						'</p>'
					)
					.show();
			}
		} );
	}

	// ─── Dropdown Helpers ──────────────────────────────────────────────────────

	function populateStates( $select, data ) {
		$select.find( 'option:not([value=""])' ).remove();
		if ( ! data || ! data.states || ! data.states.length ) {
			return;
		}
		$.each( data.states, function ( i, state ) {
			$select.append(
				$( '<option>', {
					value: state.id,
					text: state.name + ' (' + state.code + ')'
				} )
			);
		} );
	}

	function filterMunicipalities( data, stateId ) {
		if ( ! data || ! data.municipalities ) {
			return [];
		}
		return data.municipalities.filter( function ( m ) {
			return parseInt( m.state_id, 10 ) === parseInt( stateId, 10 );
		} );
	}

	function filterPostcodes( data, municipalityId ) {
		if ( ! data || ! data.postcodes ) {
			return [];
		}
		return data.postcodes.filter( function ( p ) {
			return parseInt( p.municipality_id, 10 ) === parseInt( municipalityId, 10 );
		} );
	}

	function populateSelect( $select, items, valueKey, labelKey, placeholder ) {
		$select.find( 'option:not([value=""])' ).remove();
		if ( placeholder ) {
			$select.append(
				$( '<option>', { value: '', text: placeholder, disabled: true, selected: true } )
			);
		}
		$.each( items, function ( i, item ) {
			$select.append(
				$( '<option>', {
					value: item[ valueKey ],
					text: item[ labelKey ]
				} )
			);
		} );
	}

	// ─── Overlay ──────────────────────────────────────────────────────────────

	function showOverlay() {
		var $overlay = $( '#admbike-checkout-overlay' );
		if ( $overlay.length ) {
			$overlay.show();
		}
		isLoading = true;
	}

	function hideOverlay() {
		var $overlay = $( '#admbike-checkout-overlay' );
		if ( $overlay.length ) {
			$overlay.hide();
		}
		isLoading = false;
	}

	// ─── Init ─────────────────────────────────────────────────────────────────

	function init() {
		var $stateSelect       = $( '#admbike_state_id' );
		var $municipalitySelect = $( '#admbike_municipality_id' );
		var $postcodeSelect    = $( '#admbike_postcode_select' );
		var $coverageInfo      = $( '#admbike_coverage_info' );
		var $coverageContainer = $( '.admbike-coverage-info' );

		if ( ! $stateSelect.length ) {
			return;
		}

		// Show our custom fields and hide native ones.
		$( '#billing_postcode_field' ).hide().find( 'input' ).removeClass( 'validate-required' );
		$( '#billing_city_field' ).hide().find( 'input' ).removeClass( 'validate-required' );

		loadLocationData( function ( data ) {
			if ( ! data ) {
				// Graceful degradation — show a notice.
				$( '#admbike_state_id_field' ).prepend(
					'<div class="admbike-error">' + config.i18n.loading + '</div>'
				);
				return;
			}

			populateStates( $stateSelect, data );

			$stateSelect.on( 'change', function () {
				var stateId = $( this ).val();

				$municipalitySelect.find( 'option:not([value=""])' ).remove();
				$municipalitySelect.val( '' ).trigger( 'change' );
				$postcodeSelect.find( 'option:not([value=""])' ).remove();
				$postcodeSelect.val( '' ).trigger( 'change' );
				$coverageContainer.hide();

				if ( ! stateId ) {
					$municipalitySelect.closest( '.form-row' ).addClass( 'admbike-hidden' );
					$postcodeSelect.closest( '.form-row' ).addClass( 'admbike-hidden' );
					return;
				}

				var filteredMunis = filterMunicipalities( data, stateId );

				if ( filteredMunis.length === 0 ) {
					$municipalitySelect
						.closest( '.form-row' )
						.removeClass( 'admbike-hidden' )
						.find( 'select' )
						.html(
							$( '<option>', {
								value: '',
								text: config.i18n.selectMunicipality,
								disabled: true,
								selected: true
							} )
						);
					$postcodeSelect.closest( '.form-row' ).addClass( 'admbike-hidden' );
					return;
				}

				populateSelect(
					$municipalitySelect,
					filteredMunis,
					'id',
					'name',
					config.i18n.selectMunicipality
				);
				$municipalitySelect.closest( '.form-row' ).removeClass( 'admbike-hidden' );
				$postcodeSelect.closest( '.form-row' ).addClass( 'admbike-hidden' );
			} );

			$municipalitySelect.on( 'change', function () {
				var municipalityId = $( this ).val();

				$postcodeSelect.find( 'option:not([value=""])' ).remove();
				$postcodeSelect.val( '' ).trigger( 'change' );
				$coverageContainer.hide();

				if ( ! municipalityId ) {
					$postcodeSelect.closest( '.form-row' ).addClass( 'admbike-hidden' );
					return;
				}

				var filteredPCs = filterPostcodes( data, municipalityId );

				if ( filteredPCs.length === 0 ) {
					$postcodeSelect
						.closest( '.form-row' )
						.removeClass( 'admbike-hidden' )
						.find( 'select' )
						.html(
							$( '<option>', {
								value: '',
								text: config.i18n.selectPostcode,
								disabled: true,
								selected: true
							} )
						);
					return;
				}

				populateSelect(
					$postcodeSelect,
					filteredPCs,
					'postcode',
					'postcode',
					config.i18n.selectPostcode
				);
				$postcodeSelect.closest( '.form-row' ).removeClass( 'admbike-hidden' );
			} );

			$postcodeSelect.on( 'change', function () {
				var postcode = $( this ).val();

				if ( ! postcode ) {
					$coverageContainer.hide();
					return;
				}

				// Update native billing postcode/city fields so WooCommerce doesn't complain.
				$( '#billing_postcode' ).val( postcode ).trigger( 'change' );
				var municipalityName = $municipalitySelect.find( 'option:selected' ).text();
				$( '#billing_city' ).val( municipalityName ).trigger( 'change' );

				showOverlay();
				checkCoverage( postcode, $coverageContainer );
			} );

			// On "Edit" addresses view, restore state if session data is present.
			var savedLocation = window.admbikeSavedLocation || {};
			if ( savedLocation.state_id ) {
				$stateSelect.val( savedLocation.state_id ).trigger( 'change' );
			}
		} );
	}

	$( function () {
		if ( typeof window.admbikeCheckout === 'undefined' ) {
			return;
		}
		init();
	} );

})( jQuery );
