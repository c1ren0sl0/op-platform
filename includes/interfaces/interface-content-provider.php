<?php
/**
 * Content Provider interface.
 *
 * Defines the contract that content providers must implement to supply
 * artifacts to the operational platform.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Interface for content providers.
 *
 * Content providers supply artifact data to the operational platform.
 * The platform is schema-ignorant - it doesn't know about specific
 * field structures. Providers translate their internal schemas into
 * the field configurations the platform understands.
 */
interface OP_Content_Provider {

	/**
	 * Get unique provider identifier.
	 *
	 * @return string Provider ID (e.g., 'decision-substrate').
	 */
	public function get_id(): string;

	/**
	 * Get human-readable provider label.
	 *
	 * @return string Provider label for admin UI.
	 */
	public function get_label(): string;

	/**
	 * Get artifact types this provider supplies.
	 *
	 * @return array Array of type identifiers (e.g., ['source', 'profile', 'content']).
	 */
	public function get_types(): array;

	/**
	 * Get configuration for a specific artifact type.
	 *
	 * @param string $type Artifact type identifier.
	 * @return OP_Type_Config|null Type configuration or null if not found.
	 */
	public function get_type_config( string $type ): ?OP_Type_Config;

	/**
	 * Query artifacts of a type.
	 *
	 * @param string $type Artifact type identifier.
	 * @param array  $args Query arguments:
	 *                     - 'filters' => array of field => value pairs
	 *                     - 'orderby' => field to sort by
	 *                     - 'order'   => 'ASC' or 'DESC'
	 *                     - 'page'    => page number (1-indexed)
	 *                     - 'per_page'=> items per page
	 * @return array {
	 *     Query results.
	 *     @type OP_Content_Item[] $items    Array of content items.
	 *     @type int               $total    Total items matching query.
	 *     @type int               $page     Current page.
	 *     @type int               $per_page Items per page.
	 *     @type int               $pages    Total pages.
	 * }
	 */
	public function query( string $type, array $args = [] ): array;

	/**
	 * Get a single artifact by type and slug.
	 *
	 * @param string $type Artifact type identifier.
	 * @param string $slug Artifact slug.
	 * @return OP_Content_Item|null Content item or null if not found.
	 */
	public function get_item( string $type, string $slug ): ?OP_Content_Item;

	/**
	 * Check access to an artifact.
	 *
	 * @param string   $type    Artifact type identifier.
	 * @param string   $slug    Artifact slug.
	 * @param int|null $user_id User ID to check access for (null = current user).
	 * @return array {
	 *     Access check result.
	 *     @type bool   $allowed Whether access is allowed.
	 *     @type string $reason  Reason for denial (if denied).
	 *     @type string $tier    Required access tier (e.g., 'subscriber', 'premium').
	 * }
	 */
	public function check_access( string $type, string $slug, ?int $user_id = null ): array;

	/**
	 * Get detail page template path for an artifact type.
	 *
	 * @param string $type Artifact type identifier.
	 * @return string|null Absolute path to template file or null to use default.
	 */
	public function get_detail_template( string $type ): ?string;

	/**
	 * Get card template path for grid rendering.
	 *
	 * @param string $type Artifact type identifier.
	 * @return string|null Absolute path to card template or null to use default.
	 */
	public function get_card_template( string $type ): ?string;
}
