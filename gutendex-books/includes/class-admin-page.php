<?php
/**
 * Page d'administration.
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page de réglages : état du cache, renouvellement manuel, purge.
 *
 * Le renouvellement remplace chaque entrée dont la récupération aboutit ; la
 * purge, elle, supprime tout. Les deux boutons ne se recouvrent pas.
 *
 * Les deux actions passent par admin-post.php plutôt que par un traitement
 * dans la fonction de rendu : le contrôle des droits et du nonce est ainsi
 * effectué avant toute sortie HTML, et le motif POST/Redirect/GET évite un
 * rejeu de l'action au rafraîchissement du navigateur.
 */
class GTDX_Admin_Page {

	/** Slug de la page. */
	const SLUG = 'gutendex-books';

	/** Capacité requise. */
	const CAPABILITY = 'manage_options';

	/** Action admin-post de renouvellement. */
	const ACTION_REFRESH = 'gtdx_refresh';

	/** Action admin-post de purge. */
	const ACTION_FLUSH = 'gtdx_flush';

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	public static function register() {

		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION_REFRESH, array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_' . self::ACTION_FLUSH, array( __CLASS__, 'handle_flush' ) );
	}

	/**
	 * Ajoute la page sous le menu Réglages.
	 *
	 * @return void
	 */
	public static function add_menu() {

		add_options_page(
			__( 'Gutendex Books', 'gutendex-books' ),
			__( 'Gutendex Books', 'gutendex-books' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public static function render() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.', 'gutendex-books' ) );
		}

		$last_fetch = GTDX_Cache::get_last_fetch();

		$formatted_date = $last_fetch > 0
			? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_fetch )
			: '';

		$relative_date = $last_fetch > 0
			? sprintf(
				/* translators: %s: durée écoulée, ex. « 3 heures ». */
				__( 'il y a %s', 'gutendex-books' ),
				human_time_diff( $last_fetch, time() )
			)
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple lecture d'un indicateur d'affichage.
		$notice = isset( $_GET['gtdx_notice'] ) ? sanitize_key( wp_unslash( $_GET['gtdx_notice'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Idem, message d'erreur déjà échappé à l'affichage.
		$notice_detail = isset( $_GET['gtdx_detail'] ) ? sanitize_text_field( wp_unslash( $_GET['gtdx_detail'] ) ) : '';

		echo GTDX_Plugin::render_template( // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Le template échappe chaque valeur.
			'admin-page',
			array(
				'last_fetch'     => $last_fetch,
				'formatted_date' => $formatted_date,
				'relative_date'  => $relative_date,
				'live_entries'   => GTDX_Cache::count_live_entries(),
				'tracked_keys'   => count( GTDX_Cache::get_tracked_keys() ),
				'notice'         => $notice,
				'notice_detail'  => $notice_detail,
			)
		);
	}

	/**
	 * Renouvelle les données : rappel de l'API, entrée par entrée.
	 *
	 * Le remplacement est non destructif : une requête en échec laisse en place
	 * les données déjà en cache.
	 *
	 * @return void
	 */
	public static function handle_refresh() {
		
		self::authorize( self::ACTION_REFRESH );

		$queries = GTDX_Cache::get_tracked_keys();

		// Si aucun jeu d'arguments connu : actualisation de la requête par défaut..
		if ( empty( $queries ) ) {
			$queries = array( 'default' => GTDX_Api_Client::normalize_args( array() ) );
		}

		$failures = array();
		$success  = 0;

		foreach ( $queries as $args ) {
			$result = GTDX_Api_Client::refresh( is_array( $args ) ? $args : array() );

			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();
				continue;
			}

			++$success;
		}

		if ( empty( $failures ) ) {
			self::redirect_back( 'refreshed' );
		}

		self::redirect_back( $success > 0 ? 'partial' : 'failed', $failures[0] );
	}

	/**
	 * Vide le cache.
	 *
	 * @return void
	 */
	public static function handle_flush() {
		self::authorize( self::ACTION_FLUSH );

		GTDX_Cache::flush();

		self::redirect_back( 'flushed' );
	}

	/**
	 * Vérifie droits et nonce. Interrompt l'exécution en cas d'échec.
	 *
	 * Les deux contrôles sont nécessaires : le nonce atteste que la requête
	 * vient bien du formulaire, la capacité atteste que l'utilisateur a le
	 * droit d'effectuer l'action.
	 *
	 * @param string $action Nom de l'action.
	 * @return void
	 */
	private static function authorize( $action ) {

		/*
		 * admin-post.php dispatche sur $_REQUEST : sans ce contrôle, un GET
		 * porteur d'un nonce valide — récupéré dans un historique de navigation
		 * ou un journal de serveur — exécuterait l'action. Une action qui modifie
		 * l'état ne doit répondre qu'à POST.
		 */
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		if ( 'POST' !== $method ) {
			wp_die(
				esc_html__( 'Cette action n\'accepte que la méthode POST.', 'gutendex-books' ),
				405
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Vous n\'avez pas les droits nécessaires pour effectuer cette action.', 'gutendex-books' ),
				403
			);
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirige vers la page de réglages avec un indicateur de résultat.
	 *
	 * @param string $notice Code du message.
	 * @param string $detail Détail éventuel.
	 * @return void
	 */
	private static function redirect_back( $notice, $detail = '' ) {
		
		$args = array(
			'page'        => self::SLUG,
			'gtdx_notice' => $notice,
		);

		if ( '' !== $detail ) {
			$args['gtdx_detail'] = rawurlencode( $detail );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Retourne l'URL de traitement des formulaires.
	 *
	 * @return string
	 */
	public static function form_action() {
		return admin_url( 'admin-post.php' );
	}
}
