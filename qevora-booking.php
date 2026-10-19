<?php
/**
 * Plugin Name:       Qevora Booking
 * Plugin URI:        https://qevorasoftware.com/qevora-booking
 * Description:       All-in-one appointment booking for spas, salons, beauty parlours and massage centres. Free online booking with pay at the salon.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Qevora Software
 * Author URI:        https://qevorasoftware.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       qevora-booking
 * Domain Path:       /languages
 * Network:           true
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

define( 'QVB_VERSION', '1.0.1' );
define( 'QVB_DB_VERSION', '1.0.0' );
define( 'QVB_FILE', __FILE__ );
define( 'QVB_DIR', plugin_dir_path( __FILE__ ) );
define( 'QVB_URL', plugin_dir_url( __FILE__ ) );
define( 'QVB_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Core includes. These files must stay loadable in every request (front end,
 * admin, REST, WP-Cron and WP-CLI) because they contain the domain layer.
 */
require_once QVB_DIR . 'includes/class-database.php';
require_once QVB_DIR . 'includes/class-utils.php';
require_once QVB_DIR . 'includes/class-settings.php';
require_once QVB_DIR . 'includes/class-security.php';
require_once QVB_DIR . 'includes/class-capabilities.php';
require_once QVB_DIR . 'includes/class-categories.php';
require_once QVB_DIR . 'includes/class-services.php';
require_once QVB_DIR . 'includes/class-staff.php';
require_once QVB_DIR . 'includes/class-availability.php';
require_once QVB_DIR . 'includes/class-customers.php';
require_once QVB_DIR . 'includes/class-bookings.php';
require_once QVB_DIR . 'includes/class-notifications.php';
require_once QVB_DIR . 'includes/class-cron.php';
require_once QVB_DIR . 'includes/class-privacy.php';
require_once QVB_DIR . 'includes/class-rest-api.php';
require_once QVB_DIR . 'includes/class-shortcodes.php';
require_once QVB_DIR . 'includes/class-activator.php';
require_once QVB_DIR . 'includes/class-deactivator.php';
require_once QVB_DIR . 'integrations/gutenberg/class-gutenberg.php';
require_once QVB_DIR . 'integrations/elementor/class-elementor.php';
require_once QVB_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'QVB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'QVB_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return QVB_Plugin
 */
function qevora_booking() {
	return QVB_Plugin::instance();
}

qevora_booking();
