<?php
/**
 * A highlighted line that must not be missed.
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
 * Class Notice
 *
 * A banner at the top of a message: "Test mode. Nothing was charged." Plain
 * body text is exactly what a reader skims past, and the one sentence that
 * says an order was not real is the one they must not skim.
 *
 * The colours are the one exception to "every colour comes from the Style":
 * a warning is amber in every theme. Dark text on a pale amber ground reads
 * on a light page and on an inverted one alike, so it needs no variant, and
 * a sender's accent colour must not be able to turn a warning into
 * something that looks like a promotion.
 *
 * In the text part the title leads the message on one line, so a text
 * client shows the same warning first.
 *
 * @since 1.3.0
 */
final readonly class Notice implements Component {

	/**
	 * The banner's background.
	 *
	 * @since 1.3.0
	 */
	public const string BACKGROUND = '#fef3c7';

	/**
	 * The banner's text.
	 *
	 * @since 1.3.0
	 */
	public const string INK = '#78350f';

	/**
	 * Hold the warning.
	 *
	 * @since 1.3.0
	 *
	 * @param string $title   A few words in bold: "Test mode." May be ''.
	 * @param string $message The sentence after it. With no title either,
	 *                        nothing renders.
	 */
	public function __construct(
		private string $title,
		private string $message,
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		if ( '' === $this->title && '' === $this->message ) {
			return '';
		}

		$title = '' === $this->title ? '' : '<strong>' . Html::escape( $this->title ) . '</strong> ';

		return '<p style="margin:0 0 16px;padding:10px 12px;border-radius:' . $style->theme->radius . 'px;'
			. 'background:' . self::BACKGROUND . ';color:' . self::INK . ';font-size:' . $style->theme->small_size . 'px;">'
			. $title . Html::escape( $this->message ) . '</p>';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		return trim( $this->title . ' ' . $this->message );
	}
}
