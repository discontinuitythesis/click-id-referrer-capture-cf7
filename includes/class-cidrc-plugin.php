<?php
/**
 * Plugin bootstrap: asset loading, shortcode, cron and lifecycle.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires every part of the plugin together.
 */
class CIDRC_Plugin {

	/**
	 * Daily purge cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cidrc_daily_purge';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( self::CRON_HOOK, array( 'CIDRC_Log', 'purge' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		$settings = new CIDRC_Settings();
		add_action( 'admin_init', array( $settings, 'register' ) );

		$admin = new CIDRC_Admin();
		$admin->init();

		$export = new CIDRC_Export();
		$export->init();

		$cf7 = new CIDRC_CF7();
		$cf7->init();
	}

	/**
	 * Loads the translations. Harmless on WordPress.org, useful elsewhere.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'click-id-referrer-capture-cf7', false, dirname( CIDRC_BASENAME ) . '/languages' );
	}

	/**
	 * Runs the schema check and makes sure the purge event exists.
	 *
	 * @return void
	 */
	public function admin_init() {
		CIDRC_Log::maybe_install();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Registers the shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'cidrc_debug', array( $this, 'debug_shortcode' ) );
	}

	/**
	 * Enqueues the front end capture script.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_script(
			'cidrc-capture',
			CIDRC_URL . 'assets/js/cidrc-capture.js',
			array(),
			CIDRC_VERSION,
			true
		);

		$config = array(
			'cookieName'         => cidrc_cookie_name(),
			'cookieDays'         => cidrc_cookie_days(),
			'params'             => cidrc_captured_params(),
			'fields'             => cidrc_hidden_fields(),
			'storageMode'        => cidrc_get_setting( 'storage_mode', 'always' ),
			'consentCookieName'  => cidrc_get_setting( 'consent_cookie_name', '' ),
			'consentCookieValue' => cidrc_get_setting( 'consent_cookie_value', '' ),
			'maxLength'          => 500,
		);

		wp_add_inline_script( 'cidrc-capture', 'window.cidrcConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Renders the debug shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Markup.
	 */
	public function debug_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'public' => 'no' ), $atts, 'cidrc_debug' );

		if ( 'yes' !== strtolower( (string) $atts['public'] ) && ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$values = cidrc_get_values();
		$rows   = array();

		foreach ( cidrc_captured_params() as $param ) {
			$first = isset( $values['first'][ $param ] ) ? $values['first'][ $param ] : '';
			$last  = isset( $values['last'][ $param ] ) ? $values['last'][ $param ] : '';

			if ( '' === $first && '' === $last ) {
				continue;
			}

			$rows[] = array( cidrc_label_for( $param ), $first, $last );
		}

		foreach ( array( 'first_referrer', 'first_landing_page', 'first_seen', 'last_seen' ) as $key ) {
			if ( empty( $values[ $key ] ) ) {
				continue;
			}

			$rows[] = array( cidrc_label_for( $key ), $values[ $key ], '' );
		}

		$out = '<table class="cidrc-debug"><thead><tr>';
		$out .= '<th>' . esc_html__( 'Field', 'click-id-referrer-capture-cf7' ) . '</th>';
		$out .= '<th>' . esc_html__( 'First touch', 'click-id-referrer-capture-cf7' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Last touch', 'click-id-referrer-capture-cf7' ) . '</th>';
		$out .= '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			$out .= '<tr><td colspan="3">' . esc_html__( 'Nothing has been captured for this visitor yet.', 'click-id-referrer-capture-cf7' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . esc_html( $row[1] ) . '</td><td>' . esc_html( $row[2] ) . '</td></tr>';
		}

		$out .= '</tbody></table>';

		return $out;
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		CIDRC_Log::install();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		if ( false === get_option( CIDRC_Settings::OPTION, false ) ) {
			add_option( CIDRC_Settings::OPTION, CIDRC_Settings::defaults() );
		}
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
