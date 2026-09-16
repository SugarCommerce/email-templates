<?php
/**
 * Component contract.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Interface Component
 *
 * A block an operator can drop into a message body by name — an order
 * table, a list of download links, a button.
 *
 * A component owns its own escaping, because it is the only thing that
 * knows which parts of its output are markup and which came from data.
 * Everything it interpolates from the {@see Context} must go through
 * {@see Html::escape()}; the renderer trusts what comes back and will
 * not escape it again.
 *
 * @since 1.0.0
 */
interface Component {

	/**
	 * Render the HTML form.
	 *
	 * @since 1.0.0
	 *
	 * @param Context $context Template data.
	 * @param Style   $style   Resolved branding, palette and theme.
	 *                         Every colour, font and radius must come
	 *                         from here — a hardcoded light grey becomes
	 *                         a white line across a dark email, which is
	 *                         exactly how this contract was arrived at.
	 *
	 * @return string Trusted HTML. Empty string to render nothing.
	 */
	public function html( Context $context, Style $style ): string;

	/**
	 * Render the plain-text form.
	 *
	 * Never skip this. A message with no text alternative scores worse
	 * with spam filters and is unreadable in a text client.
	 *
	 * @since 1.0.0
	 *
	 * @param Context $context Template data.
	 *
	 * @return string Plain text. Empty string to render nothing.
	 */
	public function text( Context $context ): string;
}
