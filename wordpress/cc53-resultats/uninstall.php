<?php
// Suppression des réglages (dont le jeton GitHub) quand l'extension est supprimée.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'cc53_resultats' );
