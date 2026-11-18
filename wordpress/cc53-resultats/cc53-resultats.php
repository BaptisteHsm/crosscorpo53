<?php
/**
 * Plugin Name:       Cross Corpo 53 – Résultats
 * Description:       Résultats, classements de la saison, statistiques et analyse des parcours des Cross Corporatifs 53. Insérez le shortcode [cc53_resultats] dans une page.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Baptiste Houssemaine
 * License:           GPL-2.0-or-later
 * Text Domain:       cc53-resultats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CC53_VERSION      = '1.0.0';
const CC53_OPTION       = 'cc53_resultats';
const CC53_DEFAULT_BASE = 'https://baptistehsm.github.io/crosscorpo53/';
const CC53_DEFAULT_REPO = 'BaptisteHsm/crosscorpo53';

/** Réglages enregistrés, avec valeurs par défaut. */
function cc53_settings() {
	$s = get_option( CC53_OPTION, array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'base'   => CC53_DEFAULT_BASE,
			'repo'   => CC53_DEFAULT_REPO,
			'branch' => 'main',
			'token'  => '',
			'offset' => 90,
		)
	);
}

function cc53_base() {
	return trailingslashit( esc_url_raw( cc53_settings()['base'] ) );
}

/* -------------------------------------------------------------------------
 * Shortcode [cc53_resultats]
 * ---------------------------------------------------------------------- */

add_shortcode( 'cc53_resultats', 'cc53_shortcode' );

function cc53_shortcode() {
	$base = cc53_base();
	$cfg  = array(
		'data'   => $base . 'data/',
		'assets' => $base . 'wp/',
		'offset' => (int) cc53_settings()['offset'],
	);

	// Petit chargeur : lit la version publiée puis charge le CSS et le JS correspondants.
	// Les résultats ajoutés sur GitHub apparaissent ainsi sans mettre à jour le plugin.
	wp_register_script( 'cc53-loader', false, array(), CC53_VERSION, true );
	wp_enqueue_script( 'cc53-loader' );
	wp_add_inline_script(
		'cc53-loader',
		'window.CC53_CONFIG=' . wp_json_encode( $cfg ) . ';' .
		'(function(c){function go(v){var q="?v="+encodeURIComponent(v||Date.now());' .
		'var l=document.createElement("link");l.rel="stylesheet";l.href=c.assets+"app.css"+q;document.head.appendChild(l);' .
		'var s=document.createElement("script");s.src=c.assets+"app.js"+q;s.defer=true;document.body.appendChild(s);}' .
		'fetch(c.assets+"version.json",{cache:"no-store"}).then(function(r){return r.json()}).then(function(j){go(j.v)}).catch(function(){go()});' .
		'})(window.CC53_CONFIG);'
	);

	ob_start();
	?>
	<div id="cc53-app" class="cc53">
		<main id="view"><p style="text-align:center;padding:40px 0;opacity:.6"><?php esc_html_e( 'Chargement des résultats…', 'cc53-resultats' ); ?></p></main>
		<p class="cc53-credit">
			<?php esc_html_e( 'Données : classements « Scratch » (chronométrage GmCAP).', 'cc53-resultats' ); ?>
			<span id="syncState"></span>
			<?php esc_html_e( 'Réalisation : Baptiste Houssemaine.', 'cc53-resultats' ); ?>
		</p>
		<div id="overlay"></div>
		<div id="tip" class="tip" hidden></div>
	</div>
	<noscript><p><?php esc_html_e( 'Activez JavaScript pour consulter les résultats.', 'cc53-resultats' ); ?></p></noscript>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Administration : Réglages › Cross Corpo 53
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'cc53_admin_menu' );
add_action( 'admin_post_cc53_save', 'cc53_handle_save' );
add_action( 'admin_post_cc53_upload', 'cc53_handle_upload' );

function cc53_admin_menu() {
	add_options_page(
		__( 'Cross Corpo 53 – Résultats', 'cc53-resultats' ),
		__( 'Cross Corpo 53', 'cc53-resultats' ),
		'manage_options',
		'cc53-resultats',
		'cc53_admin_page'
	);
}

/** Appel à l'API GitHub avec le jeton enregistré. */
function cc53_github( $method, $path, $body = null ) {
	$s    = cc53_settings();
	$args = array(
		'method'  => $method,
		'timeout' => 30,
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . $s['token'],
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'cc53-resultats-wordpress',
		),
	);
	if ( null !== $body ) {
		$args['body']                    = wp_json_encode( $body );
		$args['headers']['Content-Type'] = 'application/json';
	}
	$res = wp_remote_request( 'https://api.github.com/repos/' . $s['repo'] . $path, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array(
		'code' => wp_remote_retrieve_response_code( $res ),
		'body' => json_decode( wp_remote_retrieve_body( $res ), true ),
	);
}

