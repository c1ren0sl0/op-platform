<?php
/**
 * Navigation builder.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Derives navigation structure from the page tree and registers WordPress menus.
 */
class OP_Navigation {

	/**
	 * Transient key for cached navigation.
	 */
	private const CACHE_KEY = 'op_navigation';

	/**
	 * Cache TTL in seconds.
	 */
	private const CACHE_TTL = 3600;

	/**
	 * WordPress menu name.
	 */
	private const MENU_NAME = 'Operational Platform';

	/**
	 * Menu location slug.
	 */
	public const MENU_LOCATION = 'op-platform-primary';

	/**
	 * Page tree instance.
	 *
	 * @var OP_Page_Tree
	 */
	private OP_Page_Tree $page_tree;

	/**
	 * Navigation items.
	 *
	 * @var array
	 */
	private array $items = [];

	/**
	 * Whether navigation is built.
	 *
	 * @var bool
	 */
	private bool $built = false;

	/**
	 * Constructor.
	 *
	 * @param OP_Page_Tree $page_tree Page tree instance.
	 */
	public function __construct( OP_Page_Tree $page_tree ) {
		$this->page_tree = $page_tree;

		// Register menu location.
		add_action( 'after_setup_theme', [ $this, 'register_menu_location' ] );
	}

	/**
	 * Register nav menu location.
	 */
	public function register_menu_location(): void {
		register_nav_menus(
			[
				self::MENU_LOCATION => __( 'Operational Platform Primary', 'op-platform' ),
			]
		);
	}

	/**
	 * Build navigation from page tree.
	 *
	 * @param bool $force Force rebuild ignoring cache.
	 * @return bool True on success.
	 */
	public function build( bool $force = false ): bool {
		// Ensure page tree is built.
		if ( ! $this->page_tree->is_built() ) {
			if ( ! $this->page_tree->build() ) {
				return false;
			}
		}

		// Try cache first.
		if ( ! $force ) {
			$cached = $this->load_from_cache();
			if ( $cached ) {
				return true;
			}
		}

		// Build navigation from roots.
		$this->items = [];
		foreach ( $this->page_tree->get_roots() as $root_route ) {
			$item = $this->build_nav_item( $root_route );
			if ( $item ) {
				$this->items[] = $item;
			}
		}

		$this->built = true;
		$this->save_to_cache();

		// Sync WordPress menu.
		$this->sync_wordpress_menu();

		return true;
	}

