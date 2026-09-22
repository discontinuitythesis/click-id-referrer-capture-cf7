<?php
/**
 * Plugin Name:       Click ID & Referrer Capture for Contact Form 7
 * Plugin URI:        https://firepixel.co.uk/wordpress-click-id-capture
 * Description:       Captures gclid, gbraid, wbraid, msclkid, UTMs and the referrer, adds them to Contact Form 7 submissions and exports offline conversion CSV files.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Fire Pixel (Ben Luong)
 * Author URI:        https://firepixel.co.uk
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       click-id-referrer-capture-cf7
 * Domain Path:       /languages
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

define( 'CIDRC_VERSION', '1.0.0' );
define( 'CIDRC_FILE', __FILE__ );
define( 'CIDRC_DIR', plugin_dir_path( __FILE__ ) );
define( 'CIDRC_URL', plugin_dir_url( __FILE__ ) );
define( 'CIDRC_BASENAME', plugin_basename( __FILE__ ) );

require_once CIDRC_DIR . 'includes/functions.php';
require_once CIDRC_DIR . 'includes/formatting.php';
require_once CIDRC_DIR . 'includes/class-cidrc-settings.php';
require_once CIDRC_DIR . 'includes/class-cidrc-log.php';
require_once CIDRC_DIR . 'includes/class-cidrc-cf7.php';
require_once CIDRC_DIR . 'includes/class-cidrc-webhook.php';
require_once CIDRC_DIR . 'includes/class-cidrc-export.php';
require_once CIDRC_DIR . 'includes/class-cidrc-admin-fields.php';
require_once CIDRC_DIR . 'includes/class-cidrc-admin.php';
require_once CIDRC_DIR . 'includes/class-cidrc-plugin.php';

register_activation_hook( __FILE__, array( 'CIDRC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CIDRC_Plugin', 'deactivate' ) );

/**
 * Returns the single plugin instance.
 *
 * @return CIDRC_Plugin Plugin instance.
 */
function cidrc() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new CIDRC_Plugin();
	}

	return $instance;
}

cidrc()->init();
