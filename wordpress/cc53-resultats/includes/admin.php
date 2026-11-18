<?php
/**
 * Administration : menu « Cross Corpo », fiche de chaque cross, dépôt des PDF et GPX, réglages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cc53_register_cpt' );

function cc53_register_cpt() {
	register_post_type(
		CC53_CPT,
		array(
			'labels'          => array(
				'name'               => __( 'Cross', 'cc53-resultats' ),
				'singular_name'      => __( 'Cross', 'cc53-resultats' ),
				'menu_name'          => __( 'Cross Corpo', 'cc53-resultats' ),
				'all_items'          => __( 'Tous les cross', 'cc53-resultats' ),
				'add_new'            => __( 'Ajouter un cross', 'cc53-resultats' ),
				'add_new_item'       => __( 'Ajouter un cross', 'cc53-resultats' ),
				'edit_item'          => __( 'Modifier le cross', 'cc53-resultats' ),
				'new_item'           => __( 'Nouveau cross', 'cc53-resultats' ),
				'search_items'       => __( 'Rechercher un cross', 'cc53-resultats' ),
				'not_found'          => __( 'Aucun cross.', 'cc53-resultats' ),
				'not_found_in_trash' => __( 'Aucun cross dans la corbeille.', 'cc53-resultats' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 26,
			'menu_icon'       => 'dashicons-awards',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);
}

/* ---------- Liste des cross ---------- */

add_filter(
	'manage_' . CC53_CPT . '_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => __( 'Cross', 'cc53-resultats' ),
			'cc53_date' => __( 'Date', 'cc53-resultats' ),
			'cc53_res'  => __( 'Résultats', 'cc53-resultats' ),
			'cc53_trk'  => __( 'Parcours', 'cc53-resultats' ),
		);
	}
);

add_action(
	'manage_' . CC53_CPT . '_posts_custom_column',
	function ( $col, $id ) {
		$n = (int) get_post_meta( $id, '_cc53_num', true );
		if ( 'cc53_date' === $col ) {
			$d = get_post_meta( $id, '_cc53_date', true );
			echo esc_html( $d ? wp_date( 'j F Y', strtotime( $d . ' 12:00' ) ) : '–' );
		} elseif ( 'cc53_res' === $col ) {
			$r = $n ? cc53_read_json( "cross-$n.json" ) : null;
			if ( $r ) {
				$parts = array();
				foreach ( $r['dists'] as $k => $d ) {
					$parts[] = ( 'long' === $k ? 'long ' : 'court ' ) . count( $d['rows'] );
				}
				echo '✅ ' . esc_html( implode( ' · ', $parts ) );
			} else {
				echo '–';
			}
		} elseif ( 'cc53_trk' === $col ) {
			$t = $n ? cc53_read_json( "track-$n.json" ) : null;
			echo $t ? '✅ ' . esc_html( implode( ' · ', array_keys( array_intersect_key( $t, array( 'long' => 1, 'court' => 1 ) ) ) ) ) : '–';
		}
	},
	10,
	2
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() && $q->is_main_query() && CC53_CPT === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_cc53_num' );
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'ASC' );
		}
	}
);

/* ---------- Fiche d'un cross ---------- */

add_action( 'add_meta_boxes_' . CC53_CPT, 'cc53_meta_boxes' );

function cc53_meta_boxes() {
	add_meta_box( 'cc53_info', __( 'Informations', 'cc53-resultats' ), 'cc53_box_info', CC53_CPT, 'normal', 'high' );
	add_meta_box( 'cc53_results', __( 'Résultats (PDF « Scratch »)', 'cc53-resultats' ), 'cc53_box_results', CC53_CPT, 'normal', 'high' );
	add_meta_box( 'cc53_tracks', __( 'Parcours et accès', 'cc53-resultats' ), 'cc53_box_tracks', CC53_CPT, 'normal', 'default' );
	add_meta_box( 'cc53_sponsors', __( 'Organisateurs', 'cc53-resultats' ), 'cc53_box_sponsors', CC53_CPT, 'normal', 'default' );
}

