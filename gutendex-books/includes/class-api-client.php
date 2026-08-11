<?php
/**
 * Client HTTP de l'API Gutendex.
 *
 * @package Gutendex_Books
 */

defined( 'ABSPATH' ) || exit;

/**
 * Récupère et normalise les livres depuis https://gutendex.com.
 */
class GTDX_Api_Client {

	/** Point d'entrée de l'API. */
	const API_ENDPOINT = 'https://gutendex.com/books/';

	/** Timeout en secondes. */
	const TIMEOUT = 10;

	/** Limite de livres renvoyés par page par l'API. */
	const PAGE_SIZE = 32;

	/**
	 * Retourne les livres, depuis le cache si possible.
	 *
	 * @param array $args Arguments bruts : limit, lang, search.
	 * @return array|WP_Error Tableau de livres normalisés, ou WP_Error.
	 */
	public static function get_books( array $args = array() ) {

		$args   = self::normalize_args( $args );
		$key    = GTDX_Cache::build_key( self::cache_args( $args ) );
		$cached = GTDX_Cache::get( $key );

		// get_transient() rend false si l'entrée est absente ou expirée.
		if ( is_array( $cached ) ) {
			// Une erreur mise en cache est restituée telle quelle.
			if ( isset( $cached['__error'] ) ) {
				return new WP_Error( $cached['__error'], $cached['__message'] );
			}

			// Le cache contient la page complète : la troncature se fait ici.
			return array_slice( $cached, 0, $args['limit'] );
		}

		return self::refresh( $args );
	}

	/**
	 * Force un appel à l'API et met le résultat en cache.
	 *
	 * @param array $args Arguments (bruts ou déjà normalisés).
	 * @return array|WP_Error
	 */
	public static function refresh( array $args = array() ) {

		$args       = self::normalize_args( $args );
		$cache_args = self::cache_args( $args );
		$key        = GTDX_Cache::build_key( $cache_args );
		$result     = self::fetch( $args );

		if ( is_wp_error( $result ) ) {
			$existing = GTDX_Cache::get( $key );

			// On ne remplace jamais des données valides par une erreur : un
			// renouvellement déclenché pendant une indisponibilité de l'API ne
			// doit pas dégrader ce qui est déjà servi aux visiteurs. L'erreur
			// n'est mise en cache que faute de mieux, et brièvement, pour ne pas
			// marteler l'API à chaque affichage de page.
			if ( ! is_array( $existing ) || isset( $existing['__error'] ) ) {
				GTDX_Cache::set(
					$key,
					array(
						'__error'   => $result->get_error_code(),
						'__message' => $result->get_error_message(),
					),
					$cache_args,
					GTDX_Cache::ERROR_TTL
				);
			}

			return $result;
		}

		// La page complète est mise en cache, la troncature est faite au retour :
		// deux shortcodes ne différant que par `limit` partagent ainsi l'entrée.
		GTDX_Cache::set( $key, $result, $cache_args );
		GTDX_Cache::touch_last_fetch();

		return array_slice( $result, 0, $args['limit'] );
	}

	/**
	 * Appel HTTP brut, sans cache.
	 *
	 * @param array $args Arguments normalisés.
	 * @return array|WP_Error
	 */
	private static function fetch( array $args ) {

		$query = array( 'page' => 1 );

		if ( '' !== $args['lang'] ) {
			$query['languages'] = rawurlencode( $args['lang'] );
		}

		if ( '' !== $args['search'] ) {
			$query['search'] = rawurlencode( $args['search'] );
		}

		$url = add_query_arg( $query, self::API_ENDPOINT );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'Gutendex Books WordPress plugin/' . GTDX_VERSION . '; ' . home_url( '/' ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		// Cas 1 : échec réseau, DNS, timeout, TLS.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gtdx_http_failure',
				sprintf(
					/* translators: %s: message d'erreur renvoyé par WordPress. */
					__( 'Impossible de contacter l\'API Gutendex : %s', 'gutendex-books' ),
					$response->get_error_message()
				)
			);
		}

