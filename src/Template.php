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
	 * Every template one language has, keyed by slug.
	 *
	 * Translations are the same files in a folder named for the locale:
	 *
	 * ```
	 * emails/receipt.html        the source language
	 * emails/de/receipt.html     German
	 * emails/pt-BR/receipt.html  Brazilian Portuguese
	 * ```
	 *
	 * so a translator works on whole emails rather than on sentences cut out
	 * of them, and the source folder alone says which emails exist.
	 *
	 * Only what that language has — no fallback. A locale with no folder, or
	 * without one of the files, simply has nothing for it here, which is what
	 * an application needs to know before deciding between an operator's
	 * edit of the English and the translation that ships. For one template
	 * with the fallback done for you, use resolve().
	 *
	 * @since 1.3.0
	 *
	 * @param string $directory The source language's folder.
	 * @param string $locale    Locale tag.
	 * @param string $source    The language the root folder is written in.
	 * @param string $extension File extension to match.
	 *
	 * @return array<string, self> Empty when the locale has no folder.
	 */
	public static function for_locale( string $directory, string $locale, string $source = 'en', string $extension = 'html' ): array {
		if ( $source === $locale ) {
			return is_dir( $directory ) ? self::from_directory( $directory, $extension ) : array();
		}

		$folder = self::folder( $directory, $locale );

		return null !== $folder && is_dir( $folder ) ? self::from_directory( $folder, $extension ) : array();
	}

	/**
	 * One template in the best language there is for it.
	 *
	 * The locale's own folder, then its language's (`de` for `de-AT`), then
	 * the source language's. A message is always better sent in English than
	 * not sent because nobody translated it yet.
	 *
	 * @since 1.3.0
	 *
	 * @param string $directory The source language's folder.
	 * @param string $slug      Template slug: the filename without extension.
	 * @param string $locale    Locale tag.
	 * @param string $source    The language the root folder is written in.
	 * @param string $extension File extension.
	 *
	 * @return self|null Null when not even the source language has it, or
	 *                   when the slug is not a plain file name.
	 */
	public static function resolve( string $directory, string $slug, string $locale, string $source = 'en', string $extension = 'html' ): ?self {
		// A slug is a file name, never a path: `../secrets` must not resolve.
		if ( 1 !== preg_match( '/^[A-Za-z0-9_\-][A-Za-z0-9_.\-]*$/', $slug ) || str_contains( $slug, '..' ) ) {
			return null;
		}

		$language = strtolower( (string) ( preg_split( '/[-_]/', $locale )[0] ?? '' ) );
		$folders  = array();

		foreach ( array_unique( array( $locale, $language ) ) as $tag ) {
			$folder = $tag === $source ? null : self::folder( $directory, $tag );

			if ( null !== $folder ) {
				$folders[] = $folder;
			}
		}

		$folders[] = rtrim( $directory, '/' );

		foreach ( $folders as $folder ) {
			$path = $folder . '/' . $slug . '.' . $extension;

			if ( is_file( $path ) ) {
				return self::from_file( $path, $slug );
			}
		}

		return null;
	}

	/**
	 * A locale's folder, or null for a tag that is not one.
	 *
	 * Checked against the shape of a locale tag, so one taken from a cookie
	 * or a query string cannot walk out of the directory.
	 *
	 * @since 1.3.0
	 *
	 * @param string $directory The source language's folder.
	 * @param string $locale    Locale tag.
	 *
	 * @return string|null
	 */
	private static function folder( string $directory, string $locale ): ?string {
		if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale ) ) {
			return null;
		}

		return rtrim( $directory, '/' ) . '/' . $locale;
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
		return clone( $this, array( 'layout' => $layout ) );
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
