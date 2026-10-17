<?php
/**
 * Plugin bootstrap.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component of the plugin.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns (and boots on first call) the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Registers the hooks of every component.
	 *
	 * @return void
	 */
	private function boot(): void {
		load_plugin_textdomain( 'oe-reservations', false, dirname( plugin_basename( OE_RESERVATIONS_FILE ) ) . '/languages' );

		foreach ( $this->components() as $component ) {
			$component->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli\Command::register();
		}
	}

	/**
	 * Components with a `register_hooks()` method.
	 *
	 * @return array<int, object>
	 */
	private function components(): array {
		$components = array(
			new Reservations\Installer(),
			new Menu\PostTypes(),
			new Rest\AvailabilityController(),
			new Rest\ReservationsController(),
			new Reservations\Cancellation(),
			new Blocks\Blocks(),
			new Frontend\JsonLd(),
			new Frontend\DemoBanner(),
			new Frontend\StaticBuild(),
		);

		if ( is_admin() ) {
			$components[] = new Menu\Admin();
			$components[] = new Admin\AdminMenu();
			$components[] = new Admin\ReservationsPage();
			$components[] = new Settings\SettingsPage();
		}

		return $components;
	}

	/**
	 * Activation: schema, content types and rewrite rules.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Reservations\Installer::install();
		Menu\PostTypes::register();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: rewrite rules only. Data is kept (removed by uninstall.php).
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
