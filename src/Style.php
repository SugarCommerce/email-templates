<?php
/**
 * The resolved design handed to a component.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates;

/**
 * Class Style
 *
 * Branding, palette and theme in one object, so a component takes a
 * single argument and gaining a new design token later does not change
 * every signature in the package.
 *
 * @since 1.0.0
 */
final readonly class Style {

	/**
	 * @since 1.0.0
	 *
	 * @param Branding $branding Sender identity.
	 * @param Palette  $palette  Colours for the layout in use.
	 * @param Theme    $theme    Type, spacing and shape.
	 */
	public function __construct(
		public Branding $branding,
		public Palette $palette,
		public Theme $theme,
	) {}

	/**
	 * Every design token, for interpolation into a layout.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function tokens(): array {
		return array(
			'{{accent}}'        => $this->palette->accent,
			'{{accent_text}}'   => $this->palette->accent_text,
			'{{text}}'          => $this->palette->text,
			'{{muted}}'         => $this->palette->muted,
			'{{border}}'        => $this->palette->border,
			'{{surface}}'       => $this->palette->surface,
			'{{card}}'          => $this->palette->card,
			'{{background}}'    => $this->palette->background,
			'{{font}}'          => $this->theme->font,
			'{{mono_font}}'     => $this->theme->mono_font,
			'{{width}}'         => (string) $this->theme->width,
			'{{font_size}}'     => (string) $this->theme->font_size,
			'{{small_size}}'    => (string) $this->theme->small_size,
			'{{line_height}}'   => $this->theme->line_height,
			'{{padding}}'       => (string) $this->theme->padding,
			'{{radius}}'        => (string) $this->theme->radius,
			'{{button_radius}}' => (string) $this->theme->button_radius,
		);
	}
}