function cc53_box_info( $post ) {
	wp_nonce_field( 'cc53_save_' . $post->ID, 'cc53_nonce' );
	$n = get_post_meta( $post->ID, '_cc53_num', true );
	if ( '' === $n ) {
		$max = 0;
		foreach ( cc53_crosses() as $k => $c ) {
			$max = max( $max, $k );
		}
		$n = $max + 1;
	}
	?>
	<table class="form-table cc53-form" role="presentation">
		<tr><th><label for="cc53_num"><?php esc_html_e( 'Numéro', 'cc53-resultats' ); ?></label></th>
			<td><input type="number" min="1" max="99" id="cc53_num" name="cc53_num" value="<?php echo esc_attr( $n ); ?>" required class="small-text"></td></tr>
		<tr><th><label for="cc53_date"><?php esc_html_e( 'Date', 'cc53-resultats' ); ?></label></th>
			<td><input type="date" id="cc53_date" name="cc53_date" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cc53_date', true ) ); ?>" required></td></tr>
		<tr><th><label for="cc53_lieu"><?php esc_html_e( 'Lieu', 'cc53-resultats' ); ?></label></th>
			<td><input type="text" id="cc53_lieu" name="cc53_lieu" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cc53_lieu', true ) ); ?>" class="regular-text" required placeholder="Saint-Berthevin"></td></tr>
		<tr><th><label for="cc53_url"><?php esc_html_e( 'Page « En savoir plus »', 'cc53-resultats' ); ?></label></th>
			<td><input type="url" id="cc53_url" name="cc53_url" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cc53_url', true ) ); ?>" class="large-text" placeholder="https://www.crosscorpo53.fr/cross/…">
			<p class="description"><?php esc_html_e( 'Page du site présentant ce cross (horaires, plan, récompenses). Laisser vide pour masquer le bouton.', 'cc53-resultats' ); ?></p></td></tr>
	</table>
	<?php
}

function cc53_box_results( $post ) {
	$n   = (int) get_post_meta( $post->ID, '_cc53_num', true );
	$res = $n ? cc53_read_json( "cross-$n.json" ) : null;
	?>
	<div class="cc53-drop" data-kind="pdf" data-post="<?php echo esc_attr( $post->ID ); ?>">
		<p class="cc53-state">
		<?php
		if ( $res ) {
			foreach ( $res['dists'] as $k => $d ) {
				printf( '<span class="cc53-ok">✅ %s : %s arrivants, %s équipes</span> ', esc_html( 'long' === $k ? __( 'Cross long', 'cc53-resultats' ) : __( 'Cross court', 'cc53-resultats' ) ), esc_html( number_format_i18n( count( $d['rows'] ) ) ), esc_html( number_format_i18n( count( $d['teams'] ) ) ) );
			}
		} else {
			esc_html_e( 'Pas encore de résultats.', 'cc53-resultats' );
		}
		?>
		</p>
		<?php if ( ! $n || 'auto-draft' === $post->post_status ) : ?>
			<p><em><?php esc_html_e( 'Enregistrez d’abord le cross (numéro, date, lieu) pour pouvoir déposer ses résultats.', 'cc53-resultats' ); ?></em></p>
		<?php else : ?>
			<label class="button button-primary cc53-pick"><?php esc_html_e( 'Choisir le PDF « Scratch »…', 'cc53-resultats' ); ?><input type="file" accept="application/pdf,.pdf" hidden></label>
			<?php if ( $res ) : ?><button type="button" class="button-link button-link-delete cc53-del" data-what="results"><?php esc_html_e( 'Supprimer les résultats', 'cc53-resultats' ); ?></button><?php endif; ?>
			<p class="description"><?php esc_html_e( 'Le PDF est lu dans votre navigateur : seuls les classements sont enregistrés (l’année de naissance est remplacée par une tranche d’âge, le PDF n’est pas conservé). Un nouveau PDF remplace le précédent.', 'cc53-resultats' ); ?></p>
			<div class="cc53-preview" hidden></div>
		<?php endif; ?>
	</div>
	<?php
}

