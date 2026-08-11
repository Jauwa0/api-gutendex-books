<?php
/**
 * Template : carte d'un livre.
 *
 * @package Gutendex_Books
 *
 * @var array $data {
 *     @type array  $book          Livre normalisé.
 *     @type string $heading_level Niveau de titre, déduit de celui de la section.
 * }
 */

defined( 'ABSPATH' ) || exit;

$gtdx_book = $data['book'];
$gtdx_lang = ! empty( $gtdx_book['languages'] ) ? $gtdx_book['languages'][0] : '';
$gtdx_tag  = tag_escape( $data['heading_level'] );

/*
 * Les auteurs sont la seule donnée de longueur imprévisible : le catalogue
 * francophone contient une fiche à quatre auteurs totalisant 170 caractères,
 * qui tripleraient la hauteur de la carte.
 *
 * Le plafond porte sur la longueur et non sur le nombre de noms : « Alexandre
 * Dumas, Auguste Maquet » tient en deux noms courts là où un seul
 * « Jean-Antoine-Nicolas de Caritat, marquis de Condorcet » sature déjà la
 * place. Le premier auteur est toujours affiché, quelle que soit sa longueur.
 *
 * La liste complète reste servie aux lecteurs d'écran et en infobulle : la
 * carte est plus courte, aucune information n'est perdue.
 */
$gtdx_max_chars = 60; // Environ deux lignes sur une carte de 200 px.

$gtdx_authors = $gtdx_book['authors'];
$gtdx_all     = implode( ', ', $gtdx_authors );
$gtdx_shown   = array();
$gtdx_length  = 0;

foreach ( $gtdx_authors as $gtdx_author ) {
	$gtdx_next = $gtdx_length + mb_strlen( $gtdx_author ) + 2; // + le « , » séparateur.

	if ( $gtdx_shown && $gtdx_next > $gtdx_max_chars ) {
		break;
	}

	$gtdx_shown[] = $gtdx_author;
	$gtdx_length  = $gtdx_next;
}

$gtdx_extra = count( $gtdx_authors ) - count( $gtdx_shown );

if ( empty( $gtdx_authors ) ) {
	$gtdx_byline = __( 'Auteur inconnu', 'gutendex-books' );
} elseif ( $gtdx_extra > 0 ) {
	$gtdx_byline = sprintf(
		/* translators: 1: premiers auteurs, 2: nombre d'auteurs restants. */
		_n( '%1$s et %2$d autre', '%1$s et %2$d autres', $gtdx_extra, 'gutendex-books' ),
		implode( ', ', $gtdx_shown ),
		$gtdx_extra
	);
} else {
	$gtdx_byline = $gtdx_all;
}
?>
<article class="gtdx-book">

	<?php if ( '' !== $gtdx_book['cover'] ) : ?>
		<img
			class="gtdx-book__cover"
			src="<?php echo esc_url( $gtdx_book['cover'] ); ?>"
			<?php // alt vide : la couverture est décorative, le titre qui suit porte déjà l'information. ?>
			alt=""
			loading="lazy"
			decoding="async"
			width="300"
			height="450"
		/>
	<?php else : ?>
		<span class="gtdx-book__cover gtdx-book__cover--placeholder" aria-hidden="true"></span>
	<?php endif; ?>

	<?php // Conteneur du texte : il permet la bascule couverture-à-gauche sur les cartes larges. ?>
	<div class="gtdx-book__body">

		<?php // Le niveau suit celui de la section : voir GTDX_Shortcode::item_heading_level(). ?>
		<<?php echo $gtdx_tag; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Valeur restreinte à une liste blanche et passée par tag_escape(). ?> class="gtdx-book__title"<?php echo '' !== $gtdx_lang ? ' lang="' . esc_attr( $gtdx_lang ) . '"' : ''; ?>>
			<?php if ( '' !== $gtdx_book['link'] ) : ?>
				<a class="gtdx-book__link" href="<?php echo esc_url( $gtdx_book['link'] ); ?>" rel="noopener external" target="_blank">
					<?php echo esc_html( $gtdx_book['title'] ); ?>
					<?php // Le lien s'ouvrant dans un nouvel onglet, l'annoncer plutôt que de le laisser deviner. ?>
					<span class="gtdx-visually-hidden"><?php esc_html_e( '(nouvel onglet)', 'gutendex-books' ); ?></span>
				</a>
			<?php else : ?>
				<?php echo esc_html( $gtdx_book['title'] ); ?>
			<?php endif; ?>
		</<?php echo $gtdx_tag; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>

		<?php // Les auteurs sont du contenu, pas une métadonnée : ligne pleine largeur, libellé réservé aux lecteurs d'écran. ?>
		<p class="gtdx-book__authors">
			<span class="gtdx-visually-hidden"><?php esc_html_e( 'Auteur :', 'gutendex-books' ); ?></span>
			<?php if ( $gtdx_extra > 0 ) : ?>
				<span aria-hidden="true" title="<?php echo esc_attr( $gtdx_all ); ?>"><?php echo esc_html( $gtdx_byline ); ?></span>
				<span class="gtdx-visually-hidden"><?php echo esc_html( $gtdx_all ); ?></span>
			<?php else : ?>
				<?php echo esc_html( $gtdx_byline ); ?>
			<?php endif; ?>
		</p>

		<dl class="gtdx-book__meta">

			<div class="gtdx-book__meta-row">
				<dt><?php esc_html_e( 'Langue', 'gutendex-books' ); ?></dt>
				<dd>
					<?php
					echo esc_html(
						! empty( $gtdx_book['languages'] )
							? strtoupper( implode( ', ', $gtdx_book['languages'] ) )
							: __( 'Non renseignée', 'gutendex-books' )
					);
					?>
				</dd>
			</div>

			<div class="gtdx-book__meta-row">
				<dt><?php esc_html_e( 'Téléchargements', 'gutendex-books' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $gtdx_book['download_count'] ) ); ?></dd>
			</div>

		</dl>

	</div>

</article>
