<?php
/**
 * Router for operational pages.
 *
 * Handles URL routing for operational pages and delegates artifact
 * detail handling to registered content providers.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Handles URL routing for operational pages.
 */
class OP_Router {

	/**
	 * Page tree instance.
	 *
	 * @var OP_Page_Tree
	 */
	private OP_Page_Tree $page_tree;

	/**
	 * Navigation instance.
	 *
	 * @var OP_Navigation
	 */
	private OP_Navigation $navigation;

	/**
	 * Query var name for platform pages.
	 */
	private const QUERY_VAR = 'op_route';

	/**
	 * Query var for artifact type.
	 */
	private const ARTIFACT_TYPE_VAR = 'op_artifact_type';

	/**
	 * Query var for artifact slug.
	 */
	private const ARTIFACT_SLUG_VAR = 'op_artifact_slug';

	/**
	 * Constructor.
	 *
	 * @param OP_Page_Tree  $page_tree  Page tree instance.
	 * @param OP_Navigation $navigation Navigation instance.
	 */
	public function __construct(
		OP_Page_Tree $page_tree,
		OP_Navigation $navigation
	) {
		$this->page_tree  = $page_tree;
		$this->navigation = $navigation;
	}

	/**
	 * Initialize router.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_rewrite_rules' ], 10 );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
	}

	/**
	 * Register rewrite rules for all operational pages.
	 */
	public function register_rewrite_rules(): void {
		// Ensure tree is built.
		if ( ! $this->page_tree->is_built() ) {
			$this->page_tree->build();
		}

		// Register artifact detail routes from providers.
		$this->register_provider_routes();

		// Get all routes from the page tree.
		$routes = $this->page_tree->get_routes();

		foreach ( $routes as $route ) {
			$route_pattern = $this->route_to_pattern( $route );
			if ( $route_pattern ) {
				add_rewrite_rule(
					$route_pattern . '?$',
					'index.php?' . self::QUERY_VAR . '=' . urlencode( $route ),
					'top'
				);
			}
		}
	}

	/**
	 * Register rewrite rules for artifact detail pages from providers.
	 */
	private function register_provider_routes(): void {
		$providers = OP_Provider_Registry::get_all();

		foreach ( $providers as $provider ) {
			foreach ( $provider->get_types() as $type ) {
				$config = $provider->get_type_config( $type );
				if ( ! $config ) {
					continue;
				}

				$slug_base = $config->get_slug_base();

				// Register route: /{slug_base}/{slug}/
				add_rewrite_rule(
					$slug_base . '/([^/]+)/?$',
					'index.php?' . self::ARTIFACT_TYPE_VAR . '=' . $type . '&' . self::ARTIFACT_SLUG_VAR . '=$matches[1]',
					'top'
				);
			}
		}

		// Allow providers to register additional routes.
		do_action( 'op_platform_register_routes', $this );
	}

	/**
	 * Convert route to rewrite pattern.
	 *
	 * @param string $route Route path.
	 * @return string|null Pattern or null.
	 */
	private function route_to_pattern( string $route ): ?string {
		$route = trim( $route, '/' );
		if ( empty( $route ) ) {
			return null; // Skip root route, handled by WordPress.
		}

		// Escape regex special characters.
		$pattern = preg_quote( $route, '/' );

		// Allow optional trailing slash.
		return $pattern . '\\/?';
	}

