<?php
/**
 * Plugin bootstrap.
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class MCD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MCD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Global settings page.
	 *
	 * @var MCD_Settings
	 */
	public $settings;

	/**
	 * Menu item fields (Appearance → Menus).
	 *
	 * @var MCD_Menu_Item_Fields
	 */
	public $menu_fields;

	/**
	 * Front-end output.
	 *
	 * @var MCD_Frontend
	 */
	public $frontend;

	/**
	 * Elementor integration, or null when Elementor is not active.
	 *
	 * @var MCD_Elementor|null
	 */
	public $elementor = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return MCD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the components and hooks.
	 */
	private function __construct() {
		$this->settings    = new MCD_Settings();
		$this->menu_fields = new MCD_Menu_Item_Fields();
		$this->frontend    = new MCD_Frontend();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_load_elementor' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( MCD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'multi-column-dropdowns', false, dirname( plugin_basename( MCD_FILE ) ) . '/languages' );
	}

	/**
	 * Loads the Elementor integration only when Elementor is active.
	 */
	public function maybe_load_elementor() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		require_once MCD_PATH . 'includes/class-mcd-elementor.php';
		$this->elementor = new MCD_Elementor();
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( current_user_can( 'edit_theme_options' ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . MCD_Settings::PAGE ) ),
				esc_html__( 'Settings', 'multi-column-dropdowns' )
			);
		}
		return $links;
	}
}
