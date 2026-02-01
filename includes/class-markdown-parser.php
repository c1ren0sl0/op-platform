<?php
/**
 * Markdown parser utility.
 *
 * Provides shared markdown/YAML parsing functionality.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Utility class for parsing markdown files with YAML frontmatter.
 */
class OP_Markdown_Parser {

	/**
	 * Parse frontmatter from markdown content.
	 *
	 * @param string $content File content.
	 * @return array|null Parsed data with 'frontmatter' and 'body' keys, or null on failure.
	 */
	public static function parse_frontmatter( string $content ): ?array {
		$content = ltrim( $content );

		if ( strpos( $content, '---' ) !== 0 ) {
			return [
				'frontmatter' => [],
				'body'        => $content,
			];
		}

		$parts = preg_split( '/^---\s*$/m', $content, 3 );
		if ( count( $parts ) < 3 ) {
			return null;
		}

		$yaml_content = $parts[1];
		$body = trim( $parts[2] );

		// Parse YAML.
		$frontmatter = self::parse_yaml( $yaml_content );

		return [
			'frontmatter' => $frontmatter,
			'body'        => $body,
		];
	}

	/**
	 * Parse simple YAML content.
	 *
	 * @param string $yaml_content YAML string.
	 * @return array Parsed key-value pairs.
	 */
	public static function parse_yaml( string $yaml_content ): array {
		$frontmatter = [];
		$lines = explode( "\n", $yaml_content );
		$current_key = null;
		$current_array = [];
		$in_array = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( empty( $trimmed ) || strpos( $trimmed, '#' ) === 0 ) {
				continue;
			}

			// Array item.
			if ( strpos( $trimmed, '- ' ) === 0 && $in_array ) {
				$current_array[] = trim( substr( $trimmed, 2 ) );
				continue;
			}

			// Save previous array.
			if ( $in_array && $current_key ) {
				$frontmatter[ $current_key ] = $current_array;
				$current_array = [];
				$in_array = false;
			}

			// Key-value pair.
			if ( strpos( $trimmed, ':' ) !== false ) {
				list( $key, $value ) = explode( ':', $trimmed, 2 );
				$key = trim( $key );
				$value = trim( $value );

				if ( empty( $value ) ) {
					$current_key = $key;
					$in_array = true;
					$current_array = [];
				} else {
					if ( strpos( $value, '[' ) === 0 ) {
						$value = trim( $value, '[]' );
						$value = array_map( 'trim', explode( ',', $value ) );
					} elseif ( $value === 'true' ) {
						$value = true;
					} elseif ( $value === 'false' ) {
						$value = false;
					}
					$frontmatter[ $key ] = $value;
				}
			}
		}

		if ( $in_array && $current_key ) {
			$frontmatter[ $current_key ] = $current_array;
		}

		return $frontmatter;
	}

	/**
	 * Parse markdown file from path.
	 *
	 * @param string $file_path Path to markdown file.
	 * @return array|null Parsed data with 'frontmatter', 'body', and 'content' keys, or null on failure.
	 */
	public static function parse_file( string $file_path ): ?array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return null;
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return null;
		}

		$parsed = self::parse_frontmatter( $content );
		if ( null === $parsed ) {
			return null;
		}

		$parsed['content'] = $content;
		return $parsed;
	}
}
