<?php
/**
 * Plugin Name:       Cross Corpo 53 – Résultats
 * Description:       Gestion des cross (lieu, date, organisateurs, parcours), dépôt des classements « Scratch » et des traces GPX, et affichage des résultats, classements de la saison, statistiques et analyses de parcours. Shortcode : [cc53_resultats].
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Baptiste Houssemaine
 * License:           GPL-2.0-or-later
 * Text Domain:       cc53-resultats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC53_VERSION', '2.0.0' );
define( 'CC53_FILE', __FILE__ );
define( 'CC53_DIR', plugin_dir_path( __FILE__ ) );
define( 'CC53_URL', plugin_dir_url( __FILE__ ) );
define( 'CC53_CPT', 'cc53_cross' );
define( 'CC53_OPTION', 'cc53_settings' );

require_once CC53_DIR . 'includes/data.php';
require_once CC53_DIR . 'includes/admin.php';
require_once CC53_DIR . 'includes/front.php';

register_activation_hook( __FILE__, 'cc53_activate' );

function cc53_activate() {
	cc53_register_cpt();
	cc53_storage_dir();
	if ( ! get_posts( array( 'post_type' => CC53_CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
		cc53_import_seed();
	}
	cc53_rebuild_index();
	flush_rewrite_rules();
}
