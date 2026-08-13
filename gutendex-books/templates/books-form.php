<?php
/**
 * Template : formulaire de filtrage.
 *
 * Méthode GET, sans JavaScript : l'état est dans l'URL, donc partageable et
 * indexable, et le formulaire fonctionne au clavier par construction.
 *
 * @package Gutendex_Books
 *
 * @var array $data Données préparées par GTDX_Shortcode::form_data().
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="gtdx-books__form" role="search" method="get" action="<?php echo esc_url( $data['action'] ); ?>">

	<?php foreach ( $data['hidden'] as $gtdx_key => $gtdx_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $gtdx_key ); ?>" value="<?php echo esc_attr( $gtdx_value ); ?>" />
	<?php endforeach; ?>

	<p class="gtdx-books__field">
		<label for="<?php echo esc_attr( $data['search_id'] ); ?>"><?php esc_html_e( 'Titre ou auteur', 'gutendex-books' ); ?></label>
		<input
			class="gtdx-books__control"
			type="search"
			id="<?php echo esc_attr( $data['search_id'] ); ?>"
			name="<?php echo esc_attr( $data['param_search'] ); ?>"
			value="<?php echo esc_attr( $data['search'] ); ?>"
			placeholder="<?php esc_attr_e( 'Ex. Jules Verne', 'gutendex-books' ); ?>"
		/>
	</p>

	<p class="gtdx-books__field">
		<label for="<?php echo esc_attr( $data['lang_id'] ); ?>"><?php esc_html_e( 'Langue', 'gutendex-books' ); ?></label>
		<select class="gtdx-books__control" id="<?php echo esc_attr( $data['lang_id'] ); ?>" name="<?php echo esc_attr( $data['param_lang'] ); ?>">
			<option value=""><?php esc_html_e( 'Toutes les langues', 'gutendex-books' ); ?></option>
			<?php foreach ( $data['languages'] as $gtdx_code => $gtdx_label ) : ?>
				<option value="<?php echo esc_attr( $gtdx_code ); ?>"<?php selected( $data['lang'], $gtdx_code ); ?>>
					<?php echo esc_html( $gtdx_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="gtdx-books__field gtdx-books__field--actions">
		<button type="submit" class="gtdx-books__submit"><?php esc_html_e( 'Filtrer', 'gutendex-books' ); ?></button>
		<?php if ( '' !== $data['search'] || '' !== $data['lang'] ) : ?>
			<a class="gtdx-books__reset" href="<?php echo esc_url( $data['reset'] ); ?>"><?php esc_html_e( 'Réinitialiser', 'gutendex-books' ); ?></a>
		<?php endif; ?>
	</p>

</form>
