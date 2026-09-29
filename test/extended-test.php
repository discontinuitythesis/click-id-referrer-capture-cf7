<?php
/**
 * Plain PHP tests for extended attribution: sanitising, the email summary, the
 * full log CSV, the webhook payload and the log insert fallback.
 * WordPress is not loaded: the functions the code reaches are stubbed below.
 *
 * Usage: php test/extended-test.php
 *
 * @package ClickIdReferrerCaptureCf7
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CIDRC_VERSION', '1.2.0' );
define( 'HOUR_IN_SECONDS', 3600 );

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
 * Stub of sanitize_text_field(), close to WordPress: tags and their script or
 * style contents removed, line breaks and repeated white space collapsed.
 *
 * @param mixed $value Value.
 * @return string Clean value.
 */
function sanitize_text_field( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $value );
	$value = strip_tags( $value );
	$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

	return trim( $value );
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
 * Stub of esc_url_raw(), close to WordPress: characters outside the URL set are
 * removed, and anything with a protocol other than http or https is rejected.
 *
 * @param string $url URL.
 * @return string URL, or an empty string.
 */
function esc_url_raw( $url ) {
	$url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $url );

	if ( preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $url, $match ) && ! in_array( strtolower( $match[1] ), array( 'http', 'https' ), true ) ) {
		return '';
	}

	return $url;
}

/**
 * Stub of wp_json_encode().
 *
 * @param mixed $data Data.
 * @return string JSON.
 */
function wp_json_encode( $data ) {
	return json_encode( $data );
}

/**
 * Stub of home_url().
 *
 * @param string $path Path.
 * @return string URL.
 */
function home_url( $path = '' ) {
	return 'https://www.example.co.uk' . $path;
}

/**
 * Stub of is_email().
 *
 * @param string $email Email.
 * @return bool Whether it looks like an email address.
 */
function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

require dirname( __DIR__ ) . '/includes/functions.php';
require dirname( __DIR__ ) . '/includes/formatting.php';
require dirname( __DIR__ ) . '/includes/extended.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-settings.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-log-schema.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-log.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-cf7.php';
require dirname( __DIR__ ) . '/includes/class-cidrc-webhook.php';
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

/**
 * Switches extended attribution on or off for the following checks.
 *
 * @param bool $on Whether it is on.
 * @return void
 */
function cidrc_test_extended( $on ) {
	global $cidrc_test_option;
	$cidrc_test_option = array( 'extended_attribution' => $on ? 1 : 0 );
	CIDRC_Settings::flush();
}

$cidrc_first = array(
	'utm_source'    => '',
	'utm_medium'    => '',
	'utm_campaign'  => '',
	'utm_term'      => '',
	'utm_content'   => '',
	'utm_id'        => '',
	'click_id_type' => '',
	'referrer'      => 'https://www.google.co.uk/',
	'landing_page'  => '/blog/x',
	'at'            => '2026-09-20T09:15:00.000Z',
	'channel'       => 'Organic Search',
);

$cidrc_last = array(
	'utm_source'    => 'google',
	'utm_medium'    => 'cpc',
	'utm_campaign'  => 'brand',
	'utm_term'      => 'emergency plumber',
	'utm_content'   => '',
	'utm_id'        => '',
	'click_id_type' => 'gclid',
	'referrer'      => 'https://www.google.com/',
	'landing_page'  => '/?gclid=Cj0KTEST',
	'at'            => '2026-09-23T21:17:00.000Z',
	'channel'       => 'Paid Search',
);

// 1. Sanitising touches.
cidrc_assert_same( $cidrc_first, cidrc_decode_touch( json_encode( $cidrc_first ) ), 'a clean touch passes through unchanged' );

$cidrc_evil = cidrc_decode_touch(
	json_encode(
		array(
			'utm_source'    => '<script>alert(1)</script>google',
			'utm_medium'    => '<b>cpc</b>',
			'utm_campaign'  => array( 'nested' ),
			'utm_term'      => str_repeat( 'x', 900 ),
			'click_id_type' => 'GCLID',
			'referrer'      => 'javascript:alert(document.cookie)',
			'landing_page'  => '/contact?a=1"><img src=x onerror=alert(1)>',
			'at'            => '<script>',
			'channel'       => 'Hacked',
			'evil'          => 'drop me',
			'__proto__'     => 'drop me too',
		)
	)
);

