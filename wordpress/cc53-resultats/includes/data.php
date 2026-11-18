<?php
/**
 * Stockage : chaque cross est un contenu « cc53_cross » (infos en métadonnées) ;
 * ses résultats et ses tracés sont des fichiers JSON dans wp-content/uploads/cc53/,
 * lus directement par l'application publique.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Réglages ---------- */

function cc53_settings() {
	$s = get_option( CC53_OPTION, array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'season'   => '2026-2027',
			'masked'   => '',
			'contact'  => '',
			'offset'   => 90,
			'start_c'  => '09:15',
			'start_l'  => '09:45',
			'credit'   => 1,
		)
	);
}

/** Dossards masqués (droit d'opposition). */
function cc53_masked_bibs() {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) cc53_settings()['masked'] ) as $b ) {
		if ( ctype_digit( $b ) ) {
			$out[ (int) $b ] = true;
		}
	}
	return $out;
}

/* ---------- Fichiers ---------- */

function cc53_storage_dir() {
	$u   = wp_upload_dir();
	$dir = trailingslashit( $u['basedir'] ) . 'cc53/';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

function cc53_storage_url() {
	$u = wp_upload_dir();
	return set_url_scheme( trailingslashit( $u['baseurl'] ) . 'cc53/' );
}

function cc53_read_json( $name ) {
	$f = cc53_storage_dir() . $name;
	if ( ! file_exists( $f ) ) {
		return null;
	}
	$j = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore
	return is_array( $j ) ? $j : null;
}

function cc53_write_json( $name, $data ) {
	return false !== file_put_contents( cc53_storage_dir() . $name, wp_json_encode( $data ), LOCK_EX ); // phpcs:ignore
}

function cc53_delete_file( $name ) {
	$f = cc53_storage_dir() . $name;
	if ( file_exists( $f ) ) {
		wp_delete_file( $f );
	}
}

/* ---------- Cross ---------- */

/** Tous les cross, triés par numéro, avec leurs métadonnées. */
function cc53_crosses() {
	$posts = get_posts(
		array(
			'post_type'   => CC53_CPT,
			'post_status' => 'publish',
			'numberposts' => -1,
		)
	);
	$out = array();
	foreach ( $posts as $p ) {
		$n = (int) get_post_meta( $p->ID, '_cc53_num', true );
		if ( $n <= 0 ) {
			continue;
		}
		$out[ $n ] = array(
			'id'       => $p->ID,
			'n'        => $n,
			'date'     => (string) get_post_meta( $p->ID, '_cc53_date', true ),
			'lieu'     => (string) get_post_meta( $p->ID, '_cc53_lieu', true ),
			'url'      => (string) get_post_meta( $p->ID, '_cc53_url', true ),
			'sponsors' => (array) get_post_meta( $p->ID, '_cc53_sponsors', true ),
			'parkings' => (array) get_post_meta( $p->ID, '_cc53_parkings', true ),
			'note'     => (string) get_post_meta( $p->ID, '_cc53_note', true ),
			'pknote'   => (string) get_post_meta( $p->ID, '_cc53_pknote', true ),
			'start'    => (string) get_post_meta( $p->ID, '_cc53_start', true ),
		);
	}
	ksort( $out );
	return $out;
}

/** Lit « 48.0769, -0.7199 » (ou un lien Google Maps) et renvoie [lon, lat]. */
function cc53_parse_latlon( $s ) {
	if ( preg_match( '/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', (string) $s, $m ) ) {
		$lat = (float) $m[1];
		$lon = (float) $m[2];
		if ( abs( $lat ) <= 90 && abs( $lon ) <= 180 ) {
			return array( round( $lon, 6 ), round( $lat, 6 ) );
		}
	}
	return null;
}

/* ---------- Résultats ---------- */

/** Applique le masquage des dossards à un fichier de résultats (en place). */
function cc53_apply_mask( &$res ) {
	$masked = cc53_masked_bibs();
	if ( ! $masked || empty( $res['dists'] ) ) {
		return 0;
	}
	$count = 0;
	foreach ( $res['dists'] as &$d ) {
		$remap = array();
		foreach ( $d['rows'] as &$r ) {
			if ( $r[3] > 0 && isset( $masked[ $r[3] ] ) ) {
				$remap[ $r[3] ] = -$r[0];
				$r[3]           = -$r[0];
				$r[1]           = 'Coureur masqué';
				$r[2]           = '';
				$count++;
			}
		}
		unset( $r );
		if ( $remap ) {
			foreach ( $d['teams'] as &$t ) {
				$t[7] = array_map(
					function ( $x ) use ( $remap ) {
						return isset( $remap[ $x ] ) ? $remap[ $x ] : $x;
					},
					$t[7]
				);
			}
			unset( $t );
		}
	}
	unset( $d );
	return $count;
}

/** Vérifie et nettoie les résultats envoyés par l'administration (déjà lus dans le navigateur). */
function cc53_clean_results( $in, $n ) {
	if ( ! is_array( $in ) || empty( $in['dists'] ) || ! is_array( $in['dists'] ) ) {
		return null;
	}
	$out = array(
		'n'     => (int) $n,
		'date'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['date'] ?? '' ) ) ? $in['date'] : null,
		'dists' => array(),
	);
	$txt = function ( $v, $len = 120 ) {
		return mb_substr( sanitize_text_field( (string) $v ), 0, $len );
	};
	foreach ( array( 'court', 'long' ) as $k ) {
		if ( empty( $in['dists'][ $k ]['rows'] ) || ! is_array( $in['dists'][ $k ]['rows'] ) ) {
			continue;
		}
		$D    = $in['dists'][ $k ];
		$rows = array();
		foreach ( array_slice( $D['rows'], 0, 6000 ) as $r ) {
			if ( ! is_array( $r ) || count( $r ) < 12 ) {
				continue;
			}
			// Année de naissance jamais conservée : seule une tranche d'âge (ex. « 40-49 ») est acceptée.
			$band   = preg_match( '/^\d{2}-\d{2}$|^\d{2}\+$/', (string) $r[4] ) ? (string) $r[4] : '';
			$rows[] = array( (int) $r[0], $txt( $r[1] ), $txt( $r[2], 3 ), (int) $r[3], $band, (int) $r[5], $txt( $r[6], 4 ), null === $r[7] ? null : (int) $r[7], 'F' === $r[8] ? 'F' : 'M', (int) $r[9], $txt( $r[10], 8 ), $txt( $r[11] ) );
		}
		$teams = array();
		foreach ( array_slice( (array) ( $D['teams'] ?? array() ), 0, 1500 ) as $t ) {
			if ( ! is_array( $t ) || count( $t ) < 8 ) {
				continue;
			}
			$teams[] = array( (int) $t[0], $txt( $t[1], 12 ), $txt( $t[2] ), (int) $t[3], (int) $t[4], null === $t[5] ? null : (int) $t[5], (int) $t[6], array_map( 'intval', array_slice( (array) $t[7], 0, 12 ) ), ! empty( $t[8] ) ? 1 : 0 );
		}
		if ( $rows ) {
			$out['dists'][ $k ] = array(
				'km'    => round( (float) ( $D['km'] ?? 0 ), 3 ),
				'rows'  => $rows,
				'teams' => $teams,
			);
		}
	}
	return $out['dists'] ? $out : null;
}

