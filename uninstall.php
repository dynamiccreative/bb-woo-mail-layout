<?php
/**
 * Suppression du plugin : effacement des options (la simple désactivation les conserve).
 *
 * @package BB\WooMailLayout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bb_woo_mail_layout' );
delete_option( 'bb_woo_mail_layout_logo_status' );
wp_clear_scheduled_hook( 'bb_wml_daily_logo_check' );
delete_option( 'bb_wml_github_access_token' );