	/**
	 * Sync navigation to WordPress menu.
	 */
	private function sync_wordpress_menu(): void {
		// Get or create menu.
		$menu_id = $this->get_or_create_menu();
		if ( ! $menu_id ) {
			return;
		}

		// Clear existing menu items.
		$this->clear_menu_items( $menu_id );

		// Add items from navigation structure.
		$this->add_menu_items( $menu_id, $this->items );

		// Assign menu to location.
		$locations = get_theme_mod( 'nav_menu_locations', [] );
		$locations[ self::MENU_LOCATION ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Get or create the Operational Platform menu.
	 *
	 * @return int|false Menu ID or false on failure.
	 */
	private function get_or_create_menu() {
		// Check if menu exists.
		$menu = wp_get_nav_menu_object( self::MENU_NAME );
		if ( $menu ) {
			return $menu->term_id;
		}

		// Create menu.
		$menu_id = wp_create_nav_menu( self::MENU_NAME );
		if ( is_wp_error( $menu_id ) ) {
			return false;
		}

		return $menu_id;
	}

	/**
	 * Clear all items from a menu.
	 *
	 * @param int $menu_id Menu ID.
	 */
	private function clear_menu_items( int $menu_id ): void {
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( $menu_items ) {
			foreach ( $menu_items as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}
	}

	/**
	 * Add navigation items to WordPress menu.
	 *
	 * @param int   $menu_id   Menu ID.
	 * @param array $items     Navigation items.
	 * @param int   $parent_id Parent menu item ID.
	 */
	private function add_menu_items( int $menu_id, array $items, int $parent_id = 0 ): void {
		$position = 0;
		foreach ( $items as $item ) {
			$position++;

			$menu_item_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				[
					'menu-item-title'     => $item['title'],
					'menu-item-url'       => $item['url'],
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $parent_id,
					'menu-item-position'  => $position,
					'menu-item-type'      => 'custom',
				]
			);

			// Add children recursively.
			if ( ! is_wp_error( $menu_item_id ) && ! empty( $item['children'] ) ) {
				$this->add_menu_items( $menu_id, $item['children'], $menu_item_id );
			}
		}
	}

	/**
	 * Build a navigation item from a page route.
	 *
	 * @param string $route Page route.
	 * @param int    $depth Current depth.
	 * @return array|null Navigation item or null.
	 */
	private function build_nav_item( string $route, int $depth = 0 ): ?array {
		$page = $this->page_tree->get_page( $route );
		if ( ! $page ) {
			return null;
		}

		// Skip pages marked as hidden from navigation.
		if ( ! $page->show_in_nav() ) {
			return null;
		}

		$item = [
			'title'        => $page->get_nav_title(),
			'route'        => $page->get_route(),
			'url'          => home_url( $page->get_route() ),
			'depth'        => $depth,
			'has_children' => ! empty( $page->get_children() ),
			'children'     => [],
			'access_level' => $page->get_access_level(),
		];

		// Recursively build children.
		foreach ( $page->get_children() as $child_route ) {
			$child_item = $this->build_nav_item( $child_route, $depth + 1 );
			if ( $child_item ) {
				$item['children'][] = $child_item;
			}
		}

		return $item;
	}

	/**
	 * Load navigation from cache.
	 *
	 * @return bool True if loaded.
	 */
	private function load_from_cache(): bool {
		$cached = get_transient( self::CACHE_KEY );
		if ( false === $cached ) {
			return false;
		}

		if ( ! isset( $cached['items'] ) ) {
			return false;
		}

		$this->items = $cached['items'];
		$this->built = true;

		return true;
	}

	/**
	 * Save navigation to cache.
	 */
	private function save_to_cache(): void {
		set_transient( self::CACHE_KEY, [ 'items' => $this->items ], self::CACHE_TTL );
	}

	/**
	 * Clear cache.
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
		$this->built = false;
		$this->items = [];
	}

	/**
	 * Get all navigation items.
	 *
	 * @return array Navigation items.
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Get primary navigation (top-level items).
	 *
	 * @return array Primary nav items.
	 */
	public function get_primary(): array {
		$primary = [];
		foreach ( $this->items as $item ) {
			$primary[] = [
				'title' => $item['title'],
				'route' => $item['route'],
				'url'   => $item['url'],
			];
		}
		return $primary;
	}

	/**
	 * Get submenu for a given route.
	 *
	 * @param string $route Parent route.
	 * @return array Submenu items.
	 */
	public function get_submenu( string $route ): array {
		$item = $this->find_item( $route, $this->items );
		if ( ! $item ) {
			return [];
		}
		return $item['children'] ?? [];
	}

	/**
	 * Find item by route in navigation tree.
	 *
	 * @param string $route Route to find.
	 * @param array  $items Items to search.
	 * @return array|null Found item or null.
	 */
	private function find_item( string $route, array $items ): ?array {
		// Normalize route.
		$route = '/' . trim( $route, '/' ) . '/';
		if ( $route === '//' ) {
			$route = '/';
		}

		foreach ( $items as $item ) {
			if ( $item['route'] === $route ) {
				return $item;
			}
			if ( ! empty( $item['children'] ) ) {
				$found = $this->find_item( $route, $item['children'] );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Get navigation tree for a section.
	 *
	 * @param string $root_route Root route of section.
	 * @return array Section navigation.
	 */
	public function get_section_nav( string $root_route ): array {
		$item = $this->find_item( $root_route, $this->items );
		return $item ? [ $item ] : [];
	}

	/**
	 * Render navigation as HTML.
	 *
	 * @param array  $items Navigation items.
	 * @param string $current_route Current page route for active state.
	 * @param int    $max_depth Maximum depth to render.
	 * @return string HTML output.
	 */
	public function render( array $items = null, string $current_route = '', int $max_depth = 3 ): string {
		if ( null === $items ) {
			$items = $this->items;
		}

		if ( empty( $items ) ) {
			return '';
		}

		return $this->render_list( $items, $current_route, 0, $max_depth );
	}

	/**
	 * Render navigation list.
	 *
	 * @param array  $items         Items to render.
	 * @param string $current_route Current route.
	 * @param int    $depth         Current depth.
	 * @param int    $max_depth     Maximum depth.
	 * @return string HTML.
	 */
	private function render_list( array $items, string $current_route, int $depth, int $max_depth ): string {
		if ( $depth >= $max_depth ) {
			return '';
		}

		$class = $depth === 0 ? 'op-nav op-nav-primary' : 'op-nav op-nav-submenu';
		$html  = '<ul class="' . esc_attr( $class ) . '">';

		foreach ( $items as $item ) {
			$is_current = $this->is_current_or_ancestor( $item['route'], $current_route );
			$classes    = [ 'op-nav-item' ];

			if ( $is_current ) {
				$classes[] = 'op-nav-item-current';
			}
			if ( ! empty( $item['children'] ) ) {
				$classes[] = 'op-nav-item-has-children';
			}

			$html .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			$html .= '<a href="' . esc_url( $item['url'] ) . '"';
			if ( $item['route'] === $current_route ) {
				$html .= ' aria-current="page"';
			}
			$html .= '>' . esc_html( $item['title'] ) . '</a>';

			if ( ! empty( $item['children'] ) ) {
				$html .= $this->render_list( $item['children'], $current_route, $depth + 1, $max_depth );
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Check if route is current or ancestor of current.
	 *
	 * @param string $item_route    Item route.
	 * @param string $current_route Current route.
	 * @return bool True if current or ancestor.
	 */
	private function is_current_or_ancestor( string $item_route, string $current_route ): bool {
		if ( empty( $current_route ) ) {
			return false;
		}

		// Exact match.
		if ( $item_route === $current_route ) {
			return true;
		}

		// Ancestor check.
		return strpos( $current_route, rtrim( $item_route, '/' ) . '/' ) === 0;
	}

	/**
	 * Check if navigation is built.
	 *
	 * @return bool True if built.
	 */
	public function is_built(): bool {
		return $this->built;
	}

	/**
	 * Get statistics.
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		$root_count = count( $this->items );
		return [
			'total_items'   => $this->count_items( $this->items ),
			'total_routes'  => $this->count_items( $this->items ),
			'root_items'    => $root_count,
			'primary_items' => $root_count,
			'built'         => $this->built,
		];
	}

	/**
	 * Count total items recursively.
	 *
	 * @param array $items Items to count.
	 * @return int Total count.
	 */
	private function count_items( array $items ): int {
		$count = count( $items );
		foreach ( $items as $item ) {
			if ( ! empty( $item['children'] ) ) {
				$count += $this->count_items( $item['children'] );
			}
		}
		return $count;
	}

	/**
	 * Export as array.
	 *
	 * @return array Navigation structure.
	 */
	public function to_array(): array {
		return $this->items;
	}
}
