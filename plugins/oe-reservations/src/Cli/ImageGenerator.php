<?php
/**
 * Procedural dish illustrations (no external downloads, no hotlinking).
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Draws flat, top-down food illustrations with GD.
 *
 * Each dish has a small "recipe" (vessel, base, pieces, sauce, garnish); a seeded PRNG
 * places the pieces, so the same dish always gets the same picture. Images are drawn at
 * twice the size and downsampled, which gives cheap anti-aliasing.
 */
final class ImageGenerator {

	private const SCALE = 2;

	/**
	 * GD canvas.
	 *
	 * @var \GdImage
	 */
	private $img;

	/**
	 * Canvas size (supersampled).
	 *
	 * @var int
	 */
	private int $w = 0;

	/**
	 * Canvas height (supersampled).
	 *
	 * @var int
	 */
	private int $h = 0;

	/**
	 * PRNG state.
	 *
	 * @var int
	 */
	private int $state = 1;

	/**
	 * Table colours: surface, texture.
	 */
	private const TABLES = array(
		array( '#e8dcc6', '#d9c9ad' ), // Linen.
		array( '#c7744f', '#b5623f' ), // Terracotta.
		array( '#59654a', '#4c573e' ), // Olive.
		array( '#2f3b4c', '#273141' ), // Night blue.
		array( '#cfc5b5', '#bfb4a2' ), // Stone.
	);

