<?php
/**
 * Couche de cache : transients WordPress + registre des clés.
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encapsule l'API des transients.
 *
 * Le registre des clés (option `gtdx_cache_keys`) permet deux choses que
 * les transients seuls ne permettent pas :
 *  - purger toutes les entrées du plugin sans requête SQL directe ;
 *  - réchauffer le cache pour chaque jeu d'arguments réellement utilisé
 *    sur le site lors d'un renouvellement manuel.
 */
class GTDX_Cache {

	/** Préfixe commun à tous les transients du plugin. */
	const PREFIX = 'gtdx_books_';

	/** Option stockant l'horodatage de la dernière récupération réussie. */
	const OPTION_LAST_FETCH = 'gtdx_last_fetch';

	/** Option stockant le registre des clés : array<string $key, array $args>. */
	const OPTION_KEYS = 'gtdx_cache_keys';

	/** Durée de vie par défaut d'une réponse valide. */
	const DEFAULT_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Durée de vie d'une réponse en erreur.
	 *
	 * On met bien une erreur en cache, mais très brièvement : cela évite de
	 * marteler une API indisponible à chaque affichage de page, sans pour
	 * autant figer le site en erreur pendant douze heures.
	 */
	const ERROR_TTL = 5 * MINUTE_IN_SECONDS;

	/** Nombre maximum de jeux d'arguments suivis dans le registre. */
	const MAX_TRACKED_KEYS = 20;

	/**
	 * Construit une clé de transient déterministe à partir des arguments de requête.
	 *
	 * @param array $args Arguments normalisés de la requête API.
	 * @return string
	 */
	public static function build_key( array $args ) {
		ksort( $args );

		return self::PREFIX . md5( (string) wp_json_encode( $args ) );
	}

	/**
	 * Lit une entrée du cache.
	 *
	 * @param string $key Clé de transient.
	 * @return mixed False si absent ou expiré.
	 */
	public static function get( $key ) {
		return get_transient( $key );
	}

	/**
	 * Écrit une entrée du cache et l'enregistre dans le registre.
	 *
	 * @param string   $key   Clé de transient.
	 * @param mixed    $value Valeur à stocker.
	 * @param array    $args  Arguments ayant produit cette entrée.
	 * @param int|null $ttl   Durée de vie en secondes.
	 * @return void
	 */
	public static function set( $key, $value, array $args, $ttl = null ) {

		if ( null === $ttl ) {
			/**
			 * Filtre la durée de vie du cache des livres.
			 *
			 * @param int $ttl Durée en secondes.
			 */
			$ttl = (int) apply_filters( 'gtdx_cache_ttl', self::DEFAULT_TTL );
		}

		set_transient( $key, $value, max( 60, (int) $ttl ) );
		self::track_key( $key, $args );
	}

	/**
	 * Vide l'intégralité du cache du plugin.
	 *
	 * @return int Nombre d'entrées supprimées.
	 */
	public static function flush() {

		$keys    = self::get_tracked_keys();
		$deleted = 0;

		foreach ( array_keys( $keys ) as $key ) {
			if ( delete_transient( $key ) ) {
				++$deleted;
			}
		}

		update_option( self::OPTION_KEYS, array(), false );

		return $deleted;
	}

	/**
	 * Retourne le registre des clés connues.
	 *
	 * @return array<string, array>
	 */
	public static function get_tracked_keys() {

		$keys = get_option( self::OPTION_KEYS, array() );

		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * Nombre d'entrées actuellement présentes en cache (transient non expiré).
	 *
	 * @return int
	 */
	public static function count_live_entries() {

		$live = 0;

		foreach ( array_keys( self::get_tracked_keys() ) as $key ) {
			if ( false !== get_transient( $key ) ) {
				++$live;
			}
		}

		return $live;
	}

	/**
	 * Horodatage UTC de la dernière récupération réussie.
	 *
	 * Stocké en option et non en transient : l'information survit à
	 * l'expiration du cache, ce qui est justement l'intérêt de l'afficher.
	 *
	 * @return int 0 si aucune récupération n'a encore eu lieu.
	 */
	public static function get_last_fetch() {
		return (int) get_option( self::OPTION_LAST_FETCH, 0 );
	}

	/**
	 * Met à jour l'horodatage de dernière récupération réussie.
	 *
	 * @return void
	 */
	public static function touch_last_fetch() {
		update_option( self::OPTION_LAST_FETCH, time(), false );
	}

	/**
	 * Ajoute une clé au registre.
	 *
	 * @param string $key  Clé de transient.
	 * @param array  $args Arguments associés.
	 * @return void
	 */
	private static function track_key( $key, array $args ) {
		
		$keys = self::get_tracked_keys();

		if ( isset( $keys[ $key ] ) ) {
			return;
		}

		/*
		 * L'entrée la plus ancienne quitte le registre : son transient doit
		 * partir avec elle. Sans cela il devient orphelin : plus jamais atteint
		 * par flush() ni par uninstall.php, puisque ces deux traitements ne
		 * connaissent que les clés du registre.
		 *
		 * Boucle et non simple décalage : le registre peut dépasser le plafond
		 * si celui-ci a été abaissé entre deux versions.
		 */
		while ( count( $keys ) >= self::MAX_TRACKED_KEYS ) {
			$evicted = array_key_first( $keys );

			if ( null === $evicted ) {
				break;
			}

			unset( $keys[ $evicted ] );
			delete_transient( $evicted );
		}

		$keys[ $key ] = $args;

		update_option( self::OPTION_KEYS, $keys, false );
	}
}
