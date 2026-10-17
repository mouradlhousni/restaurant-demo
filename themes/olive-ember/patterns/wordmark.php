<?php
/**
 * Title: Wordmark
 * Slug: olive-ember/wordmark
 * Inserter: false
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:html -->
<a class="oe-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
	<span class="oe-wordmark__olive"><?php esc_html_e( 'Olive', 'olive-ember' ); ?></span>
	<span class="oe-wordmark__amp" aria-hidden="true">&amp;</span><span class="screen-reader-text"><?php esc_html_e( 'and', 'olive-ember' ); ?></span>
	<span class="oe-wordmark__ember"><?php esc_html_e( 'Ember', 'olive-ember' ); ?></span>
</a>
<!-- /wp:html -->
