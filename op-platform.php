<?php
/**
 * Plugin Name: Operational Platform
 * Plugin URI: https://6pointco.com
 * Description: Schema-ignorant operational content platform for WordPress. Provides page tree, navigation, and routing from filesystem-based /platform/ directory.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: 6pointco
 * Author URI: https://6pointco.com
 * License: Proprietary
 * Text Domain: op-platform
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'OP_VERSION', '1.0.0' );
define( 'OP_PLUGIN_FILE', __FILE__ );
define( 'OP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check minimum requirements before loading.
 *
 * @return bool True if requirements met.
 */
function op_check_requirements(): bool {
	$php_version = '8.2';
	$wp_version  = '6.4';

	if ( version_compare( PHP_VERSION, $php_version, '<' ) ) {
		add_action( 'admin_notices', function () use ( $php_version ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					esc_html__( 'Operational Platform requires PHP %s or higher. You are running PHP %s.', 'op-platform' ),
					esc_html( $php_version ),
					esc_html( PHP_VERSION )
				)
			);
		} );
		return false;
	}

	global $wp_version;
	if ( version_compare( $wp_version, $wp_version, '<' ) ) {
		add_action( 'admin_notices', function () use ( $wp_version ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					esc_html__( 'Operational Platform requires WordPress %s or higher.', 'op-platform' ),
					esc_html( $wp_version )
				)
			);
		} );
		return false;
	}

	return true;
}

/**
 * Load plugin classes.
 */
function op_load_classes(): void {
	// Interfaces (must be loaded first).
	require_once OP_PLUGIN_DIR . 'includes/interfaces/interface-content-provider.php';
	require_once OP_PLUGIN_DIR . 'includes/interfaces/interface-type-config.php';
	require_once OP_PLUGIN_DIR . 'includes/interfaces/interface-content-item.php';

	// Core classes.
	require_once OP_PLUGIN_DIR . 'includes/class-config.php';
	require_once OP_PLUGIN_DIR . 'includes/class-markdown-parser.php';
	require_once OP_PLUGIN_DIR . 'includes/class-provider-registry.php';

	// Entity classes.
	require_once OP_PLUGIN_DIR . 'includes/entities/class-page.php';

	// Platform classes.
	require_once OP_PLUGIN_DIR . 'includes/class-platform.php';
	require_once OP_PLUGIN_DIR . 'includes/class-page-tree.php';
	require_once OP_PLUGIN_DIR . 'includes/class-navigation.php';
	require_once OP_PLUGIN_DIR . 'includes/class-router.php';
	require_once OP_PLUGIN_DIR . 'includes/class-diagnostics.php';

	// Admin classes.
	require_once OP_PLUGIN_DIR . 'includes/admin/class-admin.php';
	require_once OP_PLUGIN_DIR . 'includes/admin/class-admin-dashboard.php';
	require_once OP_PLUGIN_DIR . 'includes/admin/class-admin-platform.php';
	require_once OP_PLUGIN_DIR . 'includes/admin/class-admin-navigation.php';

	// Main plugin class (must be last).
	require_once OP_PLUGIN_DIR . 'includes/class-plugin.php';
}

/**
 * Initialize the plugin.
 *
 * @return OP_Plugin|null Plugin instance or null if requirements not met.
 */
function op_init(): ?OP_Plugin {
	if ( ! op_check_requirements() ) {
		return null;
	}

	op_load_classes();

	return OP_Plugin::get_instance();
}

// Initialize on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', 'op_init' );

/**
 * Activation hook.
 */
function op_activate(): void {
	// Ensure classes are loaded for activation.
	op_load_classes();

	// Set default options if not already set.
	if ( false === get_option( 'op_platform_config' ) ) {
		$default_config = array(
			'library_path' => '',
			'version'      => OP_VERSION,
			'installed_at' => current_time( 'mysql' ),
		);
		add_option( 'op_platform_config', $default_config );
	}

	// Flush rewrite rules on next load.
	set_transient( 'op_platform_flush_rewrite', true, 60 );
}
register_activation_hook( __FILE__, 'op_activate' );

/**
 * Deactivation hook.
 */
function op_deactivate(): void {
	// Clean up transients.
	delete_transient( 'op_platform_flush_rewrite' );
	delete_transient( 'op_page_tree' );
	delete_transient( 'op_navigation' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'op_deactivate' );
