<?php
/**
 * Elementor widget.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qevora Booking widget.
 */
class QVB_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'qevora_booking';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Qevora Booking', 'qevora-booking' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'qevora' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'booking', 'appointment', 'salon', 'spa', 'massage', 'qevora' );
	}

	/**
	 * Registers controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'qvb_content',
			array(
				'label' => __( 'Booking form', 'qevora-booking' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$service_options = array( 0 => __( 'All services', 'qevora-booking' ) );
		foreach ( QVB_Services::active() as $service ) {
			$service_options[ (int) $service->id ] = $service->name;
		}

		$this->add_control(
			'service_id',
			array(
				'label'   => __( 'Service', 'qevora-booking' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $service_options,
				'default' => 0,
			)
		);

		$staff_options = array( 0 => __( 'Any available staff', 'qevora-booking' ) );
		foreach ( QVB_Staff::active() as $member ) {
			$staff_options[ (int) $member->id ] = $member->name;
		}

		$this->add_control(
			'staff_id',
			array(
				'label'   => __( 'Staff member', 'qevora-booking' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $staff_options,
				'default' => 0,
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'qevora-booking' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Book your appointment', 'qevora-booking' ),
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Show heading', 'qevora-booking' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'qevora-booking' ),
				'label_off'    => __( 'Hide', 'qevora-booking' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renders the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$atts = array(
			'service'    => isset( $settings['service_id'] ) ? absint( $settings['service_id'] ) : 0,
			'staff'      => isset( $settings['staff_id'] ) ? absint( $settings['staff_id'] ) : 0,
			'title'      => isset( $settings['title'] ) ? sanitize_text_field( $settings['title'] ) : '',
			'show_title' => isset( $settings['show_title'] ) && 'yes' === $settings['show_title'] ? '1' : '0',
		);

		echo QVB_Shortcodes::render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped internally.
	}
}
