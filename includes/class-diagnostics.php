<?php
/**
 * Platform diagnostics.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Provides diagnostic information about platform health.
 */
class OP_Diagnostics {

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
	 * Constructor.
	 *
	 * @param OP_Config    $config    Configuration instance.
	 * @param OP_Platform  $platform  Platform instance.
	 * @param OP_Page_Tree $page_tree Page tree instance.
	 */
	public function __construct(
		OP_Config $config,
		OP_Platform $platform,
		OP_Page_Tree $page_tree
	) {
		$this->config    = $config;
		$this->platform  = $platform;
		$this->page_tree = $page_tree;
	}

	/**
	 * Get overall platform status.
	 *
	 * @return string Status: 'inactive', 'degraded', 'structurally_up'.
	 */
	public function get_status(): string {
		// Check configuration.
		if ( ! $this->config->has_library_path() ) {
			return 'inactive';
		}

		$path_validation = $this->config->validate_library_path();
		if ( ! $path_validation['valid'] ) {
			return 'inactive';
		}

		// Check platform validity.
		if ( ! $this->platform->is_valid() ) {
			return 'degraded';
		}

		// Check page tree.
		if ( ! $this->page_tree->is_built() ) {
			$this->page_tree->build();
		}

		if ( count( $this->page_tree->get_pages() ) === 0 ) {
			return 'degraded';
		}

		return 'structurally_up';
	}

	/**
	 * Get full diagnostic report.
	 *
	 * @return array Diagnostic report.
	 */
	public function get_full_report(): array {
		$report = [
			'status'        => $this->get_status(),
			'generated_at'  => current_time( 'mysql' ),
			'configuration' => $this->check_configuration(),
			'platform'      => $this->check_platform(),
			'page_tree'     => $this->check_page_tree(),
			'providers'     => $this->check_providers(),
		];

		return $report;
	}

	/**
	 * Check configuration status.
	 *
	 * @return array Configuration check result.
	 */
	public function check_configuration(): array {
		$result = [
			'valid'        => false,
			'library_path' => $this->config->get_library_path(),
			'errors'       => [],
		];

		if ( ! $this->config->has_library_path() ) {
			$result['errors'][] = 'Library path not configured.';
			return $result;
		}

		$path_validation = $this->config->validate_library_path();
		if ( ! $path_validation['valid'] ) {
			$result['errors'][] = $path_validation['error'];
			return $result;
		}

		$result['valid'] = true;
		$result['details'] = $path_validation['details'];

		return $result;
	}

	/**
	 * Check platform status.
	 *
	 * @return array Platform check result.
	 */
	public function check_platform(): array {
		$result = [
			'valid'  => false,
			'errors' => [],
			'stats'  => [],
		];

		if ( ! $this->platform->is_valid() ) {
			$result['errors'][] = 'Platform directory not accessible.';
			return $result;
		}

		$validation = $this->platform->validate();
		$result['valid']    = $validation['valid'];
		$result['errors']   = $validation['errors'];
		$result['warnings'] = $validation['warnings'];
		$result['stats']    = $this->platform->get_stats();

		return $result;
	}

	/**
	 * Check page tree status.
	 *
	 * @return array Page tree check result.
	 */
	public function check_page_tree(): array {
		$result = [
			'built'    => $this->page_tree->is_built(),
			'built_at' => $this->page_tree->get_built_at(),
			'stats'    => $this->page_tree->get_stats(),
		];

		return $result;
	}

	/**
	 * Check registered providers.
	 *
	 * @return array Provider check result.
	 */
	public function check_providers(): array {
		return OP_Provider_Registry::get_stats();
	}

	/**
	 * Get status label for display.
	 *
	 * @param string $status Status code.
	 * @return string Human-readable label.
	 */
	public function get_status_label( string $status ): string {
		$labels = [
			'inactive'        => __( 'Inactive', 'op-platform' ),
			'degraded'        => __( 'Degraded', 'op-platform' ),
			'structurally_up' => __( 'Structurally Up', 'op-platform' ),
		];

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Get status color class for display.
	 *
	 * @param string $status Status code.
	 * @return string CSS class.
	 */
	public function get_status_class( string $status ): string {
		$classes = [
			'inactive'        => 'op-status-error',
			'degraded'        => 'op-status-warning',
			'structurally_up' => 'op-status-success',
		];

		return $classes[ $status ] ?? 'op-status-unknown';
	}
}