/** Vérifie un tracé : liste de [lat, lon]. */
function cc53_clean_track( $pts ) {
	if ( ! is_array( $pts ) ) {
		return null;
	}
	$out = array();
	foreach ( array_slice( $pts, 0, 20000 ) as $p ) {
		if ( is_array( $p ) && 2 === count( $p ) && is_numeric( $p[0] ) && is_numeric( $p[1] ) && abs( $p[0] ) <= 90 && abs( $p[1] ) <= 180 ) {
			$out[] = array( round( (float) $p[0], 6 ), round( (float) $p[1], 6 ) );
		}
	}
	return count( $out ) >= 10 ? $out : null;
}

/** Applique le masquage à tous les résultats (après modification de la liste des dossards). */
function cc53_remask_all() {
	$total = 0;
	foreach ( cc53_crosses() as $n => $c ) {
		$res = cc53_read_json( "cross-$n.json" );
		if ( $res ) {
			$total += cc53_apply_mask( $res );
			cc53_write_json( "cross-$n.json", $res );
		}
	}
	cc53_rebuild_index();
	return $total;
}

/** Régénère index.json (liste des cross avec résultats et des tracés). */
function cc53_rebuild_index() {
	$races  = array();
	$tracks = array();
	foreach ( cc53_crosses() as $n => $c ) {
		$has_track = file_exists( cc53_storage_dir() . "track-$n.json" );
		if ( $has_track ) {
			$tracks[] = $n;
		}
		$res = cc53_read_json( "cross-$n.json" );
		if ( ! $res || empty( $res['dists'] ) ) {
			continue;
		}
		$dists = array();
		foreach ( $res['dists'] as $k => $d ) {
			$dists[ $k ] = array(
				'km'    => $d['km'],
				'count' => count( $d['rows'] ),
			);
		}
		$races[] = array_filter(
			array(
				'n'     => $n,
				'date'  => $c['date'] ? $c['date'] : ( $res['date'] ?? null ),
				'file'  => "cross-$n.json",
				'track' => $has_track ? "track-$n.json" : null,
				'dists' => $dists,
			)
		);
	}
	$prev    = cc53_read_json( 'index.json' );
	$updated = ( $prev && isset( $prev['races'] ) && wp_json_encode( $prev['races'] ) === wp_json_encode( $races ) ) ? $prev['updated'] : gmdate( 'c' );
	cc53_write_json(
		'index.json',
		array(
			'updated' => $updated,
			'races'   => $races,
			'tracks'  => $tracks,
		)
	);
	delete_transient( 'cc53_config' );
}

