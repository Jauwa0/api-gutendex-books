<?php
/**
 * Template : liste de livres.
 *
 * @package Gutendex_Books
 *
 * @var array $data {
 *     @type array         $books              Livres normalisés.
 *     @type WP_Error|null $error              Erreur éventuelle.
 *     @type string        $title              Titre de la section.
 *     @type string        $intro              Introduction.
 *     @type string        $heading_level      Niveau de titre (h2 à h6).
 *     @type string        $item_heading_level Niveau de titre des livres.
 *     @type string        $heading_id         Identifiant du titre.
 *     @type array|null    $form               Données du formulaire, ou null s'il est masqué.
 * }
 */

defined( 'ABSPATH' ) || exit;

$gtdx_tag       = tag_escape( $data['heading_level'] );
$gtdx_has_title = '' !== $data['title'];

/*
 * Un <section> n'est exposé comme région que s'il porte un nom accessible, et
 * aria-labelledby doit désigner un élément réellement présent. Le titre pouvant
 * être masqué (title=""), on bascule alors sur aria-label plutôt que de laisser
 * une référence pendante : qui priverait la section de tout nom.
 */
$gtdx_label_attr = $gtdx_has_title
	? ' aria-labelledby="' . esc_attr( $data['heading_id'] ) . '"'
	: ' aria-label="' . esc_attr__( 'Sélection de livres du domaine public', 'gutendex-books' ) . '"';

// Filtres actifs, pour nommer ce qui n'a rien donné plutôt que rester vague.
$gtdx_filters = array();

if ( null !== $data['form'] ) {
	if ( '' !== $data['form']['search'] ) {
		$gtdx_filters[] = sprintf( '« %s »', $data['form']['search'] );
	}

	if ( isset( $data['form']['languages'][ $data['form']['lang'] ] ) ) {
		$gtdx_filters[] = $data['form']['languages'][ $data['form']['lang'] ];
	}
}
?>
<section class="gtdx-books"<?php echo $gtdx_label_attr; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Attribut construit ci-dessus avec esc_attr(). ?>>

	<?php // Titre de la section ?>
	<?php if ( $gtdx_has_title ) : ?>
		<<?php echo $gtdx_tag; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Valeur restreinte à une liste blanche et passée par tag_escape(). ?> id="<?php echo esc_attr( $data['heading_id'] ); ?>" class="gtdx-books__title">
			<?php echo esc_html( $data['title'] ); ?>
		</<?php echo $gtdx_tag; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
	<?php endif; ?>

	<?php // Introduction ?>
	<?php if ( '' !== $data['intro'] ) : ?>
		<p class="gtdx-books__intro"><?php echo esc_html( $data['intro'] ); ?></p>
	<?php endif; ?>

	<?php // Formulaire : rendu dans les trois états, une recherche vide doit rester corrigeable ?>
	<?php if ( null !== $data['form'] ) : ?>
		<?php
		echo GTDX_Plugin::render_template( // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Le template échappe chaque valeur.
			'books-form',
			$data['form']
		);
		?>
	<?php endif; ?>

	<?php // Données indisponibles ?>
	<?php if ( null !== $data['error'] ) : ?>

		<div class="gtdx-books__message gtdx-books__message--error">
			<p class="gtdx-books__message-text">
				<?php esc_html_e( 'Les livres ne sont pas disponibles pour le moment. Réessayez dans quelques instants.', 'gutendex-books' ); ?>
			</p>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<details class="gtdx-books__debug">
					<summary><?php esc_html_e( 'Détail technique', 'gutendex-books' ); ?></summary>
					<code><?php echo esc_html( $data['error']->get_error_message() ); ?></code>
				</details>
			<?php endif; ?>
		</div>

	<?php // Aucun résultat ?>
	<?php elseif ( empty( $data['books'] ) ) : ?>

		<div class="gtdx-books__message">
			<p class="gtdx-books__message-text">
				<?php if ( $gtdx_filters ) : ?>
					<?php
					printf(
						/* translators: %s: filtres actifs, ex. « Italien » et « verne ». */
						esc_html__( 'Aucun livre pour %s.', 'gutendex-books' ),
						esc_html( implode( __( ' et ', 'gutendex-books' ), $gtdx_filters ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Aucun livre ne correspond à cette sélection.', 'gutendex-books' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( $gtdx_filters ) : ?>
				<p class="gtdx-books__message-text">
					<a href="<?php echo esc_url( $data['form']['reset'] ); ?>"><?php esc_html_e( 'Voir toute la sélection', 'gutendex-books' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<?php // Grille de livres ?>
		<ul class="gtdx-books__grid">
			<?php foreach ( $data['books'] as $gtdx_book ) : ?>
				<li class="gtdx-books__item">
					<?php
					echo GTDX_Plugin::render_template( // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- Le template échappe chaque valeur.
						'book-card',
						array(
							'book'          => $gtdx_book,
							'heading_level' => $data['item_heading_level'],
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

</section>
