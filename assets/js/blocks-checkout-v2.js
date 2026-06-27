(function () {
	'use strict';

	var blocksData = window.admbikeWooLocationsBlocks || {};
	var settings = blocksData;
	var restUrl = settings.restUrl || (window.location.origin.replace(/\/$/, '') + '/wp-json/admbike-woo-locations/v1/');
	var nonce = settings.nonce || '';
	var noCoverageMessage = settings.frontendNoCoverageMessage || 'No disponible en tu zona';
	var states = Array.isArray(settings.states) ? settings.states : [];
	var municipalities = Array.isArray(settings.municipalities) ? settings.municipalities : [];
	var formTimers = new WeakMap();
	var postcodeGuards = new WeakMap();
	var shippingRefreshTimers = new WeakMap();

	function normalizeCode(value) {
		return String(value || '').trim().toUpperCase();
	}

	function normalizeName(value) {
		return String(value || '').trim().toLowerCase();
	}

	function getStateByCode(code) {
		var targetCode = normalizeCode(code);
		var targetName = normalizeName(code);

		for (var i = 0; i < states.length; i += 1) {
			if (normalizeCode(states[i].code) === targetCode || normalizeName(states[i].name) === targetName) {
				return states[i];
			}
		}

		return null;
	}

	function getAllowedStateNames() {
		return states.map(function (state) {
			return normalizeName(state.name);
		});
	}

	function resolveStateFromSelect(select) {
		var state = null;

		if (!select) {
			return null;
		}

		if (select.value) {
			state = getStateByCode(select.value);
			if (state) {
				return state;
			}
		}

		if (select.selectedIndex >= 0) {
			state = getStateByCode(select.options[select.selectedIndex].textContent || '');
			if (state) {
				return state;
			}
		}

		return null;
	}

	function getMunicipalitiesByStateId(stateId) {
		var target = parseInt(stateId, 10) || 0;

		return municipalities.filter(function (municipality) {
			return parseInt(municipality.state_id, 10) === target;
		});
	}

	function getAddressForm(type) {
		return document.getElementById(type);
	}

	function getCityContainer(form) {
		return form ? form.querySelector('.wc-block-components-address-form__city') : null;
	}

	function getCityInput(form) {
		var container = getCityContainer(form);
		return container ? container.querySelector('input[data-admbike-city-source="1"]') || container.querySelector('input') : null;
	}

	function getCitySelect(form) {
		var container = getCityContainer(form);
		return container ? container.querySelector('select[data-admbike-city-select="1"]') : null;
	}

	function getStateSelect(form) {
		return form ? form.querySelector('.wc-block-components-address-form__state select') : null;
	}

	function getStateLabel(form) {
		var stateSelect = getStateSelect(form);
		if (!stateSelect || stateSelect.selectedIndex < 0) {
			return '';
		}

		return stateSelect.options[stateSelect.selectedIndex].textContent || '';
	}

	function getSelectedStateLabel(select) {
		if (!select || select.selectedIndex < 0) {
			return '';
		}

		return select.options[select.selectedIndex].textContent || '';
	}

	function getCountrySelect(form) {
		return form ? form.querySelector('.wc-block-components-address-form__country select') : null;
	}

	function getPostcodeInput(form) {
		return form ? form.querySelector('.wc-block-components-address-form__postcode input') : null;
	}

	function syncCheckoutPostcode(postcode) {
		if (window.wp && window.wp.data && window.wp.data.dispatch) {
			var checkoutDispatch = window.wp.data.dispatch('wc/store/checkout');
			if (checkoutDispatch && typeof checkoutDispatch.setShippingAddress === 'function') {
				checkoutDispatch.setShippingAddress({ postcode: postcode });
			}
		}
	}

	function syncCheckoutAddress(form, overrides) {
		if (!(window.wp && window.wp.data && window.wp.data.dispatch)) {
			return;
		}

		var checkoutDispatch = window.wp.data.dispatch('wc/store/checkout');
		if (!(checkoutDispatch && typeof checkoutDispatch.setShippingAddress === 'function')) {
			return;
		}

		var address = getFormAddress(form);
		var payload = {
			country: address.country,
			state: address.state,
			city: address.city,
			postcode: address.postcode
		};

		if (overrides && typeof overrides === 'object') {
			payload = Object.assign(payload, overrides);
		}

		checkoutDispatch.setShippingAddress(payload);
	}

	function requestShippingRefresh(form, overrides) {
		syncCheckoutAddress(form, overrides);

		if (shippingRefreshTimers.has(form)) {
			window.clearTimeout(shippingRefreshTimers.get(form));
		}

		shippingRefreshTimers.set(
			form,
			window.setTimeout(function () {
				syncCheckoutAddress(form, overrides);
			}, 250)
		);
	}

	function clearPostcodeValue(form) {
		var postcodeInput = getPostcodeInput(form);
		if (!postcodeInput) {
			return;
		}

		if (postcodeInput.value !== '') {
			postcodeInput.value = '';
			postcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
			postcodeInput.dispatchEvent(new Event('change', { bubbles: true }));
		}

		requestShippingRefresh(form, { postcode: '' });
	}

	function hardenPostcodeInput(postcodeInput, locked) {
		if (!postcodeInput) {
			return;
		}

		postcodeInput.setAttribute('autocorrect', 'off');
		postcodeInput.setAttribute('autocapitalize', 'none');
		postcodeInput.setAttribute('spellcheck', 'false');
		postcodeInput.setAttribute('data-lpignore', 'true');
		postcodeInput.setAttribute('data-1p-ignore', 'true');
		postcodeInput.setAttribute('aria-autocomplete', 'none');
		postcodeInput.setAttribute('inputmode', 'numeric');

		if (locked) {
			postcodeInput.setAttribute('autocomplete', 'new-password');
			return;
		}

		postcodeInput.setAttribute('autocomplete', 'one-time-code');
	}

	function setPostcodeLocked(form, locked) {
		var postcodeInput = getPostcodeInput(form);
		if (!postcodeInput) {
			return;
		}

		hardenPostcodeInput(postcodeInput, locked);
		if (locked) {
			postcodeInput.setAttribute('readonly', 'readonly');
			clearPostcodeValue(form);

			if (!postcodeGuards.has(form)) {
				postcodeGuards.set(
					form,
					window.setInterval(function () {
						var guardedInput = getPostcodeInput(form);
						if (!guardedInput || guardedInput.value === '') {
							return;
						}

						clearPostcodeValue(form);
					}, 120)
				);
			}
		} else {
			if (document.activeElement === postcodeInput) {
				window.setTimeout(function () {
					var currentInput = getPostcodeInput(form);
					if (currentInput && document.activeElement === currentInput) {
						currentInput.removeAttribute('readonly');
					}
				}, 80);
			} else {
				postcodeInput.removeAttribute('readonly');
			}

			if (postcodeGuards.has(form)) {
				window.clearInterval(postcodeGuards.get(form));
				postcodeGuards.delete(form);
			}
		}
	}

	function ensureCitySelect(form) {
		var container = getCityContainer(form);
		var input = getCityInput(form);
		var existing = getCitySelect(form);

		if (!container || !input) {
			return null;
		}

		if (existing) {
			return existing;
		}

		var label = container.querySelector('label');
		if (label) {
			label.style.display = 'none';
		}

		input.dataset.admbikeCitySource = '1';
		input.style.position = 'absolute';
		input.style.left = '-9999px';
		input.style.width = '1px';
		input.style.height = '1px';
		input.style.opacity = '0';
		input.style.pointerEvents = 'none';

		var wrapper = document.createElement('div');
		wrapper.className = 'wc-blocks-components-select';

		var inner = document.createElement('div');
		inner.className = 'wc-blocks-components-select__container';

		var selectLabel = document.createElement('label');
		selectLabel.className = 'wc-blocks-components-select__label';
		selectLabel.setAttribute('for', input.id + '-admbike-city-select');
		selectLabel.textContent = '';
		selectLabel.setAttribute('aria-hidden', 'true');
		selectLabel.style.display = 'none';

		var select = document.createElement('select');
		select.id = input.id + '-admbike-city-select';
		select.className = 'wc-blocks-components-select__select';
		select.setAttribute('data-admbike-city-select', '1');
		select.setAttribute('aria-label', input.getAttribute('aria-label') || (label ? label.textContent : 'Municipio'));

		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		icon.setAttribute('viewBox', '0 0 24 24');
		icon.setAttribute('width', '24');
		icon.setAttribute('height', '24');
		icon.setAttribute('aria-hidden', 'true');
		icon.setAttribute('focusable', 'false');
		icon.classList.add('wc-blocks-components-select__expand');

		var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		path.setAttribute('d', 'M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z');
		icon.appendChild(path);

		inner.appendChild(selectLabel);
		inner.appendChild(select);
		inner.appendChild(icon);
		wrapper.appendChild(inner);
		container.insertBefore(wrapper, input);

		return select;
	}

	function filterShippingStateOptions(form) {
		var stateSelect = getStateSelect(form);
		var allowedStateNames = getAllowedStateNames();

		if (!stateSelect || form.id !== 'shipping' || stateSelect.dataset.admbikeStatesFiltered === '1') {
			return;
		}

		for (var i = stateSelect.options.length - 1; i >= 0; i -= 1) {
			var option = stateSelect.options[i];
			var optionValue = String(option.value || '').trim();
			var optionName = normalizeName(option.textContent || '');

			if (!optionValue) {
				continue;
			}

			if (allowedStateNames.indexOf(optionName) === -1) {
				stateSelect.removeChild(option);
			}
		}

		stateSelect.dataset.admbikeStatesFiltered = '1';
	}

	function syncHiddenCityInput(form, value) {
		var input = getCityInput(form);
		if (!input) {
			return;
		}

		var nextValue = String(value || '');
		if (input.value === nextValue) {
			return;
		}

		input.value = nextValue;
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		input.dispatchEvent(new Event('blur', { bubbles: true }));
	}

	function getSelectedMunicipalityName(select) {
		if (!select || select.selectedIndex < 0) {
			return '';
		}

		return select.options[select.selectedIndex].value || '';
	}

	function getSelectedMunicipalityId(form) {
		var stateSelect = getStateSelect(form);
		var state = resolveStateFromSelect(stateSelect);
		var citySelect = getCitySelect(form);
		var cityName = getSelectedMunicipalityName(citySelect);

		if (!state || !cityName) {
			return 0;
		}

		for (var i = 0; i < municipalities.length; i += 1) {
			if (parseInt(municipalities[i].state_id, 10) === parseInt(state.id, 10) && String(municipalities[i].name || '') === cityName) {
				return parseInt(municipalities[i].id, 10) || 0;
			}
		}

		return 0;
	}

	function persistCheckoutLocation(form, overrides) {
		var address = getFormAddress(form);
		var payload = {
			country: address.country,
			state: address.state,
			city: address.city,
			municipality_id: getSelectedMunicipalityId(form),
			postcode: address.postcode
		};

		if (overrides && typeof overrides === 'object') {
			payload = Object.assign(payload, overrides);
		}

		return window.fetch(restUrl + 'checkout-location', {
			credentials: 'same-origin',
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-ADMBIKE-NONCE': nonce,
				Accept: 'application/json'
			},
			body: JSON.stringify(payload)
		}).catch(function () {
			return null;
		});
	}

	function hasSelectedMunicipality(form) {
		var citySelect = getCitySelect(form);
		return !!getSelectedMunicipalityName(citySelect);
	}

	function populateMunicipalitySelect(form) {
		var stateSelect = getStateSelect(form);
		var citySelect = ensureCitySelect(form);
		var hiddenInput = getCityInput(form);
		var state = resolveStateFromSelect(stateSelect);
		var list = state ? getMunicipalitiesByStateId(state.id) : [];
		var currentValue = hiddenInput ? String(hiddenInput.value || '') : '';
		var currentName = normalizeName(currentValue);

		if (!citySelect) {
			return;
		}

		citySelect.innerHTML = '';

		var placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.textContent = (settings.i18n && settings.i18n.selectMunicipality) || 'Selecciona un municipio…';
		citySelect.appendChild(placeholder);

		for (var i = 0; i < list.length; i += 1) {
			var municipality = list[i];
			var option = document.createElement('option');
			option.value = municipality.name;
			option.textContent = municipality.name;

			if (normalizeName(municipality.name) === currentName) {
				option.selected = true;
				placeholder.selected = false;
			}

			citySelect.appendChild(option);
		}

		citySelect.disabled = list.length === 0;

		if (!list.length) {
			syncHiddenCityInput(form, '');
			setPostcodeLocked(form, true);
			return;
		}

		if (currentValue) {
			for (var j = 0; j < citySelect.options.length; j += 1) {
				if (normalizeName(citySelect.options[j].value) === currentName) {
					citySelect.value = citySelect.options[j].value;
					break;
				}
			}
		}

		syncHiddenCityInput(form, citySelect.value || '');

		if (placeholder.selected) {
			setPostcodeLocked(form, true);
		} else {
			setPostcodeLocked(form, false);
		}
	}

	function getFormAddress(form) {
		var countrySelect = getCountrySelect(form);
		var stateSelect = getStateSelect(form);
		var citySelect = getCitySelect(form);
		var postcodeInput = getPostcodeInput(form);
		var cityInput = getCityInput(form);

		return {
			country: countrySelect ? String(countrySelect.value || '').trim() : '',
			state: stateSelect ? String(stateSelect.value || '').trim() : '',
			city: citySelect ? String(getSelectedMunicipalityName(citySelect) || '').trim() : String((cityInput && cityInput.value) || '').trim(),
			postcode: postcodeInput ? String(postcodeInput.value || '').trim() : ''
		};
	}

	function buildCoverageUrl(address) {
		var query = new URLSearchParams();

		if (address.country) {
			query.set('country', address.country);
		}
		if (address.state) {
			query.set('state', address.state);
		}
		if (address.city) {
			query.set('city', address.city);
		}
		if (address.postcode) {
			query.set('postcode', address.postcode);
		}

		return restUrl + 'coverage?' + query.toString();
	}

	function fetchCoverage(address) {
		return window.fetch(buildCoverageUrl(address), {
			credentials: 'same-origin',
			method: 'GET',
			headers: { Accept: 'application/json' }
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('coverage_request_failed');
			}

			return response.json();
		});
	}

	function findNoticeHost() {
		return document.querySelector('.wc-block-checkout__shipping-option') || document.querySelector('.wc-block-components-totals-shipping') || document.querySelector('.wc-block-checkout');
	}

	function getNoticeNode() {
		var host = findNoticeHost();
		if (!host) {
			return null;
		}

		var node = host.querySelector('.admbike-woo-locations-blocks-notice');
		if (!node) {
			node = document.createElement('div');
			node.className = 'admbike-woo-locations-blocks-notice admbike-coverage-message admbike-coverage-unavailable';
			node.setAttribute('role', 'alert');
			host.insertBefore(node, host.firstChild);
		}

		return node;
	}

	function updateNotice(message) {
		var node = getNoticeNode();
		if (!node) {
			return;
		}

		if (!message) {
			node.remove();
			return;
		}

		node.textContent = message;
	}

	function replaceWooShippingMessage(root) {
		var message = String(noCoverageMessage || '').trim();
		var walker;
		var node;
		var phrases;

		if (!message || !root) {
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

		walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
		while ((node = walker.nextNode())) {
			var text = String(node.nodeValue || '');
			for (var i = 0; i < phrases.length; i += 1) {
				if (text.indexOf(phrases[i]) !== -1) {
					node.nodeValue = text.replace(phrases[i], message);
					break;
				}
			}
		}
	}

	function scheduleCoverage(form, skipPostcode) {
		var timer = formTimers.get(form);
		if (timer) {
			window.clearTimeout(timer);
		}

		timer = window.setTimeout(function () {
			var address = getFormAddress(form);
			if (skipPostcode) {
				address.postcode = '';
			}
			if (!address.country && !address.state && !address.city && !address.postcode) {
				updateNotice('');
				return;
			}

			fetchCoverage(address)
				.then(function (response) {
					if (response && response.available === false) {
						updateNotice(response.message || noCoverageMessage);
						return;
					}

					updateNotice('');
				})
				.catch(function () {
					updateNotice('');
				});
		}, 200);

		formTimers.set(form, timer);
	}

	function bindForm(form) {
		if (!form || form.dataset.admbikeBound === '1') {
			return;
		}

		filterShippingStateOptions(form);

		var stateSelect = getStateSelect(form);
		var citySelect = ensureCitySelect(form);
		var postcodeInput = getPostcodeInput(form);
		var countrySelect = getCountrySelect(form);

		if (!stateSelect || !citySelect) {
			return;
		}

		form.dataset.admbikeBound = '1';

		stateSelect.addEventListener('change', function () {
			setPostcodeLocked(form, true);
			syncHiddenCityInput(form, '');
			persistCheckoutLocation(form, { state: stateSelect.value || '', city: '', municipality_id: 0, postcode: '' });
			requestShippingRefresh(form, { state: stateSelect.value || '', city: '', postcode: '' });
			setTimeout(function () {
				clearPostcodeValue(form);
			}, 150);
			populateMunicipalitySelect(form);
			scheduleCoverage(form, true);
		});

		citySelect.addEventListener('change', function () {
			var selectedValue = getSelectedMunicipalityName(citySelect);
			syncHiddenCityInput(form, selectedValue);
			clearPostcodeValue(form);
			setPostcodeLocked(form, true);
			persistCheckoutLocation(form, { city: selectedValue, municipality_id: getSelectedMunicipalityId(form), postcode: '' });
			requestShippingRefresh(form, { city: selectedValue, postcode: '' });
			scheduleCoverage(form, true);
		});

		if (postcodeInput) {
			postcodeInput.addEventListener('focus', function () {
				if (hasSelectedMunicipality(form)) {
					setPostcodeLocked(form, false);
					if (postcodeInput.value !== '') {
						clearPostcodeValue(form);
					}
				}
			});
			postcodeInput.addEventListener('blur', function () {
				hardenPostcodeInput(postcodeInput, !hasSelectedMunicipality(form));
			});
			postcodeInput.addEventListener('input', function () {
				persistCheckoutLocation(form, { postcode: String(postcodeInput.value || '').trim() });
				scheduleCoverage(form);
			});
			postcodeInput.addEventListener('change', function () {
				persistCheckoutLocation(form, { postcode: String(postcodeInput.value || '').trim() });
				scheduleCoverage(form);
			});
		}

		if (countrySelect) {
			countrySelect.addEventListener('change', function () {
				setPostcodeLocked(form, true);
				syncHiddenCityInput(form, '');
				persistCheckoutLocation(form, { country: countrySelect.value || '', state: '', city: '', municipality_id: 0, postcode: '' });
				requestShippingRefresh(form, { country: countrySelect.value || '', state: '', city: '', postcode: '' });
				setTimeout(function () {
					clearPostcodeValue(form);
				}, 150);
				populateMunicipalitySelect(form);
				scheduleCoverage(form, true);
			});
		}

		populateMunicipalitySelect(form);
		scheduleCoverage(form);
	}

	function initForms() {
		bindForm(getAddressForm('shipping'));
	}

	function observeShippingMessages() {
		if (!window.MutationObserver || !document.body) {
			return;
		}

		replaceWooShippingMessage(document.body);

		if (window.__admbikeBlocksMessageObserver) {
			return;
		}

		window.__admbikeBlocksMessageObserver = new MutationObserver(function () {
			replaceWooShippingMessage(document.body);
		});

		window.__admbikeBlocksMessageObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
	}

	function start() {
		initForms();
		observeShippingMessages();

		if (window.__admbikeBlocksObserver) {
			return;
		}

		window.__admbikeBlocksObserver = new MutationObserver(function () {
			initForms();
		});

		window.__admbikeBlocksObserver.observe(document.body, { childList: true, subtree: true });

		var originalFetch = window.fetch;
		window.fetch = function () {
			var args = Array.prototype.slice.call(arguments);
			var url = (args[0] && typeof args[0] === 'object' && args[0].url) ? args[0].url : args[0];
			var isShippingRates = typeof url === 'string' && url.indexOf('shipping-rates') !== -1;
			if (isShippingRates) {
				if (typeof url === 'string') {
					url = url.replace(/postcode=[^&]*/g, 'postcode=');
					url = url.replace(/&postcode=$/, '');
					if (args[0] && typeof args[0] === 'object' && !args[0].url) {
						args[0].url = url;
					} else if (args[0] && typeof args[0] === 'string') {
						args[0] = url;
					}
				}
				try {
					var body = args[0] && args[0].body ? JSON.parse(args[0].body) : null;
					if (body) {
						if (body.cart_data && body.cart_data.shipping_address) {
							body.cart_data.shipping_address.postcode = '';
						}
						if (body.shipping_address) {
							body.shipping_address.postcode = '';
						}
						if (body.address) {
							body.address.postcode = '';
						}
						args[0] = Object.assign({}, args[0], { body: JSON.stringify(body) });
					}
				} catch (e) {}
			}
			return originalFetch.apply(this, args);
		};

		document.addEventListener('wc_checkout_place_order', function () {
			var shippingForm = document.getElementById('shipping');
			if (shippingForm) {
				var postcodeInputs = shippingForm.querySelectorAll('input[name*="postcode"]');
				for (var pi = 0; pi < postcodeInputs.length; pi++) {
					postcodeInputs[pi].value = '';
				}
				if (window.wp && window.wp.data && window.wp.data.dispatch) {
					var d = window.wp.data.dispatch('wc/store/checkout');
					if (d && d.setShippingAddress) {
						d.setShippingAddress({ postcode: '' });
					}
				}
			}
		}, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
}());