	/**
	 * Register query variables.
	 *
	 * @param array $vars Existing vars.
	 * @return array Modified vars.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::ARTIFACT_TYPE_VAR;
		$vars[] = self::ARTIFACT_SLUG_VAR;
		return $vars;
	}

	/**
	 * Handle incoming request.
	 */
	public function handle_request(): void {
		// Check for artifact detail request first.
		$artifact_type = get_query_var( self::ARTIFACT_TYPE_VAR );
		$artifact_slug = get_query_var( self::ARTIFACT_SLUG_VAR );

		if ( ! empty( $artifact_type ) && ! empty( $artifact_slug ) ) {
			$this->handle_artifact_request( $artifact_type, $artifact_slug );
			return;
		}

		// Handle platform page request.
		$route = get_query_var( self::QUERY_VAR );

		// Check if this is the front page and we have a platform home page.
		if ( empty( $route ) && ( is_front_page() || is_home() ) ) {
			$home_page = $this->page_tree->get_page( '/' );
			if ( $home_page ) {
				$this->render_page( $home_page );
				exit;
			}
		}

		if ( empty( $route ) ) {
			return;
		}

		$route = urldecode( $route );
		$page  = $this->page_tree->get_page( $route );

		if ( ! $page ) {
			// Page not found in tree.
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Check access level.
		if ( ! $this->check_page_access( $page ) ) {
			$this->render_gated_page( $page );
			exit;
		}

		// Render the page.
		$this->render_page( $page );
		exit;
	}

	/**
	 * Handle artifact detail request.
	 *
	 * Delegates to the appropriate content provider.
	 *
	 * @param string $type Artifact type.
	 * @param string $slug Artifact slug.
	 */
	private function handle_artifact_request( string $type, string $slug ): void {
		// Get provider for this type.
		$provider = OP_Provider_Registry::get_for_type( $type );
		if ( ! $provider ) {
			$this->render_404();
			return;
		}

		// Get item from provider.
		$item = $provider->get_item( $type, $slug );
		if ( ! $item ) {
			$this->render_404();
			return;
		}

		// Check access via provider.
		$access = $provider->check_access( $type, $slug, get_current_user_id() ?: null );
		if ( ! $access['allowed'] ) {
			$this->render_gated_artifact( $item, $type, $provider, $access );
			exit;
		}

		// Fire action before rendering.
		do_action( 'op_platform_artifact_request', $type, $slug, $item );

		// Render the artifact detail page.
		$this->render_artifact( $item, $type, $provider );
		exit;
	}

	/**
	 * Render 404 page.
	 */
	private function render_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}

	/**
	 * Render an artifact detail page.
	 *
	 * @param OP_Content_Item    $item     Content item.
	 * @param string             $type     Artifact type.
	 * @param OP_Content_Provider $provider Content provider.
	 */
	private function render_artifact( OP_Content_Item $item, string $type, OP_Content_Provider $provider ): void {
		$type_config = $provider->get_type_config( $type );

		// Build breadcrumbs.
		$type_label = $type_config ? $type_config->get_plural_label() : ucfirst( $type ) . 's';
		$slug_base  = $type_config ? $type_config->get_slug_base() : $type;

		$breadcrumbs = [
			[
				'title' => __( 'Home', 'op-platform' ),
				'route' => '/',
			],
			[
				'title' => $type_label,
				'route' => '/' . $slug_base . 's/', // Convention: plural directory
			],
			[
				'title' => $item->get_title(),
				'route' => $item->get_url(),
			],
		];

		// Set up template variables.
		$template_vars = [
			'item'         => $item,
			'type'         => $type,
			'type_config'  => $type_config,
			'provider'     => $provider,
			'breadcrumbs'  => $breadcrumbs,
			'navigation'   => $this->navigation,
			'content_html' => $item->get_content(),
			'meta'         => $item->get_all_meta(),
		];

		// Set document title.
		add_filter( 'document_title_parts', function ( $title_parts ) use ( $item ) {
			$title_parts['title'] = $item->get_title();
			return $title_parts;
		} );

		// Check if provider has custom detail template.
		$provider_template = $provider->get_detail_template( $type );
		if ( $provider_template && file_exists( $provider_template ) ) {
			$this->load_provider_template( $provider_template, $template_vars );
		} else {
			// Use default platform template (minimal).
			$this->load_template( 'artifact-detail', $template_vars );
		}
	}

	/**
	 * Check if current user has access to page.
	 *
	 * @param OP_Page $page Page to check.
	 * @return bool True if access granted.
	 */
	private function check_page_access( OP_Page $page ): bool {
		$access_level = $page->get_access_level();

		// Allow filtering for custom access logic.
		return apply_filters( 'op_platform_check_access', $this->default_access_check( $access_level ), $access_level, get_current_user_id() );
	}

	/**
	 * Default access check implementation.
	 *
	 * @param string $access_level Access level to check.
	 * @return bool True if access granted.
	 */
	private function default_access_check( string $access_level ): bool {
		switch ( $access_level ) {
			case 'public':
				return true;

			case 'member':
			case 'registered':
				return is_user_logged_in();

			case 'premium':
			case 'subscriber':
				if ( is_user_logged_in() && current_user_can( 'read_private_posts' ) ) {
					return true;
				}
				return apply_filters( 'op_platform_check_premium_access', false, $access_level );

			default:
				return true;
		}
	}

