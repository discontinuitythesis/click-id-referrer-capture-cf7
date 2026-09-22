<?php
/**
 * Formatting, hashing and CSV helpers.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns a readable label for a stored key.
 *
 * @param string $key Stored key.
 * @return string Human readable label.
 */
function cidrc_label_for( $key ) {
	$labels = array(
		'gclid'              => __( 'Google Click ID', 'click-id-referrer-capture-cf7' ),
		'gbraid'             => __( 'GBRAID', 'click-id-referrer-capture-cf7' ),
		'wbraid'             => __( 'WBRAID', 'click-id-referrer-capture-cf7' ),
		'dclid'              => __( 'Display Click ID', 'click-id-referrer-capture-cf7' ),
		'msclkid'            => __( 'Microsoft Click ID', 'click-id-referrer-capture-cf7' ),
		'fbclid'             => __( 'Facebook Click ID', 'click-id-referrer-capture-cf7' ),
		'ttclid'             => __( 'TikTok Click ID', 'click-id-referrer-capture-cf7' ),
		'li_fat_id'          => __( 'LinkedIn Click ID', 'click-id-referrer-capture-cf7' ),
		'utm_source'         => __( 'Source', 'click-id-referrer-capture-cf7' ),
		'utm_medium'         => __( 'Medium', 'click-id-referrer-capture-cf7' ),
		'utm_campaign'       => __( 'Campaign', 'click-id-referrer-capture-cf7' ),
		'utm_term'           => __( 'Term', 'click-id-referrer-capture-cf7' ),
		'utm_content'        => __( 'Content', 'click-id-referrer-capture-cf7' ),
		'utm_id'             => __( 'Campaign ID', 'click-id-referrer-capture-cf7' ),
		'first_referrer'     => __( 'First referrer', 'click-id-referrer-capture-cf7' ),
		'first_landing_page' => __( 'First landing page', 'click-id-referrer-capture-cf7' ),
		'first_seen'         => __( 'First seen', 'click-id-referrer-capture-cf7' ),
		'last_seen'          => __( 'Last seen', 'click-id-referrer-capture-cf7' ),
	);

	return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
}

/**
 * Hashes an email address for storage. Returns an empty string for invalid input.
 *
 * @param string $email Raw email address.
 * @return string SHA-256 hash or an empty string.
 */
function cidrc_hash_email( $email ) {
	$email = strtolower( trim( (string) $email ) );

	if ( '' === $email || ! is_email( $email ) ) {
		return '';
	}

	return hash( 'sha256', $email );
}

/**
 * Normalises a phone number to E.164 where possible and hashes it.
 *
 * @param string $phone           Raw phone number.
 * @param string $default_country Dialling code used when the number has no prefix.
 * @return string SHA-256 hash or an empty string.
 */
function cidrc_hash_phone( $phone, $default_country = '44' ) {
	$normalised = cidrc_normalise_phone( $phone, $default_country );

	if ( '' === $normalised ) {
		return '';
	}

	return hash( 'sha256', $normalised );
}

/**
 * Normalises a phone number to an E.164 style string.
 *
 * @param string $phone           Raw phone number.
 * @param string $default_country Dialling code used when the number has no prefix.
 * @return string Normalised number or an empty string.
 */
function cidrc_normalise_phone( $phone, $default_country = '44' ) {
	$phone = trim( (string) $phone );

	if ( '' === $phone ) {
		return '';
	}

	$has_plus = ( 0 === strpos( $phone, '+' ) || 0 === strpos( $phone, '00' ) );
	$digits   = preg_replace( '/[^0-9]/', '', $phone );

	if ( null === $digits || '' === $digits ) {
		return '';
	}

	if ( 0 === strpos( $digits, '00' ) ) {
		$digits = substr( $digits, 2 );
	} elseif ( ! $has_plus ) {
		$digits = ltrim( $digits, '0' );
		$digits = $default_country . $digits;
	}

	if ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) {
		return '';
	}

	return '+' . $digits;
}

/**
 * Formats a UTC timestamp for a Google or Microsoft offline conversion import.
 *
 * @param string $utc_datetime Date and time in UTC, in MySQL format.
 * @return string Formatted date, for example 2026-09-22 10:14:00+01:00.
 */
function cidrc_format_conversion_time( $utc_datetime ) {
	$timezone = wp_timezone();

	try {
		$date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
	} catch ( Exception $e ) {
		return '';
	}

	$date->setTimezone( $timezone );

	return $date->format( 'Y-m-d H:i:sP' );
}

/**
 * Escapes one CSV field to RFC 4180 and neutralises spreadsheet formula injection.
 *
 * @param mixed $field Field value.
 * @return string Escaped field.
 */
function cidrc_esc_csv_field( $field ) {
	$field = is_scalar( $field ) ? (string) $field : '';

	if ( '' !== $field && preg_match( '/^[=+\-@\t\r]/', $field ) && ! preg_match( '/^-?[0-9]+(\.[0-9]+)?$/', $field ) ) {
		$field = "'" . $field;
	}

	return '"' . str_replace( '"', '""', $field ) . '"';
}

/**
 * Escapes a whole CSV row and terminates it with a carriage return and line feed.
 *
 * @param array $fields Field values.
 * @return string Escaped row.
 */
function cidrc_esc_csv_row( array $fields ) {
	return implode( ',', array_map( 'cidrc_esc_csv_field', $fields ) ) . "\r\n";
}

/**
 * Builds the readable attribution summary block.
 *
 * @param array $values Values from cidrc_get_values() or a posted submission.
 * @return string Summary text, with one line per non-empty value.
 */
function cidrc_build_summary( $values ) {
	$lines = array();
	$first = isset( $values['first'] ) && is_array( $values['first'] ) ? $values['first'] : array();
	$last  = isset( $values['last'] ) && is_array( $values['last'] ) ? $values['last'] : array();

	foreach ( cidrc_captured_params() as $param ) {
		$first_value = isset( $first[ $param ] ) ? $first[ $param ] : '';
		$last_value  = isset( $last[ $param ] ) ? $last[ $param ] : '';

		if ( '' === $first_value && '' === $last_value ) {
			continue;
		}

		if ( $first_value === $last_value || '' === $last_value ) {
			$lines[] = cidrc_label_for( $param ) . ': ' . $first_value;
			continue;
		}

		if ( '' === $first_value ) {
			$lines[] = cidrc_label_for( $param ) . ': ' . $last_value;
			continue;
		}

		/* translators: 1: first touch value, 2: last touch value. */
		$lines[] = cidrc_label_for( $param ) . ': ' . sprintf( __( '%1$s (first touch), %2$s (last touch)', 'click-id-referrer-capture-cf7' ), $first_value, $last_value );
	}

	foreach ( array( 'first_referrer', 'first_landing_page', 'first_seen', 'last_seen' ) as $key ) {
		if ( empty( $values[ $key ] ) ) {
			continue;
		}

		$lines[] = cidrc_label_for( $key ) . ': ' . $values[ $key ];
	}

	if ( empty( $lines ) ) {
		return __( 'No attribution data was captured for this visitor.', 'click-id-referrer-capture-cf7' );
	}

	return implode( "\n", $lines );
}
