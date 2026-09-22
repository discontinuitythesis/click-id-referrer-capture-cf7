<?php
/**
 * Submission log: table creation, inserts, queries and purging.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores one row per Contact Form 7 submission that sent mail.
 */
class CIDRC_Log {

	/**
	 * Schema version option name.
	 *
	 * @var string
	 */
	const DB_OPTION = 'cidrc_db_version';

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Returns the fully qualified table name.
	 *
	 * @return string Table name.
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cidrc_submissions';
	}

	/**
	 * Creates or upgrades the table when the stored schema version differs.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Creates the table with dbDelta.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_title varchar(191) NOT NULL DEFAULT '',
			gclid varchar(255) NOT NULL DEFAULT '',
			gbraid varchar(255) NOT NULL DEFAULT '',
			wbraid varchar(255) NOT NULL DEFAULT '',
			msclkid varchar(255) NOT NULL DEFAULT '',
			fbclid varchar(255) NOT NULL DEFAULT '',
			utm_source varchar(191) NOT NULL DEFAULT '',
			utm_medium varchar(191) NOT NULL DEFAULT '',
			utm_campaign varchar(191) NOT NULL DEFAULT '',
			landing_page varchar(500) NOT NULL DEFAULT '',
			referrer varchar(500) NOT NULL DEFAULT '',
			email_hash char(64) NOT NULL DEFAULT '',
			phone_hash char(64) NOT NULL DEFAULT '',
			conversion_name varchar(191) NOT NULL DEFAULT '',
			conversion_value decimal(18,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY form_id (form_id)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Inserts one submission row.
	 *
	 * @param array $row Row data.
	 * @return int Inserted row identifier, or 0 on failure.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$defaults = array(
			'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			'form_id'          => 0,
			'form_title'       => '',
			'gclid'            => '',
			'gbraid'           => '',
			'wbraid'           => '',
			'msclkid'          => '',
			'fbclid'           => '',
			'utm_source'       => '',
			'utm_medium'       => '',
			'utm_campaign'     => '',
			'landing_page'     => '',
			'referrer'         => '',
			'email_hash'       => '',
			'phone_hash'       => '',
			'conversion_name'  => '',
			'conversion_value' => 0,
			'currency'         => '',
		);

		$row = array_merge( $defaults, array_intersect_key( $row, $defaults ) );

		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$inserted = $wpdb->insert( self::table_name(), $row, $formats );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Builds the WHERE clause and arguments for the supplied filters.
	 *
	 * @param array $args Filter arguments: form_id, date_from, date_to.
	 * @return array Array with the sql and args keys.
	 */
	protected static function build_where( array $args ) {
		$sql  = ' WHERE 1=1';
		$vals = array();

		if ( ! empty( $args['form_id'] ) ) {
			$sql   .= ' AND form_id = %d';
			$vals[] = absint( $args['form_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$sql   .= ' AND created_at >= %s';
			$vals[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$sql   .= ' AND created_at <= %s';
			$vals[] = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['require_google'] ) ) {
			$sql .= " AND ( gclid <> '' OR gbraid <> '' OR wbraid <> '' )";
		}

		if ( ! empty( $args['require_microsoft'] ) ) {
			$sql .= " AND msclkid <> ''";
		}

		return array(
			'sql'  => $sql,
			'args' => $vals,
		);
	}

	/**
	 * Returns rows matching the supplied filters.
	 *
	 * @param array $args Filters plus per_page and page.
	 * @return array List of rows as associative arrays.
	 */
	public static function get_rows( array $args = array() ) {
		global $wpdb;

		$where    = self::build_where( $args );
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
		$per_page = max( 1, min( 5000, $per_page ) );
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table_name();

		$query = 'SELECT * FROM %i' . $where['sql'] . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
		$vals  = array_merge( array( $table ), $where['args'], array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; the SQL contains only literal fragments and placeholders, every value goes through $wpdb->prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $vals ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Counts rows matching the supplied filters.
	 *
	 * @param array $args Filters.
	 * @return int Row count.
	 */
	public static function count_rows( array $args = array() ) {
		global $wpdb;

		$where = self::build_where( $args );
		$query = 'SELECT COUNT(*) FROM %i' . $where['sql'];
		$vals  = array_merge( array( self::table_name() ), $where['args'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; the SQL contains only literal fragments and placeholders, every value goes through $wpdb->prepare().
		$count = $wpdb->get_var( $wpdb->prepare( $query, $vals ) );

		return (int) $count;
	}

	/**
	 * Returns the distinct forms present in the log.
	 *
	 * @return array Map of form identifier to form title.
	 */
	public static function get_forms() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT form_id, form_title FROM %i ORDER BY form_title ASC', self::table_name() ), ARRAY_A );

		$forms = array();

		if ( ! is_array( $rows ) ) {
			return $forms;
		}

		foreach ( $rows as $row ) {
			$forms[ (int) $row['form_id'] ] = (string) $row['form_title'];
		}

		return $forms;
	}

	/**
	 * Deletes rows older than the configured retention period.
	 *
	 * @return int Number of rows removed.
	 */
	public static function purge() {
		global $wpdb;

		$days = absint( cidrc_get_setting( 'retention_days', 90 ) );

		if ( 0 === $days ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table_name(), $cutoff ) );

		return (int) $deleted;
	}
}
