<?php
/**
 * A rendered message.
 *
 * @package   Sugar\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\EmailTemplates;

/**
 * Class Rendered
 *
 * What comes out of the renderer: the three strings a transport needs.
 *
 * Hand it to `sugarcommerce/mailer` in three lines:
 *
 *   Message::to( $customer )
 *       ->subject( $rendered->subject )
 *       ->html( $rendered->html )
 *       ->text( $rendered->text );
 *
 * @since 1.0.0
 */
final readonly class Rendered {

	/**
	 * @since 1.0.0
	 *
	 * @param string   $subject   Resolved subject line.
	 * @param string   $html      Full HTML document, layout included.
	 * @param string   $text      Plain-text alternative.
	 * @param string[] $unresolved Tags that had no value and were
	 *                             removed. Empty on a clean render.
	 */
	public function __construct(
		public string $subject,
		public string $html,
		public string $text,
		public array $unresolved = array(),
	) {}

	/**
	 * Whether every tag resolved.
	 *
	 * Worth asserting in a test over your whole template catalogue: a
	 * template referencing `{{customer.first_name}}` when the data says
	 * `name` renders a subtly broken email, and nothing else will tell
	 * you.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return array() === $this->unresolved;
	}
}