function cc53_box_tracks( $post ) {
	$n  = (int) get_post_meta( $post->ID, '_cc53_num', true );
	$tr = $n ? cc53_read_json( "track-$n.json" ) : null;
	$pk = (array) get_post_meta( $post->ID, '_cc53_parkings', true );
	?>
	<h4><?php esc_html_e( 'Tracés GPX', 'cc53-resultats' ); ?></h4>
	<?php if ( ! $n || 'auto-draft' === $post->post_status ) : ?>
		<p><em><?php esc_html_e( 'Enregistrez d’abord le cross pour pouvoir déposer ses tracés.', 'cc53-resultats' ); ?></em></p>
	<?php else : ?>
		<?php foreach ( array( 'long' => __( 'Cross long', 'cc53-resultats' ), 'court' => __( 'Cross court', 'cc53-resultats' ) ) as $d => $label ) : ?>
			<div class="cc53-drop cc53-trk" data-kind="gpx" data-dist="<?php echo esc_attr( $d ); ?>" data-post="<?php echo esc_attr( $post->ID ); ?>">
				<strong><?php echo esc_html( $label ); ?></strong>
				<span class="cc53-state"><?php echo ! empty( $tr[ $d ] ) ? '✅ ' . esc_html( sprintf( __( '%s points', 'cc53-resultats' ), number_format_i18n( count( $tr[ $d ] ) ) ) ) : '–'; ?></span>
				<label class="button cc53-pick"><?php esc_html_e( 'Choisir un GPX…', 'cc53-resultats' ); ?><input type="file" accept=".gpx,application/gpx+xml" hidden></label>
				<?php if ( ! empty( $tr[ $d ] ) ) : ?><button type="button" class="button-link button-link-delete cc53-del" data-what="track"><?php esc_html_e( 'Supprimer', 'cc53-resultats' ); ?></button><?php endif; ?>
			</div>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Seules les coordonnées sont gardées (pas les horaires). Si seul le cross long est fourni, le cross court reprend son premier tour quand le parcours revient au départ.', 'cc53-resultats' ); ?></p>
	<?php endif; ?>

	<table class="form-table cc53-form" role="presentation">
		<tr><th><label for="cc53_start"><?php esc_html_e( 'Lieu de départ', 'cc53-resultats' ); ?></label></th>
			<td><input type="text" id="cc53_start" name="cc53_start" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cc53_start', true ) ); ?>" class="regular-text" placeholder="48.0769, -0.7199">
			<p class="description"><?php esc_html_e( 'Latitude, longitude (copiées depuis Google Maps : clic droit sur le lieu). Facultatif si un tracé est déposé.', 'cc53-resultats' ); ?></p></td></tr>
		<tr><th><label for="cc53_note"><?php esc_html_e( 'Note sur le parcours', 'cc53-resultats' ); ?></label></th>
			<td><textarea id="cc53_note" name="cc53_note" rows="2" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_cc53_note', true ) ); ?></textarea></td></tr>
		<tr><th><?php esc_html_e( 'Parkings', 'cc53-resultats' ); ?></th>
			<td>
				<div class="cc53-rows" data-tpl="parking">
				<?php foreach ( array_merge( $pk, array( array() ) ) as $i => $p ) : ?>
					<div class="cc53-row">
						<input type="text" name="cc53_pk[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $p['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Nom (ex. Parking rue des Sports)', 'cc53-resultats' ); ?>">
						<input type="text" name="cc53_pk[<?php echo (int) $i; ?>][pos]" value="<?php echo esc_attr( $p['pos'] ?? '' ); ?>" placeholder="48.0776, -0.7039">
						<input type="text" name="cc53_pk[<?php echo (int) $i; ?>][sub]" value="<?php echo esc_attr( $p['sub'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Précision (facultatif)', 'cc53-resultats' ); ?>">
						<button type="button" class="button-link cc53-rm" aria-label="<?php esc_attr_e( 'Retirer', 'cc53-resultats' ); ?>">✕</button>
					</div>
				<?php endforeach; ?>
				</div>
				<button type="button" class="button cc53-add"><?php esc_html_e( 'Ajouter un parking', 'cc53-resultats' ); ?></button>
			</td></tr>
		<tr><th><label for="cc53_pknote"><?php esc_html_e( 'Consigne de stationnement', 'cc53-resultats' ); ?></label></th>
			<td><input type="text" id="cc53_pknote" name="cc53_pknote" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cc53_pknote', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Ex. Le parking de la zone de départ n’est accessible que jusqu’à 9 h.', 'cc53-resultats' ); ?>"></td></tr>
	</table>
	<?php
}