cidrc_assert_same( cidrc_touch_keys(), array_keys( $cidrc_evil ), 'a touch keeps exactly the known keys, in order' );
cidrc_assert_same( 'google', $cidrc_evil['utm_source'], 'script tags and their contents are removed' );
cidrc_assert_same( 'cpc', $cidrc_evil['utm_medium'], 'other tags are removed' );
cidrc_assert_same( '', $cidrc_evil['utm_campaign'], 'a nested value becomes blank' );
cidrc_assert_same( 500, strlen( $cidrc_evil['utm_term'] ), 'a long UTM value is capped at 500 characters' );
cidrc_assert_same( 'gclid', $cidrc_evil['click_id_type'], 'the click ID type is lower cased' );
cidrc_assert_same( '', $cidrc_evil['referrer'], 'a javascript: referrer is rejected' );
cidrc_assert_same( false, strpbrk( $cidrc_evil['landing_page'], '<>"' ), 'markup characters are removed from the landing page' );
cidrc_assert_same( '', $cidrc_evil['at'], 'a time that is not ISO 8601 is blank' );
cidrc_assert_same( '', $cidrc_evil['channel'], 'a channel outside the allowed list is blank' );

cidrc_assert_same( '', cidrc_decode_touch( '{"channel":"paid search","utm_id":"7"}' )['channel'], 'channel matching is exact' );
cidrc_assert_same( array(), cidrc_decode_touch( '{"channel":"paid search"}' ), 'a touch with nothing valid left is empty' );
cidrc_assert_same( '', cidrc_decode_touch( '{"click_id_type":"evilclid","utm_id":"7"}' )['click_id_type'], 'an unknown click ID type is blank' );

foreach ( array( '"just a string"', '42', 'null', 'true', '[1,2,3]', '{not json', '', '   ' ) as $cidrc_garbage ) {
	cidrc_assert_same( array(), cidrc_decode_touch( $cidrc_garbage ), 'garbage touch JSON gives an empty touch: ' . $cidrc_garbage );
}

cidrc_assert_same( array(), cidrc_decode_touch( array( 'channel' => 'Direct' ) ), 'a posted array rather than JSON text is refused' );
cidrc_assert_same( array(), cidrc_decode_touch( '{"utm_source":"' . str_repeat( 'a', 5000 ) . '"}' ), 'touch JSON over 4 KB is refused' );
cidrc_assert_same( '2026-09-23T21:17:00+01:00', cidrc_clean_touch_time( '2026-09-23T21:17:00+01:00' ), 'a time with an offset is kept' );

// 2. Contact Form 7 posted data.
$cidrc_posted = array(
	'cidrc_gclid'           => 'Cj0KTEST',
	'cidrc_utm_source'      => 'google',
	'cidrc_form_page'       => '/contact',
	'cidrc_form_page_title' => array( '<em>Contact</em> us' ),
	'cidrc_touch_first'     => json_encode( $cidrc_first ),
	'cidrc_touch_last'      => json_encode( array_merge( $cidrc_last, array( 'unknown' => 'x' ) ) ),
);

$cidrc_cf7 = new CIDRC_CF7();

cidrc_test_extended( false );
$cidrc_values = $cidrc_cf7->values_from_posted( $cidrc_posted );
cidrc_assert_same( false, array_key_exists( 'extended', $cidrc_values ), 'with the setting off the posted extended fields are ignored' );
cidrc_assert_same( array_merge( array( 'a' => '' ), array_fill_keys( array_keys( cidrc_hidden_fields() ), '' ) ), $cidrc_cf7->hidden_fields( array( 'a' => '' ) ), 'with the setting off the hidden fields are those of 1.1.0' );

$cidrc_summary_off = cidrc_build_summary( $cidrc_values );

