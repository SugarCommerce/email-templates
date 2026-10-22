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
	public const string TAG_PATTERN = '/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/';

	/**
	 * Substitute for an HTML body: scalars escaped, components as HTML.
	 *
	 * @since 1.4.0
	 */
	private const int MODE_HTML = 0;

	/**
	 * Substitute for a subject or a hand-written text body: scalars raw,
	 * components as text.
	 *
	 * @since 1.4.0
	 */
	private const int MODE_TEXT = 1;

	/**
	 * Substitute an HTML body that will become the text part: components as
	 * text, and every value escaped so {@see Html::to_text()} hands it back
	 * exactly — a customer called `<Bob>` must not be stripped as a tag.
	 *
	 * @since 1.4.0
	 */
	private const int MODE_DERIVED = 2;

	/**
	 * Components available to templates, keyed by tag name.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, Component>
	 */
	private array $components = array();

	/**
	 * Set up a renderer for one sender.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding $branding      Sender identity.
	 * @param Theme    $theme         Type, spacing, shape and palettes.
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
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.\-]+$/D', $name ) ) {
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
	 * @return list<string>
	 */
	public function components(): array {
		return array_keys( $this->components );
	}

	/**
	 * Render a template against some data.
	 *
	 * The document says which language it is in. A screen reader reading a
	 * German email with English pronunciation is the same failure as a web
	 * page without `lang`, and a right-to-left language laid out left to
	 * right reads as broken however good the translation. Whether a language
	 * is right to left is the caller's to say — an application that
	 * translates already knows, and this package keeps no language tables.
	 *
	 * The subject comes back on one line whatever the data held. It ends up
	 * in a mail header, and a customer name carrying a line break is the
	 * classic way to write extra headers — a `Bcc:` — into a message.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 The `$lang` and `$rtl` arguments.
	 * @since 1.4.0 The subject and preheader are always one line.
	 *
	 * @param Template             $template Template.
	 * @param array<string, mixed> $data     Values for its tags.
	 * @param string               $lang     BCP 47 tag for `<html lang>`.
	 *                                       Anything that is not one is
	 *                                       sent as 'en'.
	 * @param bool                 $rtl      Add `dir="rtl"` to the document.
	 *
	 * @return Rendered
	 *
	 * @throws \RuntimeException When strict and a tag has no value.
	 */
	public function render( Template $template, array $data = array(), string $lang = 'en', bool $rtl = false ): Rendered {
		$context    = new Context( $data );
		$unresolved = array();
		$style      = new Style(
			$this->branding,
			$this->theme->palette( $this->branding, $template->layout->is_inverted() ),
			$this->theme
		);

		$subject = self::single_line( $this->substitute( $template->subject, $context, self::MODE_TEXT, $unresolved, $style ) );
		$body    = $this->substitute( $template->html, $context, self::MODE_HTML, $unresolved, $style );

		// With no hand-written text body, render the *body template* again
		// in text mode rather than converting the finished HTML. That way
		// each component contributes its own text() output — a proper list
		// instead of table cells mashed together — and only the operator's
		// surrounding markup needs stripping.
		$text = '' !== $template->text
			? $this->substitute( $template->text, $context, self::MODE_TEXT, $unresolved, $style )
			: Html::to_text( $this->substitute( $template->html, $context, self::MODE_DERIVED, $unresolved, $style ) );

		$preheader = self::single_line( $this->substitute( $template->preheader, $context, self::MODE_TEXT, $unresolved, $style ) );

		$unresolved = array_values( array_unique( $unresolved ) );

		if ( $this->strict && array() !== $unresolved ) {
			throw new \RuntimeException(
				'Template "' . $template->slug . '" has unresolved tags: ' . implode( ', ', $unresolved )
			);
		}

		return new Rendered(
			$subject,
			$this->wrap( $template->layout, $style, $body, $subject, $preheader, self::language( $lang ), $rtl ),
			$this->text_document( $text ),
			$unresolved
		);
	}

	/**
	 * Replace every tag in a string.
	 *
	 * One pass: a value that itself contains `{{tag}}` text is inserted as
	 * that text and never expanded, so a customer cannot pull another
	 * customer's data, or a component, into their own message.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Takes a mode rather than a bool.
	 *
	 * @param string       $subject    Raw string.
	 * @param Context      $context    Data.
	 * @param int          $mode       One of the MODE_ constants.
	 * @param list<string> $unresolved Collects tags with no value.
	 * @param Style        $style      Design tokens and component style.
	 *
	 * @return string
	 */
	private function substitute( string $subject, Context $context, int $mode, array &$unresolved, Style $style ): string {
		if ( '' === $subject ) {
			return '';
		}

		$found =& $unresolved;

		return (string) preg_replace_callback(
			self::TAG_PATTERN,
			function ( array $match ) use ( $context, $mode, &$found, $style ): string {
				$name = $match[1];

				// A component owns its own escaping — see Component.
				if ( isset( $this->components[ $name ] ) ) {
					if ( self::MODE_HTML === $mode ) {
						return $this->components[ $name ]->html( $context, $style );
					}

					$rendered = $this->components[ $name ]->text( $context );

					if ( self::MODE_DERIVED === $mode ) {
						$rendered = Html::escape( $rendered );
					}

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

				return self::MODE_TEXT === $mode ? $value : Html::escape( $value );
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
	 * @param Style  $style     Design tokens for the layout.
	 * @param string $body      Rendered body HTML.
	 * @param string $subject   Resolved subject, for the <title>.
	 * @param string $preheader Inbox preview text.
	 * @param string $lang      Language tag, already checked.
	 * @param bool   $rtl       Whether the document reads right to left.
	 *
	 * @return string
	 */
	private function wrap( Layout $layout, Style $style, string $body, string $subject, string $preheader, string $lang = 'en', bool $rtl = false ): string {
		return strtr(
			$layout->html(),
			array_merge(
				$style->tokens(),
				array(
					'{{lang}}'          => $lang,
					'{{dir}}'           => $rtl ? ' dir="rtl"' : '',
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
	 * A string with every line break and control character turned to a
	 * space, and runs of spaces collapsed.
	 *
	 * For the subject — which a transport writes into a header, where a
	 * carriage return or line feed starts a new header — and for the
	 * preheader, which an inbox shows on one line anyway. Unicode line and
	 * paragraph separators go too: a lenient parser downstream may treat
	 * them as line ends.
	 *
	 * @since 1.4.0
	 *
	 * @param string $value Substituted subject or preheader.
	 *
	 * @return string
	 */
	private static function single_line( string $value ): string {
		$value = preg_replace( '/[\x00-\x1F\x7F]/', ' ', $value ) ?? '';
		$value = preg_replace( '/[\x{0085}\x{2028}\x{2029}]/u', ' ', $value ) ?? $value;

		return trim( preg_replace( '/ {2,}/', ' ', $value ) ?? $value );
	}

	/**
	 * A language tag safe to put in an attribute, or 'en'.
	 *
	 * The shape of a BCP 47 tag — a primary language and hyphenated
	 * subtags — rather than an escaped copy of whatever arrived, so a value
	 * from a cookie or a query string cannot carry anything else into the
	 * document.
	 *
	 * @since 1.3.0
	 *
	 * @param string $lang Tag: `de`, `pt-BR` or `pt_BR`.
	 *
	 * @return string
	 */
	private static function language( string $lang ): string {
		$lang = str_replace( '_', '-', trim( $lang ) );

		return 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*$/D', $lang ) ? $lang : 'en';
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
