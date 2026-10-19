/**
 * Qevora Booking — admin interactions.
 *
 * No jQuery dependency. All progressive enhancements degrade to the
 * server-rendered forms when JavaScript is unavailable.
 */
(function () {
	'use strict';

	var config = window.qvbAdmin || {};
	var strings = config.strings || {};

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function text(key, fallback) {
		return strings[key] || fallback;
	}

	/* ------------------------------------------------------------------ */
	/* Clipboard                                                           */
	/* ------------------------------------------------------------------ */

	function copyText(value) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(value);
		}

		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = value;
			area.setAttribute('readonly', 'readonly');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();

			try {
				var ok = document.execCommand('copy');
				document.body.removeChild(area);
				return ok ? resolve() : reject(new Error('copy failed'));
			} catch (error) {
				document.body.removeChild(area);
				reject(error);
			}
		});
	}

	function feedback(button, message) {
		var original = button.getAttribute('data-qvb-original-label');

		if (original === null) {
			original = button.textContent;
			button.setAttribute('data-qvb-original-label', original);
		}

		button.textContent = message;
		button.classList.add('is-copied');

		window.setTimeout(function () {
			button.textContent = original;
			button.classList.remove('is-copied');
		}, 1800);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.qvb-copy');

		if (!button) {
			return;
		}

		event.preventDefault();

		var value = button.getAttribute('data-copy');
		var targetSelector = button.getAttribute('data-copy-target');

		if (!value && targetSelector) {
			var target = document.querySelector(targetSelector);
			value = target && 'value' in target ? target.value : (target ? target.textContent : '');
		}

		if (!value) {
			return;
		}

		copyText(value).then(function () {
			feedback(button, text('copied', 'Copied.'));
		}).catch(function () {
			feedback(button, text('copyError', 'Press Ctrl/Cmd + C to copy.'));
		});
	});

	/* ------------------------------------------------------------------ */
	/* Confirm dialogs                                                     */
	/* ------------------------------------------------------------------ */

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.qvb-confirm');

		if (!link) {
			return;
		}

		var message = link.getAttribute('data-confirm') || text('confirmDelete', 'Are you sure?');

		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});

	/* ------------------------------------------------------------------ */
	/* Media picker                                                        */
	/* ------------------------------------------------------------------ */

	document.querySelectorAll('[data-qvb-media]').forEach(function (picker) {
		var input = picker.querySelector('[data-qvb-media-input]');
		var preview = picker.querySelector('[data-qvb-media-preview]');
		var select = picker.querySelector('[data-qvb-media-select]');
		var remove = picker.querySelector('[data-qvb-media-remove]');
		var frame = null;

		if (!input || !preview || !select || !window.wp || !window.wp.media) {
			return;
		}

		function setPreview(url) {
			preview.innerHTML = '';

			if (url) {
				var image = document.createElement('img');
				image.src = url;
				image.alt = '';
				preview.appendChild(image);

				if (remove) {
					remove.hidden = false;
				}
			} else {
				var icon = document.createElement('span');
				icon.className = 'dashicons dashicons-format-image';
				icon.setAttribute('aria-hidden', 'true');
				preview.appendChild(icon);

				if (remove) {
					remove.hidden = true;
				}
			}
		}

		select.addEventListener('click', function () {
			if (frame) {
				frame.open();
				return;
			}

			frame = window.wp.media({
				title: text('mediaTitle', 'Choose an image'),
				button: { text: text('mediaButton', 'Use this image') },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first();

				if (!attachment) {
					return;
				}

				var data = attachment.toJSON();
				var url = data.sizes && data.sizes.medium ? data.sizes.medium.url : data.url;

				input.value = data.id;
				setPreview(url);
			});

			frame.open();
		});

		if (remove) {
			remove.addEventListener('click', function () {
				input.value = '';
				setPreview('');
			});
		}
	});

	/* ------------------------------------------------------------------ */
	/* Slot picker                                                         */
	/* ------------------------------------------------------------------ */

	function renderSlots(container, slots, timeInput) {
		container.innerHTML = '';

		if (!slots.length) {
			var empty = document.createElement('p');
			empty.className = 'qvb-muted';
			empty.textContent = text('noSlots', 'No times available on this date.');
			container.appendChild(empty);
			return;
		}

		slots.forEach(function (slot) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'qvb-slot';
			button.textContent = slot.label || slot.time;
			button.setAttribute('data-time', slot.time);

			if (timeInput && timeInput.value && slot.time === timeInput.value.slice(0, 5)) {
				button.classList.add('is-selected');
				button.setAttribute('aria-pressed', 'true');
			}

			button.addEventListener('click', function () {
				if (timeInput) {
					timeInput.value = slot.time;
					timeInput.dispatchEvent(new Event('change', { bubbles: true }));
				}

				container.querySelectorAll('.qvb-slot').forEach(function (other) {
					other.classList.remove('is-selected');
					other.setAttribute('aria-pressed', 'false');
				});

				button.classList.add('is-selected');
				button.setAttribute('aria-pressed', 'true');
			});

			container.appendChild(button);
		});
	}

	document.querySelectorAll('[data-qvb-admin-booking]').forEach(function (form) {
		var service = form.querySelector('[name="service_id"]');
		var staff = form.querySelector('[name="staff_id"]');
		var date = form.querySelector('[name="date"]');
		var time = form.querySelector('[name="time"]');
		var results = form.querySelector('[data-qvb-slot-results]');
		var exclude = form.getAttribute('data-exclude') || '0';
		var requestId = 0;

		if (!service || !date || !results) {
			return;
		}

		function message(value) {
			results.innerHTML = '';
			var note = document.createElement('p');
			note.className = 'qvb-muted';
			note.textContent = value;
			results.appendChild(note);
		}

		function load() {
			var serviceId = parseInt(service.value, 10) || 0;
			var dateValue = date.value;

			if (!serviceId) {
				message(text('selectService', 'Please select a service first.'));
				return;
			}

			if (!dateValue) {
				message(text('chooseDate', 'Choose a date to load available times.'));
				return;
			}

			if (!config.restUrl || !config.nonce) {
				message(text('loadError', 'Could not load availability.'));
				return;
			}

			var current = ++requestId;
			message(text('loading', 'Loading…'));

			var url = new URL(config.restUrl.replace(/\/$/, '') + '/availability');
			url.searchParams.set('service_id', serviceId);
			url.searchParams.set('staff_id', parseInt(staff && staff.value, 10) || 0);
			url.searchParams.set('date', dateValue);

			if (exclude && parseInt(exclude, 10)) {
				url.searchParams.set('exclude', parseInt(exclude, 10));
			}

			fetch(url.toString(), {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.nonce
				}
			})
				.then(function (response) {
					return response.json().then(function (body) {
						if (!response.ok) {
							throw new Error(body && body.message ? body.message : 'request failed');
						}
						return body;
					});
				})
				.then(function (body) {
					if (current !== requestId) {
						return;
					}

					renderSlots(results, (body && body.slots) || [], time);
				})
				.catch(function () {
					if (current === requestId) {
						message(text('loadError', 'Could not load availability.'));
					}
				});
		}

		function filterStaff() {
			if (!staff) {
				return;
			}

			var serviceId = service.value;

			Array.prototype.forEach.call(staff.options, function (option) {
				var list = option.getAttribute('data-services');

				if (!list || option.value === '0') {
					option.hidden = false;
					option.disabled = false;
					return;
				}

				var allowed = serviceId !== '' && list.split(',').indexOf(serviceId) !== -1;
				option.hidden = !allowed;
				option.disabled = !allowed;

				if (!allowed && option.selected) {
					staff.value = '0';
				}
			});
		}

		service.addEventListener('change', function () {
			filterStaff();
			load();
		});

		date.addEventListener('change', load);

		if (staff) {
			staff.addEventListener('change', load);
		}

		filterStaff();

		if (service.value && date.value) {
			load();
		}
	});

	/* ------------------------------------------------------------------ */
	/* Schedule editor                                                     */
	/* ------------------------------------------------------------------ */

	function reindex(container) {
		container.querySelectorAll('[data-qvb-schedule-row]').forEach(function (row, index) {
			row.querySelectorAll('[name^="rows["]').forEach(function (field) {
				field.name = field.name.replace(/rows\[[^\]]*\]/, 'rows[' + index + ']');
			});
		});
	}

	function scheduleRowValues(row) {
		var fields = {};

		row.querySelectorAll('[name]').forEach(function (field) {
			var match = field.name.match(/\[([a-z_]+)\]$/);

			if (match) {
				fields[match[1]] = field;
			}
		});

		return fields;
	}

	var schedule = document.querySelector('[data-qvb-schedule]');
	var rowContainer = schedule ? schedule.querySelector('.qvb-schedule-rows') : null;

	function addScheduleRow(values) {
		if (!schedule || !rowContainer) {
			return null;
		}

		var template = schedule.querySelector('[data-qvb-row-template]');
		var row = null;

		if (template && template.content && template.content.firstElementChild) {
			row = template.content.firstElementChild.cloneNode(true);
		} else {
			return null;
		}

		rowContainer.appendChild(row);

		var fields = scheduleRowValues(row);
		var data = values || {};

		if (fields.day_of_week && typeof data.day_of_week !== 'undefined') {
			fields.day_of_week.value = String(data.day_of_week);
		}

		['start', 'end', 'break_start', 'break_end'].forEach(function (key) {
			if (fields[key] && typeof data[key] !== 'undefined') {
				fields[key].value = data[key];
			}
		});

		reindex(rowContainer);

		return row;
	}

	document.querySelectorAll('[data-qvb-mode]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			if (!schedule || !radio.checked) {
				return;
			}

			schedule.hidden = radio.value !== 'custom';
		});
	});

	document.addEventListener('click', function (event) {
		var add = event.target.closest('[data-qvb-add-row]');

		if (add) {
			event.preventDefault();
			var row = addScheduleRow();
			if (row) {
				var field = row.querySelector('select, input');
				if (field) {
					field.focus();
				}
			}
			return;
		}

		var remove = event.target.closest('[data-qvb-remove-row]');

		if (remove) {
			event.preventDefault();
			var target = remove.closest('[data-qvb-schedule-row]');
			if (target && target.parentNode) {
				target.parentNode.removeChild(target);
				if (rowContainer) {
					reindex(rowContainer);
				}
			}
			return;
		}

		var copy = event.target.closest('[data-qvb-copy-hours]');

		if (!copy) {
			return;
		}

		event.preventDefault();

		var table = copy.closest('.qvb-hours-table');

		if (table) {
			var rows = table.querySelectorAll('tbody tr');
			var first = rows[0];

			if (!first) {
				return;
			}

			var values = {};

			first.querySelectorAll('input[type="time"]').forEach(function (field) {
				values[field.name.replace(/\[\d+\]/, '[day]')] = field.value;
			});

			Array.prototype.slice.call(rows, 1).forEach(function (row) {
				row.querySelectorAll('input[type="time"]').forEach(function (field) {
					var key = field.name.replace(/\[\d+\]/, '[day]');
					if (Object.prototype.hasOwnProperty.call(values, key)) {
						field.value = values[key];
					}
				});
			});

			return;
		}

		if (!schedule || !rowContainer) {
			return;
		}

		var data = {};

		try {
			data = JSON.parse(schedule.getAttribute('data-qvb-business-hours') || '{}');
		} catch (error) {
			data = {};
		}

		rowContainer.innerHTML = '';

		Object.keys(data).forEach(function (day) {
			var hours = data[day];

			if (!hours || parseInt(hours.enabled, 10) !== 1) {
				return;
			}

			addScheduleRow({
				day_of_week: parseInt(day, 10),
				start: hours.start,
				end: hours.end,
				break_start: hours.break_start || '',
				break_end: hours.break_end || ''
			});
		});
	});

	document.querySelectorAll('.qvb-hours-row').forEach(function (row) {
		var toggle = row.querySelector('.qvb-hours-toggle');

		if (!toggle) {
			return;
		}

		function sync() {
			row.classList.toggle('is-closed', !toggle.checked);
		}

		toggle.addEventListener('change', sync);
		sync();
	});

	/* ------------------------------------------------------------------ */
	/* Exceptions                                                          */
	/* ------------------------------------------------------------------ */

	var exceptionType = document.querySelector('[data-qvb-exception-type]');

	if (exceptionType) {
		var exceptionFields = document.querySelectorAll('[data-qvb-exception-hours]');

		function syncException() {
			var isCustom = exceptionType.value === 'custom';

			exceptionFields.forEach(function (field) {
				field.hidden = !isCustom;
				field.querySelectorAll('input, select, textarea').forEach(function (input) {
					input.disabled = !isCustom;
				});
			});
		}

		exceptionType.addEventListener('change', syncException);
		syncException();
	}
}());
