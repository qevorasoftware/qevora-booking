/**
 * Qevora Booking block editor UI.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;

	registerBlockType( 'qevora/booking', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var state = useState( null );
			var services = state[ 0 ];
			var setServices = state[ 1 ];

			useEffect( function () {
				wp.apiFetch( { path: '/qevora/v1/services' } )
					.then( function ( response ) {
						setServices( response && response.services ? response.services : [] );
					} )
					.catch( function () {
						setServices( [] );
					} );
			}, [] );

			var options = [ { label: __( 'All services', 'qevora-booking' ), value: 0 } ];
			( services || [] ).forEach( function ( service ) {
				options.push( { label: service.name, value: service.id } );
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Booking form', 'qevora-booking' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Service', 'qevora-booking' ),
							help: __( 'Preselect a service, or let the customer choose.', 'qevora-booking' ),
							value: attributes.serviceId,
							options: options,
							onChange: function ( value ) {
								setAttributes( { serviceId: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( TextControl, {
							label: __( 'Heading', 'qevora-booking' ),
							value: attributes.title,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show heading', 'qevora-booking' ),
							checked: !! attributes.showTitle,
							onChange: function ( value ) {
								setAttributes( { showTitle: value } );
							},
						} )
					)
				),
				el(
					'div',
					useBlockProps( { className: 'qvb-block-preview' } ),
					el(
						Placeholder,
						{
							icon: 'calendar-alt',
							label: __( 'Qevora Booking', 'qevora-booking' ),
							instructions: services === null
								? __( 'Loading services…', 'qevora-booking' )
								: __( 'The booking form is rendered on the front end.', 'qevora-booking' ),
						},
						services === null ? el( Spinner ) : null,
						services !== null && services.length === 0
							? el( 'p', null, __( 'Add a service in Qevora Booking → Services to get started.', 'qevora-booking' ) )
							: null
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
