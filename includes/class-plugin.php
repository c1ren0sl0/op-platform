<?php
/**
 * Main plugin orchestrator.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Main plugin class. Singleton pattern.
 */
final class OP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var OP_Plugin|null
	 */
	private static ?OP_Plugin $instance = null;

	/**
	 * Configuration instance.
	 *
	 * @var OP_Config
	 */
	private OP_Config $config;

	/**
	 * Platform instance.
	 *
	 * @var OP_Platform
	 */
	private OP_Platform $platform;

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
	 * Router instance.
	 *
	 * @var OP_Router
	 */
	private OP_Router $router;

	/**
	 * Diagnostics instance.
	 *
	 * @var OP_Diagnostics
	 */
	private OP_Diagnostics $diagnostics;

	/**
	 * Admin instance.
	 *
	 * @var OP_Admin|null
	 */
	private ?OP_Admin $admin = null;

	/**
	 * Platform status.
	 *
	 * @var string One of: 'inactive', 'degraded', 'structurally_up'
	 */
	private string $status = 'inactive';

	/**
	 * Get singleton instance.
	 *
	 * @return OP_Plugin
	 */
	public static function get_instance(): OP_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 */
	private function init(): void {
		// Initialize configuration.
		$this->config = new OP_Config();

		// Initialize platform components.
		$this->platform   = new OP_Platform( $this->config );
		$this->page_tree  = new OP_Page_Tree( $this->platform );
		$this->navigation = new OP_Navigation( $this->page_tree );
		$this->router     = new OP_Router( $this->page_tree, $this->navigation );

		// Initialize diagnostics.
		$this->diagnostics = new OP_Diagnostics(
			$this->config,
			$this->platform,
			$this->page_tree
		);

		// Initialize admin if in admin context.
		if ( is_admin() ) {
			$this->admin = new OP_Admin(
				$this->config,
				$this->diagnostics,
				$this->platform,
				$this->page_tree,
				$this->navigation
			);
		}

		// Register hooks.
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		// Fire provider registration hook.
		add_action( 'init', [ $this, 'fire_provider_registration' ], 5 );

		// Initialize router.
		$this->router->init();

		// Handle rewrite flush.
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 20 );

		// Build platform on init (after providers register).
		add_action( 'init', [ $this, 'build_platform' ], 15 );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
	}

	/**
	 * Fire provider registration hook.
	 */
	public function fire_provider_registration(): void {
		do_action( 'op_platform_register_providers' );
	}

	/**
	 * Build platform structures.
	 */
	public function build_platform(): void {
		if ( ! $this->config->has_library_path() ) {
			return;
		}

		// Build page tree (will cache).
		$this->page_tree->build();

		// Build navigation (will cache).
		$this->navigation->build();

		// Update status.
		$this->status = $this->diagnostics->get_status();
	}

	/**
	 * Maybe flush rewrite rules.
	 */
	public function maybe_flush_rewrites(): void {
		if ( get_transient( 'op_platform_flush_rewrite' ) ) {
			delete_transient( 'op_platform_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->router->is_operational_request() ) {
			return;
		}

		// Enqueue CSS.
		$css_file = OP_PLUGIN_DIR . 'assets/css/frontend.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'op-platform-frontend',
				OP_PLUGIN_URL . 'assets/css/frontend.css',
				[],
				filemtime( $css_file )
			);
		}

		// Enqueue JS.
		$js_file = OP_PLUGIN_DIR . 'assets/js/frontend.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'op-platform-frontend',
				OP_PLUGIN_URL . 'assets/js/frontend.js',
				[],
				filemtime( $js_file ),
				true
			);
		}
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_rest_endpoints(): void {
		register_rest_route(
			'op-platform/v1',
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			]
		);

		register_rest_route(
			'op-platform/v1',
			'/page-tree',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_page_tree' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			]
		);

		register_rest_route(
			'op-platform/v1',
			'/navigation',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_navigation' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			]
		);

		register_rest_route(
			'op-platform/v1',
			'/rebuild',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_rebuild' ],
				'permission_callback' => [ $this, 'rest_admin_permission_check' ],
			]
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool True if allowed.
	 */
	public function rest_permission_check(): bool {
		return true; // Public read access.
	}

	/**
	 * REST admin permission check.
	 *
	 * @return bool True if allowed.
	 */
	public function rest_admin_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST endpoint: Get platform status.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function rest_get_status(): WP_REST_Response {
		return new WP_REST_Response( $this->diagnostics->get_full_report() );
	}

	/**
	 * REST endpoint: Get page tree.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function rest_get_page_tree(): WP_REST_Response {
		return new WP_REST_Response( $this->page_tree->to_array() );
	}

	/**
	 * REST endpoint: Get navigation.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function rest_get_navigation(): WP_REST_Response {
		return new WP_REST_Response( $this->navigation->to_array() );
	}

	/**
	 * REST endpoint: Rebuild platform.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function rest_rebuild(): WP_REST_Response {
		// Clear caches.
		$this->page_tree->clear_cache();
		$this->navigation->clear_cache();

		// Rebuild.
		$this->page_tree->build( true );
		$this->navigation->build( true );

		// Flush rewrite rules.
		flush_rewrite_rules();

		return new WP_REST_Response( [
			'success' => true,
			'status'  => $this->diagnostics->get_status(),
			'stats'   => $this->page_tree->get_stats(),
		] );
	}

	/**
	 * Get configuration instance.
	 *
	 * @return OP_Config
	 */
	public function get_config(): OP_Config {
		return $this->config;
	}

	/**
	 * Get platform instance.
	 *
	 * @return OP_Platform
	 */
	public function get_platform(): OP_Platform {
		return $this->platform;
	}

	/**
	 * Get page tree instance.
	 *
	 * @return OP_Page_Tree
	 */
	public function get_page_tree(): OP_Page_Tree {
		return $this->page_tree;
	}

	/**
	 * Get navigation instance.
	 *
	 * @return OP_Navigation
	 */
	public function get_navigation(): OP_Navigation {
		return $this->navigation;
	}

	/**
	 * Get router instance.
	 *
	 * @return OP_Router
	 */
	public function get_router(): OP_Router {
		return $this->router;
	}

	/**
	 * Get diagnostics instance.
	 *
	 * @return OP_Diagnostics
	 */
	public function get_diagnostics(): OP_Diagnostics {
		return $this->diagnostics;
	}

	/**
	 * Get platform status.
	 *
	 * @return string Platform status.
	 */
	public function get_status(): string {
		return $this->status;
	}
}
