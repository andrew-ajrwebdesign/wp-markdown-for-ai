<?php
/**
 * Runs on plugin uninstall — removes all plugin data from the database.
 *
 * Only executes when triggered by WordPress's uninstall mechanism.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove settings.
delete_option( 'wpmai_settings' );

// Remove all plugin transients.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wpmai_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wpmai_' ) . '%'
	)
);
