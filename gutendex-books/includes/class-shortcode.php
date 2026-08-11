<?php
/**
 * Shortcode [books_list].
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre et rend le shortcode.
 */
class GTDX_Shortcode {

	/** Nom du shortcode. */
	const TAG = 'books_list';

	/** Handle de la feuille de style. */
	const STYLE_HANDLE = 'gutendex-books';

	/** Niveaux de titre autorisés pour la section, du plus haut au plus bas. */
	const HEADING_LEVELS = array( 'h2', 'h3', 'h4', 'h5', 'h6' );

	/** Compteur d'instances, pour générer des identifiants uniques. */
	private static $instance_count = 0;

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Déclare la feuille de style sans la charger.
	 *
	 * Elle ne sera réellement mise en file que si le shortcode est rendu,
	 * afin de ne pas alourdir les pages qui ne l'utilisent pas.
	 *
	 * @return void
	 */
	public static function register_assets() {

		wp_register_style(
			self::STYLE_HANDLE,
			GTDX_URL . 'assets/css/books-list.css',
			array(),
			GTDX_VERSION
		);

	}

	/**
	 * Rend le shortcode.
	 *
	 * @param array|string $atts Attributs du shortcode.
	 * @return string HTML. Toujours retourné, jamais affiché.
	 */
	public static function render( $atts = array() ) {

		$atts = shortcode_atts(
			array(
				'limit'         => 10,
				'lang'          => '',
				'search'        => '',
				'title'         => __( 'Une sélection de livres du domaine public', 'gutendex-books' ),
				'intro'         => __( 'Titres les plus téléchargés sur Project Gutenberg, via l\'API Gutendex.', 'gutendex-books' ),
				'heading_level' => 'h2',
			),
			$atts,
			self::TAG
		);

		// Filet de sécurité : un shortcode rendu avant `wp_enqueue_scripts`
		// (en-tête de thème, appel direct à do_shortcode()) trouverait sinon
		// un handle non déclaré.
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		++self::$instance_count;

		$books = GTDX_Api_Client::get_books(
			array(
				'limit'  => $atts['limit'],
				'lang'   => $atts['lang'],
				'search' => $atts['search'],
			)
		);

		$title         = (string) $atts['title'];
		$heading_level = self::sanitize_heading_level( $atts['heading_level'] );

		return GTDX_Plugin::render_template(
			'books-list',
			array(
				'books'              => is_wp_error( $books ) ? array() : $books,
				'error'              => is_wp_error( $books ) ? $books : null,
				'title'              => $title,
				'intro'              => (string) $atts['intro'],
				'heading_level'      => $heading_level,
				'item_heading_level' => self::item_heading_level( $heading_level, '' !== $title ),
				'heading_id'         => 'gtdx-books-heading-' . self::$instance_count,
			)
		);

	}

	/**
	 * Restreint le niveau de titre à une valeur cohérente.
	 *
	 * Laisser le choix évite de casser la hiérarchie des titres de la page,
	 * qui est un critère d'accessibilité.
	 *
	 * @param string $level Niveau demandé.
	 * @return string
	 */
	private static function sanitize_heading_level( $level ) {
		$level = strtolower( trim( (string) $level ) );

		return in_array( $level, self::HEADING_LEVELS, true ) ? $level : 'h2';
	}

	/**
	 * Déduit le niveau de titre des livres de celui de la section.
	 *
	 * Les cartes se placent un cran sous le titre de section, plafonné à h6
	 * faute de h7. Titre de section masqué : la section n'apporte alors aucun
	 * titre à la hiérarchie du document, les cartes prennent directement le
	 * niveau demandé. Sans ce calcul, un `heading_level="h5"` produirait un h5
	 * suivi de h3 : exactement la rupture de hiérarchie que l'attribut existe
	 * pour éviter.
	 *
	 * @param string $section_level Niveau du titre de section, déjà validé.
	 * @param bool   $has_title     Le titre de section est-il rendu ?
	 * @return string
	 */
	private static function item_heading_level( $section_level, $has_title ) {

		if ( ! $has_title ) {
			return $section_level;
		}

		$index = (int) array_search( $section_level, self::HEADING_LEVELS, true );
		$index = min( $index + 1, count( self::HEADING_LEVELS ) - 1 );

		return self::HEADING_LEVELS[ $index ];

	}

	
}
