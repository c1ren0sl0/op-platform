<?php
/**
 * Configuration management.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Handles plugin configuration storage and retrieval.
 */
class OP_Config {

	/**
	 * Option name in WordPress options table.
	 */
	private const OPTION_NAME = 'op_platform_config';

	/**
	 * Configuration array.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Default configuration values.
	 *
	 * @var array
	 */
	private array $defaults = [
		'library_path' => '',
		'version'      => '',
		'installed_at' => '',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load();
	}

	/**
	 * Load configuration from database.
	 */
	private function load(): void {
		$stored = get_option( self::OPTION_NAME, [] );
		$this->config = $this->merge_defaults( $stored );
	}

	/**
	 * Merge stored config with defaults.
	 *
	 * @param array $stored Stored configuration.
	 * @return array Merged configuration.
	 */
	private function merge_defaults( array $stored ): array {
		return array_replace_recursive( $this->defaults, $stored );
	}

	/**
	 * Save configuration to database.
	 *
	 * @return bool True on success.
	 */
	public function save(): bool {
		return update_option( self::OPTION_NAME, $this->config );
	}

	/**
	 * Get a configuration value.
	 *
	 * @param string $key     Configuration key (supports dot notation: 'features.something').
	 * @param mixed  $default Default value if not found.
	 * @return mixed Configuration value.
	 */
	public function get( string $key, $default = null ) {
		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	/**
	 * Set a configuration value.
	 *
	 * @param string $key   Configuration key (supports dot notation).
	 * @param mixed  $value Value to set.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$keys = explode( '.', $key );
		$ref  = &$this->config;

		foreach ( $keys as $i => $k ) {
			if ( $i === count( $keys ) - 1 ) {
				$ref[ $k ] = $value;
			} else {
				if ( ! isset( $ref[ $k ] ) || ! is_array( $ref[ $k ] ) ) {
					$ref[ $k ] = [];
				}
				$ref = &$ref[ $k ];
			}
		}

		return $this;
	}

	/**
	 * Get all configuration.
	 *
	 * @return array Full configuration array.
	 */
	public function get_all(): array {
		return $this->config;
	}

	/**
	 * Get library path.
	 *
	 * @return string Library path or empty string.
	 */
	public function get_library_path(): string {
		return $this->get( 'library_path', '' );
	}

	/**
	 * Set library path.
	 *
	 * @param string $path Library path.
	 * @return self
	 */
	public function set_library_path( string $path ): self {
		return $this->set( 'library_path', $path );
	}

	/**
	 * Check if library path is configured.
	 *
	 * @return bool True if path is set.
	 */
	public function has_library_path(): bool {
		return ! empty( $this->get_library_path() );
	}

	/**
	 * Validate library path.
	 *
	 * @return array{valid: bool, error: string|null, details: array}
	 */
	public function validate_library_path(): array {
		$path = $this->get_library_path();

		if ( empty( $path ) ) {
			return [
				'valid'   => false,
				'error'   => 'Library path not configured.',
				'details' => [],
			];
		}

		if ( ! file_exists( $path ) ) {
			return [
				'valid'   => false,
				'error'   => 'Library path does not exist.',
				'details' => [ 'path' => $path ],
			];
		}

		if ( ! is_dir( $path ) ) {
			return [
				'valid'   => false,
				'error'   => 'Library path is not a directory.',
				'details' => [ 'path' => $path ],
			];
		}

		if ( ! is_readable( $path ) ) {
			return [
				'valid'   => false,
				'error'   => 'Library path is not readable.',
				'details' => [ 'path' => $path ],
			];
		}

		// Check for expected subdirectory (platform is required).
		$platform_path = rtrim( $path, '/' ) . '/platform';
		if ( ! is_dir( $platform_path ) ) {
			return [
				'valid'   => false,
				'error'   => 'Library path missing required /platform/ directory.',
				'details' => [ 'path' => $path ],
			];
		}

		return [
			'valid'   => true,
			'error'   => null,
			'details' => [ 'path' => $path ],
		];
	}

	/**
	 * Reset configuration to defaults.
	 *
	 * @return self
	 */
	public function reset(): self {
		$this->config = $this->defaults;
		$this->config['version'] = OP_VERSION;
		return $this;
	}

	/**
	 * Delete all configuration (for uninstall).
	 *
	 * @return bool True on success.
	 */
	public static function delete_all(): bool {
		return delete_option( self::OPTION_NAME );
	}
}