function cc53_box_sponsors( $post ) {
	$sp = (array) get_post_meta( $post->ID, '_cc53_sponsors', true );
	?>
	<div class="cc53-rows cc53-sponsors" data-tpl="sponsor">
	<?php foreach ( array_merge( $sp, array( array() ) ) as $i => $s ) : ?>
		<?php $src = ! empty( $s['logo'] ) ? wp_get_attachment_image_url( (int) $s['logo'], 'thumbnail' ) : ''; ?>
		<div class="cc53-row">
			<span class="cc53-logo"><?php if ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt=""><?php endif; ?></span>
			<input type="hidden" name="cc53_sp[<?php echo (int) $i; ?>][logo]" value="<?php echo esc_attr( $s['logo'] ?? '' ); ?>" class="cc53-logo-id">
			<button type="button" class="button cc53-media"><?php esc_html_e( 'Logo…', 'cc53-resultats' ); ?></button>
			<input type="text" name="cc53_sp[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $s['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Entreprise ou partenaire', 'cc53-resultats' ); ?>" class="regular-text">
			<button type="button" class="button-link cc53-rm" aria-label="<?php esc_attr_e( 'Retirer', 'cc53-resultats' ); ?>">✕</button>
		</div>
	<?php endforeach; ?>
	</div>
	<button type="button" class="button cc53-add"><?php esc_html_e( 'Ajouter un organisateur', 'cc53-resultats' ); ?></button>
	<?php
}

add_action( 'save_post_' . CC53_CPT, 'cc53_save_post', 10, 2 );