	/**
	 * Render gated page for unauthorized access.
	 *
	 * @param OP_Page $page Page to render.
	 */
	private function render_gated_page( OP_Page $page ): void {
		$template_vars = [
			'page'         => $page,
			'breadcrumbs'  => $this->page_tree->get_breadcrumbs( $page->get_route() ),
			'navigation'   => $this->navigation,
			'access_level' => $page->get_access_level(),
			'content_type' => 'page',
		];

		// Set document title.
		add_filter( 'document_title_parts', function ( $title_parts ) use ( $page ) {
			$title_parts['title'] = $page->get_title();
			return $title_parts;
		} );

		$this->load_template( 'gated', $template_vars );
	}

	/**
	 * Render gated artifact for unauthorized access.
	 *
	 * @param OP_Content_Item     $item     Content item.
	 * @param string              $type     Artifact type.
	 * @param OP_Content_Provider $provider Content provider.
	 * @param array               $access   Access check result.
	 */
	private function render_gated_artifact( OP_Content_Item $item, string $type, OP_Content_Provider $provider, array $access ): void {
		$type_config = $provider->get_type_config( $type );
		$type_label  = $type_config ? $type_config->get_plural_label() : ucfirst( $type ) . 's';
		$slug_base   = $type_config ? $type_config->get_slug_base() : $type;

		$breadcrumbs = [
			[
				'title' => __( 'Home', 'op-platform' ),
				'route' => '/',
			],
			[
				'title' => $type_label,
				'route' => '/' . $slug_base . 's/',
			],
			[
				'title' => $item->get_title(),
				'route' => $item->get_url(),
			],
		];

		$template_vars = [
			'page'         => $item, // For template compatibility
			'item'         => $item,
			'breadcrumbs'  => $breadcrumbs,
			'navigation'   => $this->navigation,
			'access_level' => $access['tier'] ?? 'member',
			'access_reason'=> $access['reason'] ?? '',
			'content_type' => $type,
		];

		// Set document title.
		add_filter( 'document_title_parts', function ( $title_parts ) use ( $item ) {
			$title_parts['title'] = $item->get_title();
			return $title_parts;
		} );

		$this->load_template( 'gated', $template_vars );
	}

	/**
	 * Render an operational page.
	 *
	 * @param OP_Page $page Page to render.
	 */
	private function render_page( OP_Page $page ): void {
		// Set up template variables.
		$template_vars = [
			'page'        => $page,
			'breadcrumbs' => $this->page_tree->get_breadcrumbs( $page->get_route() ),
			'navigation'  => $this->navigation,
			'children'    => $this->page_tree->get_children( $page->get_route() ),
		];

		// Parse markdown body to HTML.
		$template_vars['content_html'] = $this->parse_markdown( $page->get_body() );

		// If page has an artifact type, query items from provider.
		$artifact_type = $page->get_artifact_type();
		if ( ! empty( $artifact_type ) ) {
			$provider = OP_Provider_Registry::get_for_type( $artifact_type );
			if ( $provider ) {
				$query_args = [
					'filters'  => $page->get_filter(),
					'per_page' => apply_filters( 'op_platform_items_per_page', 24, $artifact_type ),
					'page'     => max( 1, intval( $_GET['paged'] ?? 1 ) ),
				];

				$query_args = apply_filters( 'op_platform_artifact_query_args', $query_args, $artifact_type, $page );

				$result = $provider->query( $artifact_type, $query_args );

				$template_vars['items']       = $result['items'] ?? [];
				$template_vars['total']       = $result['total'] ?? 0;
				$template_vars['total_pages'] = $result['pages'] ?? 1;
				$template_vars['current_page']= $result['page'] ?? 1;
				$template_vars['provider']    = $provider;
				$template_vars['type_config'] = $provider->get_type_config( $artifact_type );
				$template_vars['artifact_type'] = $artifact_type;

				$template_vars['items'] = apply_filters( 'op_platform_artifact_items', $template_vars['items'], $artifact_type, $page );
			}
		}

		// Set document title.
		add_filter( 'document_title_parts', function ( $title_parts ) use ( $page ) {
			$title_parts['title'] = $page->get_title();
			return $title_parts;
		} );

		// Set meta description.
		if ( $page->get_description() ) {
			add_action( 'wp_head', function () use ( $page ) {
				printf(
					'<meta name="description" content="%s" />' . "\n",
					esc_attr( $page->get_description() )
				);
			}, 1 );
		}

		// Fire action before rendering.
		do_action( 'op_platform_before_page_render', $page );

		// Load template.
		$this->load_template( 'operational', $template_vars );

		// Fire action after rendering.
		do_action( 'op_platform_after_page_render', $page );
	}

