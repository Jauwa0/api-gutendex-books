<?php
/**
 * Nettoyage à la désinstallation.
 *
 * Exécuté par WordPress uniquement lors d'une suppression définitive du plugin.
 *
 * @package Gutendex_Books
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$gtdx_keys = get_option( 'gtdx_cache_keys', array() );

if ( is_array( $gtdx_keys ) ) {
	foreach ( array_keys( $gtdx_keys ) as $gtdx_key ) {
		delete_transient( $gtdx_key );
	}
}

delete_option( 'gtdx_cache_keys' );
delete_option( 'gtdx_last_fetch' );

unset( $gtdx_keys, $gtdx_key );
