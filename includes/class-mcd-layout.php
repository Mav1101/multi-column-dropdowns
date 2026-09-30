<?php
/**
 * Shared layout rules and value sanitizers.
 *
 * @package MultiColumnDropdowns
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers used by the admin screens, the Elementor integration and the front end,
 * so every settings source is validated and interpreted the same way.
 */
final class MCD_Layout {

	const MIN_COLUMNS = 1;
	const MAX_COLUMNS = 6;
	const MAX_ITEMS   = 100;
	const MAX_GAP     = 200;
	const MIN_WIDTH   = 100;
	const MAX_WIDTH   = 3000;

	/**
	 * Clamps a numeric value to an integer range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int|null Null for empty or non-numeric input, which means "inherit".
	 */
	public static function sanitize_int( $value, $min, $max ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return (int) max( $min, min( $max, round( (float) $value ) ) );
	}

	/**
	 * Validates a dropdown width: "auto" or a number of pixels.
	 *
	 * @param mixed $value Raw value, e.g. "auto", "640" or "640px".
	 * @return string|int|null "auto", an integer pixel width, or null (empty/invalid = inherit).
	 */
	public static function sanitize_width( $value ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return null;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( 'auto' === $value ) {
			return 'auto';
		}
		if ( preg_match( '/^(\d{1,5})\s*(?:px)?$/', $value, $matches ) ) {
			return (int) max( self::MIN_WIDTH, min( self::MAX_WIDTH, (int) $matches[1] ) );
		}
		return null;
	}

	/**
	 * Works out the grid for one dropdown.
	 *
	 * Rule (also shown in the UI, see rule_help()):
	 * - Items always flow top to bottom, then into the next column.
	 * - No max: rows = ceil( items / columns ), so items are spread evenly.
	 * - Max N:  rows = N, and "columns" becomes an upper limit. If items > columns × N,
	 *           rows grow to ceil( items / columns ) so nothing is hidden.
	 * - Rows never exceed the item count, and the columns actually used are ceil( items / rows ).
	 *
	 * @param int $count     Number of direct children in the dropdown.
	 * @param int $columns   Configured number of columns (1–6).
	 * @param int $max_items Max items per column, 0 for no limit.
	 * @return array{rows:int,columns:int}
	 */
	public static function compute( $count, $columns, $max_items ) {
		$count     = max( 1, (int) $count );
		$columns   = max( self::MIN_COLUMNS, min( self::MAX_COLUMNS, (int) $columns ) );
		$max_items = max( 0, (int) $max_items );

		$rows = (int) ceil( $count / $columns );
		if ( $max_items > 0 ) {
			$rows = max( $rows, $max_items );
		}
		$rows = max( 1, min( $rows, $count ) );

		return array(
			'rows'    => $rows,
			'columns' => (int) ceil( $count / $rows ),
		);
	}

	/**
	 * Converts a width setting to a CSS value.
	 *
	 * @param string|int|null $width "auto" or pixels.
	 * @return string
	 */
	public static function width_css( $width ) {
		if ( null === $width || 'auto' === $width ) {
			// An absolutely positioned dropdown would otherwise shrink to its narrow parent <li>.
			return 'max-content';
		}
		return absint( $width ) . 'px';
	}

	/**
	 * Help text that explains how "columns" and "max items per column" interact.
	 *
	 * @return string
	 */
	public static function rule_help() {
		return __( 'Items fill each column from top to bottom, then continue in the next column. Without a maximum, items are split evenly over the chosen number of columns. With a maximum of N, a new column starts after every N items and "Number of columns" becomes an upper limit, so fewer columns may be used. If the items do not fit in columns × N, the columns grow taller than N so no item is hidden.', 'multi-column-dropdowns' );
	}
}
