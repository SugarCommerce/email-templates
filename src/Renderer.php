<?php
/**
 * The rendering engine.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Class Renderer
 *
 * Substitutes `{{tags}}` and wraps the result in a layout.
 *
 * Two rules make the rest of the package safe:
 *
 * **Scalar tags are escaped for the context they land in.** A customer
 * called `<script>` is escaped in the HTML body and left alone in the
 * text body and subject, where escaping would show them literal
 * `&lt;script&gt;`. Getting this backwards is the classic bug in
 * template engines that were written for HTML pages and then pointed at
 * email.
 *
 * **Component tags are not escaped.** A component returns markup by
 * definition, so it escapes its own interpolations and the renderer
 * trusts the result. That is the contract in {@see Component}.
 *
 * @since 1.0.0
 */
final class Renderer {

	/**
	 * Matches `{{ tag.path }}` with optional inner whitespace.
	 *
	 * Deliberately restrictive: letters, digits, underscore, dot and
	 * hyphen only. A permissive pattern lets an operator write something
	 * that looks like a tag, gets no value, and ships to a customer.
	 *
	 * @since 1.0.0
	 */
	public const TAG_PATTERN = '/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/';

	/**
	 * Components available to templates, keyed by tag name.
	 *
	 * @var array<string, Component>
	 */
	private array $components = array();

	/**
	 * @since 1.0.0
	 *
	 * @param Branding $branding      Sender identity.
	 * @param bool     $strict        Throw on an unresolved tag instead
	 *                                of removing it. Good in tests and
	 *                                in a template-save validator; risky
	 *                                on a live send, where a missing
	 *                                value should not stop a receipt.
	 * @param bool     $register_defaults Register the built-in components.
	 */
	public function __construct(
		private readonly Branding $branding,
		private readonly Theme $theme = new Theme(),
		private readonly bool $strict = false,
		bool $register_defaults = true,
	) {
		if ( $register_defaults ) {
			$this->register( 'order_items', new Components\OrderItems() );
			$this->register( 'links', new Components\LinkList() );
			$this->register( 'codes', new Components\CodeList() );
			$this->register( 'button', new Components\Button() );
			$this->register( 'details', new Components\Details() );
			$this->register( 'divider', new Components\Divider() );
		}
	}