function cc53_redirect( $msg, $type = 'success' ) {
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'cc53-resultats',
				'cc53_msg'  => rawurlencode( $msg ),
				'cc53_type' => $type,
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}

function cc53_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'cc53-resultats' ) );
	}
	check_admin_referer( 'cc53_save' );
	$s           = cc53_settings();
	$s['base']   = esc_url_raw( wp_unslash( $_POST['base'] ?? CC53_DEFAULT_BASE ) );
	$s['repo']   = preg_replace( '/[^A-Za-z0-9_.\-\/]/', '', sanitize_text_field( wp_unslash( $_POST['repo'] ?? '' ) ) );
	$s['branch'] = sanitize_text_field( wp_unslash( $_POST['branch'] ?? 'main' ) );
	$s['offset'] = max( 0, (int) ( $_POST['offset'] ?? 90 ) );
	$token       = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
	if ( '' !== $token ) {
		$s['token'] = $token;
	}
	if ( ! empty( $_POST['forget_token'] ) ) {
		$s['token'] = '';
	}
	update_option( CC53_OPTION, $s, false );
	cc53_redirect( __( 'Réglages enregistrés.', 'cc53-resultats' ) );
}

function cc53_handle_upload() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'cc53-resultats' ) );
	}
	check_admin_referer( 'cc53_upload' );
	$s = cc53_settings();
	if ( '' === $s['token'] ) {
		cc53_redirect( __( 'Ajoutez d’abord le jeton GitHub dans les réglages.', 'cc53-resultats' ), 'error' );
	}
	if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
		cc53_redirect( __( 'Aucun fichier reçu.', 'cc53-resultats' ), 'error' );
	}
	$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$size = (int) $_FILES['file']['size'];
	if ( ! in_array( $ext, array( 'pdf', 'gpx' ), true ) || $size <= 0 || $size > 20 * MB_IN_BYTES ) {
		cc53_redirect( __( 'Fichier refusé : seuls les PDF « Scratch » et les traces GPX de moins de 20 Mo sont acceptés.', 'cc53-resultats' ), 'error' );
	}
	$content = file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( 'pdf' === $ext && 0 !== strpos( $content, '%PDF' ) ) {
		cc53_redirect( __( 'Ce fichier n’est pas un PDF valide.', 'cc53-resultats' ), 'error' );
	}
	if ( 'gpx' === $ext && false === stripos( substr( $content, 0, 2000 ), '<gpx' ) ) {
		cc53_redirect( __( 'Ce fichier n’est pas une trace GPX valide.', 'cc53-resultats' ), 'error' );
	}
	$folder = 'pdf' === $ext ? 'pdfs' : 'gpx';
	$res    = cc53_github(
		'PUT',
		'/contents/' . $folder . '/' . rawurlencode( $name ),
		array(
			'message' => ( 'pdf' === $ext ? 'Ajout des résultats ' : 'Ajout du tracé ' ) . $name . ' (WordPress)',
			'content' => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			'branch'  => $s['branch'],
		)
	);
	if ( is_wp_error( $res ) ) {
		cc53_redirect( $res->get_error_message(), 'error' );
	}
	if ( 201 === $res['code'] || 200 === $res['code'] ) {
		cc53_redirect( sprintf( __( '%s envoyé. Les résultats seront en ligne dans une à deux minutes : suivez l’avancement ci-dessous.', 'cc53-resultats' ), $name ) );
	}
	if ( 422 === $res['code'] ) {
		cc53_redirect( __( 'Un fichier portant ce nom est déjà en attente de traitement. Renommez-le ou patientez une minute.', 'cc53-resultats' ), 'error' );
	}
	$msg = is_array( $res['body'] ) && isset( $res['body']['message'] ) ? $res['body']['message'] : '';
	cc53_redirect( sprintf( __( 'GitHub a refusé l’envoi (code %1$d) : %2$s', 'cc53-resultats' ), $res['code'], $msg ), 'error' );
}