cidrc_test_extended( true );
$cidrc_values = $cidrc_cf7->values_from_posted( $cidrc_posted );
cidrc_assert_same(
	array(
		'form_page'       => '/contact',
		'form_page_title' => 'Contact us',
		'first'           => $cidrc_first,
		'last'            => $cidrc_last,
	),
	$cidrc_values['extended'],
	'with the setting on the extended record is built and sanitised'
);
cidrc_assert_same( array_keys( cidrc_extended_fields() ), array_slice( array_keys( $cidrc_cf7->hidden_fields( array() ) ), -4 ), 'with the setting on the four extended hidden fields are added' );
cidrc_assert_same( array(), cidrc_extended_from_posted( array( 'cidrc_touch_first' => 'garbage' ) ), 'nothing usable posted gives no extended record' );

$cidrc_long_title = cidrc_extended_from_posted( array( 'cidrc_form_page_title' => str_repeat( 'T', 250 ) ) );
cidrc_assert_same( 200, strlen( $cidrc_long_title['form_page_title'] ), 'the form page title is capped at 200 characters' );

// 3. Email summary.
$cidrc_block = "Form page: /contact (Contact us)\n"
	. "First touch: Organic Search · google / organic · landed /blog/x · 2026-09-20 10:15\n"
	. 'Last touch: Paid Search · google / cpc / brand · term: emergency plumber · landed /?gclid=Cj0KTEST · 2026-09-23 22:17';

cidrc_assert_same( $cidrc_block, implode( "\n", cidrc_extended_summary_lines( $cidrc_values['extended'] ) ), 'the extended summary block reads as documented' );
cidrc_assert_same( $cidrc_summary_off . "\n" . $cidrc_block, cidrc_build_summary( $cidrc_values ), 'the extended block follows the 1.1.0 summary lines' );

$cidrc_direct = array(
	'form_page_title' => 'Quote',
	'first'           => array(
		'landing_page' => '/contact/',
		'at'           => '2026-01-10T12:00:00Z',
		'channel'      => 'Direct',
	),
	'last'            => array(
		'referrer' => 'https://www.chatgpt.com/',
		'channel'  => 'AI Assistant',
	),
);
cidrc_assert_same(
	"Form page: (Quote)\nFirst touch: Direct · landed /contact/ · 2026-01-10 12:00\nLast touch: AI Assistant · chatgpt.com / referral",
	implode( "\n", cidrc_extended_summary_lines( $cidrc_direct ) ),
	'only the non-empty parts are shown'
);
cidrc_assert_same( 'Referral · blog.example.org / referral', cidrc_touch_summary( cidrc_sanitize_touch( array( 'referrer' => 'https://blog.example.org/post', 'channel' => 'Referral' ) ) ), 'a referral shows its host' );
cidrc_assert_same( 'Paid Search · landed /?gclid=X', cidrc_touch_summary( cidrc_sanitize_touch( array( 'referrer' => 'https://www.google.com/', 'landing_page' => '/?gclid=X', 'click_id_type' => 'gclid', 'channel' => 'Paid Search' ) ) ), 'a paid click without UTMs does not invent a source' );
cidrc_assert_same( array(), cidrc_extended_summary_lines( 'not an array' ), 'no lines without extended data' );

// 4. Full log CSV.
$cidrc_base_columns = array( 'id', 'created_at', 'form_id', 'form_title', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'landing_page', 'referrer', 'email_hash', 'phone_hash', 'conversion_name', 'conversion_value', 'currency', 'ad_user_data', 'ad_personalization', 'order_id' );
$cidrc_ext_columns  = array( 'form_page', 'form_page_title' );

foreach ( array( 'first', 'last' ) as $cidrc_prefix ) {
	foreach ( array( 'channel', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'click_id_type', 'referrer_ext', 'landing_page_ext', 'touch_at' ) as $cidrc_suffix ) {
		$cidrc_ext_columns[] = $cidrc_prefix . '_' . $cidrc_suffix;
	}
}

cidrc_assert_same( $cidrc_base_columns, CIDRC_Export_Rows::full_columns(), 'full CSV header without extended columns is the 1.1.0 header' );
cidrc_assert_same( $cidrc_base_columns, CIDRC_Export_Rows::full_columns( false ), 'full CSV header with extended false is the 1.1.0 header' );
cidrc_assert_same( array_merge( $cidrc_base_columns, $cidrc_ext_columns ), CIDRC_Export_Rows::full_columns( true ), 'full CSV header with extended columns appended' );
cidrc_assert_same( 46, count( CIDRC_Export_Rows::full_columns( true ) ), 'full CSV with extended columns has 46 columns' );

