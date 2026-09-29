<?php
/**
 * Uninstall routine. Removes the submission table, the options and the cron event.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$cidrc_table = $wpdb->prefix . 'cidrc_submissions';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time custom table removal on uninstall; caching a DROP has no purpose.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $cidrc_table ) );

delete_option( 'cidrc_settings' );
delete_option( 'cidrc_db_version' );

wp_clear_scheduled_hook( 'cidrc_daily_purge' );