	/**
	 * Add or replace a component.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $name      Tag name, used as `{{name}}`.
	 * @param Component $component Implementation.
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException On a name the tag pattern cannot match.
	 */
	public function register( string $name, Component $component ): self {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.\-]+$/', $name ) ) {
			throw new \InvalidArgumentException( 'A component name may only contain letters, digits, underscore, dot and hyphen: ' . $name );
		}

		$this->components[ $name ] = $component;

		return $this;
	}

	/**
	 * Add or replace a component, written inline.
	 *
	 * The same thing as register(), without a class for a tag that only ever
	 * has one implementation. For the tags an application needs that no
	 * library could ship — a licence key block, a seat count, a renewal date
	 * in the seller's own wording.
	 *
	 * ```php
	 * $renderer->tag(
	 *     'licence',
	 *     fn( Context $c, Style $s ) => '<code>' . Html::escape( (string) $c->get( 'licence.key' ) ) . '</code>',
	 *     fn( Context $c ) => (string) $c->get( 'licence.key' ),
	 * );
	 * ```
	 *
	 * The HTML closure owns its own escaping, exactly as a component class
	 * does — whatever it returns is trusted and will not be escaped again.
	 *
	 * Both forms are required. A tag with no text form silently contributes
	 * nothing to the plain-text body, which scores worse with spam filters
	 * and reads as a hole in a text client.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $name Tag name, used as `{{name}}`.
	 * @param callable $html Receives Context and Style, returns trusted HTML.
	 * @param callable $text Receives Context, returns plain text.
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException On a name the tag pattern cannot match.
	 */
	public function tag( string $name, callable $html, callable $text ): self {
		return $this->register( $name, Components\Callback::of( $html, $text ) );
	}

	/**
	 * Component names currently registered.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function components(): array {
		return array_keys( $this->components );
	}

	/**
	 * Render a template against some data.
	 *
	 * @since 1.0.0
	 *
	 * @param Template             $template Template.
	 * @param array<string, mixed> $data     Values for its tags.
	 *
	 * @return Rendered
	 *
	 * @throws \RuntimeException When strict and a tag has no value.
	 */
	public function render( Template $template, array $data = array() ): Rendered {
		$context    = new Context( $data );
		$unresolved = array();
		$style      = new Style(
			$this->branding,
			$this->theme->palette( $this->branding, $template->layout->is_inverted() ),
			$this->theme
		);

		$subject = $this->substitute( $template->subject, $context, false, $unresolved, $style );
		$body    = $this->substitute( $template->html, $context, true, $unresolved, $style );

		// With no hand-written text body, render the *body template* again
		// in text mode rather than converting the finished HTML. That way
		// each component contributes its own text() output — a proper list
		// instead of table cells mashed together — and only the operator's
		// surrounding markup needs stripping.
		$text = '' !== $template->text
			? $this->substitute( $template->text, $context, false, $unresolved, $style )
			: Html::to_text( $this->substitute( $template->html, $context, false, $unresolved, $style ) );

		$preheader = $this->substitute( $template->preheader, $context, false, $unresolved, $style );

		$unresolved = array_values( array_unique( $unresolved ) );

		if ( $this->strict && array() !== $unresolved ) {
			throw new \RuntimeException(
				'Template "' . $template->slug . '" has unresolved tags: ' . implode( ', ', $unresolved )
			);
		}

		return new Rendered(
			$subject,
			$this->wrap( $template->layout, $style, $body, $subject, $preheader ),
			$this->text_document( $text ),
			$unresolved
		);
	}

	/**
	 * Replace every tag in a string.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $subject    Raw string.
	 * @param Context  $context    Data.
	 * @param bool     $html       Whether the output is HTML.
	 * @param string[] $unresolved Collects tags with no value.
	 *
	 * @return string
	 */
	private function substitute( string $subject, Context $context, bool $html, array &$unresolved, Style $style ): string {
		if ( '' === $subject ) {
			return '';
		}

		$found =& $unresolved;

		return (string) preg_replace_callback(
			self::TAG_PATTERN,
			function ( array $match ) use ( $context, $html, &$found, $style ): string {
				$name = $match[1];

				// A component owns its own escaping — see Component.
				if ( isset( $this->components[ $name ] ) ) {
					if ( $html ) {
						return $this->components[ $name ]->html( $context, $style );
					}

					$rendered = $this->components[ $name ]->text( $context );

					// Two components written side by side in a body have
					// nothing between them, so their text runs together:
					// "Date: 1 Aug 2026- Download". Padding here rather
					// than in each component keeps the contract simple —
					// runs of blank lines collapse later.
					return '' === $rendered ? '' : "\n" . $rendered . "\n";
				}

				// Design tokens resolve inside the body too, so an
				// operator can write style="color:{{muted}}" and have it
				// track the layout instead of hardcoding a grey that
				// turns into a white line on the inverted variant.
				$tokens = $style->tokens();

				if ( isset( $tokens[ '{{' . $name . '}}' ] ) ) {
					return $tokens[ '{{' . $name . '}}' ];
				}

				$value = $context->get( $name );

				if ( null === $value ) {
					// Removed rather than left in place. A customer
					// seeing "Hi {{customer.name}}" is worse than seeing
					// "Hi" — and worse than the operator being told.
					$found[] = $name;

					return '';
				}

				return $html ? Html::escape( $value ) : $value;
			},
			$subject
		);
	}

	/**
	 * Wrap a rendered body in its layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout    Layout.
	 * @param string $body      Rendered body HTML.
	 * @param string $subject   Resolved subject, for the <title>.
	 * @param string $preheader Inbox preview text.
	 *
	 * @return string
	 */
	private function wrap( Layout $layout, Style $style, string $body, string $subject, string $preheader ): string {
		return strtr(
			$layout->html(),
			array_merge(
				$style->tokens(),
				array(
					'{{content}}'       => $body,
					'{{business_name}}' => Html::escape( $this->branding->name ),
					'{{footer}}'        => $this->footer_html(),
					'{{subject}}'       => Html::escape( $subject ),
					'{{preheader}}'     => Html::escape( $preheader ),
				)
			)
		);
	}

	/**
	 * The footer block, built from branding.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function footer_html(): string {
		$lines = array();

		if ( '' !== $this->branding->footer ) {
			$lines[] = Html::escape( $this->branding->footer );
		}

		if ( '' !== $this->branding->address ) {
			$lines[] = Html::escape( $this->branding->address );
		}

		return implode( '<br>', $lines );
	}

	/**
	 * Append the footer to the text body.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Rendered text body.
	 *
	 * @return string
	 */
	private function text_document( string $text ): string {
		$parts = array( $text );

		if ( '' !== $this->branding->footer ) {
			$parts[] = $this->branding->footer;
		}

		if ( '' !== $this->branding->address ) {
			$parts[] = $this->branding->address;
		}

		return implode( "\n\n--\n", $parts );
	}
}
