<?php
/**
 * Extended attribution: sanitising the posted touches and presenting them.
 *
 * Every function here takes and returns plain arrays and strings, so that it can
 * be tested without WordPress. Extended data holds UTM values, referrers, paths,
 * a page title, times and channel names only, never personal data.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the channel names the capture script can assign.
 *
 * @return array Channel names.
 */
function cidrc_channels() {
	return array(
		'Paid Search',
		'Paid Social',
		'Display',
		'Email',
		'Affiliate',
		'Organic Social',
		'Referral',
		'Other Campaign',
		'Organic Search',
		'AI Assistant',
		'Direct',
	);
}

/**
 * Returns the keys of one touch, in the order used by the script and the exports.
 *
 * @return array Touch keys.
 */
function cidrc_touch_keys() {
	return array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'utm_id',
		'click_id_type',
		'referrer',
		'landing_page',
		'at',
		'channel',
	);
}

/**
 * Returns the click ID types a touch can name.
 *
 * @return array Click ID parameter names.
 */
function cidrc_click_id_types() {
	return array( 'gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id', 'dclid' );
}

/**
 * Keeps a touch time only when it looks like an ISO 8601 date and time.
 *
 * @param string $value Raw time.
 * @return string Time, or an empty string.
 */
function cidrc_clean_touch_time( $value ) {
	$value = trim( (string) $value );

	return preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+\-]\d{2}:?\d{2})?$/', $value ) ? $value : '';
}

/**
 * Sanitises one touch. Unknown keys are dropped and every known key is present.
 *
 * @param mixed $touch Decoded touch.
 * @return array Clean touch, or an empty array when nothing usable is left.
 */
function cidrc_sanitize_touch( $touch ) {
	if ( ! is_array( $touch ) ) {
		return array();
	}

	$clean  = array();
	$filled = false;

	foreach ( cidrc_touch_keys() as $key ) {
		$value = ( isset( $touch[ $key ] ) && is_scalar( $touch[ $key ] ) ) ? trim( (string) $touch[ $key ] ) : '';

		if ( 'referrer' === $key || 'landing_page' === $key ) {
			$value = cidrc_clean_url_value( $value );
		} elseif ( 'click_id_type' === $key ) {
			$value = in_array( strtolower( $value ), cidrc_click_id_types(), true ) ? strtolower( $value ) : '';
		} elseif ( 'channel' === $key ) {
			$value = in_array( $value, cidrc_channels(), true ) ? $value : '';
		} elseif ( 'at' === $key ) {
			$value = cidrc_clean_touch_time( $value );
		} else {
			$value = cidrc_clean_value( $value, 500 );
		}

		$clean[ $key ] = $value;
		$filled        = $filled || '' !== $value;
	}

	return $filled ? $clean : array();
}

/**
 * Decodes and sanitises one touch posted as JSON.
 *
 * @param mixed $raw JSON text.
 * @return array Clean touch, or an empty array.
 */
function cidrc_decode_touch( $raw ) {
	if ( ! is_string( $raw ) ) {
		return array();
	}

	$raw = trim( $raw );

	if ( '' === $raw || strlen( $raw ) > 4096 ) {
		return array();
	}

	return cidrc_sanitize_touch( json_decode( $raw, true ) );
}

/**
 * Sanitises a whole extended record: form page, form page title and both touches.
 *
 * @param mixed $data Decoded record.
 * @return array Clean record, or an empty array when it holds nothing.
 */
function cidrc_sanitize_extended( $data ) {
	if ( ! is_array( $data ) ) {
		return array();
	}

	$extended = array(
		'form_page'       => isset( $data['form_page'] ) ? cidrc_clean_url_value( $data['form_page'] ) : '',
		'form_page_title' => isset( $data['form_page_title'] ) ? cidrc_clean_value( $data['form_page_title'], 200 ) : '',
		'first'           => isset( $data['first'] ) ? cidrc_sanitize_touch( $data['first'] ) : array(),
		'last'            => isset( $data['last'] ) ? cidrc_sanitize_touch( $data['last'] ) : array(),
	);

	if ( '' === $extended['form_page'] && '' === $extended['form_page_title'] && empty( $extended['first'] ) && empty( $extended['last'] ) ) {
		return array();
	}

	return $extended;
}

/**
 * Builds the extended record from Contact Form 7 posted data.
 *
 * @param array $posted Posted data.
 * @return array Clean record, or an empty array when nothing was posted.
 */
function cidrc_extended_from_posted( array $posted ) {
	$read = array();

	foreach ( cidrc_extended_fields() as $field => $key ) {
		$value        = isset( $posted[ $field ] ) ? $posted[ $field ] : '';
		$read[ $key ] = is_array( $value ) ? reset( $value ) : $value;
	}

	return cidrc_sanitize_extended(
		array(
			'form_page'       => $read['form_page'],
			'form_page_title' => $read['form_page_title'],
			'first'           => cidrc_decode_touch( $read['touch_first'] ),
			'last'            => cidrc_decode_touch( $read['touch_last'] ),
		)
	);
}

/**
 * Reads the extended record stored with a log row.
 *
 * @param mixed $stored JSON text from the extended column, or an array.
 * @return array Clean record, or an empty array.
 */
