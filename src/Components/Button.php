<?php
/**
 * A single call-to-action button.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates\Components;

use SugarMill\EmailTemplates\{Component, Context, Html, Style};

/**
 * Class Button
 *
 * `{{button}}` — one call to action, from `button.label` and
 * `button.url`.
 *
 * Built as a bordered table cell rather than a styled `<a>`, because
 * Outlook on Windows renders through Word and drops padding on inline
 * elements — the usual cause of a "button" arriving as bare underlined
 * text.
 *
 * The URL is repeated beneath in the text part, since a plain-text
 * reader cannot click anything.
 *
 * @since 1.0.0
 */
final readonly class Button implements Component {

	/**
	 * {@inheritDoc}
	 */
	public function html( Context $context, Style $style ): string {
		$url = Html::url( (string) $context->get( 'button.url' ) );

		if ( '' === $url ) {
			return '';
		}

		$label = Html::escape( (string) ( $context->get( 'button.label' ) ?? 'Continue' ) );

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">'
			. '<tr><td style="background:' . $style->palette->accent . ';border-radius:' . $style->theme->radius . 'px;">'
			. '<a href="' . $url . '" style="display:inline-block;padding:12px 22px;font-size:' . $style->theme->font_size . 'px;font-weight:600;'
			. 'color:' . $style->palette->accent_text . ';text-decoration:none;">'
			. $label
			. '</a></td></tr></table>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function text( Context $context ): string {
		$url = (string) $context->get( 'button.url' );

		if ( '' === Html::url( $url ) ) {
			return '';
		}

		$label = (string) ( $context->get( 'button.label' ) ?? 'Continue' );

		return $label . ':' . "\n" . $url;
	}
}
