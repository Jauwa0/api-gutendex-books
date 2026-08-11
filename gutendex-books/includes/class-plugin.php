<?php
/**
 * Orchestrateur du plugin.
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée unique : enregistre les composants et expose les utilitaires partagés.
 */
class GTDX_Plugin {

	/**
	 * Initialise le plugin.
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		GTDX_Shortcode::register();

		if ( is_admin() ) {
			GTDX_Admin_Page::register();
		}
	}

	/**
	 * Charge les traductions.
	 *
	 * @return void
	 */
	public static function load_textdomain() {

		load_plugin_textdomain(
			'gutendex-books',
			false,
			dirname( plugin_basename( GTDX_FILE ) ) . '/languages'
		);

	}

	/**
	 * Charge un template et retourne son rendu.
	 *
	 * Les templates reçoivent un unique tableau `$data`. On évite volontairement
	 * extract() : les variables disponibles restent explicites et traçables.
	 *
	 * @param string $name Nom du fichier, sans extension.
	 * @param array  $data Données transmises au template.
	 * @return string
	 */
	public static function render_template( $name, array $data = array() ) {

		$file = GTDX_PATH . 'templates/' . sanitize_file_name( $name ) . '.php';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * À l'activation : rien à créer, on repart simplement d'un cache propre.
	 *
	 * @return void
	 */
	public static function activate() {
		GTDX_Cache::flush();
	}

	/**
	 * À la désactivation : on libère le cache, les options de suivi restent.
	 *
	 * @return void
	 */
	public static function deactivate() {
		GTDX_Cache::flush();
	}
	
}
