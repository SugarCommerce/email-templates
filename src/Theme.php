<?php
/**
 * Everything about the look that is not a colour.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Class Theme
 *
 * Type, spacing and shape. Every value a layout or component uses comes
 * from here or from a {@see Palette}, so nothing about the appearance is
 * fixed in the markup.
 *
 * The defaults are deliberately restrained — a system font stack, a
 * 600px column, small radii — because that is what renders predictably
 * across mail clients. Change any of it without touching the package.
 *
 * @since 1.0.0
 */
final readonly class Theme {

	/**
	 * @since 1.0.0
	 *
	 * @param string        $font          Body font stack. Web fonts do
	 *                                     not load in most mail clients,
	 *                                     so this should end in a system
	 *                                     fallback whatever you put first.
	 * @param string        $mono_font     Monospace stack, for codes.
	 * @param int           $width         Content width in pixels.
	 * @param int           $font_size     Base body size in pixels.
	 * @param string        $line_height   Body line height, unitless.
	 * @param int           $padding       Inner padding of the container.
	 * @param int           $radius        Container corner radius. 0 for
	 *                                     square.
	 * @param int           $button_radius Button corner radius.
	 * @param int           $small_size    Secondary text size.
	 * @param Palette|null  $light         Overrides the light palette.
	 * @param Palette|null  $inverted      Overrides the dark palette.
	 */
	public function __construct(
		public string $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
		public string $mono_font = "ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace",
		public int $width = 600,
		public int $font_size = 15,
		public string $line_height = '1.6',
		public int $padding = 32,
		public int $radius = 8,
		public int $button_radius = 6,
		public int $small_size = 13,
		public ?Palette $light = null,
		public ?Palette $inverted = null,
	) {}

	/**
	 * The palette for a variant, honouring any override.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding $branding Sender identity, for the accent.
	 * @param bool     $inverted Whether the dark variant is wanted.
	 *
	 * @return Palette
	 */
	public function palette( Branding $branding, bool $inverted ): Palette {
		if ( $inverted ) {
			return $this->inverted ?? Palette::inverted( $branding );
		}

		return $this->light ?? Palette::light( $branding );
	}

	/**
	 * A copy with different palettes.
	 *
	 * @since 1.0.0
	 *
	 * @param Palette|null $light    Light override.
	 * @param Palette|null $inverted Dark override.
	 *
	 * @return self
	 */
	public function with_palettes( ?Palette $light = null, ?Palette $inverted = null ): self {
		return new self(
			$this->font,
			$this->mono_font,
			$this->width,
			$this->font_size,
			$this->line_height,
			$this->padding,
			$this->radius,
			$this->button_radius,
			$this->small_size,
			$light ?? $this->light,
			$inverted ?? $this->inverted,
		);
	}
}