function cc53_save_post( $post_id, $post ) {
	if ( ! isset( $_POST['cc53_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cc53_nonce'] ), 'cc53_save_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$old  = (int) get_post_meta( $post_id, '_cc53_num', true );
	$n    = max( 1, min( 99, (int) ( $_POST['cc53_num'] ?? 1 ) ) );
	$lieu = sanitize_text_field( wp_unslash( $_POST['cc53_lieu'] ?? '' ) );
	$date = sanitize_text_field( wp_unslash( $_POST['cc53_date'] ?? '' ) );

	// Un numéro déjà pris par un autre cross garde l'ancien numéro.
	foreach ( cc53_crosses() as $k => $c ) {
		if ( $k === $n && $c['id'] !== $post_id ) {
			$n = $old ? $old : $n;
			set_transient( 'cc53_notice_' . get_current_user_id(), __( 'Ce numéro est déjà utilisé par un autre cross : le numéro n’a pas été modifié.', 'cc53-resultats' ), 60 );
		}
	}
	if ( $old && $old !== $n ) {
		foreach ( array( 'cross', 'track' ) as $kind ) {
			$from = cc53_storage_dir() . "$kind-$old.json";
			$to   = cc53_storage_dir() . "$kind-$n.json";
			if ( file_exists( $from ) && ! file_exists( $to ) ) {
				rename( $from, $to ); // phpcs:ignore
			}
		}
		$res = cc53_read_json( "cross-$n.json" );
		if ( $res ) {
			$res['n'] = $n;
			cc53_write_json( "cross-$n.json", $res );
		}
	}
	update_post_meta( $post_id, '_cc53_num', $n );
	update_post_meta( $post_id, '_cc53_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
	update_post_meta( $post_id, '_cc53_lieu', $lieu );
	update_post_meta( $post_id, '_cc53_url', esc_url_raw( wp_unslash( $_POST['cc53_url'] ?? '' ) ) );
	update_post_meta( $post_id, '_cc53_start', sanitize_text_field( wp_unslash( $_POST['cc53_start'] ?? '' ) ) );
	update_post_meta( $post_id, '_cc53_note', sanitize_textarea_field( wp_unslash( $_POST['cc53_note'] ?? '' ) ) );
	update_post_meta( $post_id, '_cc53_pknote', sanitize_text_field( wp_unslash( $_POST['cc53_pknote'] ?? '' ) ) );

	$pk = array();
	foreach ( (array) wp_unslash( $_POST['cc53_pk'] ?? array() ) as $p ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$label = sanitize_text_field( $p['label'] ?? '' );
		$pos   = sanitize_text_field( $p['pos'] ?? '' );
		if ( '' !== $label || '' !== $pos ) {
			$pk[] = array(
				'label' => $label,
				'pos'   => $pos,
				'sub'   => sanitize_text_field( $p['sub'] ?? '' ),
			);
		}
	}
	update_post_meta( $post_id, '_cc53_parkings', $pk );

	$sp = array();
	foreach ( (array) wp_unslash( $_POST['cc53_sp'] ?? array() ) as $s ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$name = sanitize_text_field( $s['name'] ?? '' );
		$logo = absint( $s['logo'] ?? 0 );
		if ( '' !== $name || $logo ) {
			$sp[] = array(
				'name' => $name,
				'logo' => $logo,
			);
		}
	}
	update_post_meta( $post_id, '_cc53_sponsors', $sp );

	// Titre automatique « Cross N°3 – Saint-Berthevin ».
	$title = sprintf( 'Cross N°%d – %s', $n, $lieu );
	if ( $post->post_title !== $title ) {
		remove_action( 'save_post_' . CC53_CPT, 'cc53_save_post', 10 );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);
		add_action( 'save_post_' . CC53_CPT, 'cc53_save_post', 10, 2 );
	}
	cc53_rebuild_index();
}

add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( CC53_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		$n = (int) get_post_meta( $post_id, '_cc53_num', true );
		if ( $n ) {
			cc53_delete_file( "cross-$n.json" );
			cc53_delete_file( "track-$n.json" );
		}
	}
);
add_action( 'trashed_post', 'cc53_maybe_rebuild' );
add_action( 'untrashed_post', 'cc53_maybe_rebuild' );
add_action( 'deleted_post', 'cc53_maybe_rebuild' );
function cc53_maybe_rebuild( $post_id ) {
	if ( CC53_CPT === get_post_type( $post_id ) || ! get_post( $post_id ) ) {
		cc53_rebuild_index();
	}
}

add_action(
	'admin_notices',
	function () {
		$k = 'cc53_notice_' . get_current_user_id();
		$m = get_transient( $k );
		if ( $m ) {
			delete_transient( $k );
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $m ) );
		}
	}
);

/* ---------- Envoi des résultats et tracés (lus dans le navigateur) ---------- */

add_action( 'wp_ajax_cc53_save', 'cc53_ajax_save' );

