/**
 * ADM Bike Woo Locations - WooCommerce Blocks Checkout Integration.
 *
 * @package ADMBike_Woo_Locations
 */

(function () {
	'use strict';

	var locationData = null;
	var injected = false;
	var isSyncingNativeField = false;

	function getCheckoutData() {
		if (locationData) {
			return locationData;
		}

		// Try WooCommerce settings store
		if (window.wc && window.wc.wcSettings) {
			try {
				var settings = window.wc.wcSettings.getSetting('wc-checkout-block-data');
				if (settings && settings.admbikeLocations) {
					locationData = settings.admbikeLocations;
					return locationData;
				}
			} catch (e) {
				// Ignore
			}
		}

		// Try wc_blocks_data
		if (window.wc_blocks_data) {
			try {
				var data = window.wc_blocks_data;
				if (data.admbikeLocations) {
					locationData = data.admbikeLocations;
					return locationData;
				}
			} catch (e) {
				// Ignore
			}
		}

		// Fallback: script tag
		var script = document.getElementById('admbike-location-data');
		if (script) {
			try {
				locationData = JSON.parse(script.textContent);
				return locationData;
			} catch (e) {
				// Ignore
			}
		}

		// Fallback: window variable
		if (window.admbikeBlocksData) {
			locationData = window.admbikeBlocksData;
			return locationData;
		}

		return null;
	}

	function getRestUrl() {
		var data = getCheckoutData();
		if (data && data.restUrl) {
			return data.restUrl;
		}

		if (window.admbikeBlocksData && window.admbikeBlocksData.restUrl) {
			return window.admbikeBlocksData.restUrl;
		}

		return window.location.origin.replace(/\/$/, '') + '/wp-json/admbike-woo-locations/v1/';
	}

	function getStoreApiUrl() {
		return window.location.origin.replace(/\/$/, '') + '/wp-json/wc/store/v1/cart/update-customer';
	}

	function populateStates() {
		var data = getCheckoutData();
		var select = document.getElementById('admbike_blocks_state');
		if (!select) return;

		if (!data || !data.states || data.states.length === 0) {
			console.log('[ADM Bike] No states data available');
			return;
		}

		select.innerHTML = '<option value="">' + (data.i18n.selectState || 'Selecciona un estado…') + '</option>';
		data.states.forEach(function (state) {
			var option = document.createElement('option');
			option.value = state.id;
			option.textContent = state.name + ' (' + state.code + ')';
			select.appendChild(option);
		});
		console.log('[ADM Bike] States populated:', data.states.length);
	}

	function filterMunicipalities(stateId) {
		var data = getCheckoutData();
		if (!data || !data.municipalities) return [];
		return data.municipalities.filter(function (m) {
			return parseInt(m.state_id, 10) === parseInt(stateId, 10);
		});
	}

	function filterPostcodes(municipalityId) {
		var data = getCheckoutData();
		if (!data || !data.postcodes) return [];
		return data.postcodes.filter(function (p) {
			return parseInt(p.municipality_id, 10) === parseInt(municipalityId, 10);
		});
	}

	function showElement(el) {
		if (el) {
			el.classList.remove('admbike-hidden');
			el.style.display = '';
		}
	}

	function hideElement(el) {
		if (el) {
			el.classList.add('admbike-hidden');
			el.style.display = 'none';
		}
	}

	function getStateById(stateId) {
		var data = getCheckoutData();
		if (!data || !data.states) {
			return null;
		}

		for (var i = 0; i < data.states.length; i++) {
			if (parseInt(data.states[i].id, 10) === parseInt(stateId, 10)) {
				return data.states[i];
			}
		}

		return null;
	}

	function getStateByCode(stateCode) {
		var data = getCheckoutData();
		if (!data || !data.states) {
			return null;
		}

		for (var i = 0; i < data.states.length; i++) {
			if ((data.states[i].code || '') === (stateCode || '')) {
				return data.states[i];
			}
		}

		return null;
	}

	function setNativeFieldValue(selector, value, triggerChange) {
		var field = document.querySelector(selector);
		if (!field) {
			return;
		}

		var nextValue = String(value || '');
		if (field.value === nextValue) {
			return;
		}

		isSyncingNativeField = true;
		field.value = nextValue;
		if (triggerChange) {
			field.dispatchEvent(new Event('input', { bubbles: true }));
			field.dispatchEvent(new Event('change', { bubbles: true }));
		}
		isSyncingNativeField = false;
	}

	function setBlocksFieldValue(selectors, value, triggerChange) {
		var fields = [];
		for (var i = 0; i < selectors.length; i++) {
			var matches = document.querySelectorAll(selectors[i]);
			for (var j = 0; j < matches.length; j++) {
				if (fields.indexOf(matches[j]) === -1) {
					fields.push(matches[j]);
				}
			}
		}

		if (!fields.length) {
			return;
		}

		var nextValue = String(value || '');
		isSyncingNativeField = true;
		for (var k = 0; k < fields.length; k++) {
			if (fields[k].value === nextValue) {
				continue;
			}

			fields[k].value = nextValue;
			if (triggerChange) {
				fields[k].dispatchEvent(new Event('input', { bubbles: true }));
				fields[k].dispatchEvent(new Event('change', { bubbles: true }));
			}
		}
		isSyncingNativeField = false;
	}

	function getFirstFieldValue(selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var field = document.querySelector(selectors[i]);
			if (field && field.value) {
				return field.value;
			}
		}

		return '';
	}

	function getCurrentLocation() {
		var stateSelect = document.getElementById('admbike_blocks_state');
		var municipalitySelect = document.getElementById('admbike_blocks_municipality');
		var postcodeSelect = document.getElementById('admbike_blocks_postcode');
		var blocksCountry = getFirstFieldValue([
			'.wc-block-components-country-input select',
			'.wc-block-components-address-form select[name$="country"]'
		]);
		var blocksState = getFirstFieldValue([
			'.wc-block-components-address-form__state select',
			'.wc-block-components-address-form select[name$="state"]'
		]);
		var blocksCity = getFirstFieldValue([
			'.wc-block-components-address-form__city input',
			'.wc-block-components-address-form input[name$="city"]'
		]);
		var blocksPostcode = getFirstFieldValue([
			'.wc-block-components-address-form__postcode input',
			'.wc-block-components-address-form input[name$="postcode"]'
		]);
		var nativeState = document.querySelector('select[name="billing_state"]');
		var nativeShippingState = document.querySelector('select[name="shipping_state"]');
		var nativePostcode = document.querySelector('input[name="billing_postcode"], input#billing_postcode');
		var nativeShippingPostcode = document.querySelector('input[name="shipping_postcode"], input#shipping_postcode');

		var stateId = 0;
		if (stateSelect && stateSelect.value) {
			stateId = parseInt(stateSelect.value, 10);
		} else if (blocksState) {
			var blocksStateData = getStateByCode(blocksState);
			if (blocksStateData) {
				stateId = parseInt(blocksStateData.id, 10);
			}
		} else if (nativeState && nativeState.value) {
			var state = getStateByCode(nativeState.value);
			if (state) {
				stateId = parseInt(state.id, 10);
			}
		} else if (nativeShippingState && nativeShippingState.value) {
			var shippingState = getStateByCode(nativeShippingState.value);
			if (shippingState) {
				stateId = parseInt(shippingState.id, 10);
			}
		}

		var municipalityId = 0;
		if (municipalitySelect && municipalitySelect.value) {
			municipalityId = parseInt(municipalitySelect.value, 10);
		}

		var postcode = '';
		if (postcodeSelect && postcodeSelect.value) {
			postcode = postcodeSelect.value;
		} else if (blocksPostcode) {
			postcode = blocksPostcode;
		} else if (nativePostcode && nativePostcode.value) {
			postcode = nativePostcode.value;
		} else if (nativeShippingPostcode && nativeShippingPostcode.value) {
			postcode = nativeShippingPostcode.value;
		}

		var country = 'MX';
		if (blocksCountry) {
			country = blocksCountry;
		}

		return {
			country: country,
			state_id: stateId,
			municipality_id: municipalityId,
			postcode: postcode
		};
	}

	function syncCheckoutLocation() {
		var data = getCheckoutData();
		if (!data || !data.restUrl) {
			return;
		}

		var location = getCurrentLocation();
		if (!location.postcode && !location.state_id && !location.municipality_id) {
			return;
		}

		var state = location.state_id ? getStateById(location.state_id) : null;
		var municipalitySelect = document.getElementById('admbike_blocks_municipality');
		var municipalityName = municipalitySelect && municipalitySelect.selectedIndex > 0 ? municipalitySelect.options[municipalitySelect.selectedIndex].text : '';
		var stateCode = state && state.code ? state.code : '';

		var addressPayload = {
			first_name: '',
			last_name: '',
			company: '',
			address_1: '',
			address_2: '',
			city: municipalityName || '',
			state: stateCode || '',
			postcode: location.postcode || '',
			country: 'MX',
			phone: ''
		};

		fetch(data.restUrl + 'checkout-location', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(location)
		}).catch(function () {
			return null;
		});

		fetch(getStoreApiUrl(), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				billing_address: addressPayload,
				shipping_address: addressPayload
			})
		}).then(function () {
			if (window.wp && window.wp.data && window.wp.data.dispatch) {
				try {
					window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
				} catch (e) {
					// Ignore.
				}
			}
		}).catch(function () {
			return null;
		});
	}

	function mirrorCustomFieldsToNative() {
		var stateSelect = document.getElementById('admbike_blocks_state');
		var postcodeSelect = document.getElementById('admbike_blocks_postcode');
		var municipalitySelect = document.getElementById('admbike_blocks_municipality');
		var municipalityName = municipalitySelect && municipalitySelect.selectedIndex > 0 ? municipalitySelect.options[municipalitySelect.selectedIndex].text : '';
		var postcode = postcodeSelect && postcodeSelect.value ? postcodeSelect.value : '';
		var stateCode = '';

		if (stateSelect && stateSelect.value) {
			var state = getStateById(stateSelect.value);
			if (state && state.code) {
				stateCode = state.code;
				setNativeFieldValue('select[name="billing_state"]', state.code, true);
				setNativeFieldValue('select[name="shipping_state"]', state.code, true);
				setNativeFieldValue('select#billing_state', state.code, true);
				setNativeFieldValue('select#shipping_state', state.code, true);
			}
			setNativeFieldValue('select[name="billing_country"]', 'MX', true);
			setNativeFieldValue('select[name="shipping_country"]', 'MX', true);
			setNativeFieldValue('select#billing_country', 'MX', true);
			setNativeFieldValue('select#shipping_country', 'MX', true);
			setBlocksFieldValue([
				'.wc-block-components-country-input select',
				'.wc-block-components-address-form select[name$="country"]',
				'.wc-block-components-address-form .wc-blocks-components-select__select'
			], 'MX', true);
			setBlocksFieldValue([
				'.wc-block-components-address-form__state select',
				'.wc-block-components-address-form select[name$="state"]',
				'.wc-block-components-address-form .wc-blocks-components-select__select'
			], state.code, true);
		}

		if (municipalityName) {
			setNativeFieldValue('input[name="billing_city"], input#billing_city', municipalityName, true);
			setNativeFieldValue('input[name="shipping_city"], input#shipping_city', municipalityName, true);
			setBlocksFieldValue([
				'.wc-block-components-address-form__city input',
				'input.wc-block-components-address-form__city',
				'.wc-block-components-address-form input[name$="city"]'
			], municipalityName, true);
		}

		if (postcode) {
			setNativeFieldValue('input[name="billing_postcode"], input#billing_postcode', postcode, true);
			setNativeFieldValue('input[name="shipping_postcode"], input#shipping_postcode', postcode, true);
			setBlocksFieldValue([
				'.wc-block-components-address-form__postcode input',
				'input.wc-block-components-address-form__postcode',
				'.wc-block-components-address-form input[name$="postcode"]'
			], postcode, true);
		}
	}

	function loadLocationData(callback) {
		var data = getCheckoutData();
		if (data && data.states && data.states.length) {
			callback(data);
			return;
		}

		var restUrl = getRestUrl();
		var payload = {
			states: [],
			municipalities: [],
			postcodes: [],
			restUrl: restUrl,
			i18n: {
				selectState: 'Selecciona un estado…',
				selectMunicipality: 'Selecciona un municipio…',
				selectPostcode: 'Selecciona un código postal…',
				noCoverage: 'No contamos con cobertura para esta ubicación.',
				loading: 'Cargando…'
			}
		};

		var statesXhr = new XMLHttpRequest();
		statesXhr.open('GET', restUrl + 'states', true);
		statesXhr.onreadystatechange = function () {
			if (statesXhr.readyState !== 4) {
				return;
			}

			if (statesXhr.status !== 200) {
				callback(null);
				return;
			}

			try {
				payload.states = JSON.parse(statesXhr.responseText);
			} catch (e) {
				callback(null);
				return;
			}

			var muniXhr = new XMLHttpRequest();
			muniXhr.open('GET', restUrl + 'municipalities', true);
			muniXhr.onreadystatechange = function () {
				if (muniXhr.readyState !== 4) {
					return;
				}

				if (muniXhr.status === 200) {
					try {
						payload.municipalities = JSON.parse(muniXhr.responseText);
					} catch (e) {
						payload.municipalities = [];
					}
				}

				var pcXhr = new XMLHttpRequest();
				pcXhr.open('GET', restUrl + 'postcodes', true);
				pcXhr.onreadystatechange = function () {
					if (pcXhr.readyState !== 4) {
						return;
					}

					if (pcXhr.status === 200) {
						try {
							payload.postcodes = JSON.parse(pcXhr.responseText);
						} catch (e) {
							payload.postcodes = [];
						}
					}

					locationData = payload;
					callback(locationData);
				};
				pcXhr.send();
			};
			muniXhr.send();
		};
		statesXhr.send();
	}

	function checkCoverage(postcode, callback) {
		var data = getCheckoutData();
		if (!data || !data.restUrl) {
			callback({ available: false, message: 'REST URL not available' });
			return;
		}

		var xhr = new XMLHttpRequest();
		xhr.open('GET', data.restUrl + 'coverage?postcode=' + encodeURIComponent(postcode), true);
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					try {
						var response = JSON.parse(xhr.responseText);
						callback(response);
					} catch (e) {
						callback({ available: false, message: 'Error parsing response' });
					}
				} else {
					callback({ available: false, message: 'Request failed' });
				}
			}
		};
		xhr.send();
	}

	function initStateSelector() {
		var stateSelect = document.getElementById('admbike_blocks_state');
		var municipalityGroup = document.getElementById('admbike_blocks_municipality_group');
		var municipalitySelect = document.getElementById('admbike_blocks_municipality');
		var postcodeGroup = document.getElementById('admbike_blocks_postcode_group');
		var postcodeSelect = document.getElementById('admbike_blocks_postcode');
		var coverageResult = document.getElementById('admbike_blocks_coverage_result');

		if (!stateSelect) {
			console.log('[ADM Bike] State select not found');
			return;
		}

		console.log('[ADM Bike] Initializing state selector');
		loadLocationData(function () {
			populateStates();

			stateSelect.addEventListener('change', function () {
				var stateId = this.value;
				municipalitySelect.innerHTML = '<option value="">' + (getCheckoutData()?.i18n.selectMunicipality || 'Selecciona un municipio…') + '</option>';
				postcodeSelect.innerHTML = '<option value="">' + (getCheckoutData()?.i18n.selectPostcode || 'Selecciona un código postal…') + '</option>';
				hideElement(coverageResult);

				if (!stateId) {
					hideElement(municipalityGroup);
					hideElement(postcodeGroup);
					return;
				}

				var munis = filterMunicipalities(stateId);
				console.log('[ADM Bike] Filtered municipalities:', munis.length);

				if (munis.length === 0) {
					showElement(municipalityGroup);
					hideElement(postcodeGroup);
					return;
				}

				munis.forEach(function (m) {
					var option = document.createElement('option');
					option.value = m.id;
					option.textContent = m.name;
					municipalitySelect.appendChild(option);
				});

				showElement(municipalityGroup);
				hideElement(postcodeGroup);
				mirrorCustomFieldsToNative();
				syncCheckoutLocation();
			});

			municipalitySelect.addEventListener('change', function () {
				var municipalityId = this.value;
				postcodeSelect.innerHTML = '<option value="">' + (getCheckoutData()?.i18n.selectPostcode || 'Selecciona un código postal…') + '</option>';
				hideElement(coverageResult);

				if (!municipalityId) {
					hideElement(postcodeGroup);
					return;
				}

				var postcodes = filterPostcodes(municipalityId);
				console.log('[ADM Bike] Filtered postcodes:', postcodes.length);

				if (postcodes.length === 0) {
					showElement(postcodeGroup);
					return;
				}

				postcodes.forEach(function (p) {
					var option = document.createElement('option');
					option.value = p.postcode;
					option.textContent = p.postcode;
					postcodeSelect.appendChild(option);
				});

				showElement(postcodeGroup);
				mirrorCustomFieldsToNative();
				syncCheckoutLocation();
			});

			postcodeSelect.addEventListener('change', function () {
				var postcode = this.value;
				if (!postcode) {
					hideElement(coverageResult);
					return;
				}

				coverageResult.innerHTML = '<p class="admbike-coverage-loading">' + (getCheckoutData()?.i18n.loading || 'Cargando…') + '</p>';
				showElement(coverageResult);

				checkCoverage(postcode, function (response) {
					if (!response.available) {
						coverageResult.innerHTML = '<p class="admbike-coverage-message admbike-coverage-unavailable">' +
							'<span class="dashicons dashicons-dismiss"></span> ' +
							(response.message || getCheckoutData()?.i18n.noCoverage || 'No coverage') +
							'</p>';
						coverageResult.className = 'admbike-coverage-unavailable';
					} else {
						var icon = 'free' === response.rule_type ? 'dashicons-yes-alt' : 'dashicons-money-alt';
						coverageResult.innerHTML = '<p class="admbike-coverage-message admbike-coverage-' + response.rule_type + '">' +
							'<span class="dashicons ' + icon + '"></span> ' +
							response.message +
							'</p>';
						coverageResult.className = 'admbike-coverage-' + response.rule_type;
					}
				});
				mirrorCustomFieldsToNative();
				syncCheckoutLocation();
			});
		});

		var nativeState = document.querySelector('select[name="billing_state"]');
		var nativePostcode = document.querySelector('input[name="billing_postcode"], input#billing_postcode');
		var blocksCountry = document.querySelector('.wc-block-components-country-input select, .wc-block-components-address-form select[name$="country"]');
		var blocksState = document.querySelector('.wc-block-components-address-form__state select, .wc-block-components-address-form select[name$="state"]');
		var blocksCity = document.querySelector('.wc-block-components-address-form__city input, .wc-block-components-address-form input[name$="city"]');
		var blocksPostcode = document.querySelector('.wc-block-components-address-form__postcode input, .wc-block-components-address-form input[name$="postcode"]');

		if (nativeState) {
			nativeState.addEventListener('change', function () {
				if (isSyncingNativeField) {
					return;
				}

				syncCheckoutLocation();
			});
		}

		if (nativePostcode) {
			nativePostcode.addEventListener('change', function () {
				if (isSyncingNativeField) {
					return;
				}

				syncCheckoutLocation();
			});
		}

		if (blocksCountry) {
			blocksCountry.addEventListener('change', syncCheckoutLocation);
		}

		if (blocksState) {
			blocksState.addEventListener('change', syncCheckoutLocation);
		}

		if (blocksCity) {
			blocksCity.addEventListener('input', syncCheckoutLocation);
			blocksCity.addEventListener('change', syncCheckoutLocation);
		}

		if (blocksPostcode) {
			blocksPostcode.addEventListener('input', syncCheckoutLocation);
			blocksPostcode.addEventListener('change', syncCheckoutLocation);
		}
	}

	function addCheckoutFieldsStyle() {
		if (document.getElementById('admbike-blocks-styles')) return;

		var style = document.createElement('style');
		style.id = 'admbike-blocks-styles';
		style.textContent = [
			'.admbike-blocks-checkout-fields {',
			'  margin: 1.5em 0;',
			'  padding: 1.5em;',
			'  background: #f8f8f8;',
			'  border-radius: 8px;',
			'  border: 1px solid #e2e2e2;',
			'}',
			'.admbike-blocks-field-group {',
			'  margin-bottom: 1em;',
			'}',
			'.admbike-blocks-field-group:last-child {',
			'  margin-bottom: 0;',
			'}',
			'.admbike-blocks-label {',
			'  display: block;',
			'  margin-bottom: 0.5em;',
			'  font-weight: 600;',
			'  font-size: 14px;',
			'}',
			'.admbike-blocks-select {',
			'  width: 100%;',
			'  padding: 0.75em;',
			'  border: 1px solid #ddd;',
			'  border-radius: 4px;',
			'  font-size: 16px;',
			'  background: #fff;',
			'}',
			'.admbike-hidden {',
			'  display: none !important;',
			'}',
			'.admbike-coverage-message {',
			'  padding: 0.75em 1em;',
			'  border-radius: 4px;',
			'  margin-top: 1em;',
			'  font-size: 14px;',
			'}',
			'.admbike-coverage-ok {',
			'  background: #d4edda;',
			'  color: #155724;',
			'}',
			'.admbike-coverage-paid {',
			'  background: #fff3cd;',
			'  color: #856404;',
			'}',
			'.admbike-coverage-unavailable {',
			'  background: #f8d7da;',
			'  color: #721c24;',
			'}',
			'.admbike-coverage-loading {',
			'  background: #e2e3e5;',
			'  color: #383d41;',
			'}',
			'.admbike-coverage-message .dashicons {',
			'  margin-right: 0.5em;',
			'}',
		].join('\n');

		document.head.appendChild(style);
	}

	function getFieldsHTML() {
		return [
			'<div class="admbike-blocks-checkout-fields">',
			'  <h4 style="margin: 0 0 1em 0;">Datos de Envío</h4>',
			'  <div class="admbike-blocks-field-group">',
			'    <label for="admbike_blocks_state" class="admbike-blocks-label">Estado</label>',
			'    <select id="admbike_blocks_state" class="admbike-blocks-select" name="admbike_blocks_state">',
			'      <option value="">Selecciona un estado…</option>',
			'    </select>',
			'  </div>',
			'  <div class="admbike-blocks-field-group admbike-hidden" id="admbike_blocks_municipality_group">',
			'    <label for="admbike_blocks_municipality" class="admbike-blocks-label">Municipio / Ciudad</label>',
			'    <select id="admbike_blocks_municipality" class="admbike-blocks-select" name="admbike_blocks_municipality">',
			'      <option value="">Selecciona un municipio…</option>',
			'    </select>',
			'  </div>',
			'  <div class="admbike-blocks-field-group admbike-hidden" id="admbike_blocks_postcode_group">',
			'    <label for="admbike_blocks_postcode" class="admbike-blocks-label">Código Postal</label>',
			'    <select id="admbike_blocks_postcode" class="admbike-blocks-select" name="admbike_blocks_postcode">',
			'      <option value="">Selecciona un código postal…</option>',
			'    </select>',
			'  </div>',
			'  <div id="admbike_blocks_coverage_result" class="admbike-hidden"></div>',
			'</div>'
		].join('\n');
	}

	function findAndInjectFields() {
		if (injected) return;

		// WooCommerce Blocks uses specific classes
		var targets = [
			'.wc-block-checkout__main',
			'.wc-block-checkout__order-summary',
			'#wc-block-checkout__form',
			'.woocommerce-checkout',
			'[data-block-name="woocommerce/checkout"]',
			'[data-block-name="woocommerce/checkout-fields-block"]',
			'.wc-block-components-form'
		];

		var target = null;
		for (var i = 0; i < targets.length; i++) {
			var el = document.querySelector(targets[i]);
			if (el) {
				target = el;
				console.log('[ADM Bike] Found target:', targets[i]);
				break;
			}
		}

		if (!target) {
			console.log('[ADM Bike] No target found for injection, using body fallback');
			target = document.body;
		}

		// Create wrapper
		var wrapper = document.createElement('div');
		wrapper.className = 'admbike-blocks-injected';
		wrapper.innerHTML = getFieldsHTML();

		// Insert at the beginning of the form
		if (target.firstChild) {
			target.insertBefore(wrapper, target.firstChild);
		} else {
			target.appendChild(wrapper);
		}

		injected = true;
		console.log('[ADM Bike] Fields injected successfully');

		// Now initialize the selectors
		initStateSelector();
	}

	function init() {
		console.log('[ADM Bike] Initializing blocks checkout integration');
		addCheckoutFieldsStyle();

		// Try immediately
		findAndInjectFields();
		syncCheckoutLocation();

		// If not found, use MutationObserver to watch for checkout loading
		if (!injected) {
			var observer = new MutationObserver(function (mutations) {
				if (injected) {
					observer.disconnect();
					return;
				}
				findAndInjectFields();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});

			// Stop observing after 30 seconds
			setTimeout(function() {
				observer.disconnect();
				if (!injected) {
					console.log('[ADM Bike] Injection timeout - checkout may not be using blocks');
				}
			}, 30000);
		}
	}

	// Start
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		setTimeout(init, 100);
	}

})();
