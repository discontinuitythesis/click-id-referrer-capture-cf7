<?php
/**
 * List table for the stored submissions.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the submission log.
 */
class CIDRC_List_Table extends WP_List_Table {

	/**
	 * Active filters.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Constructor.
	 *
	 * @param array $filters Filter arguments.
	 */
	public function __construct( array $filters = array() ) {
		parent::__construct(
			array(
				'singular' => 'cidrc_submission',
				'plural'   => 'cidrc_submissions',
				'ajax'     => false,
			)
		);

		$this->filters = $filters;
	}

	/**
	 * Returns the table columns.
	 *
	 * @return array Column map.
	 */
	public function get_columns() {
		return array(
			'created_at'   => __( 'Date', 'click-id-referrer-capture-cf7' ),
			'form_title'   => __( 'Form', 'click-id-referrer-capture-cf7' ),
			'click_id'     => __( 'Click ID', 'click-id-referrer-capture-cf7' ),
			'source'       => __( 'Source / medium / campaign', 'click-id-referrer-capture-cf7' ),
			'landing_page' => __( 'Landing page', 'click-id-referrer-capture-cf7' ),
			'referrer'     => __( 'Referrer', 'click-id-referrer-capture-cf7' ),
			'email_hash'   => __( 'Email hash', 'click-id-referrer-capture-cf7' ),
		);
	}

	/**
	 * Loads the items for the current page.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 25;
		$page     = $this->get_pagenum();
		$args     = array_merge(
			$this->filters,
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);

		$this->items           = CIDRC_Log::get_rows( $args );
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$total = CIDRC_Log::count_rows( $this->filters );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Message shown when the log is empty.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No submissions have been logged yet.', 'click-id-referrer-capture-cf7' );
	}

	/**
	 * Renders a column that needs no special handling.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column name.
	 * @return string Cell contents.
	 */
	public function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '';

		return esc_html( $this->truncate( $value, 60 ) );
	}

	/**
	 * Renders the date column in the site timezone.
	 *
	 * @param array $item Row data.
	 * @return string Cell contents.
	 */
	public function column_created_at( $item ) {
		$created = isset( $item['created_at'] ) ? (string) $item['created_at'] : '';

		if ( '' === $created ) {
			return '';
		}

		$local = get_date_from_gmt( $created, 'Y-m-d H:i' );

		return esc_html( $local );
	}

	/**
	 * Renders the form column.
	 *
	 * @param array $item Row data.
	 * @return string Cell contents.
	 */
	public function column_form_title( $item ) {
		$title = isset( $item['form_title'] ) ? (string) $item['form_title'] : '';

		if ( '' === $title ) {
			/* translators: %d: Contact Form 7 form identifier. */
			$title = sprintf( __( 'Form %d', 'click-id-referrer-capture-cf7' ), isset( $item['form_id'] ) ? (int) $item['form_id'] : 0 );
		}

		return esc_html( $title );
	}

	/**
	 * Renders the click identifier column.
	 *
	 * @param array $item Row data.
	 * @return string Cell contents.
	 */
	public function column_click_id( $item ) {
		foreach ( array( 'gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid' ) as $key ) {
			if ( empty( $item[ $key ] ) ) {
				continue;
			}

			return '<strong>' . esc_html( cidrc_label_for( $key ) ) . '</strong><br /><code>' . esc_html( $this->truncate( (string) $item[ $key ], 28 ) ) . '</code>';
		}

		return esc_html__( 'None', 'click-id-referrer-capture-cf7' );
	}

	/**
	 * Renders the source, medium and campaign column.
	 *
	 * @param array $item Row data.
	 * @return string Cell contents.
	 */
	public function column_source( $item ) {
		$parts = array();

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $key ) {
			$parts[] = empty( $item[ $key ] ) ? '-' : $this->truncate( (string) $item[ $key ], 24 );
		}

		return esc_html( implode( ' / ', $parts ) );
	}

	/**
	 * Renders the email hash column.
	 *
	 * @param array $item Row data.
	 * @return string Cell contents.
	 */
	public function column_email_hash( $item ) {
		$hash = isset( $item['email_hash'] ) ? (string) $item['email_hash'] : '';

		if ( '' === $hash ) {
			return esc_html__( 'None', 'click-id-referrer-capture-cf7' );
		}

		return '<code title="' . esc_attr( $hash ) . '">' . esc_html( substr( $hash, 0, 12 ) ) . '</code>';
	}

	/**
	 * Shortens a string for display.
	 *
	 * @param string $value  Value.
	 * @param int    $length Maximum length.
	 * @return string Shortened value.
	 */
	protected function truncate( $value, $length ) {
		if ( strlen( $value ) <= $length ) {
			return $value;
		}

		return substr( $value, 0, $length ) . '...';
	}
}