function cc53_ajax_save() {
	check_ajax_referer( 'cc53_ajax', 'nonce' );
	$post_id = absint( $_POST['post'] ?? 0 );
	if ( ! $post_id || CC53_CPT !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'cc53-resultats' ) ), 403 );
	}
	$n = (int) get_post_meta( $post_id, '_cc53_num', true );
	if ( ! $n ) {
		wp_send_json_error( array( 'message' => __( 'Enregistrez d’abord le cross.', 'cc53-resultats' ) ) );
	}
	$what = sanitize_key( $_POST['what'] ?? '' );
	$raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON vérifié champ par champ ci-dessous.
	if ( strlen( $raw ) > 8 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => __( 'Données trop volumineuses.', 'cc53-resultats' ) ) );
	}
	$data = json_decode( $raw, true );

	if ( 'results' === $what ) {
		$res = cc53_clean_results( $data, $n );
		if ( ! $res ) {
			wp_send_json_error( array( 'message' => __( 'Aucun classement exploitable.', 'cc53-resultats' ) ) );
		}
		$masked = cc53_apply_mask( $res );
		cc53_write_json( "cross-$n.json", $res );
		if ( empty( get_post_meta( $post_id, '_cc53_date', true ) ) && $res['date'] ) {
			update_post_meta( $post_id, '_cc53_date', $res['date'] );
		}
	} elseif ( 'track' === $what ) {
		$d   = 'court' === ( $_POST['dist'] ?? '' ) ? 'court' : 'long';
		$tr  = cc53_read_json( "track-$n.json" );
		$tr  = $tr ? $tr : array();
		$pts = cc53_clean_track( $data['pts'] ?? null );
		if ( ! $pts ) {
			wp_send_json_error( array( 'message' => __( 'Tracé introuvable dans ce GPX.', 'cc53-resultats' ) ) );
		}
		$tr[ $d ] = $pts;
		if ( 'court' === $d ) {
			$tr['courtFromFile'] = true;
		} elseif ( empty( $tr['courtFromFile'] ) ) {
			$lap = cc53_clean_track( $data['lap'] ?? null );
			if ( $lap ) {
				$tr['court'] = $lap;
			}
		}
		cc53_write_json( "track-$n.json", $tr );
	} elseif ( 'del_results' === $what ) {
		cc53_delete_file( "cross-$n.json" );
	} elseif ( 'del_track' === $what ) {
		$d  = 'court' === ( $_POST['dist'] ?? '' ) ? 'court' : 'long';
		$tr = cc53_read_json( "track-$n.json" );
		if ( $tr ) {
			unset( $tr[ $d ] );
			if ( 'court' === $d ) {
				unset( $tr['courtFromFile'] );
			}
			if ( empty( $tr['long'] ) && empty( $tr['court'] ) ) {
				cc53_delete_file( "track-$n.json" );
			} else {
				cc53_write_json( "track-$n.json", $tr );
			}
		}
	} else {
		wp_send_json_error( array( 'message' => 'Action inconnue.' ) );
	}
	cc53_rebuild_index();
	wp_send_json_success( array( 'masked' => isset( $masked ) ? $masked : 0 ) );
}

/* ---------- Scripts de l'administration ---------- */

add_action( 'admin_enqueue_scripts', 'cc53_admin_assets' );

function cc53_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || CC53_CPT !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'cc53-admin', CC53_URL . 'assets/admin.css', array(), CC53_VERSION );
	wp_enqueue_script( 'cc53-pdfjs', CC53_URL . 'assets/vendor/pdf.min.js', array(), '3.11.174', true );
	wp_enqueue_script( 'cc53-admin', CC53_URL . 'assets/admin.js', array( 'cc53-pdfjs', 'jquery' ), CC53_VERSION, true );
	wp_localize_script(
		'cc53-admin',
		'CC53_ADMIN',
		array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'cc53_ajax' ),
			'worker' => CC53_URL . 'assets/vendor/pdf.worker.min.js',
			'core'   => CC53_URL . 'assets/parse-core.js',
			'num'    => (int) get_post_meta( get_the_ID(), '_cc53_num', true ),
			'year'   => (int) substr( (string) cc53_settings()['season'], 0, 4 ),
		)
	);
}

/* ---------- Réglages ---------- */

add_action( 'admin_menu', 'cc53_settings_menu' );
add_action( 'admin_post_cc53_settings', 'cc53_settings_save' );

function cc53_settings_menu() {
	add_submenu_page( 'edit.php?post_type=' . CC53_CPT, __( 'Réglages', 'cc53-resultats' ), __( 'Réglages', 'cc53-resultats' ), 'manage_options', 'cc53-settings', 'cc53_settings_page' );
}

