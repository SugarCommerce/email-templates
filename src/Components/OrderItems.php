<?php
/**
 * Itemised order table.
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
 * Class OrderItems
 *
 * `{{order_items}}` — the line items of a purchase, from `items` in the
 * context. Each entry may carry `title`, `meta`, `quantity` and `total`;
 * only `title` is required.
 *
 * Amounts are taken as already-formatted strings rather than minor units,
 * so this package needs no opinion about currency. Format them with
 * `sugarmill/currency` before handing them over.
 *
 * @since 1.0.0
 */
final readonly class OrderItems implements Component {

	/**
	 * {@inheritDoc}
	 */
	public function html( Context $context, Style $style ): string {
		$items = $context->list( 'items' );

		if ( array() === $items ) {
			return '';
		}

		$rows = '';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title    = Html::escape( (string) ( $item['title'] ?? '' ) );
			$meta     = (string) ( $item['meta'] ?? '' );
			$quantity = (string) ( $item['quantity'] ?? '' );
			$total    = Html::escape( (string) ( $item['total'] ?? '' ) );

			if ( '' !== $meta ) {
				$title .= '<br><span style="color:' . $style->palette->muted . ';font-size:' . $style->theme->small_size . 'px;">' . Html::escape( $meta ) . '</span>';
			}

			if ( '' !== $quantity && '1' !== $quantity ) {
				$title .= ' <span style="color:' . $style->palette->muted . ';">&times;' . Html::escape( $quantity ) . '</span>';
			}

			$rows .= '<tr>'
				. '<td style="padding:10px 0;border-bottom:1px solid ' . $style->palette->border . ';font-size:' . $style->theme->font_size . 'px;line-height:1.5;">' . $title . '</td>'
				. '<td style="padding:10px 0;border-bottom:1px solid ' . $style->palette->border . ';font-size:' . $style->theme->font_size . 'px;text-align:right;white-space:nowrap;">' . $total . '</td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		$total = $context->get( 'order.total' );

		if ( null !== $total ) {
			$rows .= '<tr>'
				. '<td style="padding:12px 0;font-size:' . $style->theme->font_size . 'px;font-weight:700;">Total</td>'
				. '<td style="padding:12px 0;font-size:' . $style->theme->font_size . 'px;font-weight:700;text-align:right;white-space:nowrap;">' . Html::escape( $total ) . '</td>'
				. '</tr>';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:8px 0 4px;">' . $rows . '</table>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function text( Context $context ): string {
		$items = $context->list( 'items' );

		if ( array() === $items ) {
			return '';
		}

		$lines = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$line = '- ' . (string) ( $item['title'] ?? '' );

			if ( '' !== (string) ( $item['meta'] ?? '' ) ) {
				$line .= ' (' . $item['meta'] . ')';
			}

			$quantity = (string) ( $item['quantity'] ?? '' );

			if ( '' !== $quantity && '1' !== $quantity ) {
				$line .= ' x' . $quantity;
			}

			if ( '' !== (string) ( $item['total'] ?? '' ) ) {
				$line .= '  ' . $item['total'];
			}

			$lines[] = $line;
		}

		$total = $context->get( 'order.total' );

		if ( null !== $total ) {
			$lines[] = '';
			$lines[] = 'Total: ' . $total;
		}

		return implode( "\n", $lines );
	}
}
