<?php
// Suppression complète : réglages, fiches des cross et fichiers de résultats.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'cc53_settings' );
delete_transient( 'cc53_config' );
foreach ( get_posts( array( 'post_type' => 'cc53_cross', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
$u   = wp_upload_dir();
$dir = trailingslashit( $u['basedir'] ) . 'cc53/';
if ( is_dir( $dir ) ) {
	foreach ( (array) glob( $dir . '*' ) as $f ) {
		wp_delete_file( $f );
	}
	@rmdir( $dir ); // phpcs:ignore
}