function cc53_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'cc53-resultats' ) );
	}
	check_admin_referer( 'cc53_settings' );
	$s          = cc53_settings();
	$old_masked = $s['masked'];
	$s['season']  = sanitize_text_field( wp_unslash( $_POST['season'] ?? '' ) );
	$s['masked']  = implode( ', ', array_filter( preg_split( '/[\s,;]+/', sanitize_text_field( wp_unslash( $_POST['masked'] ?? '' ) ) ), 'ctype_digit' ) );
	$s['contact'] = sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) );
	$s['offset']  = max( 0, min( 400, (int) ( $_POST['offset'] ?? 90 ) ) );
	foreach ( array( 'start_c', 'start_l' ) as $k ) {
		$v       = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		$s[ $k ] = preg_match( '/^\d{1,2}:\d{2}$/', $v ) ? $v : $s[ $k ];
	}
	update_option( CC53_OPTION, $s, false );
	$msg = __( 'Réglages enregistrés.', 'cc53-resultats' );
	if ( $old_masked !== $s['masked'] ) {
		$c   = cc53_remask_all();
		$msg .= ' ' . sprintf( _n( '%d résultat masqué.', '%d résultats masqués.', $c, 'cc53-resultats' ), $c );
	}
	delete_transient( 'cc53_config' );
	wp_safe_redirect( add_query_arg( array( 'post_type' => CC53_CPT, 'page' => 'cc53-settings', 'msg' => rawurlencode( $msg ) ), admin_url( 'edit.php' ) ) );
	exit;
}

function cc53_settings_page() {
	$s = cc53_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cross Corpo – Réglages', 'cc53-resultats' ); ?></h1>
		<?php if ( isset( $_GET['msg'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); ?></p></div>
		<?php endif; ?>
		<p><?php echo wp_kses_post( __( 'Pour afficher les résultats sur le site, insérez le shortcode <code>[cc53_resultats]</code> dans une page (bloc « Code court »).', 'cc53-resultats' ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cc53_settings' ); ?>
			<input type="hidden" name="action" value="cc53_settings">
			<table class="form-table" role="presentation">
				<tr><th><label for="season"><?php esc_html_e( 'Saison', 'cc53-resultats' ); ?></label></th>
					<td><input id="season" name="season" type="text" value="<?php echo esc_attr( $s['season'] ); ?>" class="regular-text" placeholder="2026-2027"></td></tr>
				<tr><th><?php esc_html_e( 'Heures de départ', 'cc53-resultats' ); ?></th>
					<td><label><?php esc_html_e( 'Cross court', 'cc53-resultats' ); ?> <input name="start_c" type="time" value="<?php echo esc_attr( $s['start_c'] ); ?>"></label>
					&nbsp; <label><?php esc_html_e( 'Cross long', 'cc53-resultats' ); ?> <input name="start_l" type="time" value="<?php echo esc_attr( $s['start_l'] ); ?>"></label>
					<p class="description"><?php esc_html_e( 'Utilisées pour « Revivre la course ».', 'cc53-resultats' ); ?></p></td></tr>
				<tr><th><label for="masked"><?php esc_html_e( 'Dossards masqués', 'cc53-resultats' ); ?></label></th>
					<td><textarea id="masked" name="masked" rows="2" class="large-text" placeholder="1234, 5678"><?php echo esc_textarea( $s['masked'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Coureurs ayant demandé le retrait de leurs résultats : leur nom devient « Coureur masqué » sur tout le site, sans changer les classements. Le masquage est définitif pour les résultats déjà déposés.', 'cc53-resultats' ); ?></p></td></tr>
				<tr><th><label for="contact"><?php esc_html_e( 'Contact « données personnelles »', 'cc53-resultats' ); ?></label></th>
					<td><input id="contact" name="contact" type="text" value="<?php echo esc_attr( $s['contact'] ); ?>" class="regular-text" placeholder="https://www.crosscorpo53.fr/nous-contacter/">
					<p class="description"><?php esc_html_e( 'Page ou adresse e-mail pour les demandes de retrait, affichée dans la page « Données personnelles » des résultats.', 'cc53-resultats' ); ?></p></td></tr>
				<tr><th><label for="offset"><?php esc_html_e( 'Hauteur du menu fixe (px)', 'cc53-resultats' ); ?></label></th>
					<td><input id="offset" name="offset" type="number" min="0" max="400" value="<?php echo esc_attr( $s['offset'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Pour que le retour en haut des résultats ne passe pas sous le menu du thème.', 'cc53-resultats' ); ?></p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_filter(
	'plugin_action_links_' . plugin_basename( CC53_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CC53_CPT ) ) . '">' . esc_html__( 'Cross', 'cc53-resultats' ) . '</a>' );
		return $links;
	}
);
