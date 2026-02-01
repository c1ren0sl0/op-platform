<?php
/**
 * Content Item interface.
 *
 * Defines the contract for individual content items returned by providers.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Interface for content items.
 *
 * Content items are the individual artifacts returned by providers.
 * The platform uses this interface to access item data without
 * knowing the underlying storage mechanism.
 */
interface OP_Content_Item {

	/**
	 * Get item ID.
	 *
	 * @return int Item ID.
	 */
	public function get_id(): int;

	/**
	 * Get item slug.
	 *
	 * @return string URL-safe slug.
	 */
	public function get_slug(): string;

	/**
	 * Get item title.
	 *
	 * @return string Display title.
	 */
	public function get_title(): string;

	/**
	 * Get artifact type.
	 *
	 * @return string Type identifier (e.g., 'source').
	 */
	public function get_type(): string;

	/**
	 * Get item URL.
	 *
	 * @return string Full URL to item detail page.
	 */
	public function get_url(): string;

	/**
	 * Get a specific metadata field.
	 *
	 * @param string $key     Field key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Field value.
	 */
	public function get_meta( string $key, $default = null );

	/**
	 * Get all metadata fields.
	 *
	 * @return array All metadata as key => value pairs.
	 */
	public function get_all_meta(): array;

	/**
	 * Get access tier for this item.
	 *
	 * @return string Access tier (e.g., 'public', 'subscriber', 'premium').
	 */
	public function get_access_tier(): string;

	/**
	 * Get rendered content (for detail pages).
	 *
	 * @return string HTML content.
	 */
	public function get_content(): string;

	/**
	 * Get excerpt (for cards/lists).
	 *
	 * @return string Short excerpt.
	 */
	public function get_excerpt(): string;

	/**
	 * Convert to array representation.
	 *
	 * @return array Item data as array.
	 */
	public function to_array(): array;
}
