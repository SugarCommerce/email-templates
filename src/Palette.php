<?php
/**
 * The colours a layout hands to its components.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Class Palette
 *
 * Components cannot hardcode colours: the same order table has to work
 * on white and on near-black, and a grey that reads as "secondary" on
 * one is invisible on the other.
 *
 * The values are Notion's, near enough — a warm off-black rather than
 * pure `#000`, and hairlines light enough to separate without drawing
 * attention. Pure black on pure white is harsher than it looks on a
 * screen, and it is the quickest way to make an email feel like a
 * system notice.
 *
 * @since 1.0.0
 */
final readonly class Palette {

	/**
	 * @since 1.0.0
	 *
	 * @param string $text        Primary body text.
	 * @param string $muted       Secondary text — meta, notes, footers.
	 * @param string $border      Hairlines and table rules.
	 * @param string $surface     Panel fill, for code blocks.
	 * @param string $card        Container fill, where a layout uses one.
	 * @param string $background  Page background behind the container.
	 * @param string $accent      Brand accent, for links and buttons.
	 * @param string $accent_text Readable text on top of the accent.
	 * @param bool   $inverted    Whether this is the dark variant.
	 */
	public function __construct(
		public string $text,
		public string $muted,
		public string $border,
		public string $surface,
		public string $card,
		public string $background,
		public string $accent,
		public string $accent_text,
		public bool $inverted = false,
	) {}

	/**
	 * A copy with some colours replaced.
	 *
	 * Overriding one value should not mean restating the other seven:
	 *
	 *   Palette::light( $branding )->with( text: '#000000' );
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $text        Primary text.
	 * @param string|null $muted       Secondary text.
	 * @param string|null $border      Hairlines.
	 * @param string|null $surface     Panel fill.
	 * @param string|null $card        Container fill.
	 * @param string|null $background  Page background.
	 * @param string|null $accent      Accent.
	 * @param string|null $accent_text Text on the accent.
	 *
	 * @return self
	 */
	public function with(
		?string $text = null,
		?string $muted = null,
		?string $border = null,
		?string $surface = null,
		?string $card = null,
		?string $background = null,
		?string $accent = null,
		?string $accent_text = null,
	): self {
		return new self(
			$text ?? $this->text,
			$muted ?? $this->muted,
			$border ?? $this->border,
			$surface ?? $this->surface,
			$card ?? $this->card,
			$background ?? $this->background,
			$accent ?? $this->accent,
			$accent_text ?? $this->accent_text,
			$this->inverted,
		);
	}

	/**
	 * The light palette.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding $branding Sender identity, for the accent.
	 *
	 * @return self
	 */
	public static function light( Branding $branding ): self {
		return new self(
			text: '#37352f',
			muted: '#787774',
			border: '#e9e9e7',
			surface: '#f7f6f3',
			card: '#ffffff',
			background: '#f7f6f3',
			accent: $branding->accent,
			accent_text: $branding->accent_foreground(),
		);
	}

	/**
	 * The inverted palette.
	 *
	 * Note this is dark for every reader, not a response to their system
	 * theme — `prefers-color-scheme` support across mail clients is too
	 * inconsistent to depend on, and a half-applied dark mode is worse
	 * than none.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding $branding Sender identity, for the accent.
	 *
	 * @return self
	 */
	public static function inverted( Branding $branding ): self {
		return new self(
			text: '#d4d4d4',
			muted: '#9b9b9b',
			border: '#2f2f2f',
			surface: '#252525',
			card: '#202020',
			background: '#191919',
			accent: $branding->accent,
			accent_text: $branding->accent_foreground(),
			inverted: true,
		);
	}
}
