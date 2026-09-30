<?php
/**
 * Elementor integration. Loaded only when Elementor is active.
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

/**
 * Adds a "Multi-Column Dropdown" section to the WordPress Navigation Menu widget and the
 * Elementor Pro Nav Menu widget, and exposes the settings of the widget being rendered.
 */
final class MCD_Elementor {

	/**
	 * Widgets that receive the controls.
	 */
	const WIDGETS = array( 'nav-menu', 'wp-widget-nav_menu' );

	/**
	 * Widget names that already have the section.
	 *
	 * @var array
	 */
	private $injected = array();

	/**
	 * Contexts of widgets currently rendering (a stack, in case of nesting).
	 *
	 * @var array
	 */
	private $stack = array();

	/**
	 * Cached active breakpoints.
	 *
	 * @var array|null
	 */
	private static $devices = null;

	/**
	 * Cached parent item options.
	 *
	 * @var array|null
	 */
	private static $item_options = null;

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'elementor/element/after_section_end', array( $this, 'inject_controls' ), 10, 3 );
		add_action( 'elementor/widget/before_render_content', array( $this, 'push_context' ) );
		add_filter( 'elementor/widget/render_content', array( $this, 'pop_context' ), 10, 2 );
		add_filter( 'mcd_responsive_css', array( $this, 'responsive_css' ) );
	}

	/**
	 * Adds our section after the first section of each supported widget.
	 * The generic hook is used because the section ids differ between the two widgets.
	 *
	 * @param \Elementor\Controls_Stack $element    Element.
	 * @param string                    $section_id Section that just ended.
	 * @param array                     $args       Section arguments.
	 */
	public function inject_controls( $element, $section_id, $args ) {
		if ( ! $element instanceof \Elementor\Widget_Base ) {
			return;
		}
		$name = $element->get_name();
		if ( ! in_array( $name, self::WIDGETS, true ) || isset( $this->injected[ $name ] ) ) {
			return;
		}
		$this->injected[ $name ] = true;
		$this->register_controls( $element );
	}

	/**
	 * Registers the controls.
	 *
	 * @param \Elementor\Widget_Base $element Widget.
	 */
	private function register_controls( $element ) {
		$custom = array( 'mcd_mode' => 'custom' );

		$element->start_controls_section(
			'mcd_section',
			array(
				'label' => esc_html__( 'Multi-Column Dropdown', 'multi-column-dropdowns' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$element->add_control(
			'mcd_mode',
			array(
				'label'   => esc_html__( 'Layout', 'multi-column-dropdowns' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''        => esc_html__( 'Use menu settings', 'multi-column-dropdowns' ),
					'custom'  => esc_html__( 'Customize in this widget', 'multi-column-dropdowns' ),
					'disable' => esc_html__( 'Disable in this widget', 'multi-column-dropdowns' ),
				),
			)
		);

		$element->add_control(
			'mcd_priority_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Priority: this widget → menu item (Appearance → Menus) → global defaults (Settings → Multi-Column Dropdowns). Empty fields inherit the next level. The mobile toggle menu always stays single-column.', 'multi-column-dropdowns' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$element->add_control(
			'mcd_apply_to',
			array(
				'label'     => esc_html__( 'Apply to', 'multi-column-dropdowns' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'all',
				'options'   => array(
					'all'      => esc_html__( 'All top-level dropdowns', 'multi-column-dropdowns' ),
					'selected' => esc_html__( 'Selected parent items', 'multi-column-dropdowns' ),
					'menu'     => esc_html__( 'Items enabled in the menu settings', 'multi-column-dropdowns' ),
				),
				'condition' => $custom,
			)
		);

		$element->add_control(
			'mcd_items',
			array(
				'label'       => esc_html__( 'Parent items', 'multi-column-dropdowns' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::item_options(),
				'description' => esc_html__( 'Top-level items that have a dropdown, from all menus.', 'multi-column-dropdowns' ),
				'condition'   => array(
					'mcd_mode'     => 'custom',
					'mcd_apply_to' => 'selected',
				),
			)
		);

		$element->add_responsive_control(
			'mcd_columns',
			array(
				'label'       => esc_html__( 'Number of columns', 'multi-column-dropdowns' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => MCD_Layout::MIN_COLUMNS,
				'max'         => MCD_Layout::MAX_COLUMNS,
				'step'        => 1,
				'description' => esc_html__( '1–6. Empty = inherit.', 'multi-column-dropdowns' ),
				'condition'   => $custom,
			)
		);

		$element->add_control(
			'mcd_max_items',
			array(
				'label'       => esc_html__( 'Max items per column', 'multi-column-dropdowns' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => MCD_Layout::MAX_ITEMS,
				'step'        => 1,
				'description' => esc_html__( '0 = no limit. Empty = inherit.', 'multi-column-dropdowns' ) . ' ' . esc_html( MCD_Layout::rule_help() ),
				'condition'   => $custom,
			)
		);

		foreach ( array(
			'mcd_col_gap' => esc_html__( 'Column gap', 'multi-column-dropdowns' ),
			'mcd_row_gap' => esc_html__( 'Row gap', 'multi-column-dropdowns' ),
		) as $control_id => $label ) {
			$element->add_responsive_control(
				$control_id,
				array(
					'label'      => $label,
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => MCD_Layout::MAX_GAP,
						),
					),
					'condition'  => $custom,
				)
			);
		}

		$element->add_responsive_control(
			'mcd_width',
			array(
				'label'       => esc_html__( 'Dropdown width', 'multi-column-dropdowns' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'auto',
				'description' => esc_html__( '"auto" or a number of pixels (100–3000). Empty = inherit.', 'multi-column-dropdowns' ),
				'condition'   => $custom,
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Remembers the settings of a supported widget while it renders.
	 *
	 * @param \Elementor\Widget_Base $widget Widget.
	 */
	public function push_context( $widget ) {
		if ( ! $widget instanceof \Elementor\Widget_Base || ! in_array( $widget->get_name(), self::WIDGETS, true ) ) {
			return;
		}
		$this->stack[] = array(
			'id'      => $widget->get_id(),
			'context' => $this->build_context( $widget ),
		);
	}

	/**
	 * Forgets the widget after it rendered. The content is returned unchanged.
	 *
	 * @param string                 $content Widget HTML.
	 * @param \Elementor\Widget_Base $widget  Widget.
	 * @return string
	 */
	public function pop_context( $content, $widget ) {
		if ( $this->stack && is_object( $widget ) ) {
			$top = end( $this->stack );
			if ( $top['id'] === $widget->get_id() ) {
				array_pop( $this->stack );
			}
		}
		return $content;
	}

	/**
	 * Context of the widget currently rendering, or null.
	 *
	 * @return array|null
	 */
	public function current_context() {
		if ( ! $this->stack ) {
			return null;
		}
		$top = $this->stack[ count( $this->stack ) - 1 ];
		return $top['context'];
	}

	/**
	 * Reads and validates the widget settings.
	 *
	 * @param \Elementor\Widget_Base $widget Widget.
	 * @return array|null Null means "use menu settings".
	 */
	private function build_context( $widget ) {
		$settings = $widget->get_settings_for_display();
		$mode     = isset( $settings['mcd_mode'] ) && is_string( $settings['mcd_mode'] ) ? sanitize_key( $settings['mcd_mode'] ) : '';

		// An Elementor Pro Nav Menu set to the "Dropdown" layout only has the toggle menu, which stays single-column.
		if ( 'nav-menu' === $widget->get_name() && isset( $settings['layout'] ) && 'dropdown' === $settings['layout'] ) {
			$mode = 'disable';
		}

		if ( 'disable' === $mode ) {
			return array( 'mode' => 'disable' );
		}
		if ( 'custom' !== $mode ) {
			return null;
		}

		$apply_to = isset( $settings['mcd_apply_to'] ) && in_array( $settings['mcd_apply_to'], array( 'all', 'selected', 'menu' ), true )
			? $settings['mcd_apply_to']
			: 'all';

		$items = isset( $settings['mcd_items'] ) ? array_values( array_filter( array_map( 'absint', (array) $settings['mcd_items'] ) ) ) : array();

		$devices = array();
		foreach ( array_keys( self::devices() ) as $device ) {
			$values = array_filter(
				$this->read_values( $settings, '_' . $device ),
				static function ( $value ) {
					return null !== $value;
				}
			);
			if ( $values ) {
				$devices[ $device ] = $values;
			}
		}

		$base              = $this->read_values( $settings, '' );
		$base['max_items'] = MCD_Layout::sanitize_int( $settings['mcd_max_items'] ?? null, 0, MCD_Layout::MAX_ITEMS );

		return array(
			'mode'     => 'custom',
			'apply_to' => $apply_to,
			'items'    => $items,
			'base'     => $base,
			'devices'  => $devices,
		);
	}

	/**
	 * Reads the responsive values for one device.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $suffix   '' for desktop, '_tablet', '_mobile', ...
	 * @return array
	 */
	private function read_values( $settings, $suffix ) {
		return array(
			'columns' => MCD_Layout::sanitize_int( $settings[ 'mcd_columns' . $suffix ] ?? null, MCD_Layout::MIN_COLUMNS, MCD_Layout::MAX_COLUMNS ),
			'col_gap' => self::slider( $settings[ 'mcd_col_gap' . $suffix ] ?? null ),
			'row_gap' => self::slider( $settings[ 'mcd_row_gap' . $suffix ] ?? null ),
			'width'   => MCD_Layout::sanitize_width( $settings[ 'mcd_width' . $suffix ] ?? null ),
		);
	}

	/**
	 * Reads a slider value.
	 *
	 * @param mixed $value Slider value array.
	 * @return int|null
	 */
	private static function slider( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['size'] ) ) {
			return null;
		}
		return MCD_Layout::sanitize_int( $value['size'], 0, MCD_Layout::MAX_GAP );
	}

	/**
	 * Active Elementor breakpoints (desktop is the base and is not listed).
	 *
	 * @return array<string,array{value:int,direction:string}>
	 */
	public static function devices() {
		if ( null !== self::$devices ) {
			return self::$devices;
		}

		$devices = array();
		try {
			$plugin = \Elementor\Plugin::$instance;
			if ( $plugin && isset( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
				foreach ( $plugin->breakpoints->get_active_breakpoints() as $name => $breakpoint ) {
					$devices[ sanitize_key( $name ) ] = array(
						'value'     => (int) $breakpoint->get_value(),
						'direction' => 'min' === $breakpoint->get_direction() ? 'min' : 'max',
					);
				}
			}
		} catch ( \Throwable $e ) {
			$devices = array();
		}

		if ( ! $devices ) {
			$devices = array(
				'mobile' => array(
					'value'     => 767,
					'direction' => 'max',
				),
				'tablet' => array(
					'value'     => 1024,
					'direction' => 'max',
				),
			);
		}

		self::$devices = $devices;
		return $devices;
	}

	/**
	 * Builds the global CSS that picks the right per-device custom property,
	 * using the site's actual Elementor breakpoints. Output is a few lines, not per item.
	 *
	 * @param string $css Existing CSS.
	 * @return string
	 */
	public function responsive_css( $css ) {
		$max = array();
		$min = array();
		foreach ( self::devices() as $name => $device ) {
			if ( 'min' === $device['direction'] ) {
				$min[ $name ] = $device['value'];
			} else {
				$max[ $name ] = $device['value'];
			}
		}
		arsort( $max, SORT_NUMERIC );

		$selector = '.mcd-enabled>.sub-menu';
		$chain    = array();
		foreach ( $max as $name => $px ) {
			// Nearest device first, then every larger one: mobile → tablet → desktop.
			array_unshift( $chain, $name );
			$css .= '@media (max-width:' . absint( $px ) . 'px){' . $selector . '{' . self::declarations( $chain ) . '}}';
		}
		foreach ( $min as $name => $px ) {
			$css .= '@media (min-width:' . absint( $px ) . 'px){' . $selector . '{' . self::declarations( array( $name ) ) . '}}';
		}

		return $css;
	}

	/**
	 * Declarations for one breakpoint.
	 *
	 * @param string[] $chain Devices, nearest first.
	 * @return string
	 */
	private static function declarations( $chain ) {
		$defaults = array(
			'cols'    => '2',
			'rows'    => '1',
			'col-gap' => '24px',
			'row-gap' => '0px',
			'width'   => 'max-content',
		);

		$out = '';
		foreach ( $defaults as $property => $default ) {
			$value = 'var(--mcd-' . $property . ',' . $default . ')';
			foreach ( array_reverse( $chain ) as $device ) {
				$value = 'var(--mcd-' . $property . '-' . $device . ',' . $value . ')';
			}
			$out .= '--_mcd-' . $property . ':' . $value . ';';
		}
		return $out;
	}

	/**
	 * Options for the "Parent items" control: top-level items with children, from every menu.
	 * Only built in wp-admin (the editor); the front end does not need the labels.
	 *
	 * @return array<string,string>
	 */
	private static function item_options() {
		if ( null !== self::$item_options ) {
			return self::$item_options;
		}
		self::$item_options = array();

		if ( ! is_admin() ) {
			return self::$item_options;
		}

		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( empty( $items ) ) {
				continue;
			}

			$has_children = array();
			foreach ( $items as $item ) {
				if ( (int) $item->menu_item_parent ) {
					$has_children[ (int) $item->menu_item_parent ] = true;
				}
			}

			foreach ( $items as $item ) {
				if ( 0 === (int) $item->menu_item_parent && isset( $has_children[ (int) $item->ID ] ) ) {
					self::$item_options[ (string) $item->ID ] = sprintf( '%1$s — %2$s', $menu->name, wp_strip_all_tags( $item->title ) );
				}
			}
		}

		return self::$item_options;
	}
}
