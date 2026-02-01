<?php
/**
 * Type Config interface.
 *
 * Defines the configuration structure for an artifact type.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Interface for artifact type configuration.
 *
 * Type configs tell the platform how to display and interact with
 * artifacts of a specific type. This is how providers communicate
 * their schema to the schema-ignorant platform.
 */
interface OP_Type_Config {

	/**
	 * Get the type identifier.
	 *
	 * @return string Type identifier (e.g., 'source').
	 */
	public function get_type(): string;

	/**
	 * Get singular label.
	 *
	 * @return string Singular label (e.g., 'Source').
	 */
	public function get_label(): string;

	/**
	 * Get plural label.
	 *
	 * @return string Plural label (e.g., 'Sources').
	 */
	public function get_plural_label(): string;

	/**
	 * Get URL slug base for detail pages.
	 *
	 * @return string Slug base (e.g., 'source' for /source/{slug}/).
	 */
	public function get_slug_base(): string;

	/**
	 * Get filterable fields configuration.
	 *
	 * @return array Array of filter field configurations:
	 *               [
	 *                   [
	 *                       'field'   => 'type',           // Field key
	 *                       'label'   => 'Type',           // Display label
	 *                       'type'    => 'select',         // select|search|date_range
	 *                       'options' => [                 // For select type
	 *                           ['value' => 'report', 'label' => 'Report'],
	 *                           ...
	 *                       ],
	 *                   ],
	 *                   ...
	 *               ]
	 */
	public function get_filters(): array;

	/**
	 * Get sortable fields configuration.
	 *
	 * @return array Array of sort field configurations:
	 *               [
	 *                   [
	 *                       'field'   => 'title',
	 *                       'label'   => 'Title',
	 *                       'default' => 'asc',  // Optional default direction
	 *                   ],
	 *                   ...
	 *               ]
	 */
	public function get_sorts(): array;

	/**
	 * Get card display configuration.
	 *
	 * @return array Card configuration:
	 *               [
	 *                   'title_field'    => 'title',
	 *                   'subtitle_field' => 'publisher',  // Optional
	 *                   'badges' => [
	 *                       ['field' => 'type', 'class' => 'badge-type'],
	 *                       ...
	 *                   ],
	 *               ]
	 */
	public function get_card_config(): array;

	/**
	 * Get detail page field configuration.
	 *
	 * @return array Array of detail field configurations:
	 *               [
	 *                   [
	 *                       'field'  => 'publisher',
	 *                       'label'  => 'Publisher',
	 *                       'format' => 'text',  // text|date|url|email
	 *                   ],
	 *                   ...
	 *               ]
	 */
	public function get_detail_fields(): array;

	/**
	 * Convert to array representation.
	 *
	 * @return array Full type configuration as array.
	 */
	public function to_array(): array;
}
