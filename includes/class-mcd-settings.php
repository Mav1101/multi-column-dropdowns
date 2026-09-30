<?php
/**
 * Global defaults page (Settings → Multi-Column Dropdowns).
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the global settings.
 */
final class MCD_Settings {

	const OPTION = 'mcd_settings';
	const GROUP  = 'mcd_settings_group';
	const PAGE   = 'mcd-settings';

	/**
	 * Normalized settings for the current request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		// options.php checks manage_options by default; this plugin uses edit_theme_options like the Menus screen.
		add_filter( 'option_page_capability_' . self::GROUP, array( $this, 'capability' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_all'     => 0,
			'columns'        => 2,
			'max_items'      => 0,
			'col_gap'        => 24,
			'row_gap'        => 0,
			'width'          => 'auto',
			'widget_mode'    => 'static',
			'open_detection' => 'css',
			'edge_gutter'    => 16,
		);
	}

	/**
	 * Returns the saved, validated settings.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = self::normalize( is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/**
	 * Clears the request cache after the option changes.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Capability required to save the settings.
	 *
	 * @return string
	 */
	public function capability() {
		return 'edit_theme_options';
	}

	/**
	 * Adds the settings page.
	 */
	public function add_page() {
		add_options_page(
			__( 'Multi-Column Dropdowns', 'multi-column-dropdowns' ),
			__( 'Multi-Column Dropdowns', 'multi-column-dropdowns' ),
			'edit_theme_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the setting, sections and fields.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'mcd_layout', __( 'Default layout', 'multi-column-dropdowns' ), array( $this, 'render_layout_intro' ), self::PAGE );
		add_settings_section( 'mcd_behaviour', __( 'Behaviour', 'multi-column-dropdowns' ), '__return_false', self::PAGE );

		$this->add_field(
			'enable_all',
			__( 'Enable by default', 'multi-column-dropdowns' ),
			'checkbox',
			'mcd_layout',
			array( 'label' => __( 'Use the multi-column layout for every top-level dropdown, unless a menu item or an Elementor widget turns it off.', 'multi-column-dropdowns' ) )
		);
		$this->add_field(
			'columns',
			__( 'Number of columns', 'multi-column-dropdowns' ),
			'number',
			'mcd_layout',
			array(
				'min' => MCD_Layout::MIN_COLUMNS,
				'max' => MCD_Layout::MAX_COLUMNS,
			)
		);
		$this->add_field(
			'max_items',
			__( 'Max items per column', 'multi-column-dropdowns' ),
			'number',
			'mcd_layout',
			array(
				'min'         => 0,
				'max'         => MCD_Layout::MAX_ITEMS,
				'description' => __( '0 = no limit.', 'multi-column-dropdowns' ) . ' ' . MCD_Layout::rule_help(),
			)
		);
		$this->add_field(
			'col_gap',
			__( 'Column gap', 'multi-column-dropdowns' ),
			'number',
			'mcd_layout',
			array(
				'min'    => 0,
				'max'    => MCD_Layout::MAX_GAP,
				'suffix' => 'px',
			)
		);
		$this->add_field(
			'row_gap',
			__( 'Row gap', 'multi-column-dropdowns' ),
			'number',
			'mcd_layout',
			array(
				'min'    => 0,
				'max'    => MCD_Layout::MAX_GAP,
				'suffix' => 'px',
			)
		);
		$this->add_field(
			'width',
			__( 'Dropdown width', 'multi-column-dropdowns' ),
			'text',
			'mcd_layout',
			array( 'description' => __( '"auto" (fits the content) or a number of pixels between 100 and 3000, for example 640.', 'multi-column-dropdowns' ) )
		);

		$this->add_field(
			'widget_mode',
			__( 'Navigation Menu widget', 'multi-column-dropdowns' ),
			'select',
			'mcd_behaviour',
			array(
				'options'     => array(
					'static'   => __( 'Static list: sub-menus are always visible', 'multi-column-dropdowns' ),
					'dropdown' => __( 'Dropdown: sub-menus open on hover/click', 'multi-column-dropdowns' ),
				),
				'description' => __( 'How the WordPress "Navigation Menu" widget shows sub-menus on your site. Most themes, including OceanWP, show them as a static nested list. Choose "Dropdown" only if you styled the widget as a dropdown menu.', 'multi-column-dropdowns' ),
			)
		);
		$this->add_field(
			'open_detection',
			__( 'Open-state detection', 'multi-column-dropdowns' ),
			'select',
			'mcd_behaviour',
			array(
				'options'     => array(
					'css'     => __( 'Theme scripts and CSS :hover / :focus-within (recommended)', 'multi-column-dropdowns' ),
					'scripts' => __( 'Theme scripts only', 'multi-column-dropdowns' ),
				),
				'description' => __( 'Choose "Theme scripts only" if a dropdown appears a moment before the theme\'s own fade-in animation starts.', 'multi-column-dropdowns' ),
			)
		);
		$this->add_field(
			'edge_gutter',
			__( 'Screen edge margin', 'multi-column-dropdowns' ),
			'number',
			'mcd_behaviour',
			array(
				'min'         => 0,
				'max'         => 100,
				'suffix'      => 'px',
				'description' => __( 'Minimum space kept between a dropdown and the left or right edge of the screen.', 'multi-column-dropdowns' ),
			)
		);
	}

	/**
	 * Helper for add_settings_field().
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Field label.
	 * @param string $type    checkbox|number|text|select.
	 * @param string $section Section id.
	 * @param array  $extra   Extra render arguments.
	 */
	private function add_field( $key, $label, $type, $section, $extra = array() ) {
		add_settings_field(
			'mcd_' . $key,
			$label,
			array( $this, 'render_field' ),
			self::PAGE,
			$section,
			array_merge(
				array(
					'key'       => $key,
					'type'      => $type,
					'label_for' => 'mcd_' . $key,
				),
				$extra
			)
		);
	}

	/**
	 * Validates the submitted settings.
	 *
	 * @param mixed $input Submitted value (already unslashed by options.php).
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( isset( $input['width'] ) && '' !== trim( (string) $input['width'] ) && null === MCD_Layout::sanitize_width( $input['width'] ) ) {
			add_settings_error(
				self::OPTION,
				'mcd_width',
				__( 'Dropdown width must be "auto" or a number of pixels. "auto" was saved instead.', 'multi-column-dropdowns' )
			);
		}

		return self::normalize( $input );
	}

	/**
	 * Builds a complete, valid settings array from raw input.
	 *
	 * @param array $input Raw values.
	 * @return array
	 */
	private static function normalize( array $input ) {
		$defaults = self::defaults();

		$pick = static function ( $value, $fallback ) {
			return null === $value ? $fallback : $value;
		};

		return array(
			'enable_all'     => empty( $input['enable_all'] ) ? 0 : 1,
			'columns'        => $pick( MCD_Layout::sanitize_int( $input['columns'] ?? null, MCD_Layout::MIN_COLUMNS, MCD_Layout::MAX_COLUMNS ), $defaults['columns'] ),
			'max_items'      => $pick( MCD_Layout::sanitize_int( $input['max_items'] ?? null, 0, MCD_Layout::MAX_ITEMS ), $defaults['max_items'] ),
			'col_gap'        => $pick( MCD_Layout::sanitize_int( $input['col_gap'] ?? null, 0, MCD_Layout::MAX_GAP ), $defaults['col_gap'] ),
			'row_gap'        => $pick( MCD_Layout::sanitize_int( $input['row_gap'] ?? null, 0, MCD_Layout::MAX_GAP ), $defaults['row_gap'] ),
			'width'          => $pick( MCD_Layout::sanitize_width( $input['width'] ?? null ), $defaults['width'] ),
			'widget_mode'    => ( isset( $input['widget_mode'] ) && 'dropdown' === $input['widget_mode'] ) ? 'dropdown' : 'static',
			'open_detection' => ( isset( $input['open_detection'] ) && 'scripts' === $input['open_detection'] ) ? 'scripts' : 'css',
			'edge_gutter'    => $pick( MCD_Layout::sanitize_int( $input['edge_gutter'] ?? null, 0, 100 ), $defaults['edge_gutter'] ),
		);
	}

	/**
	 * Intro text for the layout section.
	 */
	public function render_layout_intro() {
		echo '<p>' . esc_html__( 'These defaults apply to every top-level menu item that has a dropdown. Individual menu items (Appearance → Menus) and Elementor widgets can override them.', 'multi-column-dropdowns' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Priority:', 'multi-column-dropdowns' ) . '</strong> ' . esc_html__( 'Elementor widget settings → menu item settings → these global defaults. Empty fields inherit the next level.', 'multi-column-dropdowns' ) . '</p>';
	}

	/**
	 * Renders one field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field( $args ) {
		$values = self::get();
		$key    = $args['key'];
		$id     = 'mcd_' . $key;
		$name   = self::OPTION . '[' . $key . ']';
		$value  = $values[ $key ];

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html( $args['label'] )
				);
				break;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="1">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					(int) $args['min'],
					(int) $args['max']
				);
				if ( ! empty( $args['suffix'] ) ) {
					echo ' ' . esc_html( $args['suffix'] );
				}
				break;

			case 'text':
				printf(
					'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		?>
		<div class="wrap mcd-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
