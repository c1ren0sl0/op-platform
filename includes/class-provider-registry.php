<?php
/**
 * Provider Registry.
 *
 * Manages registration and lookup of content providers.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Registry for content providers.
 *
 * Content providers register themselves via the 'op_platform_register_providers'
 * action hook. The platform then uses this registry to find providers for
 * artifact types.
 */
class OP_Provider_Registry {

	/**
	 * Registered providers.
	 *
	 * @var array<string, OP_Content_Provider>
	 */
	private static array $providers = [];

	/**
	 * Type to provider mapping cache.
	 *
	 * @var array<string, string>
	 */
	private static array $type_map = [];

	/**
	 * Register a content provider.
	 *
	 * @param OP_Content_Provider $provider Provider instance.
	 */
	public static function register( OP_Content_Provider $provider ): void {
		$id = $provider->get_id();
		self::$providers[ $id ] = $provider;

		// Update type map.
		foreach ( $provider->get_types() as $type ) {
			self::$type_map[ $type ] = $id;
		}
	}

	/**
	 * Unregister a provider.
	 *
	 * @param string $id Provider ID.
	 */
	public static function unregister( string $id ): void {
		if ( ! isset( self::$providers[ $id ] ) ) {
			return;
		}

		// Remove from type map.
		$provider = self::$providers[ $id ];
		foreach ( $provider->get_types() as $type ) {
			unset( self::$type_map[ $type ] );
		}

		unset( self::$providers[ $id ] );
	}

	/**
	 * Get a provider by ID.
	 *
	 * @param string $id Provider ID.
	 * @return OP_Content_Provider|null Provider or null if not found.
	 */
	public static function get( string $id ): ?OP_Content_Provider {
		return self::$providers[ $id ] ?? null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array<string, OP_Content_Provider> All providers indexed by ID.
	 */
	public static function get_all(): array {
		return self::$providers;
	}

	/**
	 * Get provider for an artifact type.
	 *
	 * @param string $type Artifact type identifier.
	 * @return OP_Content_Provider|null Provider or null if no provider handles this type.
	 */
	public static function get_for_type( string $type ): ?OP_Content_Provider {
		if ( ! isset( self::$type_map[ $type ] ) ) {
			return null;
		}

		return self::$providers[ self::$type_map[ $type ] ] ?? null;
	}

	/**
	 * Check if a type has a registered provider.
	 *
	 * @param string $type Artifact type identifier.
	 * @return bool True if a provider handles this type.
	 */
	public static function has_provider_for_type( string $type ): bool {
		return isset( self::$type_map[ $type ] );
	}

	/**
	 * Get all registered artifact types.
	 *
	 * @return array Array of type identifiers.
	 */
	public static function get_all_types(): array {
		return array_keys( self::$type_map );
	}

	/**
	 * Get type configuration.
	 *
	 * @param string $type Artifact type identifier.
	 * @return OP_Type_Config|null Type configuration or null.
	 */
	public static function get_type_config( string $type ): ?OP_Type_Config {
		$provider = self::get_for_type( $type );
		if ( ! $provider ) {
			return null;
		}

		return $provider->get_type_config( $type );
	}

	/**
	 * Get registry statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_stats(): array {
		$stats = [
			'providers'      => count( self::$providers ),
			'types'          => count( self::$type_map ),
			'provider_list'  => [],
		];

		foreach ( self::$providers as $id => $provider ) {
			$stats['provider_list'][] = [
				'id'    => $id,
				'label' => $provider->get_label(),
				'types' => $provider->get_types(),
			];
		}

		return $stats;
	}

	/**
	 * Clear all registrations (for testing).
	 */
	public static function clear(): void {
		self::$providers = [];
		self::$type_map  = [];
	}
}
