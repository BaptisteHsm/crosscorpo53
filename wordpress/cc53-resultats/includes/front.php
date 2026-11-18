<?php
/**
 * Affichage public : shortcode [cc53_resultats].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cc53_resultats', 'cc53_shortcode' );

function cc53_shortcode() {
	$ver = CC53_VERSION . '-' . substr( md5( (string) @filemtime( CC53_DIR . 'assets/app.js' ) ), 0, 6 ); // phpcs:ignore
	wp_enqueue_style( 'cc53-app', CC53_URL . 'assets/app.css', array(), $ver );
	wp_enqueue_script( 'cc53-app', CC53_URL . 'assets/app.js', array(), $ver, true );
	wp_add_inline_script( 'cc53-app', 'window.CC53_CONFIG=' . wp_json_encode( cc53_front_config() ) . ';', 'before' );

	ob_start();
	?>
	<div id="cc53-app" class="cc53 alignwide">
		<main id="view"><p class="cc53-loading"><?php esc_html_e( 'Chargement des résultats…', 'cc53-resultats' ); ?></p></main>
		<p class="cc53-credit">
			<?php esc_html_e( 'Chronométrage GmCAP.', 'cc53-resultats' ); ?>
			<span id="syncState"></span>
			<a href="#mentions" data-go="mentions"><?php esc_html_e( 'Données personnelles', 'cc53-resultats' ); ?></a>
		</p>
		<div id="overlay"></div>
		<div id="tip" class="tip" hidden></div>
	</div>
	<noscript><p><?php esc_html_e( 'Activez JavaScript pour consulter les résultats.', 'cc53-resultats' ); ?></p></noscript>
	<?php
	return ob_get_clean();
}
