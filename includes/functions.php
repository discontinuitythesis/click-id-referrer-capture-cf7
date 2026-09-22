<?php
/**
 * Shared helper functions.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the list of query parameters the plugin captures.
 *
 * @return array List of parameter names.
 */
function cidrc_captured_params() {
	$params = array(
		'gclid',
		'gbraid',
		'wbraid',
		'dclid',
		'msclkid',
		'fbclid',
		'ttclid',
		'li_fat_id',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'utm_id',
	);

	/**
	 * Filters the query parameters captured by the plugin.
	 *
	 * @param array $params List of parameter names.
	 */
	$params = apply_filters( 'cidrc_captured_params', $params );

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $params ) ) ) );
}

/**
 * Returns the cookie name used for storage.
 *
 * @return string Cookie name.
 */
function cidrc_cookie_name() {
	return 'cidrc';
}

/**
 * Returns the configured cookie lifetime in days.
 *
 * @return int Number of days, between 1 and 400.
 */
function cidrc_cookie_days() {
	$days = (int) cidrc_get_setting( 'cookie_days', 90 );

	/**
	 * Filters the cookie lifetime in days.
	 *
	 * @param int $days Number of days.
	 */
	$days = (int) apply_filters( 'cidrc_cookie_days', $days );

	return max( 1, min( 400, $days ) );
}

/**
 * Reads the stored attribution values for the current visitor.
 *
 * Returns an array with the keys first, last, first_referrer, first_landing_page,
 * first_seen and last_seen. Missing values are returned as empty strings.
 *
 * @return array Stored values.
 */
function cidrc_get_values() {
	$empty = array(
		'first'              => array(),
		'last'               => array(),
		'first_referrer'     => '',
		'first_landing_page' => '',
		'first_seen'         => '',
		'last_seen'          => '',
	);

	$name = cidrc_cookie_name();

	if ( empty( $_COOKIE[ $name ] ) ) {
		return $empty;
	}

	/*
	 * The cookie holds a JSON document written by encodeURIComponent(), so it is
	 * percent-encoded. sanitize_text_field() strips percent-encoded octets, which
	 * would destroy it, so the raw value is only unslashed here. Every individual
	 * value taken out of the decoded document is sanitised below instead.
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Percent-encoded JSON; every decoded value is sanitised individually below.
	$raw = is_string( $_COOKIE[ $name ] ) ? wp_unslash( $_COOKIE[ $name ] ) : '';

	if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 8192 ) {
		return $empty;
	}

	$decoded = json_decode( rawurldecode( $raw ), true );

	if ( ! is_array( $decoded ) ) {
		return $empty;
	}

	$values                       = $empty;
	$allowed                      = cidrc_captured_params();
	$values['first_referrer']     = isset( $decoded['first_referrer'] ) ? cidrc_clean_url_value( $decoded['first_referrer'] ) : '';
	$values['first_landing_page'] = isset( $decoded['first_landing_page'] ) ? cidrc_clean_url_value( $decoded['first_landing_page'] ) : '';
	$values['first_seen']         = isset( $decoded['first_seen'] ) ? cidrc_clean_value( $decoded['first_seen'], 40 ) : '';
	$values['last_seen']          = isset( $decoded['last_seen'] ) ? cidrc_clean_value( $decoded['last_seen'], 40 ) : '';

	foreach ( array( 'first', 'last' ) as $bucket ) {
		if ( empty( $decoded[ $bucket ] ) || ! is_array( $decoded[ $bucket ] ) ) {
			continue;
		}

		foreach ( $decoded[ $bucket ] as $key => $value ) {
			$key = sanitize_key( $key );

			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			$values[ $bucket ][ $key ] = cidrc_clean_value( $value, 500 );
		}
	}

	return $values;
}

/**
 * Sanitises a single stored value and caps its length.
 *
 * @param mixed $value  Raw value.
 * @param int   $length Maximum length.
 * @return string Cleaned value.
 */
function cidrc_clean_value( $value, $length = 500 ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$clean = sanitize_text_field( (string) $value );

	if ( strlen( $clean ) > $length ) {
		$clean = substr( $clean, 0, $length );
	}

	return $clean;
}

/**
 * Sanitises a referrer or landing page and caps its length.
 *
 * esc_url_raw() is used rather than sanitize_text_field() because the latter
 * removes percent-encoded octets, which are legitimate inside a query string.
 *
 * @param mixed $value  Raw value.
 * @param int   $length Maximum length.
 * @return string Cleaned value.
 */
function cidrc_clean_url_value( $value, $length = 500 ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$clean = esc_url_raw( trim( (string) $value ) );

	if ( strlen( $clean ) > $length ) {
		$clean = substr( $clean, 0, $length );
	}

	return $clean;
}

/**
 * Returns a single plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value returned when the setting is missing.
 * @return mixed Setting value.
 */
function cidrc_get_setting( $key, $default = '' ) {
	$settings = CIDRC_Settings::get_all();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Returns the hidden field names added to Contact Form 7 forms.
 *
 * @return array Map of field name to source key.
 */
function cidrc_hidden_fields() {
	return array(
		'cidrc_gclid'        => 'gclid',
		'cidrc_gbraid'       => 'gbraid',
		'cidrc_wbraid'       => 'wbraid',
		'cidrc_msclkid'      => 'msclkid',
		'cidrc_fbclid'       => 'fbclid',
		'cidrc_ttclid'       => 'ttclid',
		'cidrc_utm_source'   => 'utm_source',
		'cidrc_utm_medium'   => 'utm_medium',
		'cidrc_utm_campaign' => 'utm_campaign',
		'cidrc_utm_term'     => 'utm_term',
		'cidrc_utm_content'  => 'utm_content',
		'cidrc_referrer'     => 'first_referrer',
		'cidrc_landing_page' => 'first_landing_page',
		'cidrc_first_seen'   => 'first_seen',
		'cidrc_last_touch'   => 'last_touch',
	);
}
