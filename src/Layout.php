<?php
/**
 * The wrapper chrome around a message body.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Enum Layout
 *
 * Layouts are developer-owned; message bodies are operator-owned. That
 * split is the point of the package — an operator rewording a receipt
 * cannot break the table scaffolding that makes it render in Outlook,
 * and cannot delete the postal address in the footer.
 *
 * Three designs, each with an inverted variant:
 *
 *   Card     a bordered card on a tinted page
 *   Plain    text on the page, no container
 *   Ruled    hairline rules instead of a border, for documents
 *
 * All are table-based with inline styles, because Outlook on Windows
 * renders through Word — no flexbox, no grid, and most of `<style>`
 * discarded.
 *
 * @since 1.0.0
 */
enum Layout: string {

	/**
	 * A bordered card on a tinted page.
	 *
	 * @since 1.0.0
	 */
	case Card = 'card';

	/**
	 * The card, dark.
	 *
	 * @since 1.0.0
	 */
	case CardInverted = 'card-inverted';

	/**
	 * Text on the page, no container.
	 *
	 * @since 1.0.0
	 */
	case Plain = 'plain';

	/**
	 * Plain, dark.
	 *
	 * @since 1.0.0
	 */
	case PlainInverted = 'plain-inverted';

	/**
	 * Hairline rules instead of a border, for documents.
	 *
	 * @since 1.0.0
	 */
	case Ruled = 'ruled';

	/**
	 * Ruled, dark.
	 *
	 * @since 1.0.0
	 */
	case RuledInverted = 'ruled-inverted';

	/**
	 * Whether this is a dark variant.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_inverted(): bool {
		return str_ends_with( $this->value, '-inverted' );
	}

	/**
	 * The opposite variant of the same design.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function inverted(): self {
		return $this->is_inverted()
			? self::from( substr( $this->value, 0, -9 ) )
			: self::from( $this->value . '-inverted' );
	}

	/**
	 * The design, ignoring the variant.
	 *
	 * @since 1.0.0
	 *
	 * @return self The light form.
	 */
	public function base(): self {
		return $this->is_inverted() ? $this->inverted() : $this;
	}

	/**
	 * The colours this layout hands to its components.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding $branding Sender identity.
	 *
	 * @return Palette
	 */
	public function palette( Branding $branding ): Palette {
		return $this->is_inverted() ? Palette::inverted( $branding ) : Palette::light( $branding );
	}

	/**
	 * The layout HTML, with `{{content}}` and branding tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the layout file is missing.
	 */
	public function html(): string {
		$file = __DIR__ . '/Layouts/' . $this->base()->value . '.html';
		$html = @file_get_contents( $file );

		if ( false === $html ) {
			throw new \RuntimeException( 'Missing layout file: ' . $file );
		}

		return $html;
	}

	/**
	 * A human label, for a settings dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function label(): string {
		$label = match ( $this->base() ) {
			self::Card  => 'Card',
			self::Plain => 'Plain',
			self::Ruled => 'Ruled',
			default     => 'Card',
		};

		return $this->is_inverted() ? $label . ' (inverted)' : $label;
	}

	/**
	 * When to reach for each one.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function description(): string {
		$base = match ( $this->base() ) {
			self::Card  => 'A bordered card on a tinted page. The default for most mail.',
			self::Plain => 'Text on the page, no container. Reads as personal rather than automated.',
			self::Ruled => 'Hairline rules instead of a border. For receipts and invoices.',
			default     => '',
		};

		return $this->is_inverted() ? $base . ' Dark for every reader, not a response to their theme.' : $base;
	}

	/**
	 * Every layout, as value => label, for a settings dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $include_inverted Whether to list the dark variants.
	 *
	 * @return array<string, string>
	 */
	public static function options( bool $include_inverted = true ): array {
		$options = array();

		foreach ( self::cases() as $case ) {
			if ( ! $include_inverted && $case->is_inverted() ) {
				continue;
			}

			$options[ $case->value ] = $case->label();
		}

		return $options;
	}
}
