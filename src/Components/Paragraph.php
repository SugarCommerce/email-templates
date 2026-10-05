<?php
/**
 * A paragraph of somebody's own words.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.3.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Components;

use SugarCommerce\EmailTemplates\{Component, Context, Html, Style};

/**
 * Class Paragraph
 *
 * Text the application knows at send time — a seller's note to their
 * buyers, a sentence about how long a link lasts — as a component rather
 * than a substitution. The difference is the empty case: `<p>{{note}}</p>`
 * in a body still prints an empty paragraph, and its margin, when there is
 * no note. A component renders nothing at all.
 *
 * Line breaks the writer typed are kept, and added after escaping — doing it
 * the other way round escapes the `<br>` it just added and prints the tag.
 *
 * Two weights: body text, spaced from what follows it, or an aside — small,
 * muted and spaced from what precedes it, for the line under a button.
 *
 * ```php
 * $renderer->register( 'note', new Paragraph( $seller_note ) );
 * $renderer->register( 'expiry', new Paragraph( 'This link works for a week.', aside: true ) );
 * ```
 *
 * @since 1.3.0
 */
final readonly class Paragraph implements Component {

	/**
	 * @since 1.3.0
	 *
	 * @param string $text  Plain text. Escaped here; '' renders nothing.
	 * @param bool   $aside Small and muted, spaced from what is above it,
	 *                      rather than body text spaced from what is below.
	 */
	public function __construct(
		private string $text,
		private bool $aside = false,
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		if ( '' === $this->text ) {
			return '';
		}

		$css = $this->aside
			? 'margin:16px 0 0;font-size:' . $style->theme->small_size . 'px;color:' . $style->palette->muted . ';'
			: 'margin:0 0 16px;font-size:' . $style->theme->font_size . 'px;color:' . $style->palette->text . ';';

		return '<p style="' . $css . '">' . nl2br( Html::escape( $this->text ) ) . '</p>';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		return $this->text;
	}
}
