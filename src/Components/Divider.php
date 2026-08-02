<?php
/**
 * A horizontal rule.
 *
 * @package   Sugar\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\EmailTemplates\Components;

use Sugar\EmailTemplates\{Component, Context, Html, Style};

/**
 * Class Divider
 *
 * `{{divider}}` — a hairline rule between sections.
 *
 * A styled table row rather than an `<hr>`, which mail clients render
 * with wildly different default margins and colours.
 *
 * @since 1.0.0
 */
final readonly class Divider implements Component {

	/**
	 * {@inheritDoc}
	 */
	public function html( Context $context, Style $style ): string {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:20px 0;">'
			. '<tr><td style="height:1px;background:' . $style->palette->border . ';line-height:1px;font-size:0;">&nbsp;</td></tr>'
			. '</table>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function text( Context $context ): string {
		return "\n" . str_repeat( '-', 40 ) . "\n";
	}
}
