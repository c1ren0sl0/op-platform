<?php
/**
 * Page entity for operational content.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Represents an operational page from /platform/ directory.
 */
class OP_Page {

	/**
	 * Page title.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Navigation title (shorter version for menus).
	 *
	 * @var string
	 */
	private string $nav_title;

	/**
	 * Page description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Page slug (derived from path).
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Full route path (e.g., /sources/type/regulatory/).
	 *
	 * @var string
	 */
	private string $route;

	/**
	 * File path in library.
	 *
	 * @var string
	 */
	private string $file_path;

	/**
	 * Artifact type (derived or explicit).
	 *
	 * @var string
	 */
	private string $artifact_type;

	/**
	 * Filter configuration.
	 *
	 * @var array
	 */
	private array $filter;

	/**
	 * Sort order among siblings.
	 *
	 * @var int
	 */
	private int $sort_order;

	/**
	 * Access level.
	 *
	 * @var string
	 */
	private string $access_level;

	/**
	 * Markdown body content.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * Parent page slug (null for root).
	 *
	 * @var string|null
	 */
	private ?string $parent;

	/**
	 * Child page slugs.
	 *
	 * @var array
	 */
	private array $children;

	/**
	 * Depth in tree (0 = root).
	 *
	 * @var int
	 */
	private int $depth;

	/**
	 * Whether this is an index page (_index.md).
	 *
	 * @var bool
	 */
	private bool $is_index;

	/**
	 * Whether to show in navigation menu.
	 *
	 * @var bool
	 */
	private bool $show_in_nav;

	/**
	 * Constructor.
	 *
	 * @param array $data Page data.
	 */
	public function __construct( array $data ) {
		$this->title         = $data['title'] ?? '';
		$this->nav_title     = $data['nav_title'] ?? '';
		$this->description   = $data['description'] ?? '';
		$this->slug          = $data['slug'] ?? '';
		$this->route         = $data['route'] ?? '';
		$this->file_path     = $data['file_path'] ?? '';
		$this->artifact_type = $data['artifact_type'] ?? '';
		$this->filter        = $data['filter'] ?? [];
		$this->sort_order    = (int) ( $data['sort_order'] ?? 0 );
		$this->access_level  = $data['access_level'] ?? 'public';
		$this->body          = $data['body'] ?? '';
		$this->parent        = $data['parent'] ?? null;
		$this->children      = $data['children'] ?? [];
		$this->depth         = (int) ( $data['depth'] ?? 0 );
		$this->is_index      = $data['is_index'] ?? false;
		$this->show_in_nav   = $data['show_in_nav'] ?? true;
	}

	/**
	 * Create from file.
	 *
	 * @param string $file_path    Absolute path to file.
	 * @param string $platform_root Platform directory root path.
	 * @return self|null Page instance or null on failure.
	 */
	public static function from_file( string $file_path, string $platform_root ): ?self {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return null;
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return null;
		}

		// Parse frontmatter and body.
		$parsed = OP_Markdown_Parser::parse_frontmatter( $content );
		if ( null === $parsed ) {
			return null;
		}

		$frontmatter = $parsed['frontmatter'];
		$body        = $parsed['body'];

		// Derive route from file path.
		$relative_path = self::get_relative_path( $file_path, $platform_root );
		$route         = self::path_to_route( $relative_path );
		$slug          = self::route_to_slug( $route );
		$is_index      = basename( $file_path ) === '_index.md';

		// Derive artifact type.
		$artifact_type = $frontmatter['artifact_type'] ?? self::derive_artifact_type( $relative_path );

		// Calculate depth.
		$depth = substr_count( trim( $route, '/' ), '/' );
		if ( $route === '/' ) {
			$depth = 0;
		}

		// Derive parent.
		$parent = self::derive_parent( $route );

		// Pages in underscore directories (e.g., _site/) are hidden from nav by default.
		$in_underscore_dir = self::is_in_underscore_directory( $relative_path );
		$show_in_nav = $frontmatter['show_in_nav'] ?? ( ! $in_underscore_dir );

