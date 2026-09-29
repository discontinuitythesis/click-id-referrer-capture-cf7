<?php
/**
 * CSV header and row builders for the exports.
 *
 * These methods take plain arrays and return plain arrays, so that they can be
 * tested without WordPress. The only helpers they call are cidrc_format_conversion_time(),
 * cidrc_normalise_consent() and cidrc_order_id() from formatting.php, and
 * cidrc_extended_decode() from extended.php for the extended columns.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds export headers and rows from log rows.
 */
class CIDRC_Export_Rows {

	/**
	 * Returns the Google Ads click conversion import header.
	 *
	 * The column names follow Google's file import template. GBRAID and WBRAID go
	 * immediately after Google Click ID when they are switched on.
	 *
	 * @param bool $include_braid Whether to add the GBRAID and WBRAID columns.
	 * @return array Column names.
	 */
	public static function google_header( $include_braid ) {
		$header = array( 'Google Click ID' );

		if ( $include_braid ) {
			$header[] = 'GBRAID';
			$header[] = 'WBRAID';
		}

		return array_merge(
			$header,
			array( 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency', 'Order ID', 'Ad User Data', 'Ad Personalization' )
		);
	}

	/**
	 * Returns the identifier cells for a Google Ads row, or null to skip the row.
	 *
	 * Only one identifier is filled per row, in the order gclid, gbraid, wbraid,
	 * because the import rejects a row that carries more than one.
	 *
	 * @param array $row           Log row.
	 * @param bool  $include_braid Whether the GBRAID and WBRAID columns are present.
	 * @return array|null Identifier cells, or null when the row has no usable identifier.
	 */
	public static function google_identifiers( array $row, $include_braid ) {
		$gclid = self::field( $row, 'gclid' );

		if ( '' !== $gclid ) {
			return $include_braid ? array( $gclid, '', '' ) : array( $gclid );
		}

		if ( ! $include_braid ) {
			return null;
		}

		$gbraid = self::field( $row, 'gbraid' );

		if ( '' !== $gbraid ) {
			return array( '', $gbraid, '' );
		}

		$wbraid = self::field( $row, 'wbraid' );

		if ( '' !== $wbraid ) {
			return array( '', '', $wbraid );
		}

		return null;
	}

	/**
	 * Builds one Google Ads row in the same column order as google_header().
	 *
	 * @param array $row           Log row.
	 * @param bool  $include_braid Whether the GBRAID and WBRAID columns are present.
	 * @return array|null CSV cells, or null when the row cannot be imported.
	 */
	public static function google_row( array $row, $include_braid ) {
		$identifiers = self::google_identifiers( $row, $include_braid );

		if ( null === $identifiers ) {
			return null;
		}

		return array_merge(
			$identifiers,
			array(
				self::field( $row, 'conversion_name' ),
				cidrc_format_conversion_time( self::field( $row, 'created_at' ) ),
				self::money( $row ),
				self::field( $row, 'currency' ),
				cidrc_order_id( self::field( $row, 'id' ) ),
				cidrc_normalise_consent( self::field( $row, 'ad_user_data' ) ),
				cidrc_normalise_consent( self::field( $row, 'ad_personalization' ) ),
			)
		);
	}

	/**
	 * Returns the Microsoft Advertising header.
	 *
	 * @return array Column names.
	 */
	public static function microsoft_header() {
		return array( 'Microsoft Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency' );
	}

	/**
	 * Builds one Microsoft Advertising row.
	 *
	 * @param array $row Log row.
	 * @return array CSV cells.
	 */
	public static function microsoft_row( array $row ) {
		return array(
			self::field( $row, 'msclkid' ),
			self::field( $row, 'conversion_name' ),
			cidrc_format_conversion_time( self::field( $row, 'created_at' ) ),
			self::money( $row ),
			self::field( $row, 'currency' ),
		);
	}

	/**
	 * Returns the full log columns. The header row uses these keys as they are.
	 *
	 * @param bool $extended Whether to append the extended attribution columns.
	 * @return array Column keys.
	 */
	public static function full_columns( $extended = false ) {
		$columns = array(
			'id',
			'created_at',
			'form_id',
			'form_title',
			'gclid',
			'gbraid',
			'wbraid',
			'msclkid',
			'fbclid',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'landing_page',
			'referrer',
			'email_hash',
			'phone_hash',
			'conversion_name',
			'conversion_value',
			'currency',
			'ad_user_data',
			'ad_personalization',
			'order_id',
		);

		return $extended ? array_merge( $columns, self::extended_columns() ) : $columns;
	}

	/**
	 * Maps each touch key to its column name suffix in the full log.
	 *
	 * @return array Map of touch key to column suffix.
	 */
	protected static function touch_columns() {
		return array(
			'channel'       => 'channel',
			'utm_source'    => 'utm_source',
			'utm_medium'    => 'utm_medium',
			'utm_campaign'  => 'utm_campaign',
			'utm_term'      => 'utm_term',
			'utm_content'   => 'utm_content',
			'utm_id'        => 'utm_id',
			'click_id_type' => 'click_id_type',
			'referrer'      => 'referrer_ext',
			'landing_page'  => 'landing_page_ext',
			'at'            => 'touch_at',
		);
	}

	/**
	 * Returns the extended attribution columns appended to the full log.
	 *
	 * @return array Column keys.
	 */
	public static function extended_columns() {
		$columns = array( 'form_page', 'form_page_title' );

		foreach ( array( 'first', 'last' ) as $prefix ) {
			foreach ( self::touch_columns() as $suffix ) {
				$columns[] = $prefix . '_' . $suffix;
			}
		}

		return $columns;
	}

	/**
	 * Builds the extended attribution cells of one row, in the order of extended_columns().
	 *
	 * @param array $row Log row, with the extended column as JSON text.
	 * @return array CSV cells, blank when the row has no extended data.
	 */
	public static function extended_cells( array $row ) {
		$extended = cidrc_extended_decode( isset( $row['extended'] ) ? $row['extended'] : '' );
		$cells    = array(
			isset( $extended['form_page'] ) ? $extended['form_page'] : '',
			isset( $extended['form_page_title'] ) ? $extended['form_page_title'] : '',
		);

		foreach ( array( 'first', 'last' ) as $prefix ) {
			$touch = isset( $extended[ $prefix ] ) ? $extended[ $prefix ] : array();

			foreach ( array_keys( self::touch_columns() ) as $key ) {
				$cells[] = self::field( $touch, $key );
			}
		}

		return $cells;
	}

	/**
	 * Builds one full log row in the order of full_columns().
	 *
	 * @param array $row      Log row.
	 * @param bool  $extended Whether to append the extended attribution cells.
	 * @return array CSV cells.
	 */
	public static function full_row( array $row, $extended = false ) {
		$row['order_id'] = cidrc_order_id( self::field( $row, 'id' ) );
		$fields          = array();

		foreach ( self::full_columns() as $column ) {
			$fields[] = self::field( $row, $column );
		}

		return $extended ? array_merge( $fields, self::extended_cells( $row ) ) : $fields;
	}

	/**
	 * Formats the conversion value with two decimals and a dot separator.
	 *
	 * @param array $row Log row.
	 * @return string Value.
	 */
	protected static function money( array $row ) {
		return number_format( (float) self::field( $row, 'conversion_value' ), 2, '.', '' );
	}

	/**
	 * Reads one scalar field from a row as a string.
	 *
	 * @param array  $row Log row.
	 * @param string $key Column name.
	 * @return string Value, or an empty string when missing.
	 */
	protected static function field( array $row, $key ) {
		return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
	}
}
