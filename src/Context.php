<?php
/**
 * The data a template is rendered against.
 *
 * @package   Sugar\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\EmailTemplates;

/**
 * Class Context
 *
 * Wraps the caller's data and resolves dotted paths — `customer.name`,
 * `order.total`, `items.0.title`.
 *
 * @since 1.0.0
 */
final readonly class Context {

	/**
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Arbitrary nested data.
	 */
	public function __construct( private array $data = array() ) {}

	/**
	 * Resolve a dotted path to a scalar.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Dotted path.
	 *
	 * @return string|null Null when absent or not scalar — the caller
	 *                     decides what an unresolved tag should do.
	 */
	public function get( string $path ): ?string {
		$value = $this->raw( $path );

		return match ( true ) {
			is_string( $value )           => $value,
			is_bool( $value )             => $value ? 'yes' : 'no',
			is_int( $value ), is_float( $value ) => (string) $value,
			$value instanceof \Stringable => (string) $value,
			default                       => null,
		};
	}

	/**
	 * Resolve a dotted path to whatever is there.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Dotted path.
	 *
	 * @return mixed Null when any segment is missing.
	 */
	public function raw( string $path ): mixed {
		$node = $this->data;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return null;
			}

			$node = $node[ $segment ];
		}

		return $node;
	}

	/**
	 * A nested array, for a component that iterates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Dotted path.
	 *
	 * @return array<mixed> Empty when absent or not an array.
	 */
	public function list( string $path ): array {
		$value = $this->raw( $path );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Whether a path resolves to anything at all.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Dotted path.
	 *
	 * @return bool
	 */
	public function has( string $path ): bool {
		return null !== $this->raw( $path );
	}

	/**
	 * The underlying data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * A copy with extra values merged over the top.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $extra Values to add.
	 *
	 * @return self
	 */
	public function with( array $extra ): self {
		return new self( array_replace( $this->data, $extra ) );
	}
}
