<?php
/**
 * Plain PHP tests for the export row builders and the consent helpers.
 * WordPress is not loaded: the few functions the builders reach are stubbed below.
 *
 * Usage: php test/export-consent-test.php
 *
 * @package ClickIdReferrerCaptureCf7
 */

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Stub of the WordPress translation function.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string Untranslated text.
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * Stub of wp_timezone(): the tests run as a UK site.
 *
 * @return DateTimeZone Time zone.
 */
function wp_timezone() {
	return new DateTimeZone( 'Europe/London' );
}

$cidrc_test_option = array();

/**
 * Stub of get_option(): returns the settings array the test sets.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed Value.
 */
function get_option( $name, $default = false ) {
	global $cidrc_test_option;
	return 'cidrc_settings' === $name ? $cidrc_test_option : $default;
}

/**
 * Stub of apply_filters(): returns the value untouched.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value.
 * @return mixed Value.
 */
function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

/**
 * Simplified stub of sanitize_text_field().
 *
 * @param mixed $value Value.
 * @return string Clean value.
 */
function sanitize_text_field( $value ) {
	return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
}

/**
 * Stub of sanitize_key().
 *
 * @param mixed $key Key.
 * @return string Clean key.
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Simplified stub of esc_url_raw().
 *
 * @param string $url URL.
 * @return string URL.
 */
function esc_url_raw( $url ) {
	return (string) $url;
}

require dirname( __DIR__ ) . '/includes/functions.php';
require dirname( __DIR__ ) . '/includes/formatting.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-settings.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-cf7.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-export-rows.php';

$cidrc_failures = 0;
$cidrc_passes   = 0;

/**
 * Compares two values and records the result.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Description.
 * @return void
 */
function cidrc_assert_same( $expected, $actual, $message ) {
	global $cidrc_failures, $cidrc_passes;

	if ( $expected === $actual ) {
		++$cidrc_passes;
		echo "ok - {$message}\n";
		return;
	}

	++$cidrc_failures;
	echo "not ok - {$message}\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true ) . "\n";
}

// Google header, both variants.
cidrc_assert_same(
	array( 'Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency', 'Order ID', 'Ad User Data', 'Ad Personalization' ),
	CIDRC_Export_Rows::google_header( false ),
	'Google header without braid columns follows the import template order'
);

cidrc_assert_same(
	array( 'Google Click ID', 'GBRAID', 'WBRAID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency', 'Order ID', 'Ad User Data', 'Ad Personalization' ),
	CIDRC_Export_Rows::google_header( true ),
	'Google header with braid columns puts GBRAID and WBRAID straight after Google Click ID'
);

cidrc_assert_same(
	'"Google Click ID","Conversion Name","Conversion Time","Conversion Value","Conversion Currency","Order ID","Ad User Data","Ad Personalization"' . "\r\n",
	cidrc_esc_csv_row( CIDRC_Export_Rows::google_header( false ) ),
	'Google header line as written to the file'
);

// Row mapping.
$cidrc_row = array(
	'id'                 => '42',
	'created_at'         => '2026-07-01 09:15:00',
	'gclid'              => 'Cj0KCQjwTEST',
	'gbraid'             => 'GB-SHOULD-NOT-APPEAR',
	'wbraid'             => '',
	'conversion_name'    => 'Website Lead',
	'conversion_value'   => '250.5',
	'currency'           => 'GBP',
	'ad_user_data'       => 'Granted',
	'ad_personalization' => 'Denied',
);

cidrc_assert_same(
	array( 'Cj0KCQjwTEST', 'Website Lead', '2026-07-01 10:15:00+01:00', '250.50', 'GBP', 'cidrc-42', 'Granted', 'Denied' ),
	CIDRC_Export_Rows::google_row( $cidrc_row, false ),
	'Google row without braid columns maps every cell, with the order ID and consent'
);

cidrc_assert_same(
	count( CIDRC_Export_Rows::google_header( false ) ),
	count( CIDRC_Export_Rows::google_row( $cidrc_row, false ) ),
	'Google row without braid columns has as many cells as the header'
);

cidrc_assert_same(
	array( 'Cj0KCQjwTEST', '', '', 'Website Lead', '2026-07-01 10:15:00+01:00', '250.50', 'GBP', 'cidrc-42', 'Granted', 'Denied' ),
	CIDRC_Export_Rows::google_row( $cidrc_row, true ),
	'Google row with braid columns fills only one identifier, the gclid'
);

$cidrc_braid_only = array_merge(
	$cidrc_row,
	array(
		'gclid'              => '',
		'gbraid'             => '',
		'wbraid'             => 'WB-1',
		'ad_user_data'       => '',
		'ad_personalization' => '',
		'created_at'         => '2026-01-15 12:00:00',
	)
);

cidrc_assert_same( null, CIDRC_Export_Rows::google_row( $cidrc_braid_only, false ), 'a row without a gclid is skipped when braid columns are off' );

