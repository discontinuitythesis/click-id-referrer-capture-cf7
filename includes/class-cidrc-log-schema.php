<?php
/**
 * Submission log schema: table name, creation and upgrades.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the submission table. CIDRC_Log extends this class.
 */
class CIDRC_Log_Schema {

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
	const DB_VERSION = '1.2.0';

	/**
	 * Columns added in schema 1.1.0. They are last in the table and in insert().
	 *
	 * @var array
	 */
	const CONSENT_COLUMNS = array( 'ad_user_data', 'ad_personalization' );

	/**
	 * Column added in schema 1.2.0: the extended attribution record as JSON.
	 *
	 * @var array
	 */
	const EXTENDED_COLUMNS = array( 'extended' );

	/**
	 * Insert format of every column.
	 *
	 * @var array
	 */
	const FORMATS = array(
		'created_at'         => '%s',
		'form_id'            => '%d',
		'form_title'         => '%s',
		'gclid'              => '%s',
		'gbraid'             => '%s',
		'wbraid'             => '%s',
		'msclkid'            => '%s',
		'fbclid'             => '%s',
		'utm_source'         => '%s',
		'utm_medium'         => '%s',
		'utm_campaign'       => '%s',
		'landing_page'       => '%s',
		'referrer'           => '%s',
		'email_hash'         => '%s',
		'phone_hash'         => '%s',
		'conversion_name'    => '%s',
		'conversion_value'   => '%f',
		'currency'           => '%s',
		'ad_user_data'       => '%s',
		'ad_personalization' => '%s',
		'extended'           => '%s',
	);

	/**
	 * Transient that pauses upgrade attempts for an hour after one has failed.
	 *
	 * @var string
	 */
	const RETRY_TRANSIENT = 'cidrc_db_upgrade_wait';

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
	 * Hooked on plugins_loaded, so it also runs after an update that did not fire
	 * the activation hook, such as a ZIP upload that replaces the plugin, and on the
	 * REST request Contact Form 7 uses to submit a form. When the versions match it
	 * costs one autoloaded option read.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		if ( get_transient( self::RETRY_TRANSIENT ) ) {
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
			ad_user_data varchar(10) NOT NULL DEFAULT '',
			ad_personalization varchar(10) NOT NULL DEFAULT '',
			extended longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY form_id (form_id)
		) {$collate};";

		dbDelta( $sql );

		// Record the new version only once the columns really exist, so that a failed
		// ALTER TABLE is retried later instead of being marked as done.
		if ( ! self::has_columns( array_merge( self::CONSENT_COLUMNS, self::EXTENDED_COLUMNS ) ) ) {
			set_transient( self::RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );
			return;
		}

		delete_transient( self::RETRY_TRANSIENT );

		// Autoloaded, so the check in maybe_install() needs no query of its own.
		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Reports whether the table has every one of the given columns.
	 *
	 * @param array $columns Column names.
	 * @return bool True when all the columns exist.
	 */
	protected static function has_columns( array $columns ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table schema check.
		$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table_name() ) );

		return is_array( $existing ) && array() === array_diff( $columns, $existing );
	}
}