/* ---------- Configuration transmise à l'application publique ---------- */

function cc53_front_config() {
	$cached = get_transient( 'cc53_config' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$s    = cc53_settings();
	$cal  = array();
	$orgs = array();
	$logo = array();
	$info = array();
	$town = array();
	foreach ( cc53_crosses() as $n => $c ) {
		$cal[]      = array(
			'n'    => $n,
			'date' => $c['date'],
			'lieu' => $c['lieu'],
			'url'  => $c['url'] ? $c['url'] : '#cc53-nourl',
		);
		$orgs[ $n ] = array();
		foreach ( $c['sponsors'] as $sp ) {
			$name         = (string) ( $sp['name'] ?? '' );
			$orgs[ $n ][] = $name;
			$src          = ! empty( $sp['logo'] ) ? wp_get_attachment_image_url( (int) $sp['logo'], 'thumbnail' ) : '';
			if ( $src ) {
				$logo[] = array(
					'r'    => $n,
					'name' => $name,
					'src'  => $src,
				);
			}
		}
		$pk = array();
		foreach ( $c['parkings'] as $p ) {
			$ll = cc53_parse_latlon( $p['pos'] ?? '' );
			if ( $ll ) {
				$pk[] = array_filter(
					array(
						'll'    => $ll,
						'label' => (string) ( $p['label'] ?? 'Parking' ),
						'sub'   => (string) ( $p['sub'] ?? '' ),
					)
				);
			}
		}
		if ( $pk || $c['note'] || $c['pknote'] ) {
			$info[ $n ] = array_filter(
				array(
					'note'     => $c['note'],
					'pkNote'   => $c['pknote'],
					'parkings' => $pk ? $pk : null,
				)
			);
		}
		$ll = cc53_parse_latlon( $c['start'] );
		if ( ! $ll ) {
			$tr = cc53_read_json( "track-$n.json" );
			$p0 = $tr['long'][0] ?? ( $tr['court'][0] ?? null );
			$ll = $p0 ? array( $p0[1], $p0[0] ) : null;
		}
		if ( $ll ) {
			$town[ $n ] = array( $ll[0], $ll[1], true );
		}
	}
	$hm  = function ( $v, $def ) {
		return preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $v, $m ) ? (int) $m[1] * 3600 + (int) $m[2] * 60 : $def;
	};
	$cfg = array(
		'data'      => cc53_storage_url(),
		'vendor'    => CC53_URL . 'assets/vendor/',
		'season'    => $s['season'],
		'cal'       => $cal,
		'orgs'      => (object) $orgs,
		'logos'     => $logo,
		'info'      => (object) $info,
		'towns'     => (object) $town,
		'contact'   => $s['contact'],
		'offset'    => (int) $s['offset'],
		'raceStart' => array(
			'court' => $hm( $s['start_c'], 33300 ),
			'long'  => $hm( $s['start_l'], 35100 ),
		),
	);
	set_transient( 'cc53_config', $cfg, DAY_IN_SECONDS );
	return $cfg;
}

