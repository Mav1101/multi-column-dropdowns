<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * Elementor widget settings live inside each page's Elementor data and are left alone;
 * without the plugin they are simply ignored.
 *
 * @package MultiColumnDropdowns
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the option and all menu item meta of the current site.
 */
function mcd_uninstall_site() {
	delete_option( 'mcd_settings' );
	delete_post_meta_by_key( '_mcd_settings' );
}

if ( is_multisite() ) {
	$mcd_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $mcd_site_ids as $mcd_site_id ) {
		switch_to_blog( $mcd_site_id );
		mcd_uninstall_site();
		restore_current_blog();
	}
} else {
	mcd_uninstall_site();
}
