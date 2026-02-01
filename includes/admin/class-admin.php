<?php
/**
 * Admin orchestrator.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Handles admin menu and page registration.
 */
class OP_Admin {

	/**
	 * Configuration instance.
	 *
	 * @var OP_Config
	 */
	private OP_Config $config;

	/**
	 * Diagnostics instance.
	 *
	 * @var OP_Diagnostics
	 */
	private OP_Diagnostics $diagnostics;

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
	 * Admin page instances.
	 *
	 * @var array
	 */
	private array $pages = [];

	/**
	 * Constructor.
	 *
	 * @param OP_Config      $config      Configuration instance.
	 * @param OP_Diagnostics $diagnostics Diagnostics instance.
	 * @param OP_Platform    $platform    Platform instance.
	 * @param OP_Page_Tree   $page_tree   Page tree instance.
	 * @param OP_Navigation  $navigation  Navigation instance.
	 */
	public function __construct(
		OP_Config $config,
		OP_Diagnostics $diagnostics,
		OP_Platform $platform,
		OP_Page_Tree $page_tree,
		OP_Navigation $navigation
	) {
		$this->config      = $config;
		$this->diagnostics = $diagnostics;
		$this->platform    = $platform;
		$this->page_tree   = $page_tree;
		$this->navigation  = $navigation;

		$this->init();
	}

	/**
	 * Initialize admin.
	 */
	private function init(): void {
		// Initialize admin page classes.
		$this->pages['dashboard']  = new OP_Admin_Dashboard( $this->config, $this->diagnostics );
		$this->pages['platform']   = new OP_Admin_Platform( $this->config, $this->platform, $this->page_tree );
		$this->pages['navigation'] = new OP_Admin_Navigation( $this->navigation );

		// Register hooks.
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu(): void {
		// Main menu.
		add_menu_page(
			__( 'Operational Platform', 'op-platform' ),
			__( 'OP Platform', 'op-platform' ),
			'manage_options',
			'op-platform',
			[ $this->pages['dashboard'], 'render' ],
			'dashicons-admin-site-alt3',
			30
		);

		// Dashboard (same as main).
		add_submenu_page(
			'op-platform',
			__( 'Dashboard', 'op-platform' ),
			__( 'Dashboard', 'op-platform' ),
			'manage_options',
			'op-platform',
			[ $this->pages['dashboard'], 'render' ]
		);

		// Platform Status.
		add_submenu_page(
			'op-platform',
			__( 'Platform Status', 'op-platform' ),
			__( 'Platform', 'op-platform' ),
			'manage_options',
			'op-platform-status',
			[ $this->pages['platform'], 'render' ]
		);

		// Navigation.
		add_submenu_page(
			'op-platform',
			__( 'Navigation', 'op-platform' ),
			__( 'Navigation', 'op-platform' ),
			'manage_options',
			'op-platform-navigation',
			[ $this->pages['navigation'], 'render' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on our admin pages.
		if ( strpos( $hook, 'op-platform' ) === false ) {
			return;
		}

		// Enqueue admin CSS.
		$css_file = OP_PLUGIN_DIR . 'assets/css/admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'op-platform-admin',
				OP_PLUGIN_URL . 'assets/css/admin.css',
				[],
				filemtime( $css_file )
			);
		}
	}

	/**
	 * Handle admin actions.
	 */
	public function handle_actions(): void {
		// Handle configuration save.
		if ( isset( $_POST['op_save_config'] ) && check_admin_referer( 'op_save_config' ) ) {
			$library_path = sanitize_text_field( $_POST['library_path'] ?? '' );
			$this->config->set_library_path( $library_path );
			$this->config->save();

			// Clear caches and trigger rebuild.
			$this->page_tree->clear_cache();
			$this->navigation->clear_cache();
			set_transient( 'op_platform_flush_rewrite', true, 60 );

			wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
			exit;
		}

		// Handle rebuild.
		if ( isset( $_POST['op_rebuild'] ) && check_admin_referer( 'op_rebuild' ) ) {
			$this->page_tree->clear_cache();
			$this->navigation->clear_cache();
			$this->page_tree->build( true );
			$this->navigation->build( true );
			flush_rewrite_rules();

			wp_safe_redirect( add_query_arg( 'rebuilt', '1', wp_get_referer() ) );
			exit;
		}
	}
}
