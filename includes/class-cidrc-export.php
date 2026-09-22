<?php
/**
 * CSV exports for Google Ads, Microsoft Advertising and the full log.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the nonce protected admin-post export actions.
 */
class CIDRC_Export {

	/**
	 * Nonce action used by every export.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'cidrc_export';

	/**
	 * Registers the export handlers.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_cidrc_export_google', array( $this, 'export_google' ) );
		add_action( 'admin_post_cidrc_export_microsoft', array( $this, 'export_microsoft' ) );
		add_action( 'admin_post_cidrc_export_full', array( $this, 'export_full' ) );
	}

	/**
	 * Verifies the capability and nonce, then returns the requested filters.
	 *
	 * @return array Filter arguments.
	 */
	protected function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export submissions.', 'click-id-referrer-capture-cf7' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$form_id   = isset( $_GET['cidrc_form_id'] ) ? absint( wp_unslash( $_GET['cidrc_form_id'] ) ) : 0;
		$date_from = isset( $_GET['cidrc_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cidrc_date_from'] ) ) : '';
		$date_to   = isset( $_GET['cidrc_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cidrc_date_to'] ) ) : '';

		return array(
			'form_id'   => $form_id,
			'date_from' => self::clean_date( $date_from ),
			'date_to'   => self::clean_date( $date_to ),
		);
	}

	/**
	 * Validates a date string in the Y-m-d format.
	 *
	 * @param string $date Raw date.
	 * @return string Valid date, or an empty string.
	 */
	public static function clean_date( $date ) {
		$date = trim( (string) $date );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Sends the download headers for a CSV file.
	 *
	 * @param string $filename File name.
	 * @return void
	 */
	protected function send_headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
	}

	/**
	 * Streams rows in chunks and hands each one to the supplied callback.
	 *
	 * @param array    $args     Filter arguments.
	 * @param callable $callback Row callback returning an array of CSV fields, or null to skip.
	 * @return void
	 */
	protected function stream( array $args, $callback ) {
		$page     = 1;
		$per_page = 500;

		do {
			$args['page']     = $page;
			$args['per_page'] = $per_page;
			$rows             = CIDRC_Log::get_rows( $args );

			foreach ( $rows as $row ) {
				$fields = call_user_func( $callback, $row );

				if ( is_array( $fields ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for the CSV context by cidrc_esc_csv_row().
					echo cidrc_esc_csv_row( $fields );
				}
			}

			++$page;
		} while ( count( $rows ) === $per_page && $page < 400 );
	}

	/**
	 * Returns the timezone parameter line Google and Microsoft accept as the first row.
	 *
	 * Sites configured with a plain UTC offset rather than a named zone get no such
	 * line, because the importer expects an IANA name. Each conversion time still
	 * carries its own offset, so the file remains valid without it.
	 *
	 * @return array Single field row, or an empty array.
	 */
	protected function timezone_row() {
		$timezone = wp_timezone_string();

		if ( '' === $timezone || preg_match( '/^[+-]/', $timezone ) ) {
			return array();
		}

		return array( 'Parameters:TimeZone=' . $timezone . ';' );
	}

	/**
	 * Writes the timezone parameter line when the site uses a named time zone.
	 *
	 * @return void
	 */
	protected function write_timezone_row() {
		$row = $this->timezone_row();

		if ( empty( $row ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for the CSV context by cidrc_esc_csv_row().
		echo cidrc_esc_csv_row( $row );
	}

	/**
	 * Exports the Google Ads offline conversion CSV.
	 *
	 * @return void
	 */
	public function export_google() {
		$args                   = $this->guard();
		$args['require_google'] = true;

		$this->send_headers( 'google-ads-offline-conversions-' . gmdate( 'Ymd-His' ) . '.csv' );

		$this->write_timezone_row();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for the CSV context by cidrc_esc_csv_row().
		echo cidrc_esc_csv_row( array( 'Google Click ID', 'GBRAID', 'WBRAID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency' ) );

		$this->stream(
			$args,
			static function ( $row ) {
				return array(
					$row['gclid'],
					$row['gbraid'],
					$row['wbraid'],
					$row['conversion_name'],
					cidrc_format_conversion_time( $row['created_at'] ),
					number_format( (float) $row['conversion_value'], 2, '.', '' ),
					$row['currency'],
				);
			}
		);

		exit;
	}

	/**
	 * Exports the Microsoft Advertising offline conversion CSV.
	 *
	 * @return void
	 */
	public function export_microsoft() {
		$args                      = $this->guard();
		$args['require_microsoft'] = true;

		$this->send_headers( 'microsoft-ads-offline-conversions-' . gmdate( 'Ymd-His' ) . '.csv' );

		$this->write_timezone_row();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for the CSV context by cidrc_esc_csv_row().
		echo cidrc_esc_csv_row( array( 'Microsoft Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency' ) );

		$this->stream(
			$args,
			static function ( $row ) {
				return array(
					$row['msclkid'],
					$row['conversion_name'],
					cidrc_format_conversion_time( $row['created_at'] ),
					number_format( (float) $row['conversion_value'], 2, '.', '' ),
					$row['currency'],
				);
			}
		);

		exit;
	}

	/**
	 * Exports the full submission log.
	 *
	 * @return void
	 */
	public function export_full() {
		$args = $this->guard();

		$this->send_headers( 'click-id-capture-log-' . gmdate( 'Ymd-His' ) . '.csv' );

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
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for the CSV context by cidrc_esc_csv_row().
		echo cidrc_esc_csv_row( $columns );

		$this->stream(
			$args,
			static function ( $row ) use ( $columns ) {
				$fields = array();

				foreach ( $columns as $column ) {
					$fields[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
				}

				return $fields;
			}
		);

		exit;
	}

	/**
	 * Builds a nonce protected export URL.
	 *
	 * @param string $action Export action name.
	 * @param array  $args   Filter arguments.
	 * @return string Export URL.
	 */
	public static function url( $action, array $args = array() ) {
		$url = add_query_arg(
			array(
				'action'          => $action,
				'cidrc_form_id'   => isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0,
				'cidrc_date_from' => isset( $args['date_from'] ) ? rawurlencode( $args['date_from'] ) : '',
				'cidrc_date_to'   => isset( $args['date_to'] ) ? rawurlencode( $args['date_to'] ) : '',
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, self::NONCE_ACTION );
	}
}