		// Cas 2 : réponse reçue mais statut HTTP non exploitable.
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'gtdx_http_status',
				sprintf(
					/* translators: %d: code de statut HTTP. */
					__( 'L\'API Gutendex a répondu avec le statut HTTP %d.', 'gutendex-books' ),
					$code
				)
			);
		}

		// Cas 3 : corps de réponse absent, non-JSON ou de structure inattendue.
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $payload ) || ! isset( $payload['results'] ) || ! is_array( $payload['results'] ) ) {
			return new WP_Error(
				'gtdx_invalid_payload',
				__( 'La réponse de l\'API Gutendex est illisible ou de format inattendu.', 'gutendex-books' )
			);
		}

		$books = array();

		foreach ( $payload['results'] as $raw ) {
			$book = self::normalize_book( $raw );

			if ( null !== $book ) {
				$books[] = $book;
			}

			// La page entière est conservée, `limit` n'intervient qu'au rendu.
			// Garde-fou si l'API venait à paginer plus large que PAGE_SIZE.
			if ( count( $books ) >= self::PAGE_SIZE ) {
				break;
			}
		}

		return $books;
	}

	/**
	 * Réduit les arguments à ceux qui influent réellement sur l'appel HTTP.
	 *
	 * `limit` en est exclu : la requête récupère toujours la première page de
	 * l'API, et la troncature est faite au retour. Deux shortcodes ne différant
	 * que par `limit` partagent donc la même entrée de cache : un appel réseau
	 * et une place de registre économisés.
	 *
	 * @param array $args Arguments normalisés.
	 * @return array
	 */
	private static function cache_args( array $args ) {

		return array(
			'lang'   => $args['lang'],
			'search' => $args['search'],
		);

	}

	/**
	 * Nettoie et borne les arguments d'entrée.
	 *
	 * @param array $args Arguments bruts.
	 * @return array
	 */
	public static function normalize_args( array $args ) {

		$defaults = array(
			'limit'  => 10,
			'lang'   => '',
			'search' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$limit = absint( $args['limit'] );
		$limit = $limit > 0 ? min( $limit, self::PAGE_SIZE ) : $defaults['limit'];

		/*
		 * Codes ISO 639-1, éventuellement séparés par des virgules. Les jetons
		 * sont dédoublonnés puis triés : `lang="en,fr"` et `lang="fr,en"`
		 * désignent la même requête et doivent produire la même clé de cache.
		 */
		$lang = strtolower( (string) $args['lang'] );
		$lang = (string) preg_replace( '/[^a-z,]/', '', $lang );
		$lang = array_filter( array_unique( explode( ',', $lang ) ), 'strlen' );

		sort( $lang );

		return array(
			'limit'  => $limit,
			'lang'   => implode( ',', $lang ),
			'search' => sanitize_text_field( (string) $args['search'] ),
		);
	}

	/**
	 * Transforme un livre brut de l'API en structure stable.
	 *
	 * @param mixed $raw Élément brut du tableau `results`.
	 * @return array|null Null si l'élément est inexploitable.
	 */
	private static function normalize_book( $raw ) {

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$id      = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
		$title   = isset( $raw['title'] ) && is_string( $raw['title'] ) ? trim( $raw['title'] ) : '';
		$formats = isset( $raw['formats'] ) && is_array( $raw['formats'] ) ? $raw['formats'] : array();

		if ( '' === $title ) {
			$title = __( 'Titre non renseigné', 'gutendex-books' );
		}

		return array(
			'id'             => $id,
			'title'          => $title,
			'authors'        => self::extract_authors( $raw ),
			'languages'      => self::extract_languages( $raw ),
			'download_count' => isset( $raw['download_count'] ) ? absint( $raw['download_count'] ) : 0,
			'cover'          => self::find_cover( $formats ),
			'link'           => self::find_link( $formats, $id ),
		);
	}

	/**
	 * Extrait la liste des auteurs, remise dans l'ordre naturel de lecture.
	 *
	 * @param array $raw Livre brut.
	 * @return string[]
	 */
	private static function extract_authors( array $raw ) {

		$authors = array();

		if ( empty( $raw['authors'] ) || ! is_array( $raw['authors'] ) ) {
			return $authors;
		}

		foreach ( $raw['authors'] as $author ) {
			if ( ! is_array( $author ) || empty( $author['name'] ) || ! is_string( $author['name'] ) ) {
				continue;
			}

			$authors[] = self::humanize_author_name( trim( $author['name'] ) );
		}

		return $authors;
	}

	/**
	 * Gutendex renvoie « Dickens, Charles ». On restitue « Charles Dickens ».
	 *
	 * @param string $name Nom brut.
	 * @return string
	 */
	private static function humanize_author_name( $name ) {

		if ( ! str_contains( $name, ',' ) ) {
			return $name;
		}

		$parts = array_map( 'trim', explode( ',', $name, 2 ) );

		if ( 2 !== count( $parts ) || '' === $parts[1] ) {
			return $name;
		}

		return $parts[1] . ' ' . $parts[0];
	}

	/**
	 * Extrait les codes langue.
	 *
	 * @param array $raw Livre brut.
	 * @return string[]
	 */
	private static function extract_languages( array $raw ) {

		if ( empty( $raw['languages'] ) || ! is_array( $raw['languages'] ) ) {
			return array();
		}

		$languages = array();

		foreach ( $raw['languages'] as $language ) {
			if ( is_string( $language ) && '' !== trim( $language ) ) {
				$languages[] = strtolower( trim( $language ) );
			}
		}

		return $languages;
	}

	/**
	 * Cherche une couverture dans la table des formats.
	 *
	 * @param array $formats Table MIME => URL.
	 * @return string Chaîne vide si aucune couverture exploitable.
	 */
	private static function find_cover( array $formats ) {

		foreach ( $formats as $mime => $url ) {
			if ( ! is_string( $mime ) || ! is_string( $url ) ) {
				continue;
			}

			if ( str_starts_with( $mime, 'image/' ) && wp_http_validate_url( $url ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Cherche le meilleur lien de lecture, avec repli sur la fiche Gutenberg.
	 *
	 * @param array $formats Table MIME => URL.
	 * @param int   $id      Identifiant Project Gutenberg.
	 * @return string Chaîne vide si aucun lien n'est exploitable.
	 */
	private static function find_link( array $formats, $id ) {

		$preferred = array( 'text/html', 'application/epub+zip', 'text/plain' );

		foreach ( $preferred as $needle ) {
			foreach ( $formats as $mime => $url ) {
				if ( ! is_string( $mime ) || ! is_string( $url ) ) {
					continue;
				}

				// Les clés peuvent porter un suffixe, ex. « text/html; charset=utf-8 ».
				if ( ! str_starts_with( $mime, $needle ) ) {
					continue;
				}

				// On écarte les archives, peu utiles pour une lecture en ligne.
				if ( str_ends_with( $url, '.zip' ) ) {
					continue;
				}

				if ( wp_http_validate_url( $url ) ) {
					return $url;
				}
			}
		}

		return $id > 0 ? 'https://www.gutenberg.org/ebooks/' . $id : '';
	}


}