$cidrc_row = array(
	'id'         => '9',
	'created_at' => '2026-09-23 21:20:00',
	'gclid'      => 'Cj0KTEST',
	'extended'   => json_encode( $cidrc_values['extended'] ),
);

$cidrc_cells = array_combine( CIDRC_Export_Rows::full_columns( true ), CIDRC_Export_Rows::full_row( $cidrc_row, true ) );
cidrc_assert_same( '/contact', $cidrc_cells['form_page'], 'full CSV row: form page' );
cidrc_assert_same( 'Organic Search', $cidrc_cells['first_channel'], 'full CSV row: first channel' );
cidrc_assert_same( 'https://www.google.co.uk/', $cidrc_cells['first_referrer_ext'], 'full CSV row: first referrer' );
cidrc_assert_same( 'emergency plumber', $cidrc_cells['last_utm_term'], 'full CSV row: last term' );
cidrc_assert_same( 'gclid', $cidrc_cells['last_click_id_type'], 'full CSV row: last click ID type' );
cidrc_assert_same( '2026-09-23T21:17:00.000Z', $cidrc_cells['last_touch_at'], 'full CSV row: last touch time' );
cidrc_assert_same( 'cidrc-9', $cidrc_cells['order_id'], 'full CSV row: order ID still in place' );
cidrc_assert_same( CIDRC_Export_Rows::full_row( $cidrc_row ), array_slice( CIDRC_Export_Rows::full_row( $cidrc_row, true ), 0, 22 ), 'full CSV row: the first 22 cells are the 1.1.0 cells' );
cidrc_assert_same( array_fill( 0, 24, '' ), array_slice( CIDRC_Export_Rows::full_row( array( 'id' => 3 ), true ), 22 ), 'full CSV row: a row without extended data has blank extended cells' );
cidrc_assert_same( array_fill( 0, 24, '' ), array_slice( CIDRC_Export_Rows::full_row( array( 'extended' => '{"first":"<script>"}' ), true ), 22 ), 'full CSV row: a damaged stored record gives blank cells' );
cidrc_assert_same( 22, count( CIDRC_Export_Rows::full_row( $cidrc_row ) ), 'full CSV row without extended stays at 22 cells' );

// 5. Webhook payload.
$cidrc_webhook = new class() extends CIDRC_Webhook {
	/**
	 * Exposes the payload builder.
	 *
	 * @param array $row Row.
	 * @return array Payload.
	 */
	public function payload( array $row ) {
		return $this->build_payload( $row );
	}
};

$cidrc_payload = $cidrc_webhook->payload( $cidrc_row );
cidrc_assert_same( 'Paid Search', $cidrc_payload['attribution']['last']['channel'], 'webhook: attribution object carries the last touch' );
cidrc_assert_same( 'Contact us', $cidrc_payload['attribution']['form_page_title'], 'webhook: attribution object carries the form page title' );
cidrc_assert_same( false, isset( $cidrc_webhook->payload( array( 'id' => 3 ) )['attribution'] ), 'webhook: no attribution key without extended data' );
cidrc_assert_same( null, cidrc_extended_webhook( json_encode( array( 'form_page' => '/x' ) ) )['first'], 'webhook: a missing touch is null' );

// 6. Settings.
cidrc_assert_same( 0, CIDRC_Settings::defaults()['extended_attribution'], 'settings: extended attribution is off by default' );
cidrc_assert_same( 1, CIDRC_Settings::sanitize( array( 'extended_attribution' => '1' ) )['extended_attribution'], 'settings: extended attribution on' );
cidrc_assert_same( 0, CIDRC_Settings::sanitize( array() )['extended_attribution'], 'settings: extended attribution off when unticked' );

// 7. Log insert: the lead is kept when the newer columns are missing.

/**
 * Minimal stand-in for wpdb: an insert fails when a column does not exist.
 */
class CIDRC_Fake_Wpdb {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Last insert identifier.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Existing columns.
	 *
	 * @var array
	 */
	public $columns = array();

