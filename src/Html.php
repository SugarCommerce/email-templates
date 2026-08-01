<?php
/**
 * Escaping helpers.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates;

/**
 * Class Html
 *
 * Every value that reaches an email body came from somewhere else — a
 * customer's name from a checkout form, a product title from an admin
 * screen, an address from a payment provider. All of it is untrusted.
 *
 * @since 1.0.0
 */
final readonly class Html {

	/**
	 * Escape a value for HTML text or a quoted attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	public static function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Escape a URL for an `href` or `src`.
	 *
	 * Only http and https survive. A stored `javascript:` URL in an
	 * email is less dangerous than in a browser — most clients refuse to
	 * run it — but webmail is a browser, and the ones that render it are
	 * exactly the ones you cannot audit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Raw URL.
	 *
	 * @return string Empty when the URL is not safe to link.
	 */
	public static function url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || 1 === preg_match( '/[\x00-\x1F\x7F]/', $url ) ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		if ( ! in_array( $scheme, array( 'http', 'https', 'mailto' ), true ) ) {
			return '';
		}

		return self::escape( $url );
	}

	/**
	 * Convert HTML to a readable plain-text fallback.
	 *
	 * Link targets are preserved as `text (url)`, which is the part a
	 * bare `strip_tags` loses — and losing it guts a message whose whole
	 * purpose is a link.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML.
	 *
	 * @return string
	 */
	public static function to_text( string $html ): string {
		$text = preg_replace( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $html ) ?? $html;
		// Cell boundaries need a separator too, or a table collapses into
		// "Order1234" with the label and value welded together.
		$text = preg_replace( '/<\/t[dh]>\s*<t[dh][^>]*>/i', ': ', $text ) ?? $text;
		$text = preg_replace( '/<(br|\/p|\/div|\/tr|\/h[1-6]|\/li|\/table)[^>]*>/i', "\n", $text ) ?? $text;
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
		$text = preg_replace( '/ *\n */', "\n", $text ) ?? $text;
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;

		return trim( $text );
	}
}