		return new self(
			[
				'title'         => $frontmatter['title'] ?? '',
				'nav_title'     => $frontmatter['nav_title'] ?? '',
				'description'   => $frontmatter['description'] ?? '',
				'slug'          => $slug,
				'route'         => $route,
				'file_path'     => $file_path,
				'artifact_type' => $artifact_type,
				'filter'        => $frontmatter['filter'] ?? [],
				'sort_order'    => $frontmatter['sort_order'] ?? $frontmatter['nav_order'] ?? 0,
				'access_level'  => $frontmatter['access_level'] ?? 'public',
				'body'          => $body,
				'parent'        => $parent,
				'children'      => [],
				'depth'         => $depth,
				'is_index'      => $is_index,
				'show_in_nav'   => $show_in_nav,
			]
		);
	}

	/**
	 * Get relative path from platform root.
	 *
	 * @param string $file_path     Absolute file path.
	 * @param string $platform_root Platform root path.
	 * @return string Relative path.
	 */
	private static function get_relative_path( string $file_path, string $platform_root ): string {
		$platform_root = rtrim( $platform_root, '/' ) . '/';
		if ( strpos( $file_path, $platform_root ) === 0 ) {
			return substr( $file_path, strlen( $platform_root ) );
		}
		return $file_path;
	}

	/**
	 * Convert file path to route.
	 *
	 * @param string $relative_path Relative path from platform root.
	 * @return string Route.
	 */
	private static function path_to_route( string $relative_path ): string {
		// Remove .md extension.
		$route = preg_replace( '/\.md$/', '', $relative_path );

		// Remove _index from path.
		$route = preg_replace( '/_index$/', '', $route );

		// Remove underscore-prefixed directories (e.g., _site/).
		$route = preg_replace( '#/?_[^/]+/#', '/', $route );

		// Ensure leading slash.
		$route = '/' . ltrim( $route, '/' );

		// Ensure trailing slash.
		$route = rtrim( $route, '/' ) . '/';

		// Root becomes just '/'.
		if ( $route === '//' ) {
			$route = '/';
		}

		// Root index.md becomes home page.
		if ( $route === '/index/' ) {
			$route = '/';
		}

		return $route;
	}

	/**
	 * Check if path is in an underscore-prefixed directory.
	 *
	 * @param string $relative_path Relative path from platform root.
	 * @return bool True if in underscore directory.
	 */
	private static function is_in_underscore_directory( string $relative_path ): bool {
		return (bool) preg_match( '#(^|/)_[^/]+/#', $relative_path );
	}

	/**
	 * Convert route to slug.
	 *
	 * @param string $route Route path.
	 * @return string Slug.
	 */
	private static function route_to_slug( string $route ): string {
		$slug = trim( $route, '/' );
		if ( empty( $slug ) ) {
			return 'root';
		}
		return str_replace( '/', '-', $slug );
	}

	/**
	 * Derive artifact type from path.
	 *
	 * The platform doesn't hardcode artifact types - it just derives
	 * from the directory name. Providers can override via artifact_type
	 * in frontmatter.
	 *
	 * @param string $relative_path Relative path.
	 * @return string Artifact type.
	 */
	private static function derive_artifact_type( string $relative_path ): string {
		// Get first directory component.
		$parts = explode( '/', trim( $relative_path, '/' ) );
		if ( empty( $parts[0] ) ) {
			return '';
		}

		$folder = $parts[0];

		// Strip .md extension if present (for root-level files).
		$folder = preg_replace( '/\.md$/', '', $folder );

		// Root index.md has no artifact type.
		if ( $folder === 'index' ) {
			return '';
		}

		// Return the folder name as-is - providers handle mapping.
		// Common convention: plural folder -> singular type.
		// But that's up to the provider to interpret.
		return $folder;
	}

	/**
	 * Derive parent route from current route.
	 *
	 * @param string $route Current route.
	 * @return string|null Parent route or null if root.
	 */
	private static function derive_parent( string $route ): ?string {
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
	 * Get title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Get navigation title.
	 * Falls back to title if nav_title is not set.
	 *
	 * @return string
	 */
	public function get_nav_title(): string {
		return ! empty( $this->nav_title ) ? $this->nav_title : $this->title;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Get route.
	 *
	 * @return string
	 */
	public function get_route(): string {
		return $this->route;
	}

	/**
	 * Get file path.
	 *
	 * @return string
	 */
	public function get_file_path(): string {
		return $this->file_path;
	}

	/**
	 * Get artifact type.
	 *
	 * @return string
	 */
	public function get_artifact_type(): string {
		return $this->artifact_type;
	}

	/**
	 * Get filter.
	 *
	 * @return array
	 */
	public function get_filter(): array {
		return $this->filter;
	}

	/**
	 * Get sort order.
	 *
	 * @return int
	 */
	public function get_sort_order(): int {
		return $this->sort_order;
	}

	/**
	 * Get access level.
	 *
	 * @return string
	 */
	public function get_access_level(): string {
		return $this->access_level;
	}

	/**
	 * Get body content.
	 *
	 * @return string
	 */
	public function get_body(): string {
		return $this->body;
	}

	/**
	 * Get parent route.
	 *
	 * @return string|null
	 */
	public function get_parent(): ?string {
		return $this->parent;
	}

	/**
	 * Get children.
	 *
	 * @return array
	 */
	public function get_children(): array {
		return $this->children;
	}

	/**
	 * Add child.
	 *
	 * @param string $child_route Child route.
	 */
	public function add_child( string $child_route ): void {
		if ( ! in_array( $child_route, $this->children, true ) ) {
			$this->children[] = $child_route;
		}
	}

	/**
	 * Get depth.
	 *
	 * @return int
	 */
	public function get_depth(): int {
		return $this->depth;
	}

	/**
	 * Check if index page.
	 *
	 * @return bool
	 */
	public function is_index(): bool {
		return $this->is_index;
	}

	/**
	 * Check if page should show in navigation.
	 *
	 * @return bool
	 */
	public function show_in_nav(): bool {
		return $this->show_in_nav;
	}

	/**
	 * Check if page has filter.
	 *
	 * @return bool
	 */
	public function has_filter(): bool {
		return ! empty( $this->filter );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'title'         => $this->title,
			'nav_title'     => $this->nav_title,
			'description'   => $this->description,
			'slug'          => $this->slug,
			'route'         => $this->route,
			'file_path'     => $this->file_path,
			'artifact_type' => $this->artifact_type,
			'filter'        => $this->filter,
			'sort_order'    => $this->sort_order,
			'access_level'  => $this->access_level,
			'body'          => $this->body,
			'parent'        => $this->parent,
			'children'      => $this->children,
			'depth'         => $this->depth,
			'is_index'      => $this->is_index,
			'show_in_nav'   => $this->show_in_nav,
		];
	}
}
