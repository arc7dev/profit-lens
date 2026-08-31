<?php
/**
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Cleans up whatever Profit Lens has stored in the
 * database.
 *
 * The plugin doesn't persist any options or tables of its own yet — this
 * phase is scaffolding only. The standard guard and cleanup block are left
 * in place for when they exist (settings, calculation-cache transients).
 *
 * @package ProfitLens
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Options prefixed with profitlens_ (none yet at this stage).
delete_option( 'profitlens_settings' );

// Engine cache transients, if any ever exist.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_profitlens\\_%' OR option_name LIKE '\\_transient\\_timeout\\_profitlens\\_%'"
);