function cidrc_extended_decode( $stored ) {
	if ( is_array( $stored ) ) {
		return cidrc_sanitize_extended( $stored );
	}

	if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
		return array();
	}

	return cidrc_sanitize_extended( json_decode( $stored, true ) );
}

/**
 * Formats a touch time in the site time zone, for example 2026-09-20 10:15.
 *
 * @param string $at ISO 8601 time.
 * @return string Formatted time, or an empty string.
 */
function cidrc_format_touch_time( $at ) {
	if ( '' === (string) $at ) {
		return '';
	}

	try {
		$date = new DateTime( (string) $at, new DateTimeZone( 'UTC' ) );
	} catch ( Exception $e ) {
		return '';
	}

	$date->setTimezone( wp_timezone() );

	return $date->format( 'Y-m-d H:i' );
}

/**
 * Describes where a touch without UTM values came from, for example google / organic.
 *
 * @param array $touch Clean touch.
 * @return string Source and medium, or an empty string.
 */
function cidrc_touch_origin( array $touch ) {
	$media = array(
		'Organic Search' => 'organic',
		'Organic Social' => 'social',
		'AI Assistant'   => 'referral',
		'Referral'       => 'referral',
	);

	if ( '' === $touch['referrer'] || ! isset( $media[ $touch['channel'] ] ) ) {
		return '';
	}

	$host = preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', strtolower( $touch['referrer'] ) );
	$host = preg_replace( '/^www\./', '', (string) preg_split( '#[/?\#:]#', (string) $host )[0] );

	if ( '' === $host ) {
		return '';
	}

	if ( 'Organic Search' === $touch['channel'] ) {
		// The name before the domain ending: www.google.co.uk gives google.
		$labels = explode( '.', $host );

		array_pop( $labels );

		if ( count( $labels ) > 1 && in_array( end( $labels ), array( 'co', 'com', 'org', 'net', 'ac', 'gov', 'edu' ), true ) ) {
			array_pop( $labels );
		}

		$host = (string) end( $labels );
	}

	return $host . ' / ' . $media[ $touch['channel'] ];
}

/**
 * Builds the readable description of one touch.
 *
 * @param array $touch Clean touch.
 * @return string Description with the non-empty parts separated by middle dots.
 */
function cidrc_touch_summary( array $touch ) {
	if ( empty( $touch ) ) {
		return '';
	}

	$campaign = array_filter( array( $touch['utm_source'], $touch['utm_medium'], $touch['utm_campaign'] ), 'strlen' );
	$parts    = array( $touch['channel'] );
	$parts[]  = empty( $campaign ) ? cidrc_touch_origin( $touch ) : implode( ' / ', $campaign );

	if ( '' !== $touch['utm_term'] ) {
		/* translators: %s: utm_term value. */
		$parts[] = sprintf( __( 'term: %s', 'click-id-referrer-capture-cf7' ), $touch['utm_term'] );
	}

	if ( '' !== $touch['utm_content'] ) {
		/* translators: %s: utm_content value. */
		$parts[] = sprintf( __( 'content: %s', 'click-id-referrer-capture-cf7' ), $touch['utm_content'] );
	}

	if ( '' !== $touch['landing_page'] ) {
		/* translators: %s: landing page path. */
		$parts[] = sprintf( __( 'landed %s', 'click-id-referrer-capture-cf7' ), $touch['landing_page'] );
	}

	$parts[] = cidrc_format_touch_time( $touch['at'] );

	return implode( ' · ', array_filter( $parts, 'strlen' ) );
}

/**
 * Builds the extended lines of the email summary.
 *
 * @param mixed $extended Extended record.
 * @return array Lines, without empty ones.
 */
function cidrc_extended_summary_lines( $extended ) {
	$extended = cidrc_sanitize_extended( $extended );
	$lines    = array();

	if ( empty( $extended ) ) {
		return $lines;
	}

	$page  = $extended['form_page'];
	$title = $extended['form_page_title'];

	if ( '' !== $page || '' !== $title ) {
		$lines[] = __( 'Form page', 'click-id-referrer-capture-cf7' ) . ': ' . trim( $page . ( '' === $title ? '' : ' (' . $title . ')' ) );
	}

	$first = cidrc_touch_summary( $extended['first'] );
	$last  = cidrc_touch_summary( $extended['last'] );

	if ( '' !== $first ) {
		$lines[] = __( 'First touch', 'click-id-referrer-capture-cf7' ) . ': ' . $first;
	}

	if ( '' !== $last ) {
		$lines[] = __( 'Last touch', 'click-id-referrer-capture-cf7' ) . ': ' . $last;
	}

	return $lines;
}

/**
 * Builds the attribution object for the webhook payload.
 *
 * @param mixed $stored Extended record, as JSON text or an array.
 * @return array|null Attribution object, or null when there is no extended data.
 */
function cidrc_extended_webhook( $stored ) {
	$extended = cidrc_extended_decode( $stored );

	if ( empty( $extended ) ) {
		return null;
	}

	return array(
		'form_page'       => $extended['form_page'],
		'form_page_title' => $extended['form_page_title'],
		'first'           => empty( $extended['first'] ) ? null : $extended['first'],
		'last'            => empty( $extended['last'] ) ? null : $extended['last'],
	);
}