function cc53_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = cc53_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cross Corpo 53 – Résultats', 'cc53-resultats' ); ?></h1>
		<?php
		if ( isset( $_GET['cc53_msg'] ) ) {
			$type = ( isset( $_GET['cc53_type'] ) && 'error' === $_GET['cc53_type'] ) ? 'notice-error' : 'notice-success';
			printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( rawurldecode( wp_unslash( $_GET['cc53_msg'] ) ) ) );
		}
		?>
		<p><?php echo wp_kses_post( __( 'Pour afficher les résultats, insérez le shortcode <code>[cc53_resultats]</code> dans une page (bloc « Code court »).', 'cc53-resultats' ) ); ?></p>

		<h2><?php esc_html_e( 'Ajouter des résultats', 'cc53-resultats' ); ?></h2>
		<?php if ( '' === $s['token'] ) : ?>
			<p><em><?php esc_html_e( 'Renseignez le jeton GitHub dans les réglages ci-dessous pour activer l’envoi.', 'cc53-resultats' ); ?></em></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'cc53_upload' ); ?>
				<input type="hidden" name="action" value="cc53_upload">
				<p><input type="file" name="file" accept=".pdf,.gpx,application/pdf" required>
				<?php submit_button( __( 'Envoyer', 'cc53-resultats' ), 'primary', 'submit', false ); ?></p>
				<p class="description"><?php esc_html_e( 'PDF « Scratch » publié après chaque cross (le numéro du cross est lu dans le PDF), ou trace GPX d’un parcours.', 'cc53-resultats' ); ?></p>
			</form>
			<?php cc53_runs_table(); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Réglages', 'cc53-resultats' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cc53_save' ); ?>
			<input type="hidden" name="action" value="cc53_save">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="cc53-base"><?php esc_html_e( 'Adresse des données', 'cc53-resultats' ); ?></label></th>
					<td><input id="cc53-base" name="base" type="url" class="regular-text" value="<?php echo esc_attr( $s['base'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cc53-repo"><?php esc_html_e( 'Dépôt GitHub', 'cc53-resultats' ); ?></label></th>
					<td><input id="cc53-repo" name="repo" type="text" class="regular-text" value="<?php echo esc_attr( $s['repo'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cc53-branch"><?php esc_html_e( 'Branche', 'cc53-resultats' ); ?></label></th>
					<td><input id="cc53-branch" name="branch" type="text" value="<?php echo esc_attr( $s['branch'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cc53-token"><?php esc_html_e( 'Jeton GitHub', 'cc53-resultats' ); ?></label></th>
					<td><input id="cc53-token" name="token" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' === $s['token'] ? '' : esc_attr__( 'Enregistré (laisser vide pour le garder)', 'cc53-resultats' ); ?>">
					<?php if ( '' !== $s['token'] ) : ?><label><input type="checkbox" name="forget_token" value="1"> <?php esc_html_e( 'Supprimer le jeton', 'cc53-resultats' ); ?></label><?php endif; ?>
					<p class="description"><?php esc_html_e( 'Jeton « fine-grained » limité à ce dépôt, avec les droits Contents (lecture et écriture) et Actions (lecture).', 'cc53-resultats' ); ?></p></td></tr>
				<tr><th scope="row"><label for="cc53-offset"><?php esc_html_e( 'Hauteur de l’en-tête fixe (px)', 'cc53-resultats' ); ?></label></th>
					<td><input id="cc53-offset" name="offset" type="number" min="0" max="400" value="<?php echo esc_attr( $s['offset'] ); ?>">
					<p class="description"><?php esc_html_e( 'Utilisé pour remonter en haut des résultats sans passer sous le menu du thème.', 'cc53-resultats' ); ?></p></td></tr>
			</table>
			<?php submit_button( __( 'Enregistrer', 'cc53-resultats' ) ); ?>
		</form>
	</div>
	<?php
}

/** Derniers traitements GitHub (lecture des PDF et mise en ligne). */
function cc53_runs_table() {
	$res = cc53_github( 'GET', '/actions/runs?per_page=5' );
	if ( is_wp_error( $res ) || 200 !== $res['code'] || empty( $res['body']['workflow_runs'] ) ) {
		return;
	}
	echo '<h3>' . esc_html__( 'Derniers traitements', 'cc53-resultats' ) . '</h3><table class="widefat striped" style="max-width:780px"><tbody>';
	foreach ( $res['body']['workflow_runs'] as $run ) {
		$state = 'completed' !== $run['status'] ? __( '⏳ En cours', 'cc53-resultats' )
			: ( 'success' === $run['conclusion'] ? __( '✅ En ligne', 'cc53-resultats' ) : __( '❌ Échec : envoyez le PDF à Baptiste', 'cc53-resultats' ) );
		printf(
			'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( $state ),
			esc_html( wp_trim_words( (string) $run['display_title'], 12 ) ),
			esc_html( wp_date( 'd/m/Y H:i', strtotime( $run['created_at'] ) ) )
		);
	}
	echo '</tbody></table>';
}

/* Lien « Réglages » dans la liste des extensions. */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=cc53-resultats' ) ) . '">' . esc_html__( 'Réglages', 'cc53-resultats' ) . '</a>' );
		return $links;
	}
);
