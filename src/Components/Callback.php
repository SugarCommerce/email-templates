<?php
/**
 * A component defined inline, as closures.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Components;

use SugarCommerce\EmailTemplates\{Component, Context, Style};

/**
 * Class Callback
 *
 * For the tags an application needs that no library could ship: a licence key
 * block, a seat count, a renewal date in the seller's own wording. Writing a
 * class for each is more ceremony than the thing deserves.
 *
 * ```php
 * $renderer->tag(
 *     'licence',
 *     fn( Context $c, Style $s ) => '<code style="color:' . $s->tokens()['{{muted}}'] . '">'
 *         . Html::escape( (string) $c->get( 'licence.key' ) ) . '</code>',
 *     fn( Context $c ) => (string) $c->get( 'licence.key' ),
 * );
 * ```
 *
 * Both forms are required, because the alternative is a tag that silently
 * contributes nothing to the plain-text body — which scores worse with spam
 * filters and reads as a hole in a text client. See {@see Component::text()}.
 *
 * The HTML closure owns its own escaping, exactly as a component class does.
 * Whatever it returns is trusted and will not be escaped again.
 *
 * @since 1.1.0
 */
final readonly class Callback implements Component {

	/**
	 * @param \Closure $html Receives the Context and the Style, returns
	 *                       trusted HTML. '' renders nothing.
	 * @param \Closure $text Receives the Context, returns plain text. ''
	 *                       renders nothing.
	 */
	public function __construct(
		private \Closure $html,
		private \Closure $text,
	) {}

	/**
	 * Build from any callables.
	 *
	 * @since 1.1.0
	 *
	 * @param callable $html Trusted HTML, from Context and Style.
	 * @param callable $text Plain text, from Context.
	 *
	 * @return self
	 */
	public static function of( callable $html, callable $text ): self {
		return new self( $html( ... ), $text( ... ) );
	}

	/**
	 * Render the HTML form.
	 *
	 * @since 1.1.0
	 *
	 * @param Context $context Template data.
	 * @param Style   $style   Resolved branding, palette and theme.
	 *
	 * @return string
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		return (string) ( $this->html )( $context, $style );
	}

	/**
	 * Render the plain-text form.
	 *
	 * @since 1.1.0
	 *
	 * @param Context $context Template data.
	 *
	 * @return string
	 */
	#[\Override]
	public function text( Context $context ): string {
		return (string) ( $this->text )( $context );
	}
}
