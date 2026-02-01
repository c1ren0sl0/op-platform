<?php
/**
 * Platform filesystem interface.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Handles reading and scanning the /platform/ directory.
 */
class OP_Platform {

	/**
	 * Configuration instance.
	 *
	 * @var OP_Config
	 */
	private OP_Config $config;

	/**
	 * Platform directory path.
	 *
	 * @var string
	 */
	private string $platform_path;

	/**
	 * Constructor.
	 *
	 * @param OP_Config $config Configuration instance.
	 */
	public function __construct( OP_Config $config ) {
		$this->config        = $config;
		$this->platform_path = $this->get_platform_path();
	}

	/**
	 * Get platform directory path.
	 *
	 * @return string Platform path.
	 */
	public function get_platform_path(): string {
		$library_path = $this->config->get_library_path();
		if ( empty( $library_path ) ) {
			return '';
		}
		return rtrim( $library_path, '/' ) . '/platform';
	}

	/**
	 * Check if platform directory exists and is valid.
	 *
	 * @return bool True if valid.
	 */
	public function is_valid(): bool {
		if ( empty( $this->platform_path ) ) {
			return false;
		}
		return is_dir( $this->platform_path ) && is_readable( $this->platform_path );
	}

	/**
	 * Scan platform directory for all page files.
	 *
	 * @return array Array of file paths.
	 */
	public function scan(): array {
		if ( ! $this->is_valid() ) {
			return [];
		}

		return $this->scan_directory( $this->platform_path );
	}

	/**
	 * Recursively scan directory for markdown files.
	 *
	 * @param string $directory Directory to scan.
	 * @return array Array of file paths.
	 */
	private function scan_directory( string $directory ): array {
		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$directory,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'md' ) {
				$files[] = $file->getPathname();
			}
		}

		// Sort files for consistent ordering.
		sort( $files );

		return $files;
	}

	/**
	 * Load all pages from platform directory.
	 *
	 * @return array<string, OP_Page> Pages indexed by route.
	 */
	public function load_pages(): array {
		$files = $this->scan();
		$pages = [];

		foreach ( $files as $file_path ) {
			$page = OP_Page::from_file( $file_path, $this->platform_path );
			if ( $page && ! empty( $page->get_title() ) ) {
				$pages[ $page->get_route() ] = $page;
			}
		}

		return $pages;
	}

	/**
	 * Load a single page by route.
	 *
	 * @param string $route Route to load.
	 * @return OP_Page|null Page or null if not found.
	 */
	public function load_page( string $route ): ?OP_Page {
		if ( ! $this->is_valid() ) {
			return null;
		}

		// Convert route to possible file paths.
		$route = trim( $route, '/' );
		$possible_paths = [
			$this->platform_path . '/' . $route . '/_index.md',
			$this->platform_path . '/' . $route . '.md',
		];

		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) ) {
				return OP_Page::from_file( $path, $this->platform_path );
			}
		}

		return null;
	}

	/**
	 * Get directory structure as nested array.
	 *
	 * @return array Directory structure.
	 */
	public function get_structure(): array {
		if ( ! $this->is_valid() ) {
			return [];
		}

		return $this->build_structure( $this->platform_path );
	}

	/**
	 * Recursively build directory structure.
	 *
	 * @param string $directory Directory to scan.
	 * @param int    $depth     Current depth.
	 * @return array Structure array.
	 */
	private function build_structure( string $directory, int $depth = 0 ): array {
		$structure = [];

		$items = scandir( $directory );
		if ( false === $items ) {
			return $structure;
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$path = $directory . '/' . $item;

			if ( is_dir( $path ) ) {
				$structure[ $item ] = [
					'type'     => 'directory',
					'children' => $this->build_structure( $path, $depth + 1 ),
				];
			} elseif ( pathinfo( $item, PATHINFO_EXTENSION ) === 'md' ) {
				$structure[ $item ] = [
					'type' => 'file',
					'path' => $path,
				];
			}
		}

		return $structure;
	}

	/**
	 * Validate platform structure.
	 *
	 * @return array Validation result.
	 */
	public function validate(): array {
		$errors   = [];
		$warnings = [];
		$pages    = $this->load_pages();

		foreach ( $pages as $route => $page ) {
			// Check title is present.
			if ( empty( $page->get_title() ) ) {
				$errors[] = sprintf(
					'Page at %s missing required "title" field.',
					$page->get_file_path()
				);
			}

			// Check artifact type is resolvable (warning only - might be handled by provider).
			if ( empty( $page->get_artifact_type() ) ) {
				$warnings[] = sprintf(
					'Page at %s has no artifact type (derived or explicit).',
					$page->get_file_path()
				);
			}

			// Check filter fields (basic validation).
			if ( $page->has_filter() ) {
				$filter = $page->get_filter();
				if ( ! is_array( $filter ) ) {
					$errors[] = sprintf(
						'Page at %s has invalid filter format (must be object).',
						$page->get_file_path()
					);
				}
			}
		}

		return [
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
			'pages'    => count( $pages ),
		];
	}

	/**
	 * Get statistics about the platform.
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		$pages = $this->load_pages();

		$stats = [
			'total_pages'      => count( $pages ),
			'index_pages'      => 0,
			'landing_pages'    => 0,
			'by_artifact_type' => [],
			'max_depth'        => 0,
		];

		foreach ( $pages as $page ) {
			if ( $page->is_index() ) {
				$stats['index_pages']++;
			} elseif ( $page->has_filter() ) {
				$stats['landing_pages']++;
			}

			$type = $page->get_artifact_type();
			if ( ! isset( $stats['by_artifact_type'][ $type ] ) ) {
				$stats['by_artifact_type'][ $type ] = 0;
			}
			$stats['by_artifact_type'][ $type ]++;

			if ( $page->get_depth() > $stats['max_depth'] ) {
				$stats['max_depth'] = $page->get_depth();
			}
		}

		return $stats;
	}
}
