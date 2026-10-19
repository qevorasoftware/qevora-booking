/**
 * Qevora Booking – front-end booking wizard and manage view.
 * Vanilla JS, no jQuery dependency.
 */
( function () {
	'use strict';

	var cfg = window.qvbData || {};
	var S = cfg.strings || {};
	var restUrl = cfg.restUrl || '';

	function q( root, selector ) {
		return root ? root.querySelector( selector ) : null;
	}

	function qa( root, selector ) {
		return root ? Array.prototype.slice.call( root.querySelectorAll( selector ) ) : [];
	}

	function setText( node, value ) {
		if ( node ) {
			node.textContent = value === null || value === undefined ? '' : String( value );
		}
	}

	function clearNode( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function make( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	function formatDate( iso ) {
		if ( ! iso ) {
			return '—';
		}
		try {
			var date = new Date( iso + 'T12:00:00' );
			if ( isNaN( date.getTime() ) ) {
				return iso;
			}
			return date.toLocaleDateString( undefined, {
				weekday: 'short',
				year: 'numeric',
				month: 'short',
				day: 'numeric',
			} );
		} catch ( error ) {
			return iso;
		}
	}

	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function scrollTo( node ) {
		if ( ! node || ! node.scrollIntoView ) {
			return;
		}
		node.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' } );
	}

	function api( path, options ) {
		options = options || {};
		var headers = Object.assign( {}, options.headers || {}, {
			'X-WP-Nonce': cfg.nonce || '',
		} );
		var init = {
			method: options.method || 'GET',
			headers: headers,
			credentials: 'same-origin',
		};

		if ( options.body !== undefined ) {
			headers[ 'Content-Type' ] = 'application/json';
			init.body = JSON.stringify( options.body );
		}

		return fetch( restUrl + path, init ).then( function ( response ) {
			return response
				.json()
				.catch( function () {
					return null;
				} )
				.then( function ( body ) {
					if ( ! response.ok ) {
						var error = new Error( ( body && body.message ) || S.somethingWrong || 'Request failed' );
						error.code = body && body.code ? body.code : '';
						error.status = response.status;
						throw error;
					}
					return body;
				} );
		} );
	}

	function setBusy( node, busy ) {
		if ( node ) {
			node.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Booking form                                                        */
	/* ------------------------------------------------------------------ */

	function initBooking( root ) {
		var form = q( root, '[data-qvb-form]' );
		if ( ! form ) {
			return;
		}

		var panels = qa( root, '[data-qvb-step]' );
		var indicators = qa( root, '[data-qvb-indicator]' );
		var backButton = q( form, '[data-qvb-back]' );
		var nextButton = q( form, '[data-qvb-next]' );
		var submitButton = q( form, '[data-qvb-submit]' );
		var errorBox = q( form, '[data-qvb-error]' );
		var dateInput = q( form, '[data-qvb-date]' );
		var daysBox = q( form, '[data-qvb-days]' );
		var slotsBox = q( form, '[data-qvb-slots]' );
		var timeInput = q( form, '[data-qvb-time]' );
		var timeError = q( form, '[data-qvb-time-error]' );
		var tokenInput = q( form, '[data-qvb-token]' );
		var nameInput = q( form, '[data-qvb-name]' );
		var emailInput = q( form, '[data-qvb-email]' );
		var phoneInput = q( form, '[data-qvb-phone]' );
		var notesInput = q( form, '[data-qvb-notes]' );
		var consentInput = q( form, '[data-qvb-consent]' );
		var hpInput = q( form, 'input[name="qvb_hp"]' );
		var confirmation = q( root, '[data-qvb-confirmation]' );
		var stepLabel = q( form, '[data-qvb-step-label]' );

		var state = {
			service: 0,
			staff: 0,
			date: '',
			time: '',
			timeLabel: '',
		};

		var current = 1;
		var daysRequest = 0;
		var slotsRequest = 0;
		var slotsKey = '';

		panels.forEach( function ( panel ) {
			panel.tabIndex = -1;
		} );

		slotsBox.tabIndex = -1;
		dateInput.min = root.getAttribute( 'data-min-date' ) || cfg.today || '';
		dateInput.max = root.getAttribute( 'data-max-date' ) || '';

		function serviceCard() {
			return state.service
				? q( form, '[data-qvb-service][data-service-id="' + state.service + '"]' )
				: null;
		}

		function staffCard( id ) {
			id = id === undefined ? state.staff : id;
			return q( form, '[data-qvb-staff][data-staff-id="' + id + '"]' );
		}

		function showError( message, focusTarget ) {
			if ( errorBox ) {
				errorBox.textContent = message;
				errorBox.hidden = false;
			}
			if ( focusTarget ) {
				try {
					focusTarget.focus( { preventScroll: false } );
				} catch ( error ) {
					// Older browsers ignore focus options.
				}
			}
		}

		function clearError() {
			if ( errorBox ) {
				errorBox.hidden = true;
				errorBox.textContent = '';
			}
			if ( timeError ) {
				timeError.hidden = true;
				timeError.textContent = '';
			}
		}

		function setTime( time, label ) {
			state.time = time || '';
			state.timeLabel = label || '';
			if ( timeInput ) {
				timeInput.value = state.time;
			}
			qa( slotsBox, '.qvb-slot' ).forEach( function ( button ) {
				var selected = button.getAttribute( 'data-time' ) === state.time;
				button.classList.toggle( 'is-selected', selected );
				button.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
			} );
			if ( timeError ) {
				timeError.hidden = true;
				timeError.textContent = '';
			}
		}

		function setStep( step, options ) {
			options = options || {};
			current = Math.max( 1, Math.min( 5, step ) );

			panels.forEach( function ( panel ) {
				var active = parseInt( panel.getAttribute( 'data-qvb-step' ), 10 ) === current;
				panel.hidden = ! active;
			} );

			indicators.forEach( function ( indicator ) {
				var index = parseInt( indicator.getAttribute( 'data-qvb-indicator' ), 10 );
				indicator.classList.toggle( 'is-current', index === current );
				indicator.classList.toggle( 'is-complete', index < current );
				if ( index === current ) {
					indicator.setAttribute( 'aria-current', 'step' );
				} else {
					indicator.removeAttribute( 'aria-current' );
				}
			} );

			backButton.hidden = current === 1;
			nextButton.hidden = current === 5;
			submitButton.hidden = current !== 5;

			if ( stepLabel ) {
				stepLabel.textContent = ( S.step || 'Step' ) + ' ' + current + ' ' + ( S.of || 'of' ) + ' 5';
			}

			if ( current === 3 && state.service ) {
				if ( dateInput.value && state.date !== dateInput.value ) {
					state.date = dateInput.value;
				}
				loadDays();
				if ( state.date ) {
					loadSlots();
				}
			}

			if ( current === 5 ) {
				fillSummary();
			}

			if ( options.scroll ) {
				var activePanel = panels[ current - 1 ];
				if ( activePanel ) {
					scrollTo( activePanel );
					try {
						activePanel.focus( { preventScroll: true } );
					} catch ( error ) {
						activePanel.focus();
					}
				}
			}
		}

		function updateStaffVisibility() {
			var card = serviceCard();
			var allowed = card ? ( card.getAttribute( 'data-staff-ids' ) || '' ).split( ',' ).filter( Boolean ) : null;

			qa( form, '[data-qvb-staff]' ).forEach( function ( staffChoice ) {
				var id = parseInt( staffChoice.getAttribute( 'data-staff-id' ), 10 );
				if ( ! id ) {
					return;
				}
				var input = q( staffChoice, '[data-qvb-staff-input]' );
				var visible = ! allowed || allowed.indexOf( String( id ) ) !== -1;
				staffChoice.hidden = ! visible;
				if ( input ) {
					input.disabled = ! visible;
					if ( ! visible && input.checked ) {
						var any = q( form, '[data-qvb-staff][data-staff-id="0"] [data-qvb-staff-input]' );
						if ( any ) {
							any.checked = true;
						}
						state.staff = 0;
					}
				}
			} );
		}

		function loadDays() {
			if ( ! state.service ) {
				return;
			}
			var request = ++daysRequest;
			setBusy( daysBox, true );
			daysBox.innerHTML = '<span class="qvb-muted qvb-small">' + ( S.loading || 'Loading…' ) + '</span>';

			api(
				'availability/days?service_id=' +
					encodeURIComponent( state.service ) +
					'&staff_id=' +
					encodeURIComponent( state.staff ) +
					'&start_date=' +
					encodeURIComponent( cfg.today || '' ) +
					'&days=14'
			)
				.then( function ( response ) {
					if ( request !== daysRequest ) {
						return;
					}
					renderDays( response.days || [] );
				} )
				.catch( function () {
					if ( request !== daysRequest ) {
						return;
					}
					clearNode( daysBox );
					daysBox.appendChild( make( 'span', 'qvb-muted qvb-small', S.loadError || 'Could not load availability.' ) );
				} )
				.finally( function () {
					if ( request === daysRequest ) {
						setBusy( daysBox, false );
					}
				} );
		}

		function renderDays( days ) {
			clearNode( daysBox );
			var available = days.filter( function ( day ) {
				return day.available;
			} );

			if ( ! available.length ) {
				daysBox.appendChild( make( 'span', 'qvb-muted qvb-small', S.noDates || 'No availability found.' ) );
				return;
			}

			daysBox.appendChild( make( 'span', 'qvb-quick-dates__label', S.nextAvailable || 'Next available' ) );

			available.slice( 0, 10 ).forEach( function ( day ) {
				var button = make( 'button', 'qvb-day-chip' );
				button.type = 'button';
				button.setAttribute( 'data-date', day.date );
				button.appendChild( document.createTextNode( day.label ) );
				button.appendChild( make( 'small', null, ( day.count || 0 ) + ' ' + ( S.slotsAvailable || 'times' ) ) );
				button.classList.toggle( 'is-selected', day.date === state.date );
				button.addEventListener( 'click', function () {
					dateInput.value = day.date;
					state.date = day.date;
					setTime( '' );
					qa( daysBox, '.qvb-day-chip' ).forEach( function ( chip ) {
						chip.classList.toggle( 'is-selected', chip === button );
					} );
					loadSlots();
				} );
				daysBox.appendChild( button );
			} );
		}

		function loadSlots( force ) {
			if ( ! state.service || ! state.date ) {
				return;
			}
			var key = state.service + '|' + state.staff + '|' + state.date;
			if ( ! force && key === slotsKey && q( slotsBox, '.qvb-slot' ) ) {
				return;
			}
			slotsKey = key;
			var request = ++slotsRequest;
			setBusy( slotsBox, true );
			slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.loadingSlots || 'Loading times…' ) + '</p>';
			setTime( '' );

			api(
				'availability?service_id=' +
					encodeURIComponent( state.service ) +
					'&staff_id=' +
					encodeURIComponent( state.staff ) +
					'&date=' +
					encodeURIComponent( state.date )
			)
				.then( function ( response ) {
					if ( request !== slotsRequest ) {
						return;
					}
					renderSlots( response.slots || [] );
				} )
				.catch( function () {
					if ( request !== slotsRequest ) {
						return;
					}
					slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.loadError || 'Could not load times.' ) + '</p>';
				} )
				.finally( function () {
					if ( request === slotsRequest ) {
						setBusy( slotsBox, false );
					}
				} );
		}

		function renderSlots( slots ) {
			clearNode( slotsBox );

			if ( ! slots.length ) {
				slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.noSlots || 'No times are available.' ) + '</p>';
				return;
			}

			var groups = [
				{ key: 'morning', label: S.morning || 'Morning', min: 0, max: 12 },
				{ key: 'afternoon', label: S.afternoon || 'Afternoon', min: 12, max: 17 },
				{ key: 'evening', label: S.evening || 'Evening', min: 17, max: 24 },
			];

			groups.forEach( function ( group ) {
				var matching = slots.filter( function ( slot ) {
					var hour = parseInt( ( slot.time || '00:00' ).split( ':' )[ 0 ], 10 );
					return hour >= group.min && hour < group.max;
				} );

				if ( ! matching.length ) {
					return;
				}

				var wrapper = make( 'div', 'qvb-slot-group' );
				wrapper.appendChild( make( 'h4', null, group.label ) );
				var grid = make( 'div', 'qvb-slot-grid' );

				matching.forEach( function ( slot ) {
					var button = make( 'button', 'qvb-slot' );
					button.type = 'button';
					button.setAttribute( 'data-time', slot.time );
					button.setAttribute( 'data-label', slot.label );
					button.setAttribute( 'aria-pressed', 'false' );
					button.appendChild( document.createTextNode( slot.label ) );

					if ( slot.staff_count > 1 && state.staff === 0 ) {
						button.appendChild( make( 'span', 'qvb-slot__count', slot.staff_count + ' ' + ( S.available || 'available' ) ) );
						button.setAttribute(
							'aria-label',
							slot.label + ', ' + slot.staff_count + ' ' + ( S.available || 'available' )
						);
					}

					button.addEventListener( 'click', function () {
						setTime( slot.time, slot.label );
					} );

					grid.appendChild( button );
				} );

				wrapper.appendChild( grid );
				slotsBox.appendChild( wrapper );
			} );
		}

		function fillSummary() {
			var card = serviceCard();
			var staff = staffCard();

			setText( q( root, '[data-qvb-summary="service"]' ), card ? q( card, '.qvb-choice__title' ).textContent : '—' );
			setText(
				q( root, '[data-qvb-summary="staff"]' ),
				state.staff && staff ? q( staff, '.qvb-choice__title' ).textContent : S.anyStaff || 'Any available staff'
			);
			setText( q( root, '[data-qvb-summary="date"]' ), state.date ? formatDate( state.date ) : '—' );
			setText( q( root, '[data-qvb-summary="time"]' ), state.timeLabel || '—' );
			setText( q( root, '[data-qvb-summary="duration"]' ), card ? card.getAttribute( 'data-duration' ) : '—' );
			setText( q( root, '[data-qvb-summary="price"]' ), card ? card.getAttribute( 'data-price-label' ) : '—' );
			setText( q( root, '[data-qvb-summary="name"]' ), nameInput.value.trim() || '—' );
			setText( q( root, '[data-qvb-summary="email"]' ), emailInput.value.trim() || '—' );
			setText( q( root, '[data-qvb-summary="phone"]' ), phoneInput.value.trim() || '—' );
		}

		function validateStep( step ) {
			clearError();

			if ( step === 1 ) {
				if ( ! state.service ) {
					showError( S.selectService || 'Select a service.', q( form, '[data-qvb-service-input]' ) );
					return false;
				}
				return true;
			}

			if ( step === 2 ) {
				return true;
			}

			if ( step === 3 ) {
				var date = dateInput.value;
				if ( ! date ) {
					showError( S.selectDate || 'Choose a date.', dateInput );
					return false;
				}
				state.date = date;
				if ( ! state.time ) {
					showError( S.selectTime || 'Choose a time.', slotsBox );
					return false;
				}
				return true;
			}

			if ( step === 4 ) {
				if ( ! nameInput.value.trim() ) {
					showError( S.nameRequired || 'Enter your name.', nameInput );
					return false;
				}
				if ( ! emailInput.value.trim() || ( emailInput.checkValidity && ! emailInput.checkValidity() ) ) {
					showError( S.emailRequired || 'Enter a valid email.', emailInput );
					return false;
				}
				if ( phoneInput.hasAttribute( 'required' ) ) {
					var digits = phoneInput.value.replace( /\D+/g, '' );
					if ( digits.length < 6 ) {
						showError( S.phoneRequired || 'Enter your phone number.', phoneInput );
						return false;
					}
				}
				if ( consentInput && consentInput.hasAttribute( 'required' ) && ! consentInput.checked ) {
					showError( S.consentRequired || 'Accept the policy to continue.', consentInput );
					return false;
				}
				return true;
			}

			return true;
		}

		function collectPayload() {
			return {
				service_id: state.service,
				staff_id: state.staff,
				date: state.date || dateInput.value,
				time: state.time,
				name: nameInput.value.trim(),
				email: emailInput.value.trim(),
				phone: phoneInput.value.trim(),
				notes: notesInput ? notesInput.value.trim() : '',
				consent: consentInput && consentInput.checked ? 1 : 0,
				form_token: tokenInput ? tokenInput.value : '',
				qvb_hp: hpInput ? hpInput.value : '',
			};
		}

		function showConfirmation( result ) {
			var indicatorsBox = q( root, '[data-qvb-indicators]' );
			if ( indicatorsBox ) {
				indicatorsBox.hidden = true;
			}
			form.hidden = true;
			confirmation.hidden = false;

			setText( q( root, '[data-qvb-confirm-title]' ), result.status === 'pending' ? S.receivedTitle : S.confirmedTitle );
			setText( q( root, '[data-qvb-confirm-message]' ), result.message || '' );
			setText( q( root, '[data-qvb-confirm-reference]' ), result.reference || '' );

			var booking = result.booking || {};
			setText( q( root, '[data-qvb-confirm-service]' ), booking.service_name || '' );
			setText(
				q( root, '[data-qvb-confirm-when]' ),
				( booking.date_label || '' ) + ( booking.time_label ? ' · ' + booking.time_label : '' )
			);

			var manageLink = q( root, '[data-qvb-manage-link]' );
			if ( manageLink && result.manage_url ) {
				manageLink.href = result.manage_url;
			}

			scrollTo( confirmation );
			confirmation.focus();

			if ( result.manage_url && window.history && window.history.replaceState ) {
				try {
					var url = new URL( result.manage_url );
					window.history.replaceState( {}, '', url.pathname + url.search );
				} catch ( error ) {
					// Ignore history failures (e.g. sandboxed frames).
				}
			}
		}

		function submitBooking() {
			if ( ! validateStep( 3 ) || ! validateStep( 4 ) ) {
				return;
			}

			clearError();
			var original = submitButton.textContent;
			submitButton.disabled = true;
			submitButton.textContent = S.submitting || 'Booking…';
			setBusy( form, true );

			api( 'bookings', { method: 'POST', body: collectPayload() } )
				.then( function ( result ) {
					showConfirmation( result );
				} )
				.catch( function ( error ) {
					var code = error.code || '';
					showError( error.message || S.submitError || 'Something went wrong.' );

					if (
						code === 'qvb_slot_taken' ||
						code === 'qvb_slot_unavailable' ||
						code === 'qvb_min_notice' ||
						code === 'qvb_max_advance' ||
						code === 'qvb_past_date' ||
						code === 'qvb_invalid_datetime'
					) {
						setStep( 3, { scroll: true } );
						state.time = '';
						setTime( '' );
						loadSlots( true );
					} else if (
						code === 'qvb_name_required' ||
						code === 'qvb_email_invalid' ||
						code === 'qvb_phone_required' ||
						code === 'qvb_phone_invalid' ||
						code === 'qvb_consent_required'
					) {
						setStep( 4, { scroll: true } );
					} else if ( code === 'qvb_invalid_nonce' || code === 'qvb_expired_form' ) {
						showError( S.reloadPage || 'Please reload the page.' );
					}
				} )
				.finally( function () {
					submitButton.disabled = false;
					submitButton.textContent = original;
					setBusy( form, false );
				} );
		}

		/* Event wiring ---------------------------------------------------- */

		form.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target.matches( '[data-qvb-service-input]' ) ) {
				state.service = parseInt( target.value, 10 ) || 0;
				state.time = '';
				state.timeLabel = '';
				if ( timeInput ) {
					timeInput.value = '';
				}
				updateStaffVisibility();
				if ( dateInput.value ) {
					state.date = dateInput.value;
					loadSlots();
				}
				if ( current === 3 ) {
					loadDays();
				}
				clearError();
			}

			if ( target.matches( '[data-qvb-staff-input]' ) ) {
				state.staff = parseInt( target.value, 10 ) || 0;
				state.time = '';
				state.timeLabel = '';
				if ( timeInput ) {
					timeInput.value = '';
				}
				if ( state.date ) {
					loadSlots();
				}
				if ( current === 3 ) {
					loadDays();
				}
			}
		} );

		dateInput.addEventListener( 'change', function () {
			state.date = dateInput.value;
			state.time = '';
			state.timeLabel = '';
			qa( daysBox, '.qvb-day-chip' ).forEach( function ( chip ) {
				chip.classList.toggle( 'is-selected', chip.getAttribute( 'data-date' ) === state.date );
			} );
			if ( state.date ) {
				loadSlots();
			}
		} );

		nextButton.addEventListener( 'click', function () {
			if ( ! validateStep( current ) ) {
				return;
			}
			if ( current < 5 ) {
				setStep( current + 1, { scroll: true } );
			}
		} );

		backButton.addEventListener( 'click', function () {
			clearError();
			if ( current > 1 ) {
				setStep( current - 1, { scroll: true } );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( current < 5 ) {
				if ( validateStep( current ) ) {
					setStep( current + 1, { scroll: true } );
				}
				return;
			}
			submitBooking();
		} );

		/* Initial state ---------------------------------------------------- */

		var checkedService = q( form, '[data-qvb-service-input]:checked' );
		var checkedStaff = q( form, '[data-qvb-staff-input]:checked' );

		if ( checkedStaff ) {
			state.staff = parseInt( checkedStaff.value, 10 ) || 0;
		}

		if ( checkedService ) {
			state.service = parseInt( checkedService.value, 10 ) || 0;
			updateStaffVisibility();
			setStep( 2 );
		} else {
			setStep( 1 );
		}

		indicators.forEach( function ( indicator ) {
			indicator.addEventListener( 'click', function () {
				var index = parseInt( indicator.getAttribute( 'data-qvb-indicator' ), 10 );
				if ( index && index < current ) {
					setStep( index, { scroll: true } );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Manage booking                                                     */
	/* ------------------------------------------------------------------ */

	function initManage( root ) {
		var reference = root.getAttribute( 'data-reference' ) || '';
		var token = root.getAttribute( 'data-token' ) || '';
		var serviceId = parseInt( root.getAttribute( 'data-service-id' ), 10 ) || 0;
		var staffId = parseInt( root.getAttribute( 'data-staff-id' ), 10 ) || 0;
		var bookingId = parseInt( root.getAttribute( 'data-booking-id' ), 10 ) || 0;

		var cancelPanel = q( root, '[data-qvb-cancel-panel]' );
		var reschedulePanel = q( root, '[data-qvb-reschedule-panel]' );
		var cancelButton = q( root, '[data-qvb-open-cancel]' );
		var rescheduleButton = q( root, '[data-qvb-open-reschedule]' );
		var closeButtons = qa( root, '[data-qvb-close-panel]' );
		var errorBox = q( root, '[data-qvb-manage-error]' );
		var successBox = q( root, '[data-qvb-manage-success]' );
		var cancelSubmit = q( root, '[data-qvb-cancel-submit]' );
		var cancelReason = q( root, '[data-qvb-cancel-reason]' );
		var rescheduleSubmit = q( root, '[data-qvb-reschedule-submit]' );
		var dateInput = q( root, '[data-qvb-manage-date]' );
		var daysBox = q( root, '[data-qvb-manage-days]' );
		var slotsBox = q( root, '[data-qvb-manage-slots]' );
		var timeInput = q( root, '[data-qvb-manage-time]' );

		var selectedTime = '';
		var daysRequest = 0;
		var slotsRequest = 0;

		if ( dateInput ) {
			dateInput.min = root.getAttribute( 'data-min-date' ) || cfg.today || '';
			dateInput.max = root.getAttribute( 'data-max-date' ) || '';
		}

		function showError( message ) {
			if ( errorBox ) {
				errorBox.textContent = message;
				errorBox.hidden = false;
			}
			if ( successBox ) {
				successBox.hidden = true;
			}
		}

		function showSuccess( message ) {
			if ( successBox ) {
				successBox.textContent = message;
				successBox.hidden = false;
			}
			if ( errorBox ) {
				errorBox.hidden = true;
			}
		}

		function openPanel( panel ) {
			[ cancelPanel, reschedulePanel ].forEach( function ( item ) {
				if ( item ) {
					item.hidden = item !== panel;
				}
			} );
			if ( panel ) {
				panel.hidden = false;
				scrollTo( panel );
				var input = q( panel, 'textarea, input' );
				if ( input ) {
					input.focus();
				}
			}
		}

		function closePanels() {
			[ cancelPanel, reschedulePanel ].forEach( function ( item ) {
				if ( item ) {
					item.hidden = true;
				}
			} );
		}

		function loadDays() {
			if ( ! serviceId ) {
				return;
			}
			var request = ++daysRequest;
			setBusy( daysBox, true );
			daysBox.innerHTML = '<span class="qvb-muted qvb-small">' + ( S.loading || 'Loading…' ) + '</span>';

			api(
				'availability/days?service_id=' +
					encodeURIComponent( serviceId ) +
					'&staff_id=' +
					encodeURIComponent( staffId ) +
					'&start_date=' +
					encodeURIComponent( cfg.today || '' ) +
					'&days=14'
			)
				.then( function ( response ) {
					if ( request !== daysRequest ) {
						return;
					}
					renderDays( response.days || [] );
				} )
				.catch( function () {
					if ( request !== daysRequest ) {
						return;
					}
					clearNode( daysBox );
					daysBox.appendChild( make( 'span', 'qvb-muted qvb-small', S.loadError || 'Could not load availability.' ) );
				} )
				.finally( function () {
					if ( request === daysRequest ) {
						setBusy( daysBox, false );
					}
				} );
		}

		function renderDays( days ) {
			clearNode( daysBox );
			var available = days.filter( function ( day ) {
				return day.available;
			} );

			if ( ! available.length ) {
				daysBox.appendChild( make( 'span', 'qvb-muted qvb-small', S.noDates || 'No availability found.' ) );
				return;
			}

			daysBox.appendChild( make( 'span', 'qvb-quick-dates__label', S.nextAvailable || 'Next available' ) );

			available.slice( 0, 10 ).forEach( function ( day ) {
				var button = make( 'button', 'qvb-day-chip' );
				button.type = 'button';
				button.appendChild( document.createTextNode( day.label ) );
				button.appendChild( make( 'small', null, ( day.count || 0 ) + ' ' + ( S.slotsAvailable || 'times' ) ) );
				button.classList.toggle( 'is-selected', dateInput.value === day.date );
				button.addEventListener( 'click', function () {
					dateInput.value = day.date;
					qa( daysBox, '.qvb-day-chip' ).forEach( function ( chip ) {
						chip.classList.toggle( 'is-selected', chip === button );
					} );
					loadSlots();
				} );
				daysBox.appendChild( button );
			} );
		}

		function loadSlots() {
			var date = dateInput.value;
			if ( ! date ) {
				return;
			}
			var request = ++slotsRequest;
			selectedTime = '';
			timeInput.value = '';
			rescheduleSubmit.disabled = true;
			setBusy( slotsBox, true );
			slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.loadingSlots || 'Loading times…' ) + '</p>';

			api(
				'availability?service_id=' +
					encodeURIComponent( serviceId ) +
					'&staff_id=' +
					encodeURIComponent( staffId ) +
					'&date=' +
					encodeURIComponent( date ) +
					'&exclude=' +
					encodeURIComponent( bookingId ) +
					'&reference=' +
					encodeURIComponent( reference ) +
					'&token=' +
					encodeURIComponent( token )
			)
				.then( function ( response ) {
					if ( request !== slotsRequest ) {
						return;
					}
					renderSlots( response.slots || [] );
				} )
				.catch( function () {
					if ( request !== slotsRequest ) {
						return;
					}
					slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.loadError || 'Could not load times.' ) + '</p>';
				} )
				.finally( function () {
					if ( request === slotsRequest ) {
						setBusy( slotsBox, false );
					}
				} );
		}

		function renderSlots( slots ) {
			clearNode( slotsBox );

			if ( ! slots.length ) {
				slotsBox.innerHTML = '<p class="qvb-empty">' + ( S.noSlots || 'No times are available.' ) + '</p>';
				return;
			}

			var grid = make( 'div', 'qvb-slot-grid' );
			slots.forEach( function ( slot ) {
				var button = make( 'button', 'qvb-slot', slot.label );
				button.type = 'button';
				button.setAttribute( 'aria-pressed', 'false' );
				button.addEventListener( 'click', function () {
					selectedTime = slot.time;
					timeInput.value = slot.time;
					rescheduleSubmit.disabled = false;
					qa( slotsBox, '.qvb-slot' ).forEach( function ( item ) {
						var active = item === button;
						item.classList.toggle( 'is-selected', active );
						item.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );
				} );
				grid.appendChild( button );
			} );
			slotsBox.appendChild( grid );
		}

		function reloadSoon( message ) {
			showSuccess( message );
			window.setTimeout( function () {
				window.location.reload();
			}, 900 );
		}

		if ( cancelButton && cancelPanel ) {
			cancelButton.addEventListener( 'click', function () {
				openPanel( cancelPanel );
			} );
		}

		if ( rescheduleButton && reschedulePanel ) {
			rescheduleButton.addEventListener( 'click', function () {
				openPanel( reschedulePanel );
				loadDays();
			} );
		}

		closeButtons.forEach( function ( button ) {
			button.addEventListener( 'click', closePanels );
		} );

		if ( dateInput ) {
			dateInput.addEventListener( 'change', loadSlots );
		}

		if ( cancelSubmit ) {
			cancelSubmit.addEventListener( 'click', function () {
				if ( ! window.confirm( S.cancelConfirm || 'Cancel this booking?' ) ) {
					return;
				}
				cancelSubmit.disabled = true;
				api( 'booking/cancel', {
					method: 'POST',
					body: {
						reference: reference,
						token: token,
						reason: cancelReason ? cancelReason.value : '',
					},
				} )
					.then( function ( response ) {
						reloadSoon( ( response && response.message ) || S.cancelled || 'Booking cancelled.' );
					} )
					.catch( function ( error ) {
						showError( error.message || S.somethingWrong );
						cancelSubmit.disabled = false;
					} );
			} );
		}

		if ( rescheduleSubmit ) {
			rescheduleSubmit.addEventListener( 'click', function () {
				if ( ! selectedTime ) {
					showError( S.selectTime || 'Choose a time.' );
					return;
				}
				if ( ! window.confirm( S.rescheduleConfirm || 'Move your appointment?' ) ) {
					return;
				}
				rescheduleSubmit.disabled = true;
				api( 'booking/reschedule', {
					method: 'POST',
					body: {
						reference: reference,
						token: token,
						date: dateInput.value,
						time: selectedTime,
					},
				} )
					.then( function ( response ) {
						reloadSoon( ( response && response.message ) || S.rescheduled || 'Booking rescheduled.' );
					} )
					.catch( function ( error ) {
						showError( error.message || S.somethingWrong );
						rescheduleSubmit.disabled = false;
						if ( error.code === 'qvb_slot_taken' || error.code === 'qvb_slot_unavailable' ) {
							loadSlots();
						}
					} );
			} );
		}
	}

	function boot() {
		qa( document, '[data-qvb-app]' ).forEach( function ( root ) {
			try {
				initBooking( root );
			} catch ( error ) {
				// A failing widget must not break the rest of the page.
				if ( window.console && window.console.error ) {
					window.console.error( 'Qevora Booking:', error );
				}
			}
		} );

		qa( document, '[data-qvb-manage]' ).forEach( function ( root ) {
			try {
				initManage( root );
			} catch ( error ) {
				if ( window.console && window.console.error ) {
					window.console.error( 'Qevora Booking:', error );
				}
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
