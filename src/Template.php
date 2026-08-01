<?php
/**
 * An operator-editable message.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates;

/**
 * Class Template
 *
 * Subject and body, with `{{tags}}` in both. This is the part you store
 * in a database and let an operator edit; the {@see Layout} around it
 * stays under your control.
 *
 * The text body is optional. When it is absent one is derived from the
 * HTML, which is always better than sending no text part at all — but a
 * hand-written one reads better, so supply it where the message matters.
 *
 * @since 1.0.0
 */
final readonly class Template {

	/**
	 * @since 1.0.0
	 *
	 * @param string $slug      Stable identifier, e.g. `order.receipt`.
	 * @param string $subject   Subject line, may contain scalar tags.
	 * @param string $html      HTML body, may contain any tag.
	 * @param string $text      Plain-text body. Derived when empty.
	 * @param Layout $layout    Wrapper chrome.
	 * @param string $preheader The grey line after the subject in an
	 *                          inbox list. Left empty, clients pull the
	 *                          first words of the body instead — often
	 *                          "View this email in your browser".
	 */
	public function __construct(
		public string $slug,
		public string $subject,
		public string $html,
		public string $text = '',
		public Layout $layout = Layout::Card,
		public string $preheader = '',
	) {}

	/**
	 * A copy using a different layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Replacement layout.
	 *
	 * @return self
	 */
	public function with_layout( Layout $layout ): self {
		return new self( $this->slug, $this->subject, $this->html, $this->text, $layout, $this->preheader );
	}

	/**
	 * Every tag named in the subject, body and preheader.
	 *
	 * Use it to show an operator which tags a template depends on, or to
	 * validate a template at save time rather than at send time.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Unique tag names, in order of first appearance.
	 */
	public function tags(): array {
		preg_match_all(
			Renderer::TAG_PATTERN,
			$this->subject . "\n" . $this->html . "\n" . $this->text . "\n" . $this->preheader,
			$matches
		);

		return array_values( array_unique( $matches[1] ?? array() ) );
	}
}
