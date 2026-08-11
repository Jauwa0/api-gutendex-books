<?php
/**
 * Plugin Name:       Gutendex Books
 * Plugin URI:        https://example.com/gutendex-books
 * Description:       Affiche une sélection de livres issus de l'API publique Gutendex (Project Gutenberg) via le shortcode [books_list].
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Aurélien DAVID
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gutendex-books
 * Domain Path:       /languages
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

define( 'GTDX_VERSION', '1.0.0' );
define( 'GTDX_FILE', __FILE__ );
define( 'GTDX_PATH', plugin_dir_path( __FILE__ ) );
define( 'GTDX_URL', plugin_dir_url( __FILE__ ) );

/**
 * Chargement des classes.
 *
 * require_once pour une autonomie complète (!= autoloader ou Composer).
 * Le plugin est installable par simple copie du dossier, sans étape de build.
 *
 * L'ordre reflète les dépendances : les classes utilitaires d'abord,
 * l'orchestrateur en dernier.
 */
$gtdx_includes = array(
	'class-cache',       // Aucune dépendance.
	'class-api-client',  // Dépend de GTDX_Cache.
	'class-shortcode',   // Dépend de GTDX_Api_Client.
	'class-admin-page',  // Dépend de GTDX_Cache + GTDX_Api_Client.
	'class-plugin',      // Orchestre l'ensemble.
);

foreach ( $gtdx_includes as $gtdx_file ) {
	require_once GTDX_PATH . 'includes/' . $gtdx_file . '.php';
}
unset( $gtdx_includes, $gtdx_file );

// Point d'entrée unique du plugin
add_action( 'plugins_loaded', array( 'GTDX_Plugin', 'init' ) );

register_activation_hook( __FILE__, array( 'GTDX_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GTDX_Plugin', 'deactivate' ) );
