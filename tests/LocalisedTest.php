<?php
/**
 * Messages in more than one language, and the components sent with them.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.3.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\EmailTemplates\{Branding, Context, Layout, Palette, Renderer, Style, Template, Theme};
use SugarCommerce\EmailTemplates\Components\{ContactLine, Notice, Paragraph};

#[CoversClass( Renderer::class )]
#[CoversClass( Template::class )]
#[CoversClass( Paragraph::class )]
#[CoversClass( Notice::class )]
#[CoversClass( ContactLine::class )]
final class LocalisedTest extends TestCase {

	/**
	 * A scratch template tree: English at the root, German and Brazilian
	 * Portuguese in folders.
	 */
	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/et-locales-' . bin2hex( random_bytes( 4 ) );

		foreach ( array( '', '/de', '/pt-BR' ) as $folder ) {
			mkdir( $this->dir . $folder, 0777, true );
		}

		file_put_contents( $this->dir . '/receipt.html', "---\nsubject: Your receipt\n---\n<p>Thanks.</p>" );
		file_put_contents( $this->dir . '/welcome.html', "---\nsubject: Welcome\n---\n<p>Hello.</p>" );
		file_put_contents( $this->dir . '/de/receipt.html', "---\nsubject: Ihre Quittung\n---\n<p>Danke.</p>" );
		file_put_contents( $this->dir . '/pt-BR/receipt.html', "---\nsubject: Seu recibo\n---\n<p>Obrigado.</p>" );
	}

	protected function tearDown(): void {
		foreach ( array( '/de/receipt.html', '/pt-BR/receipt.html', '/receipt.html', '/welcome.html' ) as $file ) {
			@unlink( $this->dir . $file );
		}

		foreach ( array( '/de', '/pt-BR', '' ) as $folder ) {
			@rmdir( $this->dir . $folder );
		}
	}

	private function renderer(): Renderer {
		return new Renderer( new Branding( 'Acme', '#06d6a0' ) );
	}

	private function style( bool $inverted = false ): Style {
		$branding = new Branding( 'Acme', '#06d6a0' );

		return new Style( $branding, $inverted ? Palette::inverted( $branding ) : Palette::light( $branding ), new Theme() );
	}

	/* ─── The document's language ──────────────────────────────────── */

	public function test_the_document_is_english_by_default(): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>Hi</p>' ) )->html;

		$this->assertStringContainsString( '<html lang="en">', $html );
		$this->assertStringNotContainsString( '{{lang}}', $html );
		$this->assertStringNotContainsString( 'dir=', $html );
	}

	#[DataProvider( 'layouts' )]
	public function test_every_layout_says_which_language_it_is_in( Layout $layout ): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>Hi</p>', layout: $layout ), array(), 'de' )->html;

		$this->assertStringContainsString( '<html lang="de">', $html );
	}

	public static function layouts(): array {
		return array_map( static fn ( Layout $layout ): array => array( $layout ), Layout::cases() );
	}

	public function test_a_right_to_left_language_is_marked(): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>Hi</p>' ), array(), 'ar', true )->html;

		$this->assertStringContainsString( '<html lang="ar" dir="rtl">', $html );
	}

	public function test_a_regional_tag_is_kept_and_an_underscore_becomes_a_hyphen(): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>Hi</p>' ), array(), 'pt_BR' )->html;

		$this->assertStringContainsString( '<html lang="pt-BR">', $html );
	}

	#[DataProvider( 'not_tags' )]
	public function test_anything_that_is_not_a_language_tag_is_sent_as_english( string $lang ): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>Hi</p>' ), array(), $lang )->html;

		$this->assertStringContainsString( '<html lang="en">', $html );
	}

	public static function not_tags(): array {
		return array(
			'attribute break' => array( 'de" onload="x' ),
			'empty'           => array( '' ),
			'digits'          => array( '12' ),
			'markup'          => array( '<b>' ),
		);
	}

	public function test_a_lang_tag_in_the_body_is_not_the_documents(): void {
		$html = $this->renderer()->render( new Template( 'x', 'Hi', '<p>{{lang}}</p>' ), array( 'lang' => 'body' ), 'de' )->html;

		$this->assertStringContainsString( '<p>body</p>', $html );
	}

	/* ─── Templates per language ───────────────────────────────────── */

	public function test_the_source_language_is_the_root_folder(): void {
		$this->assertSame( array( 'receipt', 'welcome' ), array_keys( Template::for_locale( $this->dir, 'en' ) ) );
	}

	public function test_a_translation_has_only_what_its_folder_has(): void {
		$german = Template::for_locale( $this->dir, 'de' );

		$this->assertSame( array( 'receipt' ), array_keys( $german ) );
		$this->assertSame( 'Ihre Quittung', $german['receipt']->subject );
	}

	public function test_a_locale_with_no_folder_has_nothing(): void {
		$this->assertSame( array(), Template::for_locale( $this->dir, 'fr' ) );
	}

	public function test_a_locale_that_is_a_path_has_nothing(): void {
		$this->assertSame( array(), Template::for_locale( $this->dir, '../' . basename( $this->dir ) ) );
	}

	public function test_resolve_prefers_the_locales_own_folder(): void {
		$this->assertSame( 'Seu recibo', Template::resolve( $this->dir, 'receipt', 'pt-BR' )?->subject );
	}

	public function test_resolve_falls_back_to_the_language(): void {
		$this->assertSame( 'Ihre Quittung', Template::resolve( $this->dir, 'receipt', 'de-AT' )?->subject );
	}

	public function test_resolve_falls_back_to_the_source_language(): void {
		$this->assertSame( 'Welcome', Template::resolve( $this->dir, 'welcome', 'de' )?->subject );
		$this->assertSame( 'Your receipt', Template::resolve( $this->dir, 'receipt', 'fr' )?->subject );
	}

	public function test_resolve_keeps_the_slug_asked_for(): void {
		$this->assertSame( 'receipt', Template::resolve( $this->dir, 'receipt', 'de' )?->slug );
	}

	public function test_resolve_finds_nothing_for_an_unknown_slug(): void {
		$this->assertNull( Template::resolve( $this->dir, 'missing', 'de' ) );
	}

	public function test_resolve_refuses_a_slug_that_is_a_path(): void {
		$this->assertNull( Template::resolve( $this->dir . '/de', '../welcome', 'de' ) );
	}

	/* ─── Paragraph ────────────────────────────────────────────────── */

	public function test_a_paragraph_is_body_text(): void {
		$style = $this->style();
		$html  = ( new Paragraph( 'Thanks for buying.' ) )->html( new Context(), $style );

		$this->assertSame(
			'<p style="margin:0 0 16px;font-size:15px;color:' . $style->palette->text . ';">Thanks for buying.</p>',
			$html
		);
	}

	public function test_an_aside_is_small_and_muted(): void {
		$style = $this->style();
		$html  = ( new Paragraph( 'Works for a week.', aside: true ) )->html( new Context(), $style );

		$this->assertSame(
			'<p style="margin:16px 0 0;font-size:13px;color:' . $style->palette->muted . ';">Works for a week.</p>',
			$html
		);
	}

	public function test_a_paragraph_escapes_then_keeps_line_breaks(): void {
		$html = ( new Paragraph( "<b>Hi</b>\nthere" ) )->html( new Context(), $this->style() );

		$this->assertStringContainsString( '&lt;b&gt;Hi&lt;/b&gt;<br />' . "\n" . 'there', $html );
	}

	public function test_an_empty_paragraph_renders_nothing(): void {
		$this->assertSame( '', ( new Paragraph( '' ) )->html( new Context(), $this->style() ) );
		$this->assertSame( '', ( new Paragraph( '' ) )->text( new Context() ) );
	}

	public function test_a_paragraph_is_its_own_text(): void {
		$this->assertSame( "Line one\nLine two", ( new Paragraph( "Line one\nLine two" ) )->text( new Context() ) );
	}

	/* ─── Notice ───────────────────────────────────────────────────── */

	public function test_a_notice_is_an_amber_banner(): void {
		$html = ( new Notice( 'Test mode.', 'Nothing was charged.' ) )->html( new Context(), $this->style() );

		$this->assertSame(
			'<p style="margin:0 0 16px;padding:10px 12px;border-radius:8px;background:#fef3c7;color:#78350f;font-size:13px;">'
				. '<strong>Test mode.</strong> Nothing was charged.</p>',
			$html
		);
	}

	public function test_a_notice_is_amber_on_an_inverted_layout_too(): void {
		$html = ( new Notice( 'Test mode.', 'x' ) )->html( new Context(), $this->style( true ) );

		$this->assertStringContainsString( 'background:' . Notice::BACKGROUND, $html );
	}

	public function test_a_notice_escapes_both_parts(): void {
		$html = ( new Notice( '<i>', '<script>' ) )->html( new Context(), $this->style() );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<i>', $html );
	}

	public function test_a_notice_reads_as_one_line_of_text(): void {
		$this->assertSame( 'Test mode. Nothing was charged.', ( new Notice( 'Test mode.', 'Nothing was charged.' ) )->text( new Context() ) );
	}

	public function test_a_notice_without_a_title_has_no_bold(): void {
		$html = ( new Notice( '', 'Heads up.' ) )->html( new Context(), $this->style() );

		$this->assertStringNotContainsString( '<strong>', $html );
		$this->assertSame( 'Heads up.', ( new Notice( '', 'Heads up.' ) )->text( new Context() ) );
	}

	public function test_an_empty_notice_renders_nothing(): void {
		$this->assertSame( '', ( new Notice( '', '' ) )->html( new Context(), $this->style() ) );
	}

	/* ─── ContactLine ──────────────────────────────────────────────── */

	public function test_a_contact_line_links_both_ways_to_get_help(): void {
		$style = $this->style();
		$html  = ( new ContactLine( 'Questions? Acme can help', 'help@acme.test', 'https://acme.test/help', 'their help page' ) )
			->html( new Context(), $style );

		$this->assertSame(
			'<p style="margin:16px 0 0;font-size:14px;color:' . $style->palette->muted . ';">Questions? Acme can help &mdash; '
				. '<a href="mailto:help@acme.test" style="color:' . $style->palette->muted . ';">help@acme.test</a> or '
				. '<a href="https://acme.test/help" style="color:' . $style->palette->muted . ';">their help page</a>.</p>',
			$html
		);
	}

	public function test_a_contact_line_with_one_way_has_no_joining_word(): void {
		$html = ( new ContactLine( 'Ask us', 'help@acme.test', or: ' oder ' ) )->html( new Context(), $this->style() );

		$this->assertStringNotContainsString( 'oder', $html );
		$this->assertStringContainsString( 'mailto:help@acme.test', $html );
	}

	public function test_a_contact_line_in_text_prints_the_addresses(): void {
		$line = new ContactLine( 'Questions? Acme can help', 'help@acme.test', 'https://acme.test/help', 'their help page', ' or ' );

		$this->assertSame( 'Questions? Acme can help: help@acme.test or https://acme.test/help', $line->text( new Context() ) );
	}

	public function test_a_contact_line_with_nowhere_to_go_renders_nothing(): void {
		$line = new ContactLine( 'Questions? Acme can help' );

		$this->assertSame( '', $line->html( new Context(), $this->style() ) );
		$this->assertSame( '', $line->text( new Context() ) );
	}

	public function test_a_contact_line_does_not_link_a_script_url(): void {
		$line = new ContactLine( 'Help', '', 'javascript:alert(1)' );

		$this->assertSame( '', $line->html( new Context(), $this->style() ) );
		$this->assertSame( '', $line->text( new Context() ) );
	}

	public function test_a_contact_line_escapes_what_it_is_given(): void {
		$html = ( new ContactLine( '<b>Help</b>', 'a"b@acme.test' ) )->html( new Context(), $this->style() );

		$this->assertStringContainsString( '&lt;b&gt;Help&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'mailto:a&quot;b@acme.test', $html );
	}

	public function test_components_render_inside_a_message(): void {
		$rendered = $this->renderer()
			->register( 'note', new Paragraph( 'From the seller.' ) )
			->register( 'support', new ContactLine( 'Ask', 'help@acme.test' ) )
			->render( new Template( 'x', 'Hi', '{{note}}<p>Body</p>{{support}}' ) );

		$this->assertStringContainsString( 'From the seller.</p><p>Body</p>', $rendered->html );
		$this->assertStringContainsString( 'Ask: help@acme.test', $rendered->text );
	}
}
