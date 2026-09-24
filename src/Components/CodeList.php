<?php
/**
 * Monospaced codes — licence keys, backup codes.
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
 * Class CodeList
 *
 * `{{codes}}` — values a customer will select and copy: licence keys,
 * recovery codes, a one-time password.
 *
 * Rendered in a monospaced face on a tinted panel, which is not
 * decoration: it makes `0` and `O` distinguishable, and it signals that
 * the string is meant to be copied exactly.
 *
 * @since 1.0.0
 */
final readonly class CodeList implements Component {

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		$blocks = '';

		foreach ( $this->entries( $context ) as [ $label, $code ] ) {
			$heading = '' !== $label
				? '<div style="font-size:' . $style->theme->small_size . 'px;color:' . $style->palette->muted . ';margin-bottom:4px;">' . Html::escape( $label ) . '</div>'
				: '';

			$blocks .= '<div style="margin:10px 0;padding:12px 14px;background:' . $style->palette->surface . ';border:1px solid ' . $style->palette->border . ';border-radius:' . $style->theme->radius . 'px;">'
				. $heading
				. '<code style="font-family:' . $style->theme->mono_font . ';font-size:' . $style->theme->font_size . 'px;color:' . $style->palette->text . ';letter-spacing:0.03em;word-break:break-all;">'
				. Html::escape( $code )
				. '</code></div>';
		}

		return $blocks;
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		$lines = array();

		foreach ( $this->entries( $context ) as [ $label, $code ] ) {
			$lines[] = '' !== $label ? $label . ': ' . $code : $code;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Normalise the `codes` list into label/value pairs.
	 *
	 * Accepts a flat list of strings, or a list of arrays carrying a
	 * `code` and an optional `label` — both shapes turn up naturally
	 * depending on whether the codes are named.
	 *
	 * @since 1.0.0
	 *
	 * @param Context $context Data.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function entries( Context $context ): array {
		$entries = array();

		foreach ( $context->list( 'codes' ) as $entry ) {
			if ( is_string( $entry ) ) {
				$entries[] = array( '', $entry );

				continue;
			}

			if ( is_array( $entry ) && '' !== (string) ( $entry['code'] ?? '' ) ) {
				$entries[] = array( (string) ( $entry['label'] ?? '' ), (string) $entry['code'] );
			}
		}

		return $entries;
	}
}
