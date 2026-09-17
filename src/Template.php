<?php
/**
 * An operator-editable message.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates;

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
	 * Load a template from a file.
	 *
	 * An email body is markup with a few tags in it. Kept in a PHP file it is
	 * a string literal nobody wants to edit and no editor highlights; kept in
	 * a file it is a file, and a designer can open it.
	 *
	 * The metadata lives in front matter, so one file is the whole template:
	 *
	 * ```
	 * ---
	 * subject: Your download from {{business.name}}
	 * preheader: Your files are ready.
	 * layout: card
	 * ---
	 * <p>Thanks for your purchase.</p>
	 * {{links}}
	 * ```
	 *
	 * Front matter is optional. Without it the whole file is the HTML body
	 * and the subject has to be supplied some other way.
	 *
	 * Note that this does not execute the file. A template is content, not
	 * code — `include` would make every template an arbitrary-code hole the
	 * moment one became editable.
	 *
	 * @since 1.1.0
	 *
	 * @param string $path File path.
	 * @param string $slug Stable identifier. Defaults to the filename
	 *                     without its extension.
	 *
	 * @return self
	 *
	 * @throws \RuntimeException When the file cannot be read.
	 */
	public static function from_file( string $path, string $slug = '' ): self {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new \RuntimeException( 'No such template: ' . $path );
		}

		[ $meta, $body ] = self::split( (string) file_get_contents( $path ) );

		return new self(
			slug: '' !== $slug ? $slug : pathinfo( $path, PATHINFO_FILENAME ),
			subject: $meta['subject'] ?? '',
			html: $body,
			text: $meta['text'] ?? '',
			layout: Layout::tryFrom( $meta['layout'] ?? '' ) ?? Layout::Card,
			preheader: $meta['preheader'] ?? '',
		);
	}

	/**
	 * Load every template in a directory, keyed by slug.
	 *
	 * @since 1.1.0
	 *
	 * @param string $directory Directory to read.
	 * @param string $extension File extension to match.
	 *
	 * @return array<string, self>
	 *
	 * @throws \RuntimeException When the directory cannot be read.
	 */
	public static function from_directory( string $directory, string $extension = 'html' ): array {
		if ( ! is_dir( $directory ) ) {
			throw new \RuntimeException( 'No such template directory: ' . $directory );
		}

		$templates = array();

		foreach ( (array) glob( rtrim( $directory, '/' ) . '/*.' . $extension ) as $path ) {
			$template                    = self::from_file( (string) $path );
			$templates[ $template->slug ] = $template;
		}

		return $templates;
	}

	/**
	 * Split front matter from the body.
	 *
	 * Deliberately not YAML. The whole grammar here is `key: value` on one
	 * line, which needs no dependency and cannot surprise anyone — a real
	 * YAML parser would happily turn `subject: No` into a boolean.
	 *
	 * @since 1.1.0
	 *
	 * @param string $contents File contents.
	 *
	 * @return array{0: array<string, string>, 1: string} Metadata, then body.
	 */
	private static function split( string $contents ): array {
		$contents = preg_replace( '/^\xEF\xBB\xBF/', '', $contents ) ?? $contents;

		if ( ! preg_match( '/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $match ) ) {
			return array( array(), trim( $contents ) );
		}

		$meta = array();

		foreach ( preg_split( '/\R/', $match[1] ) ?: array() as $line ) {
			if ( '' === trim( $line ) || ! str_contains( $line, ':' ) ) {
				continue;
			}

			[ $key, $value ] = explode( ':', $line, 2 );

			$meta[ strtolower( trim( $key ) ) ] = trim( $value );
		}

		return array( $meta, trim( $match[2] ) );
	}

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
