<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sforge_settings' );
delete_option( 'sforge_log' );
delete_option( 'sforge_migrated_from_sstp' );
delete_option( 'sforge_pending_urls' );
wp_clear_scheduled_hook( 'sforge_full_rebuild' );
wp_clear_scheduled_hook( 'sforge_partial_rebuild' );

// Clean up legacy "Send Static to Pages" keys if still present from a pre-1.1.0 install.
delete_option( 'sstp_settings' );
delete_option( 'sstp_log' );
wp_clear_scheduled_hook( 'sstp_full_rebuild' );
