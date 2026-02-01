<?php
/**
 * Page tree builder.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Builds and manages the page tree from /platform/ directory.
 */
class OP_Page_Tree {

	/**
	 * Transient key for cached tree.
	 */
	private const CACHE_KEY = 'op_page_tree';

	/**
	 * Cache TTL in seconds.
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Platform instance.
	 *
	 * @var OP_Platform
	 */
	private OP_Platform $platform;

	/**
	 * Pages indexed by route.
	 *
	 * @var array<string, OP_Page>
	 */
	private array $pages = [];

	/**
	 * Root-level routes.
	 *
	 * @var array
	 */
	private array $roots = [];

	/**
	 * Whether tree is built.
	 *
	 * @var bool
	 */
	private bool $built = false;

	/**
	 * Build timestamp.
	 *
	 * @var string|null
	 */
	private ?string $built_at = null;

	/**
	 * Constructor.
	 *
	 * @param OP_Platform $platform Platform instance.
	 */
	public function __construct( OP_Platform $platform ) {
		$this->platform = $platform;
	}

	/**
	 * Build the page tree.
	 *
	 * @param bool $force Force rebuild ignoring cache.
	 * @return bool True on success.
	 */
	public function build( bool $force = false ): bool {
		// Try cache first.
		if ( ! $force ) {
			$cached = $this->load_from_cache();
			if ( $cached ) {
				return true;
			}
		}

		if ( ! $this->platform->is_valid() ) {
			return false;
		}

		// Load all pages.
		$this->pages = $this->platform->load_pages();

		if ( empty( $this->pages ) ) {
			return false;
		}

		// Build parent-child relationships.
		$this->build_relationships();

		// Sort children by sort_order.
		$this->sort_children();

		// Identify root pages.
		$this->identify_roots();

		$this->built    = true;
		$this->built_at = current_time( 'mysql' );

		// Cache the tree.
		$this->save_to_cache();

		return true;
	}

	/**
	 * Build parent-child relationships.
	 */
	private function build_relationships(): void {
		foreach ( $this->pages as $route => $page ) {
			$parent_route = $page->get_parent();

			// Walk up the tree to find the nearest existing ancestor.
			while ( $parent_route && ! isset( $this->pages[ $parent_route ] ) ) {
				$parent_route = $this->derive_parent_route( $parent_route );
			}

			if ( $parent_route && isset( $this->pages[ $parent_route ] ) ) {
				$this->pages[ $parent_route ]->add_child( $route );

				// Update the page's parent to the nearest existing ancestor.
				if ( $parent_route !== $page->get_parent() ) {
					$page_data           = $page->to_array();
					$page_data['parent'] = $parent_route;
					$this->pages[ $route ] = new OP_Page( $page_data );
				}
			}
		}
	}

	/**
	 * Derive parent route from a route.
	 *
	 * @param string $route Route to derive parent from.
	 * @return string|null Parent route or null if at root.
	 */
	private function derive_parent_route( string $route ): ?string {
		$route = trim( $route, '/' );
		if ( empty( $route ) ) {
			return null;
		}

		$parts = explode( '/', $route );
		array_pop( $parts );

		if ( empty( $parts ) ) {
			return null;
		}

		return '/' . implode( '/', $parts ) . '/';
	}

	/**
	 * Sort children by sort_order, then alphabetically by title.
	 */
	private function sort_children(): void {
		foreach ( $this->pages as $page ) {
			$children = $page->get_children();
			if ( empty( $children ) ) {
				continue;
			}

			$pages = &$this->pages;
			usort( $children, function ( $a, $b ) use ( $pages ) {
				$page_a = $pages[ $a ] ?? null;
				$page_b = $pages[ $b ] ?? null;

				if ( ! $page_a || ! $page_b ) {
					return 0;
				}

				$order_diff = $page_a->get_sort_order() - $page_b->get_sort_order();
				if ( $order_diff !== 0 ) {
					return $order_diff;
				}

				return strcasecmp( $page_a->get_title(), $page_b->get_title() );
			} );

			// Update children with sorted order.
			$page_data = $page->to_array();
			$page_data['children'] = $children;
			$this->pages[ $page->get_route() ] = new OP_Page( $page_data );
		}
	}

	/**
	 * Identify root-level pages.
	 */
	private function identify_roots(): void {
		$this->roots = [];

		foreach ( $this->pages as $route => $page ) {
			if ( $page->get_parent() === null || ! isset( $this->pages[ $page->get_parent() ] ) ) {
				$this->roots[] = $route;
			}
		}

		// Sort roots by sort_order.
		$pages = &$this->pages;
		usort( $this->roots, function ( $a, $b ) use ( $pages ) {
			$page_a = $pages[ $a ] ?? null;
			$page_b = $pages[ $b ] ?? null;

			if ( ! $page_a || ! $page_b ) {
				return 0;
			}

			$order_diff = $page_a->get_sort_order() - $page_b->get_sort_order();
			if ( $order_diff !== 0 ) {
				return $order_diff;
			}

			return strcasecmp( $page_a->get_title(), $page_b->get_title() );
		} );
	}

