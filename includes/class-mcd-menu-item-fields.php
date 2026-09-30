<?php
/**
 * Per-item fields on Appearance → Menus.
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders, saves and reads the menu item settings.
 */
final class MCD_Menu_Item_Fields {

	const META_KEY = '_mcd_settings';
	const COLUMN   = 'mcd';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render' ), 10, 5 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save' ), 10, 3 );
		add_filter( 'manage_nav-menus_columns', array( $this, 'add_screen_option' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Reads the validated settings of one menu item. Null values mean "inherit".
	 *
	 * @param int $item_id Menu item ID.
	 * @return array{mode:string,columns:?int,max_items:?int,col_gap:?int,row_gap:?int,width:string|int|null}
	 */
	public static function get( $item_id ) {
		$raw = get_post_meta( (int) $item_id, self::META_KEY, true );
		return self::sanitize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Validates raw values.
	 *
	 * @param mixed $raw Raw values.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw  = is_array( $raw ) ? $raw : array();
		$mode = isset( $raw['mode'] ) && is_string( $raw['mode'] ) ? sanitize_key( $raw['mode'] ) : 'inherit';

		return array(
			'mode'      => in_array( $mode, array( 'inherit', 'on', 'off' ), true ) ? $mode : 'inherit',
			'columns'   => MCD_Layout::sanitize_int( $raw['columns'] ?? null, MCD_Layout::MIN_COLUMNS, MCD_Layout::MAX_COLUMNS ),
			'max_items' => MCD_Layout::sanitize_int( $raw['max_items'] ?? null, 0, MCD_Layout::MAX_ITEMS ),
			'col_gap'   => MCD_Layout::sanitize_int( $raw['col_gap'] ?? null, 0, MCD_Layout::MAX_GAP ),
			'row_gap'   => MCD_Layout::sanitize_int( $raw['row_gap'] ?? null, 0, MCD_Layout::MAX_GAP ),
			'width'     => MCD_Layout::sanitize_width( $raw['width'] ?? null ),
		);
	}

	/**
	 * Adds a Screen Options checkbox so the fields can be hidden like core's optional fields.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_screen_option( $columns ) {
		if ( is_array( $columns ) ) {
			$columns[ self::COLUMN ] = __( 'Multi-column dropdown', 'multi-column-dropdowns' );
		}
		return $columns;
	}

	/**
	 * Loads the admin stylesheet on the Menus screen and the settings page.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'nav-menus.php' !== $hook_suffix && 'settings_page_' . MCD_Settings::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'mcd-admin', MCD_URL . 'assets/css/mcd-admin.css', array(), MCD_VERSION );
	}

	/**
	 * Renders the fields inside a menu item's settings panel.
	 *
	 * @param int      $item_id           Menu item ID.
	 * @param WP_Post  $menu_item         Menu item object.
	 * @param int      $depth             Depth.
	 * @param stdClass $args              Walker arguments.
	 * @param int      $current_object_id Current object ID.
	 */
	public function render( $item_id, $menu_item, $depth, $args, $current_object_id = 0 ) {
		$item_id = (int) $item_id;
		$values  = self::get( $item_id );
		$global  = MCD_Settings::get();
		$name    = 'mcd[' . $item_id . ']';

		$hidden = '';
		if ( function_exists( 'get_hidden_columns' ) && in_array( self::COLUMN, (array) get_hidden_columns( 'nav-menus' ), true ) ) {
			$hidden = ' hidden-field';
		}

		$global_state = $global['enable_all']
			? __( 'on', 'multi-column-dropdowns' )
			: __( 'off', 'multi-column-dropdowns' );
		?>
		<fieldset class="field-<?php echo esc_attr( self::COLUMN ); ?> mcd-fields description description-wide<?php echo esc_attr( $hidden ); ?>">
			<legend><?php esc_html_e( 'Multi-column dropdown', 'multi-column-dropdowns' ); ?></legend>

			<p class="mcd-field mcd-field-mode">
				<label for="mcd-mode-<?php echo esc_attr( $item_id ); ?>">
					<?php esc_html_e( 'Layout', 'multi-column-dropdowns' ); ?><br>
					<select id="mcd-mode-<?php echo esc_attr( $item_id ); ?>" name="<?php echo esc_attr( $name ); ?>[mode]">
						<option value="inherit" <?php selected( $values['mode'], 'inherit' ); ?>>
							<?php
							/* translators: %s: "on" or "off". */
							echo esc_html( sprintf( __( 'Use global default (%s)', 'multi-column-dropdowns' ), $global_state ) );
							?>
						</option>
						<option value="on" <?php selected( $values['mode'], 'on' ); ?>><?php esc_html_e( 'Multi-column', 'multi-column-dropdowns' ); ?></option>
						<option value="off" <?php selected( $values['mode'], 'off' ); ?>><?php esc_html_e( 'Normal dropdown', 'multi-column-dropdowns' ); ?></option>
					</select>
				</label>
			</p>

			<div class="mcd-grid">
				<?php
				$this->number_field( $item_id, 'columns', __( 'Columns (1–6)', 'multi-column-dropdowns' ), $values['columns'], $global['columns'], MCD_Layout::MIN_COLUMNS, MCD_Layout::MAX_COLUMNS );
				$this->number_field( $item_id, 'max_items', __( 'Max items per column', 'multi-column-dropdowns' ), $values['max_items'], $global['max_items'], 0, MCD_Layout::MAX_ITEMS );
				$this->number_field( $item_id, 'col_gap', __( 'Column gap (px)', 'multi-column-dropdowns' ), $values['col_gap'], $global['col_gap'], 0, MCD_Layout::MAX_GAP );
				$this->number_field( $item_id, 'row_gap', __( 'Row gap (px)', 'multi-column-dropdowns' ), $values['row_gap'], $global['row_gap'], 0, MCD_Layout::MAX_GAP );
				?>
				<p class="mcd-field">
					<label for="mcd-width-<?php echo esc_attr( $item_id ); ?>">
						<?php esc_html_e( 'Width ("auto" or px)', 'multi-column-dropdowns' ); ?><br>
						<input type="text" id="mcd-width-<?php echo esc_attr( $item_id ); ?>" name="<?php echo esc_attr( $name ); ?>[width]" value="<?php echo esc_attr( null === $values['width'] ? '' : (string) $values['width'] ); ?>" placeholder="<?php echo esc_attr( (string) $global['width'] ); ?>">
					</label>
				</p>
			</div>

			<p class="description mcd-help">
				<?php esc_html_e( 'Leave a field empty to use the global default (shown as the placeholder). Max items: 0 = no limit.', 'multi-column-dropdowns' ); ?>
				<?php echo esc_html( MCD_Layout::rule_help() ); ?>
				<?php esc_html_e( 'Applies to top-level items that have a dropdown. Elementor widget settings override these values.', 'multi-column-dropdowns' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Renders one number input.
	 *
	 * @param int      $item_id     Menu item ID.
	 * @param string   $key         Setting key.
	 * @param string   $label       Label.
	 * @param int|null $value       Saved value or null.
	 * @param int      $placeholder Global default.
	 * @param int      $min         Minimum.
	 * @param int      $max         Maximum.
	 */
	private function number_field( $item_id, $key, $label, $value, $placeholder, $min, $max ) {
		$id = 'mcd-' . str_replace( '_', '-', $key ) . '-' . $item_id;
		printf(
			'<p class="mcd-field"><label for="%1$s">%2$s<br><input type="number" id="%1$s" name="mcd[%3$d][%4$s]" value="%5$s" placeholder="%6$s" min="%7$d" max="%8$d" step="1"></label></p>',
			esc_attr( $id ),
			esc_html( $label ),
			(int) $item_id,
			esc_attr( $key ),
			esc_attr( null === $value ? '' : (string) $value ),
			esc_attr( (string) $placeholder ),
			(int) $min,
			(int) $max
		);
	}

	/**
	 * Saves the fields when a menu item is saved from Appearance → Menus.
	 *
	 * Runs only when our fields were posted for this item, so saves from the Customizer,
	 * the REST API or importers (which never send them) leave the stored meta untouched.
	 *
	 * @param int   $menu_id         Menu ID.
	 * @param int   $menu_item_db_id Menu item ID.
	 * @param array $args            Menu item data.
	 */
	public function save( $menu_id, $menu_item_db_id, $args = array() ) {
		$menu_item_db_id = (int) $menu_item_db_id;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only; the nonce is verified below before anything is read.
		if ( ! isset( $_POST['mcd'] ) || ! is_array( $_POST['mcd'] ) || ! isset( $_POST['mcd'][ $menu_item_db_id ] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// Reuse the nonce core prints on the Menus screen.
		$nonce = isset( $_POST['update-nav-menu-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'update-nav_menu' ) ) {
			return;
		}

		// Every value is validated by self::sanitize().
		$raw   = wp_unslash( $_POST['mcd'][ $menu_item_db_id ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean = self::sanitize( $raw );

		$stored = array_filter(
			$clean,
			static function ( $value ) {
				return null !== $value;
			}
		);

		if ( array( 'mode' => 'inherit' ) === $stored ) {
			delete_post_meta( $menu_item_db_id, self::META_KEY );
			return;
		}

		update_post_meta( $menu_item_db_id, self::META_KEY, $stored );
	}
}