	/**
	 * Every insert attempt: data and formats.
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * Records an insert, failing when a column is missing.
	 *
	 * @param string $table   Table.
	 * @param array  $data    Data.
	 * @param array  $formats Formats.
	 * @return int|false Rows inserted, or false.
	 */
	public function insert( $table, $data, $formats ) {
		unset( $table );
		$this->calls[] = array( $data, $formats );

		if ( array_diff( array_keys( $data ), $this->columns ) ) {
			return false;
		}

		$this->insert_id = count( $this->calls );

		return 1;
	}
}

$cidrc_all_columns = array_keys( CIDRC_Log::FORMATS );
$cidrc_v110        = array_diff( $cidrc_all_columns, array( 'extended' ) );
$cidrc_v100        = array_diff( $cidrc_v110, array( 'ad_user_data', 'ad_personalization' ) );
$cidrc_insert_row  = array(
	'form_id'      => 5,
	'gclid'        => 'G',
	'ad_user_data' => 'Granted',
	'extended'     => '{"form_page":"/contact"}',
);

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stand-in.
$GLOBALS['wpdb'] = new CIDRC_Fake_Wpdb();

$GLOBALS['wpdb']->columns = $cidrc_all_columns;
cidrc_assert_same( 1, CIDRC_Log::insert( $cidrc_insert_row ), 'insert: schema 1.2.0 takes the row in one go' );
cidrc_assert_same( '{"form_page":"/contact"}', $GLOBALS['wpdb']->calls[0][0]['extended'], 'insert: the extended JSON is stored' );
cidrc_assert_same( count( $GLOBALS['wpdb']->calls[0][0] ), count( $GLOBALS['wpdb']->calls[0][1] ), 'insert: one format per column' );
cidrc_assert_same( '%f', $GLOBALS['wpdb']->calls[0][1][ array_search( 'conversion_value', array_keys( $GLOBALS['wpdb']->calls[0][0] ), true ) ], 'insert: the conversion value keeps its float format' );

$GLOBALS['wpdb']          = new CIDRC_Fake_Wpdb();
$GLOBALS['wpdb']->columns = $cidrc_v110;
cidrc_assert_same( 2, CIDRC_Log::insert( $cidrc_insert_row ), 'insert: without the extended column the retry keeps the lead' );
cidrc_assert_same( 'Granted', $GLOBALS['wpdb']->calls[1][0]['ad_user_data'], 'insert: that retry keeps the consent columns' );

$GLOBALS['wpdb']          = new CIDRC_Fake_Wpdb();
$GLOBALS['wpdb']->columns = $cidrc_v100;
cidrc_assert_same( 3, CIDRC_Log::insert( $cidrc_insert_row ), 'insert: on a 1.0.0 table the lead is still kept' );
cidrc_assert_same( false, isset( $GLOBALS['wpdb']->calls[2][0]['extended'] ) || isset( $GLOBALS['wpdb']->calls[2][0]['ad_user_data'] ), 'insert: the last retry drops the 1.1.0 and 1.2.0 columns' );

$GLOBALS['wpdb']          = new CIDRC_Fake_Wpdb();
$GLOBALS['wpdb']->columns = $cidrc_v110;
unset( $cidrc_insert_row['extended'] );
cidrc_assert_same( 1, CIDRC_Log::insert( $cidrc_insert_row ), 'insert: with no extended data the 1.1.0 insert is unchanged' );
cidrc_assert_same( array_values( $cidrc_v110 ), array_keys( $GLOBALS['wpdb']->calls[0][0] ), 'insert: the extended column is left out when there is nothing to store' );

$GLOBALS['wpdb']          = new CIDRC_Fake_Wpdb();
$GLOBALS['wpdb']->columns = array( 'id' );
cidrc_assert_same( 0, CIDRC_Log::insert( $cidrc_insert_row ), 'insert: a broken table returns 0' );
cidrc_assert_same( 2, count( $GLOBALS['wpdb']->calls ), 'insert: identical retries are not repeated' );

echo "\n{$cidrc_passes} passed, {$cidrc_failures} failed\n";

exit( $cidrc_failures > 0 ? 1 : 0 );