	/**
	 * Load tree from cache.
	 *
	 * @return bool True if loaded from cache.
	 */
	private function load_from_cache(): bool {
		$cached = get_transient( self::CACHE_KEY );
		if ( false === $cached ) {
			return false;
		}

		if ( ! isset( $cached['pages'], $cached['roots'], $cached['built_at'] ) ) {
			return false;
		}

		// Reconstruct Page objects from cached data.
		$this->pages = [];
		foreach ( $cached['pages'] as $route => $page_data ) {
			$this->pages[ $route ] = new OP_Page( $page_data );
		}

		$this->roots    = $cached['roots'];
		$this->built_at = $cached['built_at'];
		$this->built    = true;

		return true;
	}

	/**
	 * Save tree to cache.
	 */
	private function save_to_cache(): void {
		$cache_data = [
			'pages'    => [],
			'roots'    => $this->roots,
			'built_at' => $this->built_at,
		];

		foreach ( $this->pages as $route => $page ) {
			$cache_data['pages'][ $route ] = $page->to_array();
		}

		set_transient( self::CACHE_KEY, $cache_data, self::CACHE_TTL );
	}

	/**
	 * Clear cache.
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
		$this->built = false;
		$this->pages = [];
		$this->roots = [];
	}

	/**
	 * Get page by route.
	 *
	 * @param string $route Route to look up.
	 * @return OP_Page|null Page or null.
	 */
	public function get_page( string $route ): ?OP_Page {
		// Normalize route.
		$route = '/' . trim( $route, '/' ) . '/';
		if ( $route === '//' ) {
			$route = '/';
		}

		return $this->pages[ $route ] ?? null;
	}

	/**
	 * Get all pages.
	 *
	 * @return array<string, OP_Page> Pages indexed by route.
	 */
	public function get_pages(): array {
		return $this->pages;
	}

	/**
	 * Get all pages (alias for get_pages).
	 *
	 * @return array<string, OP_Page> Pages indexed by route.
	 */
	public function get_all_pages(): array {
		return $this->pages;
	}

	/**
	 * Get root pages.
	 *
	 * @return array Root page routes.
	 */
	public function get_roots(): array {
		return $this->roots;
	}

	/**
	 * Get children of a page.
	 *
	 * @param string $route Parent route.
	 * @return array<OP_Page> Child pages.
	 */
	public function get_children( string $route ): array {
		$page = $this->get_page( $route );
		if ( ! $page ) {
			return [];
		}

		$children = [];
		foreach ( $page->get_children() as $child_route ) {
			if ( isset( $this->pages[ $child_route ] ) ) {
				$children[] = $this->pages[ $child_route ];
			}
		}

		return $children;
	}

	/**
	 * Get breadcrumbs for a route.
	 *
	 * @param string $route Route to get breadcrumbs for.
	 * @return array Breadcrumb items.
	 */
	public function get_breadcrumbs( string $route ): array {
		$breadcrumbs = [];
		$current     = $this->get_page( $route );

		while ( $current ) {
			array_unshift( $breadcrumbs, [
				'title' => $current->get_title(),
				'route' => $current->get_route(),
			] );

			$parent_route = $current->get_parent();
			$current = $parent_route ? $this->get_page( $parent_route ) : null;
		}

		// Add home.
		array_unshift( $breadcrumbs, [
			'title' => __( 'Home', 'op-platform' ),
			'route' => '/',
		] );

		return $breadcrumbs;
	}

	/**
	 * Get all routes.
	 *
	 * @return array Route strings.
	 */
	public function get_routes(): array {
		return array_keys( $this->pages );
	}

	/**
	 * Check if tree is built.
	 *
	 * @return bool True if built.
	 */
	public function is_built(): bool {
		return $this->built;
	}

	/**
	 * Get build timestamp.
	 *
	 * @return string|null Build timestamp.
	 */
	public function get_built_at(): ?string {
		return $this->built_at;
	}

	/**
	 * Get tree statistics.
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		return [
			'total_pages' => count( $this->pages ),
			'root_pages'  => count( $this->roots ),
			'built'       => $this->built,
			'built_at'    => $this->built_at,
		];
	}

	/**
	 * Export tree as array (for debugging/admin display).
	 *
	 * @return array Tree structure.
	 */
	public function to_array(): array {
		$result = [];

		foreach ( $this->roots as $root_route ) {
			$result[] = $this->page_to_tree_array( $root_route );
		}

		return $result;
	}

	/**
	 * Convert page and its children to tree array.
	 *
	 * @param string $route Page route.
	 * @return array|null Tree array.
	 */
	private function page_to_tree_array( string $route ): ?array {
		$page = $this->get_page( $route );
		if ( ! $page ) {
			return null;
		}

		$item = [
			'title'         => $page->get_title(),
			'route'         => $page->get_route(),
			'artifact_type' => $page->get_artifact_type(),
			'children'      => [],
		];

		foreach ( $page->get_children() as $child_route ) {
			$child_item = $this->page_to_tree_array( $child_route );
			if ( $child_item ) {
				$item['children'][] = $child_item;
			}
		}

		return $item;
	}
}
