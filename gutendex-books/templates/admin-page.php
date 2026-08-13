<?php
/**
 * Template : page d'administration.
 *
 * @package Gutendex_Books
 *
 * @var array $data Données préparées par GTDX_Admin_Page::render().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">

	<h1><?php esc_html_e( 'Gutendex Books', 'gutendex-books' ); ?></h1>

	<?php if ( 'refreshed' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Les données ont été renouvelées depuis l\'API Gutendex.', 'gutendex-books' ); ?></p>
		</div>
	<?php elseif ( 'flushed' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Le cache a été vidé.', 'gutendex-books' ); ?></p>
		</div>
	<?php elseif ( 'partial' === $data['notice'] ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Renouvellement partiel : certaines requêtes ont échoué.', 'gutendex-books' ); ?></p>
			<?php if ( '' !== $data['notice_detail'] ) : ?>
				<p><code><?php echo esc_html( $data['notice_detail'] ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'failed' === $data['notice'] ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Le renouvellement a échoué.', 'gutendex-books' ); ?></p>
			<?php if ( '' !== $data['notice_detail'] ) : ?>
				<p><code><?php echo esc_html( $data['notice_detail'] ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'État du cache', 'gutendex-books' ); ?></h2>

	<table class="widefat striped" style="max-width:720px">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dernière récupération réussie', 'gutendex-books' ); ?></th>
				<td>
					<?php if ( $data['last_fetch'] > 0 ) : ?>
						<?php echo esc_html( $data['formatted_date'] ); ?>
						<em>(<?php echo esc_html( $data['relative_date'] ); ?>)</em>
					<?php else : ?>
						<?php esc_html_e( 'Aucune récupération pour le moment.', 'gutendex-books' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Entrées valides en cache', 'gutendex-books' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: 1: entrées encore valides, 2: entrées suivies au total. */
						esc_html__( '%1$d sur %2$d requête(s) suivie(s)', 'gutendex-books' ),
						(int) $data['live_entries'],
						(int) $data['tracked_keys']
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Durée de vie du cache', 'gutendex-books' ); ?></th>
				<td>
					<?php
					echo esc_html(
						human_time_diff( 0, (int) apply_filters( 'gtdx_cache_ttl', GTDX_Cache::DEFAULT_TTL ) )
					);
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Actions', 'gutendex-books' ); ?></h2>

	<p>
		<?php esc_html_e( 'Le renouvellement rappelle immédiatement l\'API et remplace chaque entrée dont la récupération aboutit ; si l\'API est indisponible, les données déjà en cache sont conservées. La purge, elle, supprime les données : elles seront récupérées au prochain affichage du shortcode.', 'gutendex-books' ); ?>
	</p>

	<div style="display:flex;gap:1rem;flex-wrap:wrap">

		<form method="post" action="<?php echo esc_url( GTDX_Admin_Page::form_action() ); ?>">
			<?php wp_nonce_field( GTDX_Admin_Page::ACTION_REFRESH ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( GTDX_Admin_Page::ACTION_REFRESH ); ?>" />
			<?php
			submit_button(
				__( 'Renouveler les données', 'gutendex-books' ),
				'primary',
				'submit',
				false
			);
			?>
		</form>

		<form method="post" action="<?php echo esc_url( GTDX_Admin_Page::form_action() ); ?>">
			<?php wp_nonce_field( GTDX_Admin_Page::ACTION_FLUSH ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( GTDX_Admin_Page::ACTION_FLUSH ); ?>" />
			<?php
			submit_button(
				__( 'Vider le cache', 'gutendex-books' ),
				'secondary',
				'submit',
				false
			);
			?>
		</form>

	</div>

	<h2><?php esc_html_e( 'Utilisation', 'gutendex-books' ); ?></h2>

	<p><?php esc_html_e( 'Insérez le shortcode suivant dans une page ou un article :', 'gutendex-books' ); ?></p>

	<p><code>[books_list]</code></p>

	<p><?php esc_html_e( 'Attributs facultatifs :', 'gutendex-books' ); ?></p>

	<ul class="ul-disc">
		<li><code>limit</code> - <?php esc_html_e( 'nombre de livres, de 1 à 32 (défaut : 10).', 'gutendex-books' ); ?></li>
		<li><code>lang</code> - <?php esc_html_e( 'filtre de langue, code ISO 639-1, ex. « fr » ou « fr,en ».', 'gutendex-books' ); ?></li>
		<li><code>search</code> - <?php esc_html_e( 'recherche par titre ou auteur.', 'gutendex-books' ); ?></li>
		<li><code>title</code> / <code>intro</code> - <?php esc_html_e( 'personnalisation du titre et du texte d\'introduction.', 'gutendex-books' ); ?></li>
		<li><code>heading_level</code> - <?php esc_html_e( 'niveau du titre, de h2 à h6 (défaut : h2).', 'gutendex-books' ); ?></li>
		<li><code>form</code> - <?php esc_html_e( 'affiche un formulaire de recherche et de choix de langue (défaut : no).', 'gutendex-books' ); ?></li>
	</ul>

	<p><code>[books_list limit="12" lang="fr" title="Nos classiques francophones"]</code></p>

</div>