	/**
	 * Parse markdown to HTML.
	 *
	 * Simple markdown parser for page body content.
	 *
	 * @param string $markdown Markdown content.
	 * @return string HTML content.
	 */
	private function parse_markdown( string $markdown ): string {
		$html = $markdown;

		// Convert headers.
		$html = preg_replace( '/^##### (.+)$/m', '<h5>$1</h5>', $html );
		$html = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

		// Convert horizontal rules.
		$html = preg_replace( '/^---+$/m', '<hr>', $html );

		// Convert bold and italic.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );

		// Convert links.
		$html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );

		// Convert paragraphs.
		$html = preg_replace( '/\n\n+/', '</p><p>', $html );
		$html = '<p>' . $html . '</p>';

		// Clean up empty paragraphs.
		$html = preg_replace( '/<p>\s*<\/p>/', '', $html );

		return $html;
	}

	/**
	 * Load a template file.
	 *
	 * @param string $template Template name (without .php).
	 * @param array  $vars     Template variables.
	 */
	private function load_template( string $template, array $vars ): void {
		// Allow theme override.
		$theme_template = locate_template( 'op-platform/' . $template . '.php' );
		if ( $theme_template ) {
			$template_file = $theme_template;
		} else {
			$template_file = OP_PLUGIN_DIR . 'templates/frontend/' . $template . '.php';
		}

		if ( ! file_exists( $template_file ) ) {
			wp_die(
				esc_html__( 'Template not found.', 'op-platform' ),
				esc_html__( 'Template Error', 'op-platform' ),
				[ 'response' => 500 ]
			);
		}

		$this->include_template( $template_file, $vars );
	}

	/**
	 * Load a provider-supplied template.
	 *
	 * @param string $template_file Absolute path to template.
	 * @param array  $vars          Template variables.
	 */
	private function load_provider_template( string $template_file, array $vars ): void {
		if ( ! file_exists( $template_file ) ) {
			wp_die(
				esc_html__( 'Provider template not found.', 'op-platform' ),
				esc_html__( 'Template Error', 'op-platform' ),
				[ 'response' => 500 ]
			);
		}

		$this->include_template( $template_file, $vars );
	}

	/**
	 * Include template with variables.
	 *
	 * @param string $template_file Template file path.
	 * @param array  $vars          Template variables.
	 */
	private function include_template( string $template_file, array $vars ): void {
		// Whitelist of allowed template variables.
		$allowed_vars = [
			'page',
			'item',
			'breadcrumbs',
			'navigation',
			'children',
			'content_html',
			'type',
			'type_config',
			'provider',
			'meta',
			'access_level',
			'access_reason',
			'content_type',
			'items',
			'total',
			'total_pages',
			'current_page',
			'artifact_type',
		];

		// Explicitly assign only whitelisted variables.
		foreach ( $allowed_vars as $var_name ) {
			if ( array_key_exists( $var_name, $vars ) ) {
				$$var_name = $vars[ $var_name ];
			}
		}

		// Load header.
		get_header();

		// Include template.
		include $template_file;

		// Load footer.
		get_footer();
	}

	/**
	 * Get URL for a route.
	 *
	 * @param string $route Route path.
	 * @return string Full URL.
	 */
	public function get_url( string $route ): string {
		return home_url( $route );
	}

	/**
	 * Check if current request is for an operational page or artifact detail.
	 *
	 * @return bool True if operational page or artifact request.
	 */
	public function is_operational_request(): bool {
		if ( ! empty( get_query_var( self::QUERY_VAR ) ) ) {
			return true;
		}

		if ( ! empty( get_query_var( self::ARTIFACT_TYPE_VAR ) ) ) {
			return true;
		}

		if ( ( is_front_page() || is_home() ) && $this->page_tree && $this->page_tree->get_page( '/' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current route.
	 *
	 * @return string|null Current route or null.
	 */
	public function get_current_route(): ?string {
		$route = get_query_var( self::QUERY_VAR );
		return $route ? urldecode( $route ) : null;
	}
}
