<?php
/**
 * Sender identity applied to every layout.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

/**
 * Class Branding
 *
 * The chrome around every message: who it is from, the accent colour,
 * and the small print at the bottom.
 *
 * Kept separate from the template because it is the same for every email
 * a business sends, and because an operator editing a receipt should not
 * be able to change the footer on a password reset.
 *
 * @since 1.0.0
 */
final readonly class Branding {

	/**
	 * Check and hold a sender identity.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 A logo URL with whitespace, a quote or an angle bracket
	 *              is refused.
	 *
	 * @param string $name    Business name, shown in the header.
	 * @param string $accent  Accent colour as `#rrggbb`.
	 * @param string $footer  Small print. Plain text; no markup.
	 * @param string $logo_url Absolute URL to a logo image, or ''.
	 * @param string $address Postal address. Required by CAN-SPAM for
	 *                        commercial mail, and its absence is a
	 *                        common reason for a spam classification.
	 *
	 * @throws \InvalidArgumentException On a malformed accent colour or logo
	 *                                   URL.
	 */
	public function __construct(
		public string $name,
		public string $accent = '#111111',
		public string $footer = '',
		public string $logo_url = '',
		public string $address = '',
	) {
		// The accent is interpolated straight into a `style` attribute,
		// so anything but a hex colour would be a stylesheet injection
		// point — and one an operator could reach through a settings form.
		if ( 1 !== preg_match( '/^#[0-9a-fA-F]{6}$/D', $accent ) ) {
			throw new \InvalidArgumentException( 'The accent must be a six-digit hex colour, e.g. #06d6a0.' );
		}

		// Mail clients block plain-http images, and a `javascript:` or
		// `data:` URL in an <img src> is an attack surface. Whitespace,
		// quotes and angle brackets are refused too: a URL never needs them
		// unencoded, and any one of them is a way out of the attribute.
		if ( '' !== $logo_url && 1 !== preg_match( '#^https://[^\s"\'<>`\\\\\x00-\x1F\x7F]+$#iD', $logo_url ) ) {
			throw new \InvalidArgumentException( 'A logo URL must be absolute and https.' );
		}
	}

	/**
	 * A readable colour to place on top of the accent.
	 *
	 * Uses the ITU-R BT.601 luma coefficients, which weight green most
	 * heavily because the eye is most sensitive to it. A flat average of
	 * the channels puts white text on yellow.
	 *
	 * @since 1.0.0
	 *
	 * @return string `#ffffff` or `#111111`.
	 */
	public function accent_foreground(): string {
		$red   = (int) hexdec( substr( $this->accent, 1, 2 ) );
		$green = (int) hexdec( substr( $this->accent, 3, 2 ) );
		$blue  = (int) hexdec( substr( $this->accent, 5, 2 ) );

		return ( ( $red * 299 + $green * 587 + $blue * 114 ) / 1000 ) > 150 ? '#111111' : '#ffffff';
	}
}
