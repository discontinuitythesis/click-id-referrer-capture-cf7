<?php
/**
 * Settings storage, registration and sanitisation.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the plugin option.
 */
class CIDRC_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'cidrc_settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const GROUP = 'cidrc_settings_group';

	/**
	 * Runtime cache of the option.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Registers the setting with WordPress.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array Default settings.
	 */
	public static function defaults() {
		return array(
			'storage_mode'         => 'always',
			'cookie_days'          => 90,
			'consent_cookie_name'  => '',
			'consent_cookie_value' => '',
			'auto_append_summary'  => 0,
			'keep_log'             => 1,
			'retention_days'       => 90,
			'conversion_name'      => 'Website Lead',
			'conversion_value'     => '0',
			'currency'             => 'GBP',
			'webhook_url'          => '',
			'webhook_secret'       => '',
			'webhook_raw_email'    => 0,
			'form_overrides'       => '',
		);
	}

	/**
	 * Returns every setting, merged over the defaults.
	 *
	 * @return array Settings.
	 */
	public static function get_all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );

			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			self::$cache = array_merge( self::defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Clears the runtime cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitises the submitted settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array Clean settings.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = $defaults;

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		$mode                   = isset( $input['storage_mode'] ) ? sanitize_key( $input['storage_mode'] ) : 'always';
		$clean['storage_mode']  = in_array( $mode, array( 'always', 'consent' ), true ) ? $mode : 'always';
		$clean['cookie_days']   = isset( $input['cookie_days'] ) ? max( 1, min( 400, absint( $input['cookie_days'] ) ) ) : 90;
		$clean['retention_days'] = isset( $input['retention_days'] ) ? min( 3650, absint( $input['retention_days'] ) ) : 90;

		$clean['consent_cookie_name']  = isset( $input['consent_cookie_name'] ) ? sanitize_text_field( $input['consent_cookie_name'] ) : '';
		$clean['consent_cookie_value'] = isset( $input['consent_cookie_value'] ) ? sanitize_text_field( $input['consent_cookie_value'] ) : '';

		$clean['auto_append_summary'] = empty( $input['auto_append_summary'] ) ? 0 : 1;
		$clean['keep_log']            = empty( $input['keep_log'] ) ? 0 : 1;
		$clean['webhook_raw_email']   = empty( $input['webhook_raw_email'] ) ? 0 : 1;

		$clean['conversion_name'] = isset( $input['conversion_name'] ) ? sanitize_text_field( $input['conversion_name'] ) : '';

		$value                     = isset( $input['conversion_value'] ) ? sanitize_text_field( $input['conversion_value'] ) : '0';
		$value                     = preg_replace( '/[^0-9.\-]/', '', $value );
		$clean['conversion_value'] = ( '' === $value || null === $value ) ? '0' : (string) round( (float) $value, 2 );

		$currency          = isset( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : 'GBP';
		$currency          = preg_replace( '/[^A-Z]/', '', $currency );
		$clean['currency'] = ( is_string( $currency ) && 3 === strlen( $currency ) ) ? $currency : 'GBP';

		$url                  = isset( $input['webhook_url'] ) ? esc_url_raw( trim( (string) $input['webhook_url'] ) ) : '';
		$clean['webhook_url'] = ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) ? $url : '';

		$clean['webhook_secret'] = isset( $input['webhook_secret'] ) ? sanitize_text_field( $input['webhook_secret'] ) : '';

		$clean['form_overrides'] = isset( $input['form_overrides'] ) ? self::sanitize_overrides( $input['form_overrides'] ) : '';

		self::flush();

		return $clean;
	}

	/**
	 * Sanitises the per form override block.
	 *
	 * Each line is expected to be "form id | conversion name | conversion value".
	 *
	 * @param string $raw Raw textarea contents.
	 * @return string Clean textarea contents.
	 */
	public static function sanitize_overrides( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$out   = array();

		if ( ! is_array( $lines ) ) {
			return '';
		}

		foreach ( $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );

			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$form_id = absint( $parts[0] );

			if ( 0 === $form_id ) {
				continue;
			}

			$name  = $parts[1];
			$value = isset( $parts[2] ) ? preg_replace( '/[^0-9.\-]/', '', $parts[2] ) : '';

			$out[] = $form_id . ' | ' . $name . ( '' === $value ? '' : ' | ' . $value );
		}

		return implode( "\n", $out );
	}

	/**
	 * Returns the conversion name and value for a given form.
	 *
	 * @param int $form_id Contact Form 7 form identifier.
	 * @return array Array with the name, value and currency keys.
	 */
	public static function conversion_for_form( $form_id ) {
		$settings = self::get_all();
		$result   = array(
			'name'     => (string) $settings['conversion_name'],
			'value'    => (float) $settings['conversion_value'],
			'currency' => (string) $settings['currency'],
		);

		$lines = preg_split( '/\r\n|\r|\n/', (string) $settings['form_overrides'] );

		if ( ! is_array( $lines ) ) {
			return $result;
		}

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( count( $parts ) < 2 || absint( $parts[0] ) !== absint( $form_id ) ) {
				continue;
			}

			$result['name'] = $parts[1];

			if ( isset( $parts[2] ) && '' !== $parts[2] ) {
				$result['value'] = (float) $parts[2];
			}

			break;
		}

		return $result;
	}
}
