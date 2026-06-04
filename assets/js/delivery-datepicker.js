jQuery( function( $ ) {
	'use strict';

	var inputValueSetter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	var selectors = [
		'input[data-delidaam="delivery_date"]',
		'input#order-delidaam-delivery_date',
		'input[name="order_delidaam/delivery_date"]',
		'input[name="delidaam/delivery_date"]',
		'input#delidaam_delivery_date',
	].join( ', ' );

	function getBlockedDates() {
		if (
			window.delidaamCheckout &&
			Array.isArray( window.delidaamCheckout.blackoutDates )
		) {
			return window.delidaamCheckout.blackoutDates;
		}

		return [];
	}

	function isBlockCheckoutField( input ) {
		return (
			input.id === 'order-delidaam-delivery_date' ||
			input.name === 'order_delidaam/delivery_date'
		);
	}

	function setInputValue( input, value ) {
		var previousValue = input.value;
		var tracker = input._valueTracker;

		inputValueSetter.call( input, value );
		input.setAttribute( 'value', value );

		if ( tracker ) {
			tracker.setValue( previousValue );
		}
	}

	function triggerInputEvents( input ) {
		input.dispatchEvent(
			new Event(
				'input',
				{
					bubbles: true,
				}
			)
		);

		input.dispatchEvent(
			new Event(
				'change',
				{
					bubbles: true,
				}
			)
		);

		input.dispatchEvent(
			new Event(
				'blur',
				{
					bubbles: true,
				}
			)
		);
	}

	function isBlockedDate( value ) {
		return -1 !== getBlockedDates().indexOf( value );
	}

	function formatSelectedDate( input ) {
		var selectedDate = $( input ).datepicker( 'getDate' );

		if ( ! selectedDate ) {
			return '';
		}

		return $.datepicker.formatDate( 'yy-mm-dd', selectedDate );
	}

	function applyBlockFieldValue( input, value ) {
		var liveInput = document.getElementById( input.id ) || input;

		liveInput.focus();

		if ( document.queryCommandSupported && document.queryCommandSupported( 'insertText' ) ) {
			liveInput.select();
			document.execCommand( 'insertText', false, value );
		} else {
			setInputValue( liveInput, value );
		}

		liveInput.setAttribute( 'value', value );
		liveInput.dispatchEvent(
			new KeyboardEvent(
				'keyup',
				{
					bubbles: true,
					key: '0',
				}
			)
		);

		triggerInputEvents( liveInput );
		$( liveInput ).trigger( 'change' );
	}

	function syncSelectedDate( input, value ) {
		var syncValue = function() {
			var liveInput = document.getElementById( input.id ) || input;

			if ( isBlockCheckoutField( liveInput ) ) {
				applyBlockFieldValue( liveInput, value );
			} else {
				setInputValue( liveInput, value );
				triggerInputEvents( liveInput );
				$( liveInput ).trigger( 'change' );
			}
		};

		syncValue();

		window.setTimeout( syncValue, 0 );
		window.setTimeout( syncValue, 50 );

		if ( window.requestAnimationFrame ) {
			window.requestAnimationFrame( syncValue );
		}
	}

	function initDatepicker( input ) {
		var $input = $( input );

		if ( 'function' !== typeof $.fn.datepicker ) {
			return;
		}

		if ( $input.data( 'delidaamReady' ) ) {
			return;
		}

		$input.data( 'delidaamReady', true );
		$input.attr( 'autocomplete', 'off' );

		$input.datepicker(
			{
				dateFormat: 'yy-mm-dd',
				minDate: 0,
				beforeShow: function( currentInput, instance ) {
					window.setTimeout(
						function() {
							instance.dpDiv.css( 'z-index', 100000 );
						},
						0
					);
				},
				beforeShowDay: function( date ) {
					var blockedDates = getBlockedDates();
					var dateString = $.datepicker.formatDate( 'yy-mm-dd', date );

					return [ -1 === blockedDates.indexOf( dateString ) ];
				},
				onSelect: function( dateText ) {
					syncSelectedDate( input, dateText );
				},
				onClose: function() {
					var selectedDate = formatSelectedDate( input );

					if ( selectedDate ) {
						syncSelectedDate( input, selectedDate );
					}
				},
			}
		);
	}

	function syncBeforeSubmit() {
		$( selectors ).each(
			function() {
				var input = this;
				var selectedDate = formatSelectedDate( input );

				if ( selectedDate ) {
					syncSelectedDate( input, selectedDate );
				}
			}
		);
	}

	$( document.body ).on(
		'focus click',
		selectors,
		function() {
			initDatepicker( this );
			$( this ).datepicker( 'show' );
		}
	);

	$( document.body ).on(
		'click',
		'.wc-block-components-checkout-place-order-button',
		function() {
			syncBeforeSubmit();
		}
	);

	// ---- Block Checkout: Keep floating labels above delivery fields ----
	// WooCommerce Blocks uses a floating label that sits inside the field when
	// it is empty. Adding the `is-active` class (which WooCommerce Blocks itself
	// uses when a field has a value or focus) keeps the label above at all times,
	// matching the classic checkout label-above design.
	// This is applied to both the delivery date text input and the delivery time
	// slot select / combobox.

	// Per-field MutationObservers keyed by element ID.
	var blockFieldObservers = {};

	function ensureDeliveryLabelActive( wrapper ) {
		if ( wrapper && ! wrapper.classList.contains( 'is-active' ) ) {
			wrapper.classList.add( 'is-active' );
		}
	}

	function tryFixBlockField( fieldId ) {
		var input = document.getElementById( fieldId );
		if ( ! input ) {
			return;
		}

		var wrapper = input.closest(
			'.wc-block-components-text-input, .wc-block-components-combobox, .wc-block-components-select-control'
		);
		if ( ! wrapper ) {
			return;
		}

		ensureDeliveryLabelActive( wrapper );

		// Re-add is-active whenever WooCommerce Blocks JS removes it (e.g. on blur).
		if ( blockFieldObservers[ fieldId ] ) {
			blockFieldObservers[ fieldId ].disconnect();
		}

		blockFieldObservers[ fieldId ] = new MutationObserver( function() {
			ensureDeliveryLabelActive( wrapper );
		} );

		blockFieldObservers[ fieldId ].observe( wrapper, {
			attributes: true,
			attributeFilter: [ 'class' ],
		} );
	}

	function trySetupBlockLabelFix() {
		tryFixBlockField( 'order-delidaam-delivery_date' );
		tryFixBlockField( 'order-delidaam-delivery_time_slot' );
	}

	// Apply immediately (for classic checkout or block checkout already rendered).
	trySetupBlockLabelFix();

	// Re-apply after each React render (block checkout mounts asynchronously
	// and may recreate DOM nodes on updates such as validation errors).
	new MutationObserver( function() {
		trySetupBlockLabelFix();
	} ).observe( document.body, { childList: true, subtree: true } );
} );