cidrc_assert_same(
	array( '', '', 'WB-1', 'Website Lead', '2026-01-15 12:00:00+00:00', '250.50', 'GBP', 'cidrc-42', '', '' ),
	CIDRC_Export_Rows::google_row( $cidrc_braid_only, true ),
	'a WBRAID row is exported when braid columns are on, with blank (unspecified) consent'
);

cidrc_assert_same(
	null,
	CIDRC_Export_Rows::google_row( array( 'id' => 7, 'msclkid' => 'MS1' ), true ),
	'a row with no Google identifier is skipped'
);

$cidrc_lower = CIDRC_Export_Rows::google_row( array_merge( $cidrc_row, array( 'ad_user_data' => 'granted' ) ), false );
cidrc_assert_same( 'Granted', $cidrc_lower[6], 'lower case stored consent is exported as Granted' );

// Microsoft stays as it was.
cidrc_assert_same(
	array( 'Microsoft Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency' ),
	CIDRC_Export_Rows::microsoft_header(),
	'Microsoft header is unchanged'
);

cidrc_assert_same(
	array( 'MS1', 'Website Lead', '2026-07-01 10:15:00+01:00', '250.50', 'GBP' ),
	CIDRC_Export_Rows::microsoft_row( array_merge( $cidrc_row, array( 'msclkid' => 'MS1' ) ) ),
	'Microsoft row is unchanged'
);

// Full log.
$cidrc_full_columns = CIDRC_Export_Rows::full_columns();
cidrc_assert_same( array( 'ad_user_data', 'ad_personalization', 'order_id' ), array_slice( $cidrc_full_columns, -3 ), 'full log ends with the consent and order ID columns' );

$cidrc_full = array_combine( $cidrc_full_columns, CIDRC_Export_Rows::full_row( $cidrc_row ) );
cidrc_assert_same( 'cidrc-42', $cidrc_full['order_id'], 'full log row carries the order ID' );
cidrc_assert_same( 'Denied', $cidrc_full['ad_personalization'], 'full log row carries the consent value' );
cidrc_assert_same( '', $cidrc_full['fbclid'], 'full log row leaves missing columns blank' );

// Helpers.
cidrc_assert_same( 'Granted', cidrc_normalise_consent( ' GRANTED ' ), 'normalise: granted in any case' );
cidrc_assert_same( 'Denied', cidrc_normalise_consent( 'denied' ), 'normalise: denied' );
cidrc_assert_same( '', cidrc_normalise_consent( 'yes' ), 'normalise: anything else is blank' );
cidrc_assert_same( '', cidrc_normalise_consent( array( 'granted' ) ), 'normalise: arrays are blank' );
cidrc_assert_same( 'cidrc-7', cidrc_order_id( '7' ), 'order ID from a numeric string' );
cidrc_assert_same( '', cidrc_order_id( 0 ), 'no order ID without a row' );

// Contact Form 7 posted data: normalising and the consent fallback.
$cidrc_cf7    = new CIDRC_CF7();
$cidrc_posted = array(
	'cidrc_gclid'              => 'Cj0POSTED',
	'cidrc_ad_user_data'       => 'GRANTED',
	'cidrc_ad_personalization' => '',
);

$cidrc_values = $cidrc_cf7->values_from_posted( $cidrc_posted );
cidrc_assert_same( array( 'ad_user_data' => 'Granted', 'ad_personalization' => '' ), $cidrc_values['consent'], 'posted consent is normalised and blank stays blank by default' );
cidrc_assert_same( false, isset( $cidrc_values['first']['ad_user_data'] ), 'consent is not treated as a click parameter' );

$cidrc_test_option = array( 'consent_fallback' => 'Denied' );
CIDRC_Settings::flush();
$cidrc_values = $cidrc_cf7->values_from_posted( $cidrc_posted );
cidrc_assert_same( array( 'ad_user_data' => 'Granted', 'ad_personalization' => 'Denied' ), $cidrc_values['consent'], 'the fallback fills only the value with no signal' );

$cidrc_summary = cidrc_build_summary( $cidrc_values );
cidrc_assert_same( true, false !== strpos( $cidrc_summary, "Ad user data consent: Granted\nAd personalisation consent: Denied" ), 'the summary mail tag lists both consent values' );

cidrc_assert_same( 'Denied', CIDRC_Settings::sanitize( array( 'consent_fallback' => 'denied' ) )['consent_fallback'], 'settings: fallback is sanitised to Denied' );
cidrc_assert_same( '', CIDRC_Settings::sanitize( array( 'consent_fallback' => 'maybe' ) )['consent_fallback'], 'settings: an unknown fallback becomes blank' );
cidrc_assert_same( 1, CIDRC_Settings::sanitize( array( 'include_braid_columns' => '1' ) )['include_braid_columns'], 'settings: braid checkbox on' );
cidrc_assert_same( 0, CIDRC_Settings::sanitize( array() )['include_braid_columns'], 'settings: braid checkbox off by default' );

echo "\n{$cidrc_passes} passed, {$cidrc_failures} failed\n";

exit( $cidrc_failures > 0 ? 1 : 0 );
