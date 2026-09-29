<?php
/**
 * Submission log: inserts, queries and purging. The schema is in class-cidrc-log-schema.php.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores one row per Contact Form 7 submission that sent mail.
 */
class CIDRC_Log extends CIDRC_Log_Schema {

	/**
	 * Inserts one submission row.
	 *
	 * @param array $row Row data.
	 * @return int Inserted row identifier, or 0 on failure.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$defaults = array(
			'created_at'         => gmdate( 'Y-m-d H:i:s' ),
			'form_id'            => 0,
			'form_title'         => '',
			'gclid'              => '',
			'gbraid'             => '',
			'wbraid'             => '',
			'msclkid'            => '',
			'fbclid'             => '',
			'utm_source'         => '',
			'utm_medium'         => '',
			'utm_campaign'       => '',
			'landing_page'       => '',
			'referrer'           => '',
			'email_hash'         => '',
			'phone_hash'         => '',
			'conversion_name'    => '',
			'conversion_value'   => 0,
			'currency'           => '',
			'ad_user_data'       => '',
			'ad_personalization' => '',
			'extended'           => null,
		);

		$row = array_merge( $defaults, array_intersect_key( $row, $defaults ) );

		// Without extended data the column is left out, so the insert is the same
		// as in 1.1.0 and does not depend on the 1.2.0 column existing.
		if ( null === $row['extended'] || '' === $row['extended'] ) {
			unset( $row['extended'] );
		}

		// The newer columns may be missing if the schema upgrade has not been able
		// to run yet. Keep the lead rather than lose it: retry without the 1.2.0
		// column, then without the 1.1.0 columns as well.
		$attempts = array(
			$row,
			array_diff_key( $row, array_flip( self::EXTENDED_COLUMNS ) ),
			array_diff_key( $row, array_flip( array_merge( self::CONSENT_COLUMNS, self::EXTENDED_COLUMNS ) ) ),
		);
		$tried    = array();

		foreach ( $attempts as $attempt ) {
			$key = implode( ',', array_keys( $attempt ) );

			if ( isset( $tried[ $key ] ) ) {
				continue;
			}

			$tried[ $key ] = true;

			// One format per column, in the order of the columns.
			$formats = array();

			foreach ( array_keys( $attempt ) as $column ) {
				$formats[] = self::FORMATS[ $column ];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			if ( $wpdb->insert( self::table_name(), $attempt, $formats ) ) {
				return (int) $wpdb->insert_id;
			}
		}

		return 0;
	}

	/**
	 * Reports whether any row matching the filters holds extended attribution data.
	 *
	 * @param array $args Filters.
	 * @return bool True when at least one row has extended data.
	 */
	public static function has_extended_rows( array $args = array() ) {
		if ( ! self::has_columns( self::EXTENDED_COLUMNS ) ) {
			return false;
		}

		$args['require_extended'] = true;

		return self::count_rows( $args ) > 0;
	}

	/**
	 * Builds the WHERE clause and arguments for the supplied filters.
	 *
	 * @param array $args Filter arguments: form_id, date_from, date_to, require_gclid,
	 *                    require_google, require_microsoft and require_extended.
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

		if ( ! empty( $args['require_gclid'] ) ) {
			$sql .= " AND gclid <> ''";
		}

		if ( ! empty( $args['require_google'] ) ) {
			$sql .= " AND ( gclid <> '' OR gbraid <> '' OR wbraid <> '' )";
		}

		if ( ! empty( $args['require_microsoft'] ) ) {
			$sql .= " AND msclkid <> ''";
		}

		if ( ! empty( $args['require_extended'] ) ) {
			$sql .= " AND extended IS NOT NULL AND extended <> ''";
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only static clauses and placeholders are assembled above; values and table identifiers are prepared here.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only static clauses and placeholders are assembled above; values and table identifiers are prepared here.
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
