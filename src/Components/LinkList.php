<?php
/**
 * A list of labelled links.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Components;

use SugarCommerce\EmailTemplates\{Component, Context, Html, Style};

/**
 * Class LinkList
 *
 * `{{links}}` — labelled links from `links` in the context, each with a
 * `label` and a `url`. Download links, invoice links, anything.
 *
 * A link whose URL is not http, https or mailto is dropped rather than
 * rendered inert, so a bad value cannot ship a dead link to a customer.
 *
 * @since 1.0.0
 */
final readonly class LinkList implements Component {

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		$rows = '';

		foreach ( $context->list( 'links' ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$url = Html::url( Context::text( $link['url'] ?? null ) );

			if ( '' === $url ) {
				continue;
			}

			$label = Html::escape( Context::text( $link['label'] ?? $link['url'] ) );
			$note  = Context::text( $link['note'] ?? null );

			$suffix = '' !== $note
				? ' <span style="color:' . $style->palette->muted . ';font-size:' . $style->theme->small_size . 'px;">' . Html::escape( $note ) . '</span>'
				: '';

			$rows .= '<tr><td style="padding:7px 0;font-size:' . $style->theme->font_size . 'px;">'
				. '<a href="' . $url . '" style="color:' . $style->palette->accent . ';text-decoration:underline;">' . $label . '</a>'
				. $suffix
				. '</td></tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:8px 0;">' . $rows . '</table>';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		$lines = array();

		foreach ( $context->list( 'links' ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$url = Context::text( $link['url'] ?? null );

			if ( '' === Html::url( $url ) ) {
				continue;
			}

			$label   = Context::text( $link['label'] ?? $url );
			$note    = Context::text( $link['note'] ?? null );
			$lines[] = '- ' . $label . ( '' !== $note ? ' (' . $note . ')' : '' ) . "\n  " . $url;
		}

		return implode( "\n", $lines );
	}
}