	/**
	 * Recipes. Pieces: [shape, colour, count, size (fraction of the plate radius), spread].
	 */
	private const RECIPES = array(
		'flatbread'   => array(
			'vessel'  => 'board',
			'base'    => array( 'flat', '#e3b46b', '#b9783a' ),
			'pieces'  => array( array( 'blob', '#f7f1e3', 7, 0.13, 0.5 ), array( 'dot', '#9e2b1d', 30, 0.025, 0.6 ), array( 'leaf', '#6b7d3a', 10, 0.06, 0.6 ) ),
			'drizzle' => '#d39a2a',
		),
		'tomato'      => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'slice', '#d8432e', 7, 0.2, 0.45 ), array( 'slice', '#f0a23b', 4, 0.16, 0.45 ), array( 'blob', '#f2b880', 3, 0.12, 0.4 ), array( 'leaf', '#4f7a2a', 9, 0.07, 0.55 ) ),
			'drizzle' => '#6e8a2a',
		),
		'crudo'       => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'petal', '#f3d6c8', 9, 0.18, 0.42 ), array( 'dot', '#c2442e', 14, 0.035, 0.5 ), array( 'leaf', '#a9bd6a', 8, 0.05, 0.5 ) ),
			'drizzle' => '#c9a227',
		),
		'octopus'     => array(
			'vessel' => 'plate',
			'base'   => array( 'smear', '#e7d6a8' ),
			'pieces' => array( array( 'tentacle', '#8e3b3b', 4, 0.85, 0.15 ), array( 'blob', '#ead9a4', 5, 0.12, 0.45 ), array( 'dot', '#3e6b2a', 18, 0.03, 0.5 ) ),
		),
		'burrata'     => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'blob', '#fbf8f1', 1, 0.36, 0 ), array( 'dot', '#6b2a4a', 16, 0.06, 0.65 ), array( 'dot', '#a3c25b', 6, 0.05, 0.62 ), array( 'leaf', '#4f7a2a', 6, 0.07, 0.55 ) ),
			'drizzle' => '#4b2a1a',
		),
		'dip'         => array(
			'vessel'  => 'bowl',
			'base'    => array( 'fill', '#c7a27a' ),
			'pieces'  => array( array( 'swirl', '#d9bb93', 1, 0.5, 0 ), array( 'dot', '#b3122e', 16, 0.03, 0.45 ), array( 'leaf', '#4f7a2a', 6, 0.06, 0.4 ) ),
			'drizzle' => '#7c8a2a',
		),
		'fries'       => array(
			'vessel' => 'board',
			'pieces' => array( array( 'stick', '#e6b45a', 12, 0.4, 0.35 ), array( 'blob', '#f4eccc', 1, 0.16, 0.5 ), array( 'dot', '#b5361e', 10, 0.02, 0.4 ) ),
		),
		'soup'        => array(
			'vessel' => 'bowl',
			'base'   => array( 'fill', '#c4581f' ),
			'pieces' => array( array( 'blob', '#e7b04a', 3, 0.14, 0.35 ), array( 'dot', '#f3dfb0', 8, 0.05, 0.4 ), array( 'leaf', '#3f6b2a', 5, 0.05, 0.35 ) ),
		),
		'fish'        => array(
			'vessel' => 'platter',
			'pieces' => array( array( 'fish', '#9aa7ad', 1, 0.8, 0 ), array( 'slice', '#f3d34a', 3, 0.12, 0.6 ), array( 'leaf', '#5d7a2a', 8, 0.07, 0.65 ) ),
		),
		'stew'        => array(
			'vessel' => 'bowl',
			'base'   => array( 'fill', '#d0731f' ),
			'pieces' => array( array( 'blob', '#f1e0c8', 5, 0.14, 0.4 ), array( 'shell', '#26262e', 5, 0.11, 0.45 ), array( 'prawn', '#e0603a', 2, 0.22, 0.3 ), array( 'leaf', '#3f6b2a', 5, 0.05, 0.4 ) ),
		),
		'lamb'        => array(
			'vessel' => 'plate',
			'base'   => array( 'smear', '#efe5cf' ),
			'pieces' => array( array( 'slab', '#7a3e24', 1, 0.42, 0.1 ), array( 'dot', '#e3c48a', 18, 0.05, 0.55 ), array( 'dot', '#c0392b', 6, 0.03, 0.5 ), array( 'leaf', '#5d7a2a', 5, 0.06, 0.5 ) ),
		),
		'chicken'     => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'slab', '#c98a3c', 1, 0.4, 0.1 ), array( 'stick', '#8aa04b', 4, 0.32, 0.35 ), array( 'blob', '#f0e4c4', 4, 0.07, 0.5 ) ),
			'drizzle' => '#7a4a20',
		),
		'steak'       => array(
			'vessel' => 'board',
			'pieces' => array( array( 'slab', '#5e2a1c', 1, 0.45, 0.12 ), array( 'stick', '#e3b04f', 9, 0.3, 0.3 ), array( 'blob', '#f3e2a8', 1, 0.1, 0.55 ) ),
		),
		'pasta'       => array(
			'vessel' => 'bowl',
			'base'   => array( 'fill', '#f4efe6' ),
			'pieces' => array( array( 'noodles', '#1f1f24', 1, 0.6, 0 ), array( 'dot', '#d13b2a', 8, 0.06, 0.4 ), array( 'ring', '#f1e6d6', 4, 0.07, 0.35 ), array( 'leaf', '#3f7a2a', 6, 0.04, 0.4 ) ),
		),
		'risotto'     => array(
			'vessel' => 'bowl',
			'base'   => array( 'fill', '#efdcab' ),
			'pieces' => array( array( 'grain', '#f7ebc9', 120, 0.02, 0.6 ), array( 'blob', '#8a5a2b', 7, 0.1, 0.4 ), array( 'dot', '#f9f3e3', 10, 0.04, 0.4 ), array( 'leaf', '#4f6b2a', 4, 0.05, 0.35 ) ),
		),
		'cauliflower' => array(
			'vessel' => 'plate',
			'base'   => array( 'smear', '#c9532e' ),
			'pieces' => array( array( 'floret', '#ead8a6', 1, 0.38, 0.05 ), array( 'dot', '#7a3b4a', 10, 0.03, 0.55 ), array( 'dot', '#f1e3b8', 12, 0.02, 0.55 ), array( 'leaf', '#4f7a2a', 5, 0.06, 0.55 ) ),
		),
		'tart'        => array(
			'vessel' => 'board',
			'base'   => array( 'tart', '#d9a35b', '#e9cf8f' ),
			'pieces' => array( array( 'cross', '#5b3a28', 8, 0.08, 0.5 ), array( 'dot', '#1f1d22', 12, 0.03, 0.55 ) ),
		),
		'prawns'      => array(
			'vessel' => 'plate',
			'base'   => array( 'smear', '#f0c75a' ),
			'pieces' => array( array( 'prawn', '#e0603a', 4, 0.26, 0.3 ), array( 'leaf', '#3f7a2a', 6, 0.05, 0.5 ), array( 'slice', '#f3d34a', 1, 0.12, 0.62 ) ),
		),
		'cake'        => array(
			'vessel' => 'plate',
			'pieces' => array( array( 'wedge', '#e7c06a', 1, 0.7, 0 ), array( 'blob', '#fbf6ea', 1, 0.14, 0.45 ), array( 'dot', '#f39c1f', 8, 0.035, 0.5 ) ),
		),
		'chocolate'   => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'wedge', '#3b2116', 1, 0.7, 0 ), array( 'dot', '#f4ece1', 30, 0.01, 0.35 ), array( 'blob', '#e9d9c0', 1, 0.1, 0.5 ) ),
			'drizzle' => '#c7a227',
		),
		'pavlova'     => array(
			'vessel'  => 'plate',
			'pieces'  => array( array( 'blob', '#fbf7ef', 1, 0.45, 0 ), array( 'fig', '#6b2a4a', 5, 0.12, 0.28 ), array( 'leaf', '#8c7ab5', 6, 0.03, 0.35 ) ),
			'drizzle' => '#d9a227',
		),
		'sorbet'      => array(
			'vessel' => 'bowl',
			'base'   => array( 'fill', '#f6f0d8' ),
			'pieces' => array( array( 'blob', '#f7ec9a', 3, 0.22, 0.25 ), array( 'leaf', '#4f8a2a', 3, 0.07, 0.3 ), array( 'slice', '#f3d34a', 1, 0.12, 0.62 ) ),
		),
		'biscuits'    => array(
			'vessel' => 'board',
			'pieces' => array( array( 'navette', '#d79b4f', 6, 0.22, 0.4 ), array( 'cup', '#3b2116', 1, 0.24, 0.55 ) ),
		),
		'cheese'      => array(
			'vessel' => 'slate',
			'pieces' => array( array( 'wedge', '#f1dc9a', 1, 0.45, 0.35 ), array( 'blob', '#f7f2e6', 1, 0.2, 0.4 ), array( 'cube', '#e9c36a', 3, 0.1, 0.45 ), array( 'dot', '#5b2a3a', 7, 0.05, 0.55 ) ),
		),
	);

	/**
	 * Wine colours.
	 */
	private const WINES = array(
		'rose'      => '#f2a38a',
		'white'     => '#efd98a',
		'red'       => '#6e1424',
		'orange'    => '#e39a3b',
		'sparkling' => '#f3e3a1',
		'sweet'     => '#d79a2b',
	);

	/**
	 * Whether GD can produce JPEGs.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Output format: WebP when GD supports it, JPEG otherwise.
	 *
	 * @return string webp | jpg.
	 */
	public function extension(): string {
		return function_exists( 'imagewebp' ) ? 'webp' : 'jpg';
	}

	/**
	 * Renders a dish illustration (WebP or JPEG, see extension()).
	 *
	 * @param string $art    Recipe key (or wine colour key).
	 * @param int    $seed   Seed.
	 * @param int    $width  Output width.
	 * @param int    $height Output height.
	 * @return string Image data.
	 */
	public function render( string $art, int $seed, int $width = 960, int $height = 720 ): string {
		$this->state = max( 1, $seed );
		$this->w     = $width * self::SCALE;
		$this->h     = $height * self::SCALE;
		$this->img   = imagecreatetruecolor( $this->w, $this->h );
		imagealphablending( $this->img, true );

		if ( isset( self::WINES[ $art ] ) ) {
			$this->wine( self::WINES[ $art ], 'sparkling' === $art );
		} else {
			$this->dish( self::RECIPES[ $art ] ?? self::RECIPES['tomato'] );
		}

		$out = imagecreatetruecolor( $width, $height );
		imagecopyresampled( $out, $this->img, 0, 0, 0, 0, $width, $height, $this->w, $this->h );
		imagedestroy( $this->img );

		ob_start();
		if ( 'webp' === $this->extension() ) {
			imagewebp( $out, null, 80 );
		} else {
			imageinterlace( $out, true );
			imagejpeg( $out, null, 82 );
		}
		imagedestroy( $out );

		return (string) ob_get_clean();
	}

	/**
	 * Plated dish, seen from above.
	 *
	 * @param array<string, mixed> $recipe Recipe.
	 * @return void
	 */
	private function dish( array $recipe ): void {
		$table = self::TABLES[ $this->rand( 0, count( self::TABLES ) - 1 ) ];
		$this->table( $table );

		$cx = (int) ( $this->w * ( 0.5 + $this->float( -0.04, 0.04 ) ) );
		$cy = (int) ( $this->h * ( 0.52 + $this->float( -0.03, 0.03 ) ) );
		$r  = (int) ( $this->h * 0.4 );

		$this->props( $cx, $r );
		$inner = $this->vessel( (string) $recipe['vessel'], $cx, $cy, $r );

		if ( ! empty( $recipe['base'] ) ) {
			$this->base( $recipe['base'], $cx, $cy, $inner );
		}

		foreach ( $recipe['pieces'] as $piece ) {
			list( $shape, $color, $count, $size, $spread ) = $piece;

			// Recipe sizes are relative; scale them so the food fills the plate generously.
			$size  *= 1.45;
			$spread = min( 0.9, $spread * 1.5 );
			for ( $i = 0; $i < $count; $i++ ) {
				$angle    = $this->float( 0, 2 * M_PI );
				$distance = $spread * $inner * sqrt( $this->float( 0, 1 ) );
				$x        = (int) ( $cx + cos( $angle ) * $distance );
				$y        = (int) ( $cy + sin( $angle ) * $distance );
				$this->piece( $shape, $color, $x, $y, (int) max( 2, $size * $inner ), $cx, $cy, $inner );
			}
		}

		if ( ! empty( $recipe['drizzle'] ) ) {
			$this->drizzle( $recipe['drizzle'], $cx, $cy, (int) ( $inner * 0.6 ) );
		}

		$this->vignette();
	}

	/**
	 * Table surface with a woven texture.
	 *
	 * @param array{0: string, 1: string} $table Colours.
	 * @return void
	 */
	private function table( array $table ): void {
		imagefilledrectangle( $this->img, 0, 0, $this->w, $this->h, $this->color( $table[0] ) );

		$texture = $this->color( $table[1], 100 );
		for ( $y = 0; $y < $this->h; $y += 6 ) {
			imageline( $this->img, 0, $y + $this->rand( 0, 2 ), $this->w, $y + $this->rand( 0, 2 ), $texture );
		}
		$speck = $this->color( '#ffffff', 118 );
		for ( $i = 0; $i < 3000; $i++ ) {
			imagesetpixel( $this->img, $this->rand( 0, $this->w ), $this->rand( 0, $this->h ), $speck );
		}
	}

	/**
	 * Napkin and a little dish of olives on the side.
	 *
	 * @param int $cx Plate centre X.
	 * @param int $r  Plate radius.
	 * @return void
	 */
	private function props( int $cx, int $r ): void {
		$left = $this->rand( 0, 1 ) === 1;

		// Folded linen napkin, partly off-canvas.
		$nx = $left ? (int) ( $cx - $r * 1.9 ) : (int) ( $cx + $r * 1.25 );
		$ny = (int) ( $this->h * 0.08 );
		$this->shadow( $nx + (int) ( $r * 0.35 ), $ny + (int) ( $r * 0.75 ), (int) ( $r * 0.45 ), (int) ( $r * 0.8 ) );
		imagefilledpolygon(
			$this->img,
			array( $nx, $ny, $nx + (int) ( $r * 0.7 ), $ny - (int) ( $r * 0.05 ), $nx + (int) ( $r * 0.75 ), $ny + (int) ( $r * 1.5 ), $nx + (int) ( $r * 0.05 ), $ny + (int) ( $r * 1.55 ) ),
			$this->color( '#f4eee2' )
		);
		imageline( $this->img, $nx + (int) ( $r * 0.38 ), $ny, $nx + (int) ( $r * 0.4 ), $ny + (int) ( $r * 1.52 ), $this->color( '#d8cfbd' ) );

		// Olive dish.
		$ox = $left ? (int) ( $cx + $r * 1.3 ) : (int) ( $cx - $r * 1.3 );
		$oy = (int) ( $this->h * 0.82 );
		$or = (int) ( $r * 0.28 );
		$this->shadow( $ox + 8, $oy + 10, $or, $or );
		imagefilledellipse( $this->img, $ox, $oy, $or * 2, $or * 2, $this->color( '#f7f3ea' ) );
		imagefilledellipse( $this->img, $ox, $oy, (int) ( $or * 1.6 ), (int) ( $or * 1.6 ), $this->color( '#ebe4d6' ) );
		for ( $i = 0; $i < 7; $i++ ) {
			$a = $this->float( 0, 2 * M_PI );
			$d = $this->float( 0, $or * 0.45 );
			$this->rotated_ellipse( (int) ( $ox + cos( $a ) * $d ), (int) ( $oy + sin( $a ) * $d ), (int) ( $or * 0.26 ), (int) ( $or * 0.18 ), $this->float( 0, M_PI ), $this->color( 1 === $i % 2 ? '#3d4a24' : '#6b7a33' ) );
		}
	}

	/**
	 * Draws the vessel and returns the usable inner radius.
	 *
	 * @param string $vessel plate | bowl | board | slate | platter.
	 * @param int    $cx     Centre X.
	 * @param int    $cy     Centre Y.
	 * @param int    $r      Radius.
	 * @return int
	 */
	private function vessel( string $vessel, int $cx, int $cy, int $r ): int {
		switch ( $vessel ) {
			case 'board':
			case 'slate':
				$wood = 'board' === $vessel ? array( '#b07a47', '#9c6a3c' ) : array( '#34373a', '#2b2d30' );
				$w    = (int) ( $r * 2.3 );
				$h    = (int) ( $r * 1.75 );
				$this->shadow( $cx + 14, $cy + 18, (int) ( $w / 2 ), (int) ( $h / 2 ) );
				$this->rounded_rect( $cx - (int) ( $w / 2 ), $cy - (int) ( $h / 2 ), $cx + (int) ( $w / 2 ), $cy + (int) ( $h / 2 ), (int) ( $r * 0.12 ), $this->color( $wood[0] ) );
				for ( $i = 0; $i < 14; $i++ ) {
					$y = $cy - (int) ( $h / 2 ) + $this->rand( 10, $h - 10 );
					imageline( $this->img, $cx - (int) ( $w / 2 ) + 20, $y, $cx + (int) ( $w / 2 ) - 20, $y + $this->rand( -6, 6 ), $this->color( $wood[1], 70 ) );
				}
				return (int) ( $r * 0.82 );

			case 'platter':
				$this->shadow( $cx + 14, $cy + 18, (int) ( $r * 1.25 ), (int) ( $r * 0.85 ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 2.5 ), (int) ( $r * 1.7 ), $this->color( '#f6f2ea' ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 2.15 ), (int) ( $r * 1.38 ), $this->color( '#efe9dd' ) );
				return (int) ( $r * 0.95 );

			case 'bowl':
				$this->shadow( $cx + 12, $cy + 16, (int) ( $r * 0.92 ), (int) ( $r * 0.92 ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 1.84 ), (int) ( $r * 1.84 ), $this->color( '#3f5b6b' ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 1.62 ), (int) ( $r * 1.62 ), $this->color( '#f3efe6' ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 1.5 ), (int) ( $r * 1.5 ), $this->color( '#e6e0d3' ) );
				return (int) ( $r * 0.72 );

			default:
				$this->shadow( $cx + 12, $cy + 16, $r, $r );
				imagefilledellipse( $this->img, $cx, $cy, $r * 2, $r * 2, $this->color( '#f8f5ee' ) );
				imageellipse( $this->img, $cx, $cy, (int) ( $r * 1.5 ), (int) ( $r * 1.5 ), $this->color( '#e2dccf' ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $r * 1.48 ), (int) ( $r * 1.48 ), $this->color( '#fbf9f4' ) );
				return (int) ( $r * 0.74 );
		}
	}

	/**
	 * Base layer (bread, purée, broth, pastry).
	 *
	 * @param array<int, string> $base  [type, colour, colour 2].
	 * @param int                $cx    Centre X.
	 * @param int                $cy    Centre Y.
	 * @param int                $inner Inner radius.
	 * @return void
	 */
	private function base( array $base, int $cx, int $cy, int $inner ): void {
		switch ( $base[0] ) {
			case 'fill':
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $inner * 1.9 ), (int) ( $inner * 1.9 ), $this->color( $base[1] ) );
				imagefilledellipse( $this->img, $cx - (int) ( $inner * 0.25 ), $cy - (int) ( $inner * 0.25 ), (int) ( $inner * 0.8 ), (int) ( $inner * 0.5 ), $this->color( '#ffffff', 112 ) );
				break;
			case 'flat':
				$this->blob( $cx, $cy, (int) ( $inner * 1.05 ), $this->color( $base[1] ), 0.06 );
				for ( $i = 0; $i < 26; $i++ ) {
					$a = $this->float( 0, 2 * M_PI );
					$d = $inner * $this->float( 0.2, 1.0 );
					$this->blob( (int) ( $cx + cos( $a ) * $d ), (int) ( $cy + sin( $a ) * $d ), $this->rand( 6, 22 ), $this->color( $base[2], 60 ), 0.3 );
				}
				break;
			case 'tart':
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $inner * 2.1 ), (int) ( $inner * 2.1 ), $this->color( $base[1] ) );
				imagefilledellipse( $this->img, $cx, $cy, (int) ( $inner * 1.85 ), (int) ( $inner * 1.85 ), $this->color( $base[2] ) );
				break;
			case 'smear':
				$this->blob( $cx - (int) ( $inner * 0.1 ), $cy + (int) ( $inner * 0.05 ), (int) ( $inner * 0.62 ), $this->color( $base[1] ), 0.25 );
				break;
		}
	}

	/**
	 * One food piece.
	 *
	 * @param string $shape Shape.
	 * @param string $hex   Colour.
	 * @param int    $x     X.
	 * @param int    $y     Y.
	 * @param int    $s     Size.
	 * @param int    $cx    Vessel centre X.
	 * @param int    $cy    Vessel centre Y.
	 * @param int    $inner Inner radius.
	 * @return void
	 */
	private function piece( string $shape, string $hex, int $x, int $y, int $s, int $cx, int $cy, int $inner ): void {
		$c     = $this->color( $hex );
		$dark  = $this->color( $this->shade( $hex, -0.25 ) );
		$light = $this->color( $this->shade( $hex, 0.25 ) );
		$angle = $this->float( 0, M_PI );

		switch ( $shape ) {
			case 'blob':
				$this->blob( $x + 4, $y + 6, $s, $this->color( '#000000', 112 ), 0.12 );
				$this->blob( $x, $y, $s, $c, 0.12 );
				$this->blob( $x - (int) ( $s * 0.25 ), $y - (int) ( $s * 0.25 ), (int) ( $s * 0.35 ), $this->color( '#ffffff', 100 ), 0.2 );
				break;
			case 'dot':
				imagefilledellipse( $this->img, $x, $y, $s * 2, $s * 2, $c );
				if ( $s > 6 ) {
					imagefilledellipse( $this->img, $x - (int) ( $s / 3 ), $y - (int) ( $s / 3 ), (int) ( $s * 0.6 ), (int) ( $s * 0.6 ), $this->color( '#ffffff', 95 ) );
				}
				break;
			case 'grain':
				$this->rotated_ellipse( $x, $y, $s * 2, $s, $angle, $c );
				break;
			case 'leaf':
				$this->rotated_ellipse( $x, $y, $s * 2, (int) ( $s * 0.8 ), $angle, $c );
				imageline( $this->img, (int) ( $x - cos( $angle ) * $s * 1.6 ), (int) ( $y - sin( $angle ) * $s * 1.6 ), (int) ( $x + cos( $angle ) * $s * 1.6 ), (int) ( $y + sin( $angle ) * $s * 1.6 ), $dark );
				break;
			case 'slice':
				imagefilledellipse( $this->img, $x + 4, $y + 6, $s * 2, $s * 2, $this->color( '#000000', 112 ) );
				imagefilledellipse( $this->img, $x, $y, $s * 2, $s * 2, $c );
				imagefilledellipse( $this->img, $x, $y, (int) ( $s * 1.55 ), (int) ( $s * 1.55 ), $light );
				for ( $i = 0; $i < 6; $i++ ) {
					$a = $i * M_PI / 3 + $angle;
					imagefilledellipse( $this->img, (int) ( $x + cos( $a ) * $s * 0.45 ), (int) ( $y + sin( $a ) * $s * 0.45 ), (int) ( $s * 0.3 ), (int) ( $s * 0.3 ), $c );
				}
				break;
			case 'petal':
				$this->rotated_ellipse( $x + 4, $y + 6, $s, (int) ( $s * 0.55 ), $angle, $this->color( '#000000', 114 ) );
				$this->rotated_ellipse( $x, $y, $s, (int) ( $s * 0.55 ), $angle, $c );
				$this->rotated_ellipse( $x, $y, (int) ( $s * 0.8 ), (int) ( $s * 0.12 ), $angle, $this->color( '#ffffff', 90 ) );
				break;
			case 'ring':
				imagesetthickness( $this->img, max( 2, (int) ( $s * 0.35 ) ) );
				imageellipse( $this->img, $x, $y, $s * 2, $s * 2, $c );
				imagesetthickness( $this->img, 1 );
				break;
			case 'stick':
				$this->rotated_rect( $x + 4, $y + 6, $s, (int) ( $s * 0.16 ), $angle, $this->color( '#000000', 112 ) );
				$this->rotated_rect( $x, $y, $s, (int) ( $s * 0.16 ), $angle, $c );
				break;
			case 'slab':
				$this->rotated_rect( $x + 10, $y + 14, (int) ( $s * 1.6 ), $s, $angle, $this->color( '#000000', 105 ) );
				$this->rotated_rect( $x, $y, (int) ( $s * 1.6 ), $s, $angle, $c );
				$this->rotated_rect( $x, $y, (int) ( $s * 1.45 ), (int) ( $s * 0.85 ), $angle, $light );
				// Grill marks.
				imagesetthickness( $this->img, max( 3, (int) ( $s * 0.07 ) ) );
				for ( $i = -3; $i <= 3; $i++ ) {
					$ox = (int) ( $x + cos( $angle ) * $i * $s * 0.22 );
					$oy = (int) ( $y + sin( $angle ) * $i * $s * 0.22 );
					$a2 = $angle + M_PI / 3;
					imageline( $this->img, (int) ( $ox - cos( $a2 ) * $s * 0.35 ), (int) ( $oy - sin( $a2 ) * $s * 0.35 ), (int) ( $ox + cos( $a2 ) * $s * 0.35 ), (int) ( $oy + sin( $a2 ) * $s * 0.35 ), $this->color( '#2a140b', 30 ) );
				}
				imagesetthickness( $this->img, 1 );
				break;
			case 'fish':
				$a = $this->float( -0.25, 0.25 );
				$this->rotated_ellipse( $cx + 12, $cy + 16, $s, (int) ( $s * 0.32 ), $a, $this->color( '#000000', 105 ) );
				$tail = array();
				foreach ( array( array( 0.9, 0 ), array( 1.25, -0.3 ), array( 1.25, 0.3 ) ) as $p ) {
					$tail[] = (int) ( $cx + cos( $a ) * $p[0] * $s - sin( $a ) * $p[1] * $s );
					$tail[] = (int) ( $cy + sin( $a ) * $p[0] * $s + cos( $a ) * $p[1] * $s );
				}
				imagefilledpolygon( $this->img, $tail, $dark );
				$this->rotated_ellipse( $cx, $cy, $s, (int) ( $s * 0.32 ), $a, $c );
				$this->rotated_ellipse( $cx, (int) ( $cy - $s * 0.08 ), (int) ( $s * 0.85 ), (int) ( $s * 0.12 ), $a, $light );
				for ( $i = -3; $i <= 3; $i++ ) {
					$px = (int) ( $cx + cos( $a ) * $i * $s * 0.2 );
					$py = (int) ( $cy + sin( $a ) * $i * $s * 0.2 );
					imageline( $this->img, $px - 10, $py - (int) ( $s * 0.2 ), $px + 10, $py + (int) ( $s * 0.2 ), $this->color( '#2a2a2a', 60 ) );
				}
				imagefilledellipse( $this->img, (int) ( $cx - cos( $a ) * $s * 0.72 ), (int) ( $cy - sin( $a ) * $s * 0.72 - $s * 0.05 ), (int) ( $s * 0.1 ), (int) ( $s * 0.1 ), $this->color( '#1d1d1d' ) );
				break;
			case 'tentacle':
				$this->curl( $cx + (int) ( cos( $angle * 2 ) * $s * 0.15 ), $cy + (int) ( sin( $angle * 2 ) * $s * 0.15 ), $s, $angle * 2, max( 6, (int) ( $s * 0.14 ) ), $c, $light );
				break;
			case 'prawn':
				$this->curl( $x, $y, $s, $angle * 2, max( 6, (int) ( $s * 0.3 ) ), $c, $this->color( '#f6b08a' ), 1.4 );
				break;
			case 'shell':
				$this->rotated_ellipse( $x, $y, $s, (int) ( $s * 0.5 ), $angle, $c );
				$this->rotated_ellipse( $x, $y, (int) ( $s * 0.75 ), (int) ( $s * 0.3 ), $angle, $this->color( '#f0a35c' ) );
				break;
			case 'swirl':
				imagesetthickness( $this->img, 8 );
				for ( $i = 1; $i <= 4; $i++ ) {
					imagearc( $this->img, $cx, $cy, (int) ( $inner * $i * 0.42 ), (int) ( $inner * $i * 0.42 ), $this->rand( 0, 90 ), $this->rand( 220, 330 ), $light );
				}
				imagesetthickness( $this->img, 1 );
				break;
			case 'noodles':
				imagesetthickness( $this->img, max( 4, (int) ( $inner * 0.035 ) ) );
				for ( $i = 0; $i < 26; $i++ ) {
					$rr = (int) ( $inner * $this->float( 0.2, 1.15 ) );
					$ox = $cx + $this->rand( - (int) ( $inner * 0.3 ), (int) ( $inner * 0.3 ) );
					$oy = $cy + $this->rand( - (int) ( $inner * 0.3 ), (int) ( $inner * 0.3 ) );
					imagearc( $this->img, $ox, $oy, $rr, $rr, $this->rand( 0, 359 ), $this->rand( 0, 359 ), 0 === $i % 3 ? $light : $c );
				}
				imagesetthickness( $this->img, 1 );
				break;
			case 'floret':
				$this->rotated_rect( $x + 10, $y + 14, (int) ( $s * 1.2 ), (int) ( $s * 1.4 ), $angle, $this->color( '#000000', 108 ) );
				for ( $i = 0; $i < 22; $i++ ) {
					$a = $this->float( 0, 2 * M_PI );
					$d = $s * $this->float( 0, 0.85 );
					$this->blob( (int) ( $x + cos( $a ) * $d ), (int) ( $y + sin( $a ) * $d ), (int) ( $s * $this->float( 0.18, 0.32 ) ), 0 === $i % 4 ? $this->color( '#a8682f' ) : $c, 0.2 );
				}
				break;
			case 'cross':
				imagesetthickness( $this->img, max( 3, (int) ( $s * 0.25 ) ) );
				imageline( $this->img, $x - $s, $y - $s, $x + $s, $y + $s, $c );
				imageline( $this->img, $x - $s, $y + $s, $x + $s, $y - $s, $c );
				imagesetthickness( $this->img, 1 );
				break;
			case 'wedge':
				// Centre the slice on (x, y) rather than its tip.
				$x     -= (int) ( cos( $angle ) * $s * 0.55 );
				$y     -= (int) ( sin( $angle ) * $s * 0.55 );
				$points = array( $x, $y );
				$from   = $angle - 0.5;
				for ( $t = 0; $t <= 12; $t++ ) {
					$a        = $from + 1.0 * $t / 12;
					$points[] = (int) ( $x + cos( $a ) * $s );
					$points[] = (int) ( $y + sin( $a ) * $s );
				}
				$shadow = array();
				foreach ( $points as $i => $value ) {
					$shadow[] = $value + ( 0 === $i % 2 ? 10 : 14 );
				}
				imagefilledpolygon( $this->img, $shadow, $this->color( '#000000', 105 ) );
				imagefilledpolygon( $this->img, $points, $c );
				imagesetthickness( $this->img, max( 6, (int) ( $s * 0.07 ) ) );
				imagearc( $this->img, $x, $y, $s * 2 - 6, $s * 2 - 6, (int) rad2deg( $from ), (int) rad2deg( $from + 1.0 ), $dark );
				imagesetthickness( $this->img, 1 );
				break;
			case 'fig':
				imagefilledellipse( $this->img, $x, $y, $s * 2, $s * 2, $c );
				imagefilledellipse( $this->img, $x, $y, (int) ( $s * 1.5 ), (int) ( $s * 1.5 ), $this->color( '#c2405a' ) );
				for ( $i = 0; $i < 10; $i++ ) {
					imagesetpixel( $this->img, $x + $this->rand( - (int) ( $s * 0.5 ), (int) ( $s * 0.5 ) ), $y + $this->rand( - (int) ( $s * 0.5 ), (int) ( $s * 0.5 ) ), $this->color( '#f3d9a0' ) );
				}
				break;
			case 'navette':
				$this->rotated_ellipse( $x + 6, $y + 8, $s, (int) ( $s * 0.38 ), $angle, $this->color( '#000000', 108 ) );
				$this->rotated_ellipse( $x, $y, $s, (int) ( $s * 0.38 ), $angle, $c );
				$this->rotated_ellipse( $x, $y, (int) ( $s * 0.7 ), (int) ( $s * 0.06 ), $angle, $dark );
				break;
			case 'cup':
				$this->shadow( $x + 10, $y + 12, (int) ( $s * 1.1 ), (int) ( $s * 1.1 ) );
				imagefilledellipse( $this->img, $x, $y, (int) ( $s * 2.2 ), (int) ( $s * 2.2 ), $this->color( '#f7f3ea' ) );
				imagefilledellipse( $this->img, $x, $y, (int) ( $s * 1.6 ), (int) ( $s * 1.6 ), $c );
				imagefilledellipse( $this->img, $x, $y, (int) ( $s * 1.2 ), (int) ( $s * 1.2 ), $this->color( '#a8743f' ) );
				break;
			case 'cube':
				$this->rotated_rect( $x + 6, $y + 8, $s, $s, $angle, $this->color( '#000000', 108 ) );
				$this->rotated_rect( $x, $y, $s, $s, $angle, $c );
				break;
		}
	}

	/**
	 * Olive oil or sauce drizzle.
	 *
	 * @param string $hex Colour.
	 * @param int    $cx  Centre X.
	 * @param int    $cy  Centre Y.
	 * @param int    $r   Radius.
	 * @return void
	 */
	private function drizzle( string $hex, int $cx, int $cy, int $r ): void {
		$c = $this->color( $hex, 25 );
		for ( $i = 0; $i < 18; $i++ ) {
			$a = $this->float( 0, 2 * M_PI );
			$d = $r * sqrt( $this->float( 0, 1 ) );
			$s = $this->rand( 4, 12 );
			imagefilledellipse( $this->img, (int) ( $cx + cos( $a ) * $d ), (int) ( $cy + sin( $a ) * $d ), $s, $s, $c );
		}
	}

	/**
	 * A glass of wine with its bottle, on a coloured backdrop.
	 *
	 * @param string $wine      Wine colour.
	 * @param bool   $sparkling Draw bubbles.
	 * @return void
	 */
	private function wine( string $wine, bool $sparkling ): void {
		$backdrops = array( '#e7dcc8', '#c7744f', '#5f6b4a', '#2f3b4c' );
		$bg        = $backdrops[ $this->rand( 0, count( $backdrops ) - 1 ) ];

		// Soft vertical gradient.
		for ( $y = 0; $y < $this->h; $y += 4 ) {
			imagefilledrectangle( $this->img, 0, $y, $this->w, $y + 4, $this->color( $this->shade( $bg, 0.12 - 0.3 * $y / $this->h ) ) );
		}
		// Table edge.
		$table = (int) ( $this->h * 0.78 );
		imagefilledrectangle( $this->img, 0, $table, $this->w, $this->h, $this->color( $this->shade( $bg, -0.35 ) ) );

		// Bottle.
		$bx = (int) ( $this->w * 0.36 );
		$bw = (int) ( $this->w * 0.11 );
		$bt = (int) ( $this->h * 0.2 );
		$this->rounded_rect( $bx - $bw, $bt + (int) ( $this->h * 0.18 ), $bx + $bw, $table + 6, (int) ( $bw * 0.5 ), $this->color( '#203024' ) );
		$this->rounded_rect( $bx - (int) ( $bw * 0.32 ), $bt - (int) ( $this->h * 0.05 ), $bx + (int) ( $bw * 0.32 ), $bt + (int) ( $this->h * 0.24 ), 8, $this->color( '#203024' ) );
		imagefilledrectangle( $this->img, $bx - (int) ( $bw * 0.36 ), $bt - (int) ( $this->h * 0.06 ), $bx + (int) ( $bw * 0.36 ), $bt, $this->color( '#8c2a1e' ) );
		$this->rounded_rect( $bx - (int) ( $bw * 0.75 ), $bt + (int) ( $this->h * 0.36 ), $bx + (int) ( $bw * 0.75 ), $bt + (int) ( $this->h * 0.5 ), 6, $this->color( '#f3ecdc' ) );
		imagefilledrectangle( $this->img, $bx - (int) ( $bw * 0.55 ), $bt + (int) ( $this->h * 0.42 ), $bx + (int) ( $bw * 0.55 ), $bt + (int) ( $this->h * 0.44 ), $this->color( $wine ) );
		imagefilledrectangle( $this->img, $bx - (int) ( $bw * 0.75 ), $bt + (int) ( $this->h * 0.2 ), $bx - (int) ( $bw * 0.55 ), $table - 20, $this->color( '#ffffff', 105 ) );

		// Glass.
		$gx    = (int) ( $this->w * 0.62 );
		$top   = (int) ( $this->h * 0.3 );
		$bowlw = (int) ( $this->w * 0.11 );
		$bowlh = (int) ( $this->h * 0.26 );
		$glass = $this->color( '#ffffff', 85 );
		$stemy = $top + $bowlh;

		$bowl = array();
		for ( $t = 0; $t <= 20; $t++ ) {
			$a      = M_PI * $t / 20;
			$bowl[] = (int) ( $gx - cos( $a ) * $bowlw );
			$bowl[] = (int) ( $top + sin( $a ) * $bowlh );
		}
		imagefilledpolygon( $this->img, $bowl, $glass );

		$level = (int) ( $top + $bowlh * 0.45 );
		$fill  = array();
		for ( $t = 0; $t <= 20; $t++ ) {
			$a = M_PI * $t / 20;
			$y = (int) ( $top + sin( $a ) * $bowlh );
			if ( $y >= $level ) {
				$fill[] = (int) ( $gx - cos( $a ) * $bowlw * 0.96 );
				$fill[] = $y;
			}
		}
		if ( count( $fill ) >= 6 ) {
			imagefilledpolygon( $this->img, $fill, $this->color( $wine, 10 ) );
		}
		imagefilledellipse( $this->img, $gx - (int) ( $bowlw * 0.5 ), $top + (int) ( $bowlh * 0.3 ), (int) ( $bowlw * 0.18 ), (int) ( $bowlh * 0.5 ), $this->color( '#ffffff', 80 ) );

		imagefilledrectangle( $this->img, $gx - 5, $stemy, $gx + 5, $table - 4, $glass );
		imagefilledellipse( $this->img, $gx, $table - 2, (int) ( $bowlw * 1.3 ), 22, $glass );

		if ( $sparkling ) {
			for ( $i = 0; $i < 26; $i++ ) {
				$s = $this->rand( 4, 9 );
				imageellipse( $this->img, $gx + $this->rand( - (int) ( $bowlw * 0.6 ), (int) ( $bowlw * 0.6 ) ), $this->rand( $level + 10, $stemy - 10 ), $s, $s, $this->color( '#ffffff', 40 ) );
			}
		}

		// A few olives on the table.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->rotated_ellipse( (int) ( $this->w * ( 0.8 + 0.04 * $i ) ), $table + 40 + $this->rand( 0, 30 ), 30, 22, $this->float( 0, M_PI ), $this->color( 0 === $i % 2 ? '#3d4a24' : '#6b7a33' ) );
		}

		$this->vignette();
	}

	/**
	 * Darkened corners.
	 *
	 * @return void
	 */
	private function vignette(): void {
		for ( $i = 0; $i < 14; $i++ ) {
			imagesetthickness( $this->img, 40 );
			imageellipse( $this->img, (int) ( $this->w / 2 ), (int) ( $this->h / 2 ), (int) ( $this->w * ( 1.25 + $i * 0.05 ) ), (int) ( $this->h * ( 1.3 + $i * 0.06 ) ), $this->color( '#000000', 122 ) );
		}
		imagesetthickness( $this->img, 1 );
	}

	/**
	 * Soft drop shadow.
	 *
	 * @param int $x  Centre X.
	 * @param int $y  Centre Y.
	 * @param int $rx Radius X.
	 * @param int $ry Radius Y.
	 * @return void
	 */
	private function shadow( int $x, int $y, int $rx, int $ry ): void {
		for ( $i = 6; $i >= 0; $i-- ) {
			imagefilledellipse( $this->img, $x, $y, ( $rx + $i * 6 ) * 2, ( $ry + $i * 6 ) * 2, $this->color( '#000000', 121 ) );
		}
	}

	/**
	 * Irregular round shape.
	 *
	 * @param int   $x     Centre X.
	 * @param int   $y     Centre Y.
	 * @param int   $r     Radius.
	 * @param int   $color Colour.
	 * @param float $wobble Radius variation (0-1).
	 * @return void
	 */
	private function blob( int $x, int $y, int $r, int $color, float $wobble ): void {
		$points = array();
		$phase  = $this->float( 0, 2 * M_PI );
		$lobes  = $this->rand( 2, 5 );
		for ( $t = 0; $t < 24; $t++ ) {
			$a        = 2 * M_PI * $t / 24;
			$rr       = $r * ( 1 + $wobble * sin( $lobes * $a + $phase ) );
			$points[] = (int) ( $x + cos( $a ) * $rr );
			$points[] = (int) ( $y + sin( $a ) * $rr );
		}
		imagefilledpolygon( $this->img, $points, $color );
	}

	/**
	 * Curled shape (tentacle, prawn): a thick spiral arc.
	 *
	 * @param int   $x      Start X.
	 * @param int   $y      Start Y.
	 * @param int   $length Length.
	 * @param float $angle  Direction.
	 * @param int   $width  Thickness.
	 * @param int   $color  Colour.
	 * @param int   $accent Accent colour.
	 * @param float $turns  Curl amount.
	 * @return void
	 */
	private function curl( int $x, int $y, int $length, float $angle, int $width, int $color, int $accent, float $turns = 2.2 ): void {
		$steps = 40;
		$px    = (float) $x;
		$py    = (float) $y;
		for ( $i = 0; $i <= $steps; $i++ ) {
			$t     = $i / $steps;
			$a     = $angle + $turns * $t * $t * M_PI;
			$px   += cos( $a ) * $length / $steps;
			$py   += sin( $a ) * $length / $steps;
			$thick = (int) max( 3, $width * ( 1 - 0.75 * $t ) );
			imagefilledellipse( $this->img, (int) $px + 4, (int) $py + 6, $thick, $thick, $this->color( '#000000', 118 ) );
			imagefilledellipse( $this->img, (int) $px, (int) $py, $thick, $thick, $color );
			if ( 0 === $i % 4 ) {
				imagefilledellipse( $this->img, (int) $px, (int) $py, (int) ( $thick * 0.35 ), (int) ( $thick * 0.35 ), $accent );
			}
		}
	}

	/**
	 * Filled rotated ellipse.
	 *
	 * @param int   $x     Centre X.
	 * @param int   $y     Centre Y.
	 * @param int   $a     Semi-axis A.
	 * @param int   $b     Semi-axis B.
	 * @param float $theta Rotation.
	 * @param int   $color Colour.
	 * @return void
	 */
	private function rotated_ellipse( int $x, int $y, int $a, int $b, float $theta, int $color ): void {
		$points = array();
		for ( $t = 0; $t < 24; $t++ ) {
			$u        = 2 * M_PI * $t / 24;
			$points[] = (int) ( $x + $a * cos( $u ) * cos( $theta ) - $b * sin( $u ) * sin( $theta ) );
			$points[] = (int) ( $y + $a * cos( $u ) * sin( $theta ) + $b * sin( $u ) * cos( $theta ) );
		}
		imagefilledpolygon( $this->img, $points, $color );
	}

	/**
	 * Filled rotated rectangle with rounded ends.
	 *
	 * @param int   $x     Centre X.
	 * @param int   $y     Centre Y.
	 * @param int   $len   Half length.
	 * @param int   $wid   Half width.
	 * @param float $theta Rotation.
	 * @param int   $color Colour.
	 * @return void
	 */
	private function rotated_rect( int $x, int $y, int $len, int $wid, float $theta, int $color ): void {
		$points = array();
		$corner = min( $len, $wid ) * 0.45;
		foreach ( array( array( 1, 1 ), array( -1, 1 ), array( -1, -1 ), array( 1, -1 ) ) as $quadrant ) {
			for ( $t = 0; $t <= 4; $t++ ) {
				$a  = ( $quadrant[0] > 0 ? ( $quadrant[1] > 0 ? 0 : 1.5 * M_PI ) : ( $quadrant[1] > 0 ? 0.5 * M_PI : M_PI ) ) + 0.5 * M_PI * $t / 4;
				$px = $quadrant[0] * ( $len - $corner ) + cos( $a ) * $corner;
				$py = $quadrant[1] * ( $wid - $corner ) + sin( $a ) * $corner;

				$points[] = (int) ( $x + $px * cos( $theta ) - $py * sin( $theta ) );
				$points[] = (int) ( $y + $px * sin( $theta ) + $py * cos( $theta ) );
			}
		}
		imagefilledpolygon( $this->img, $points, $color );
	}

	/**
	 * Axis-aligned rounded rectangle.
	 *
	 * @param int $x1    Left.
	 * @param int $y1    Top.
	 * @param int $x2    Right.
	 * @param int $y2    Bottom.
	 * @param int $r     Corner radius.
	 * @param int $color Colour.
	 * @return void
	 */
	private function rounded_rect( int $x1, int $y1, int $x2, int $y2, int $r, int $color ): void {
		imagefilledrectangle( $this->img, $x1 + $r, $y1, $x2 - $r, $y2, $color );
		imagefilledrectangle( $this->img, $x1, $y1 + $r, $x2, $y2 - $r, $color );
		foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $c ) {
			imagefilledellipse( $this->img, $c[0], $c[1], $r * 2, $r * 2, $color );
		}
	}

	/**
	 * Allocates a colour.
	 *
	 * @param string $hex   #rrggbb.
	 * @param int    $alpha 0 (opaque) to 127 (transparent).
	 * @return int
	 */
	private function color( string $hex, int $alpha = 0 ): int {
		$hex = ltrim( $hex, '#' );

		return (int) imagecolorallocatealpha( $this->img, (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ), $alpha );
	}

	/**
	 * Lightens (positive) or darkens (negative) a colour.
	 *
	 * @param string $hex    #rrggbb.
	 * @param float  $amount -1 to 1.
	 * @return string
	 */
	private function shade( string $hex, float $amount ): string {
		$hex = ltrim( $hex, '#' );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$v    = hexdec( substr( $hex, $i * 2, 2 ) );
			$v    = $amount >= 0 ? $v + ( 255 - $v ) * $amount : $v * ( 1 + $amount );
			$out .= str_pad( dechex( (int) max( 0, min( 255, round( $v ) ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Seeded integer in [min, max] (xorshift).
	 *
	 * @param int $min Min.
	 * @param int $max Max.
	 * @return int
	 */
	private function rand( int $min, int $max ): int {
		$this->state ^= ( $this->state << 13 ) & 0xFFFFFFFF;
		$this->state ^= $this->state >> 17;
		$this->state ^= ( $this->state << 5 ) & 0xFFFFFFFF;
		$this->state &= 0xFFFFFFFF;

		return $min + ( $this->state % ( $max - $min + 1 ) );
	}

	/**
	 * Seeded float in [min, max).
	 *
	 * @param float $min Min.
	 * @param float $max Max.
	 * @return float
	 */
	private function float( float $min, float $max ): float {
		return $min + ( $max - $min ) * ( $this->rand( 0, 1000000 ) / 1000001 );
	}
}
