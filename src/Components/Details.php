<?php
/**
 * A label/value summary block.
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
 * Class Details
 *
 * `{{details}}` — a small label/value table from `details` in the
 * context. Order number, date, payment method, tracking reference.
 *
 * Accepts a map (`['Order' => '1234']`) or a list of pairs
 * (`[['Order', '1234']]`). The list form is what a loop tends to build,
 * and it is the only one that can repeat a label.
 *
 * @since 1.0.0
 */
final readonly class Details implements Component {

	/**
	 * {@inheritDoc}
	 */
	public function html( Context $context, Style $style ): string {
		$rows = '';

		foreach ( $this->pairs( $context ) as [ $label, $value ] ) {
			$rows .= '<tr>'
				. '<td style="padding:5px 12px 5px 0;font-size:' . $style->theme->font_size . 'px;color:' . $style->palette->muted . ';white-space:nowrap;vertical-align:top;">' . Html::escape( $label ) . '</td>'
				. '<td style="padding:5px 0;font-size:' . $style->theme->font_size . 'px;color:' . $style->palette->text . ';">' . Html::escape( $value ) . '</td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;">' . $rows . '</table>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function text( Context $context ): string {
		$lines = array();

		foreach ( $this->pairs( $context ) as [ $label, $value ] ) {
			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Normalise either shape into a list of pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param Context $context Data.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function pairs( Context $context ): array {
		$pairs = array();

		foreach ( $context->list( 'details' ) as $key => $value ) {
			if ( is_array( $value ) ) {
				$pairs[] = array( $this->text_of( $value[0] ?? '' ), $this->text_of( $value[1] ?? '' ) );

				continue;
			}

			$pairs[] = array( $this->text_of( $key ), $this->text_of( $value ) );
		}

		return $pairs;
	}

	/**
	 * Coerce a cell without tripping over an array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw cell.
	 *
	 * @return string
	 */
	private function text_of( mixed $value ): string {
		return match ( true ) {
			is_string( $value )           => $value,
			is_scalar( $value )           => (string) $value,
			$value instanceof \Stringable => (string) $value,
			default                       => '',
		};
	}
}
