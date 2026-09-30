=== Multi-Column Dropdowns ===
Tags: menu, dropdown, columns, elementor, oceanwp
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns long menu dropdowns into multi-column layouts. Works with the OceanWP header menu, the WordPress Navigation Menu widget and the Elementor Pro Nav Menu widget.

== Description ==

* Per menu item settings in Appearance → Menus.
* Global defaults in Settings → Multi-Column Dropdowns.
* A "Multi-Column Dropdown" section in the Elementor "Navigation Menu" (WordPress) widget and the Elementor Pro "Nav Menu" widget, with responsive columns, gaps and width.
* Priority: Elementor widget → menu item → global defaults. Empty fields inherit.
* Mobile menus stay single-column (OceanWP #mobile-dropdown, #sidr, #mobile-fullscreen and the Elementor toggle menu).
* Wide dropdowns near the edge of the screen are shifted back into view, and third-level flyouts open to the other side when needed.
* No jQuery on the front end.

= How "Number of columns" and "Max items per column" work together =

Items fill each column from top to bottom, then continue in the next column.

* No max: items are split evenly over the chosen number of columns (rows = items ÷ columns, rounded up).
* Max N: a new column starts after every N items, and "Number of columns" becomes an upper limit, so fewer columns may be used.
* If the items do not fit in columns × N, the columns grow taller than N so no item is hidden.

Example: 10 items, 3 columns, no max → 4 + 4 + 2. 10 items, 3 columns, max 3 → 4 + 4 + 2 (3 × 3 = 9 is not enough, so columns grow to 4). 10 items, 6 columns, max 5 → 5 + 5.

= How the layout is applied =

Themes and SmartMenus show and hide dropdowns by switching between `display: none` and `display: block`. The plugin never changes the display of a closed dropdown. It switches to `display: grid` only in states that exist while the theme is showing the dropdown:

* an inline `display: block` written by the theme script (Elementor SmartMenus, Superfish, OceanWP),
* the `sfHover` class, and CSS `:hover` / `:focus-within` for stylesheet-driven menus (can be turned off in the settings),
* always, for static Navigation Menu widget lists.

Settings are printed as CSS custom properties (`--mcd-cols`, `--mcd-rows`, `--mcd-col-gap`, `--mcd-row-gap`, `--mcd-width`) on the parent `<li>` and read by one static stylesheet.

= Developer filters =

* `mcd_item_settings` ( array $settings, WP_Post $item, stdClass $args ) – change or disable (return null) one dropdown.
* `mcd_skip_menu` ( bool $skip, stdClass $args ) – skip a whole menu.
* `mcd_responsive_css` ( string $css ) – the small global CSS block added after the stylesheet.

== Installation ==

1. Zip the `multi-column-dropdowns` folder (the zip must contain the folder itself).
2. In WordPress go to Plugins → Add New Plugin → Upload Plugin, choose the zip and click Install Now.
3. Activate the plugin.
4. Set defaults in Settings → Multi-Column Dropdowns, then enable items in Appearance → Menus or in an Elementor menu widget.

== Frequently Asked Questions ==

= Can I use it together with the OceanWP mega menu option? =

Use one or the other on the same item. Both change the layout of the same dropdown.

= The dropdown appears a moment before the theme's fade-in starts =

Set Settings → Multi-Column Dropdowns → Open-state detection to "Theme scripts only".

== Changelog ==

= 1.0.0 =
* First release.
