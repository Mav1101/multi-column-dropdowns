<?php
/**
 * Front-end output: classes, CSS custom properties and assets.
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves settings for each rendered menu and prints them as CSS custom properties on the parent <li>.
 */
final class MCD_Frontend {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_filter( 'wp_nav_menu_objects', array( $this, 'prepare_menu' ), 20, 2 );
		add_filter( 'nav_menu_css_class', array( $this, 'item_classes' ), 20, 4 );
		add_filter( 'nav_menu_item_attributes', array( $this, 'item_attributes' ), 20, 4 );
		add_filter( 'wp_nav_menu', array( $this, 'fallback_markup' ), 20, 2 );
		add_filter( 'widget_nav_menu_args', array( $this, 'widget_args' ), 20, 4 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the static stylesheet and the small viewport-fitting script.
	 * wp_enqueue_scripts runs on the front end and inside the Elementor preview iframe, never in wp-admin.
	 */
	public function enqueue() {
		$settings = MCD_Settings::get();

		wp_enqueue_style( 'mcd-frontend', MCD_URL . 'assets/css/mcd-frontend.css', array(), MCD_VERSION );

		/**
		 * Filters the small global CSS block added after the stylesheet.
		 * The Elementor integration appends the per-breakpoint variable resolution here.
		 *
		 * @param string $css CSS.
		 */
		$css = ':root{--mcd-edge-gutter:' . absint( $settings['edge_gutter'] ) . 'px}' . apply_filters( 'mcd_responsive_css', '' );
		wp_add_inline_style( 'mcd-frontend', $css );

		wp_enqueue_script(
			'mcd-frontend',
			MCD_URL . 'assets/js/mcd-frontend.js',
			array(),
			MCD_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Adds a body class when CSS :hover/:focus-within detection is turned off.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( 'scripts' === MCD_Settings::get()['open_detection'] ) {
			$classes[] = 'mcd-scripts-only';
		}
		return $classes;
	}

	/**
	 * Marks menus rendered by the WordPress Navigation Menu widget (also when placed with Elementor).
	 *
	 * @param array   $nav_menu_args wp_nav_menu() arguments.
	 * @param WP_Term $nav_menu      Menu.
	 * @param array   $args          Widget display arguments.
	 * @param array   $instance      Widget settings.
	 * @return array
	 */
	public function widget_args( $nav_menu_args, $nav_menu, $args, $instance ) {
		$class = 'static' === MCD_Settings::get()['widget_mode'] ? 'mcd-static-menu' : 'mcd-dropdown-menu';

		$nav_menu_args['menu_class'] = trim( ( isset( $nav_menu_args['menu_class'] ) ? $nav_menu_args['menu_class'] : 'menu' ) . ' ' . $class );
		return $nav_menu_args;
	}

	/**
	 * Resolves the layout of every top-level dropdown of the menu about to be rendered.
	 * Results are stored on the $args object, which core passes to every walker filter.
	 *
	 * @param WP_Post[] $items Sorted menu items.
	 * @param stdClass  $args  wp_nav_menu() arguments.
	 * @return WP_Post[]
	 */
	public function prepare_menu( $items, $args ) {
		if ( ! is_object( $args ) || ! is_array( $items ) ) {
			return $items;
		}

		$args->mcd_items = array();

		if ( ( isset( $args->depth ) && 1 === (int) $args->depth ) || $this->is_skipped( $args ) ) {
			return $items;
		}

		$context = $this->elementor_context();
		if ( is_array( $context ) && 'disable' === $context['mode'] ) {
			return $items;
		}

		$counts = array();
		foreach ( $items as $item ) {
			$parent = (int) $item->menu_item_parent;
			if ( $parent ) {
				$counts[ $parent ] = isset( $counts[ $parent ] ) ? $counts[ $parent ] + 1 : 1;
			}
		}

		foreach ( $items as $item ) {
			$id = (int) $item->ID;
			if ( 0 !== (int) $item->menu_item_parent || empty( $counts[ $id ] ) ) {
				continue;
			}
			$config = $this->resolve( $item, $counts[ $id ], $context, $args );
			if ( $config ) {
				$args->mcd_items[ $id ] = $config;
			}
		}

		return $items;
	}

	/**
	 * Adds the marker class to enabled parent items.
	 *
	 * @param string[] $classes Classes.
	 * @param WP_Post  $item    Menu item.
	 * @param stdClass $args    Arguments.
	 * @param int      $depth   Depth.
	 * @return string[]
	 */
	public function item_classes( $classes, $item, $args = null, $depth = 0 ) {
		if ( 0 === (int) $depth && is_object( $args ) && isset( $args->mcd_items[ (int) $item->ID ] ) && is_array( $classes ) ) {
			$classes[] = 'mcd-enabled';
		}
		return $classes;
	}

	/**
	 * Prints the CSS custom properties on the parent <li>.
	 *
	 * @param array    $atts  <li> attributes.
	 * @param WP_Post  $item  Menu item.
	 * @param stdClass $args  Arguments.
	 * @param int      $depth Depth.
	 * @return array
	 */
	public function item_attributes( $atts, $item, $args = null, $depth = 0 ) {
		$id = (int) $item->ID;
		if ( 0 !== (int) $depth || ! is_object( $args ) || ! isset( $args->mcd_items[ $id ] ) ) {
			return $atts;
		}

		$style         = $args->mcd_items[ $id ]['style'];
		$atts['style'] = empty( $atts['style'] ) ? $style : rtrim( (string) $atts['style'], '; ' ) . ';' . $style;

		$args->mcd_items[ $id ]['printed'] = true;
		return $atts;
	}

	/**
	 * Fallback for theme walkers that were written before WordPress 6.3 and never call
	 * nav_menu_item_attributes (or nav_menu_css_class): adds the class and custom properties
	 * to those <li> elements with the HTML API.
	 *
	 * @param string   $nav_menu Menu HTML.
	 * @param stdClass $args     Arguments.
	 * @return string
	 */
	public function fallback_markup( $nav_menu, $args ) {
		if ( ! is_object( $args ) || empty( $args->mcd_items ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $nav_menu;
		}

		$pending = array();
		foreach ( $args->mcd_items as $id => $config ) {
			if ( empty( $config['printed'] ) ) {
				$pending[ 'menu-item-' . $id ] = $config['style'];
			}
		}
		if ( ! $pending ) {
			return $nav_menu;
		}

		$processor = new WP_HTML_Tag_Processor( $nav_menu );
		while ( $pending && $processor->next_tag( 'li' ) ) {
			$classes = preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $classes as $class ) {
				if ( ! isset( $pending[ $class ] ) ) {
					continue;
				}
				$existing = (string) $processor->get_attribute( 'style' );
				$processor->set_attribute( 'style', ( '' === $existing ? '' : rtrim( $existing, '; ' ) . ';' ) . $pending[ $class ] );
				$processor->add_class( 'mcd-enabled' );
				unset( $pending[ $class ] );
				break;
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Menus that should never get the layout.
	 *
	 * @param stdClass $args Arguments.
	 * @return bool
	 */
	private function is_skipped( $args ) {
		// Elementor Pro renders a second copy of the menu (menu-2-{id}) for its mobile toggle.
		$skip = ! empty( $args->menu_id ) && 0 === strpos( (string) $args->menu_id, 'menu-2-' );

		/**
		 * Filters whether a menu is skipped entirely.
		 *
		 * @param bool     $skip Whether to skip.
		 * @param stdClass $args wp_nav_menu() arguments.
		 */
		return (bool) apply_filters( 'mcd_skip_menu', $skip, $args );
	}

	/**
	 * Settings of the Elementor widget currently being rendered, if any.
	 *
	 * @return array|null
	 */
	private function elementor_context() {
		$elementor = MCD_Plugin::instance()->elementor;
		return $elementor ? $elementor->current_context() : null;
	}

	/**
	 * Resolves one parent item: Elementor widget → menu item → global defaults.
	 *
	 * @param WP_Post    $item    Parent menu item.
	 * @param int        $count   Number of direct children.
	 * @param array|null $context Elementor context.
	 * @param stdClass   $args    wp_nav_menu() arguments.
	 * @return array|null Config with a "style" string, or null when disabled.
	 */
	private function resolve( $item, $count, $context, $args ) {
		$global = MCD_Settings::get();
		$meta   = MCD_Menu_Item_Fields::get( $item->ID );
		$custom = ( is_array( $context ) && 'custom' === $context['mode'] ) ? $context : null;

		$menu_enabled = 'on' === $meta['mode'] || ( 'inherit' === $meta['mode'] && ! empty( $global['enable_all'] ) );

		if ( $custom && 'all' === $custom['apply_to'] ) {
			$enabled = true;
		} elseif ( $custom && 'selected' === $custom['apply_to'] ) {
			$enabled = in_array( (int) $item->ID, $custom['items'], true );
		} else {
			$enabled = $menu_enabled;
		}

		if ( ! $enabled ) {
			return null;
		}

		$settings = array();
		foreach ( array( 'columns', 'max_items', 'col_gap', 'row_gap', 'width' ) as $key ) {
			if ( $custom && isset( $custom['base'][ $key ] ) ) {
				$settings[ $key ] = $custom['base'][ $key ];
			} elseif ( null !== $meta[ $key ] ) {
				$settings[ $key ] = $meta[ $key ];
			} else {
				$settings[ $key ] = $global[ $key ];
			}
		}

		/**
		 * Filters the resolved settings of one dropdown. Return null to disable it.
		 *
		 * @param array    $settings columns, max_items, col_gap, row_gap, width.
		 * @param WP_Post  $item     Parent menu item.
		 * @param stdClass $args     wp_nav_menu() arguments.
		 */
		$settings = apply_filters( 'mcd_item_settings', $settings, $item, $args );
		if ( ! is_array( $settings ) ) {
			return null;
		}

		$layout = MCD_Layout::compute( $count, $settings['columns'], $settings['max_items'] );
		$vars   = array(
			'--mcd-cols'    => $layout['columns'],
			'--mcd-rows'    => $layout['rows'],
			'--mcd-col-gap' => absint( $settings['col_gap'] ) . 'px',
			'--mcd-row-gap' => absint( $settings['row_gap'] ) . 'px',
			'--mcd-width'   => MCD_Layout::width_css( $settings['width'] ),
		);

		// Per-device values from Elementor responsive controls. Unset devices inherit through the CSS var() fallback chain.
		$devices = $custom ? $custom['devices'] : array();
		foreach ( $devices as $device => $values ) {
			$suffix = '-' . sanitize_key( $device );
			if ( isset( $values['columns'] ) ) {
				$device_layout                  = MCD_Layout::compute( $count, $values['columns'], $settings['max_items'] );
				$vars[ '--mcd-cols' . $suffix ] = $device_layout['columns'];
				$vars[ '--mcd-rows' . $suffix ] = $device_layout['rows'];
			}
			if ( isset( $values['col_gap'] ) ) {
				$vars[ '--mcd-col-gap' . $suffix ] = absint( $values['col_gap'] ) . 'px';
			}
			if ( isset( $values['row_gap'] ) ) {
				$vars[ '--mcd-row-gap' . $suffix ] = absint( $values['row_gap'] ) . 'px';
			}
			if ( isset( $values['width'] ) ) {
				$vars[ '--mcd-width' . $suffix ] = MCD_Layout::width_css( $values['width'] );
			}
		}

		$style = array();
		foreach ( $vars as $property => $value ) {
			$style[] = $property . ':' . $value;
		}

		return array(
			'style'   => implode( ';', $style ),
			'printed' => false,
		);
	}
}
