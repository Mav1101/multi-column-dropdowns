<?php
/**
 * Plugin Name:       Multi-Column Dropdowns
 * Description:       Turns long navigation menu dropdowns into multi-column layouts. Works with the OceanWP header menu, the WordPress Navigation Menu widget and the Elementor Pro Nav Menu widget.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       multi-column-dropdowns
 * Domain Path:       /languages
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

define( 'MCD_VERSION', '1.0.0' );
define( 'MCD_FILE', __FILE__ );
define( 'MCD_PATH', plugin_dir_path( __FILE__ ) );
define( 'MCD_URL', plugin_dir_url( __FILE__ ) );

require_once MCD_PATH . 'includes/class-mcd-layout.php';
require_once MCD_PATH . 'includes/class-mcd-settings.php';
require_once MCD_PATH . 'includes/class-mcd-menu-item-fields.php';
require_once MCD_PATH . 'includes/class-mcd-frontend.php';
require_once MCD_PATH . 'includes/class-mcd-plugin.php';

MCD_Plugin::instance();
