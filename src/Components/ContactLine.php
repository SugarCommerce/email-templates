<?php
/**
 * Where the reader goes for help.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.3.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Components;

use SugarCommerce\EmailTemplates\{Component, Context, Html, Style};

/**
 * Class ContactLine
 *
 * "Questions about this order? Acme can help — help@acme.test or their help
 * page." An address, a page, or both, after a sentence in the sender's own
 * words.
 *
 * Nothing at all when there is nowhere to send the reader. A line that
 * invites questions and then names no address is worse than no line: the
 * reader replies to the email, which is usually a no-reply.
 *
 * Every word is passed in — the lead sentence, the label on the page link
 * and the word between the two — so a translated message is translated
 * through and through, and the component holds no English of its own.
 *
 * In the text part a link cannot be clicked, so it prints the address and the
 * URL themselves.
 *
 * @since 1.3.0
 */
final readonly class ContactLine implements Component {

	/**
	 * Hold the words and the places to send the reader.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 An address that could change the `mailto:` link — one
	 *              with a `?`, `#`, whitespace or a control character —
	 *              is treated as ''.
	 *
	 * @param string $lead  The sentence before the links, without a full
	 *                      stop: "Questions about this order? Acme can help".
	 * @param string $email An address, or ''.
	 * @param string $url   A help page, or ''. Only http, https and mailto
	 *                      are linked; anything else is treated as ''.
	 * @param string $label The page link's text: "their help page".
	 * @param string $or    What joins the two, spaces included: " or ".
	 */
	public function __construct(
		private string $lead,
		private string $email = '',
		private string $url = '',
		private string $label = 'help page',
		private string $or = ' or ',
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		if ( ! $this->reachable() ) {
			return '';
		}

		$links = array();
		$link  = 'style="color:' . $style->palette->muted . ';"';

		if ( '' !== $this->address() ) {
			$links[] = '<a href="mailto:' . Html::escape( $this->address() ) . '" ' . $link . '>' . Html::escape( $this->address() ) . '</a>';
		}

		if ( '' !== Html::url( $this->url ) ) {
			$links[] = '<a href="' . Html::url( $this->url ) . '" ' . $link . '>' . Html::escape( $this->label ) . '</a>';
		}

		return '<p style="margin:16px 0 0;font-size:' . ( $style->theme->font_size - 1 ) . 'px;color:' . $style->palette->muted . ';">'
			. Html::escape( $this->lead ) . ' &mdash; ' . implode( Html::escape( $this->or ), $links ) . '.</p>';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		if ( ! $this->reachable() ) {
			return '';
		}

		$where = array_filter( array( $this->address(), '' !== Html::url( $this->url ) ? trim( $this->url ) : '' ) );

		return $this->lead . ': ' . implode( $this->or, $where );
	}

	/**
	 * Whether there is a lead and somewhere to send the reader.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	private function reachable(): bool {
		return '' !== $this->lead && ( '' !== $this->address() || '' !== Html::url( $this->url ) );
	}

	/**
	 * The address, or '' when it is not one a `mailto:` link can carry as is.
	 *
	 * A `?` starts the link's headers, so `help@acme.test?bcc=...&body=...`
	 * would open the reader's mail client with a recipient and a message the
	 * sender never wrote, and a `#` ends the address early. One `@`, with
	 * something either side and nothing that ends or splits a URL. Quotes and
	 * apostrophes are allowed — `o'brien@` is an address — and escaped where
	 * the address is printed.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	private function address(): string {
		$email = trim( $this->email );

		return 1 === preg_match( '/^[^\s@?#<>\\\\\x00-\x1F\x7F]+@[^\s@?#<>\\\\\x00-\x1F\x7F]+$/D', $email ) ? $email : '';
	}
}
