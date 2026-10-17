<?php
/**
 * Server-side render of the block (see src/Blocks/MenuSectionView.php).
 *
 * @package OliveEmber\Reservations
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo OliveEmber\Reservations\Blocks\MenuSectionView::render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view class.