/* ---------- Données de départ (saison 2026-2027 déjà commencée) ---------- */

function cc53_import_seed() {
	$seed = CC53_DIR . 'seed/';
	$list = file_exists( $seed . 'crosses.json' ) ? json_decode( (string) file_get_contents( $seed . 'crosses.json' ), true ) : null; // phpcs:ignore
	if ( ! is_array( $list ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$dir = cc53_storage_dir();
	foreach ( $list as $c ) {
		$id = wp_insert_post(
			array(
				'post_type'   => CC53_CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Cross N°%d – %s', $c['n'], $c['lieu'] ),
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_cc53_num', (int) $c['n'] );
		update_post_meta( $id, '_cc53_date', $c['date'] );
		update_post_meta( $id, '_cc53_lieu', $c['lieu'] );
		update_post_meta( $id, '_cc53_url', $c['url'] );
		update_post_meta( $id, '_cc53_note', $c['note'] ?? '' );
		update_post_meta( $id, '_cc53_pknote', $c['pknote'] ?? '' );
		update_post_meta( $id, '_cc53_start', $c['start'] ?? '' );
		update_post_meta( $id, '_cc53_parkings', $c['parkings'] ?? array() );
		$sponsors = array();
		foreach ( (array) ( $c['sponsors'] ?? array() ) as $sp ) {
			$att = 0;
			if ( ! empty( $sp['file'] ) && file_exists( $seed . 'logos/' . $sp['file'] ) ) {
				$up = wp_upload_bits( $sp['file'], null, (string) file_get_contents( $seed . 'logos/' . $sp['file'] ) ); // phpcs:ignore
				if ( empty( $up['error'] ) ) {
					$ft  = wp_check_filetype( $up['file'] );
					$att = wp_insert_attachment(
						array(
							'post_mime_type' => $ft['type'],
							'post_title'     => $sp['name'],
							'post_status'    => 'inherit',
						),
						$up['file']
					);
					if ( $att && ! is_wp_error( $att ) ) {
						wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
					} else {
						$att = 0;
					}
				}
			}
			$sponsors[] = array(
				'name' => $sp['name'],
				'logo' => (int) $att,
			);
		}
		update_post_meta( $id, '_cc53_sponsors', $sponsors );
		foreach ( array( "cross-{$c['n']}.json", "track-{$c['n']}.json" ) as $f ) {
			if ( file_exists( $seed . 'data/' . $f ) && ! file_exists( $dir . $f ) ) {
				copy( $seed . 'data/' . $f, $dir . $f );
			}
		}
	}
}
