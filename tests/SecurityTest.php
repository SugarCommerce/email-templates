<?php
/**
 * What an attacker can reach through the data, the design and the files.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.4.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\EmailTemplates\Branding;
use SugarCommerce\EmailTemplates\Components\ContactLine;
use SugarCommerce\EmailTemplates\Components\LinkList;
use SugarCommerce\EmailTemplates\Context;
use SugarCommerce\EmailTemplates\Layout;
use SugarCommerce\EmailTemplates\Palette;
use SugarCommerce\EmailTemplates\Renderer;
use SugarCommerce\EmailTemplates\Style;
use SugarCommerce\EmailTemplates\Template;
use SugarCommerce\EmailTemplates\Theme;

#[CoversClass( Renderer::class )]
#[CoversClass( Palette::class )]
#[CoversClass( Theme::class )]
#[CoversClass( Branding::class )]
#[CoversClass( ContactLine::class )]
#[CoversClass( Template::class )]
#[CoversClass( Context::class )]
final class SecurityTest extends TestCase {

	private function branding(): Branding {
		return new Branding( name: 'Acme', accent: '#06d6a0', footer: 'Small print.' );
	}

	private function renderer(): Renderer {
		return new Renderer( $this->branding() );
	}

	private function style(): Style {
		return new Style( $this->branding(), Palette::light( $this->branding() ), new Theme() );
	}

	/* ─── The subject is a header ──────────────────────────────────── */

	/**
	 * A name with a line break in it would write a header of its own when
	 * the transport puts the subject on the wire.
	 */
	#[DataProvider( 'line_breaks' )]
	public function test_a_value_cannot_break_the_subject_onto_a_new_line( string $break ): void {
		$out = $this->renderer()->render(
			new Template( 't', 'Receipt for {{name}}', '<p>x</p>' ),
			array( 'name' => 'Dave' . $break . 'Bcc: victim@example.test' )
		);

		$this->assertSame( 'Receipt for Dave Bcc: victim@example.test', $out->subject );
		$this->assertDoesNotMatchRegularExpression( '/[\r\n]/', $out->subject );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function line_breaks(): array {
		return array(
			'crlf'      => array( "\r\n" ),
			'lf'        => array( "\n" ),
			'cr'        => array( "\r" ),
			'nul'       => array( "\0" ),
			'separator' => array( "\u{2028}" ),
			'nel'       => array( "\u{0085}" ),
		);
	}

	public function test_a_component_in_the_subject_stays_on_one_line(): void {
		$out = $this->renderer()->render(
			new Template( 't', 'Codes: {{codes}}', '<p>x</p>' ),
			array( 'codes' => array( 'AAA', 'BBB' ) )
		);

		$this->assertSame( 'Codes: AAA BBB', $out->subject );
	}

	public function test_the_preheader_stays_on_one_line(): void {
		$template = new Template( 't', 'S', '<p>x</p>', '', Layout::Card, 'Hi {{name}}' );
		$out      = $this->renderer()->render( $template, array( 'name' => "Dave\r\nthere" ) );

		$this->assertStringContainsString( 'Hi Dave there</div>', $out->html );
	}

	/* ─── One pass ─────────────────────────────────────────────────── */

	/**
	 * A customer whose name is a tag must not pull other data, a component,
	 * or the layout's slots into their message.
	 */
	public function test_a_value_that_looks_like_a_tag_is_not_expanded(): void {
		$out = $this->renderer()->render(
			new Template( 't', 'Hi {{name}}', '<p>Hi {{name}}</p>', '', Layout::Card, '{{name}}' ),
			array(
				'name'   => '{{secret}} {{links}} {{footer}} {{content}}',
				'secret' => 'SECRET',
				'links'  => array( array( 'label' => 'Download', 'url' => 'https://example.test/file' ) ),
			)
		);

		$this->assertSame( 'Hi {{secret}} {{links}} {{footer}} {{content}}', $out->subject );
		$this->assertStringNotContainsString( 'SECRET', $out->html );
		$this->assertStringNotContainsString( 'SECRET', $out->text );
		$this->assertStringNotContainsString( 'example.test/file', $out->html );
		$this->assertSame( 1, substr_count( $out->html, 'Small print.' ) );
		$this->assertStringContainsString( '<p>Hi {{secret}} {{links}} {{footer}} {{content}}</p>', $out->html );
	}

	/* ─── The derived text part ────────────────────────────────────── */

	/**
	 * The text part is derived by stripping tags from the body, which used
	 * to strip them from the data too.
	 */
	public function test_derived_text_keeps_a_value_that_looks_like_markup(): void {
		$out = $this->renderer()->render(
			new Template( 't', 'S', '<p>Hi {{name}}</p>{{codes}}' ),
			array( 'name' => 'Bob <bob@example.test> &amp; co', 'codes' => array( 'AB<CD>EF' ) )
		);

		$this->assertStringContainsString( 'Hi Bob <bob@example.test> &amp; co', $out->text );
		$this->assertStringContainsString( 'AB<CD>EF', $out->text );
	}

	public function test_a_value_cannot_add_a_link_to_the_derived_text(): void {
		$out = $this->renderer()->render(
			new Template( 't', 'S', '<p>Hi {{name}}</p>' ),
			array( 'name' => '<a href="https://evil.test">Reset your password</a>' )
		);

		$this->assertStringNotContainsString( 'Reset your password (https://evil.test)', $out->text );
		$this->assertStringContainsString( '<a href="https://evil.test">Reset your password</a>', $out->text );
	}

	/* ─── Design values land in style attributes ───────────────────── */

	#[DataProvider( 'bad_colours' )]
	public function test_a_palette_colour_cannot_leave_its_declaration( string $colour ): void {
		$this->expectException( InvalidArgumentException::class );

		Palette::light( $this->branding() )->with( text: $colour );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_colours(): array {
		return array(
			'attribute'  => array( '#000" onmouseover="alert(1)' ),
			'markup'     => array( '#000"><script>alert(1)</script>' ),
			'stylesheet' => array( 'red;background:url(https://evil.test/track)' ),
			'url'        => array( 'url(https://evil.test/track)' ),
			'expression' => array( 'expression(alert(1))' ),
			'newline'    => array( "#000000\n" ),
			'empty'      => array( '' ),
		);
	}

	#[DataProvider( 'good_colours' )]
	public function test_a_palette_takes_any_ordinary_colour( string $colour ): void {
		$this->assertSame( $colour, Palette::light( $this->branding() )->with( border: $colour )->border );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function good_colours(): array {
		return array(
			'short hex'   => array( '#ddd' ),
			'hex'         => array( '#1a1a1a' ),
			'hex alpha'   => array( '#1a1a1a80' ),
			'rgb'         => array( 'rgb(26, 26, 26)' ),
			'rgba'        => array( 'rgba(0,0,0,0.5)' ),
			'hsl'         => array( 'hsl(210 40% 50% / 0.5)' ),
			'keyword'     => array( 'transparent' ),
		);
	}

	#[DataProvider( 'bad_fonts' )]
	public function test_a_font_stack_cannot_leave_its_declaration( string $font ): void {
		$this->expectException( InvalidArgumentException::class );

		new Theme( font: $font );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_fonts(): array {
		return array(
			'attribute'  => array( 'Inter" onmouseover="alert(1)' ),
			'stylesheet' => array( 'Inter;background:url(https://evil.test/track)' ),
			'markup'     => array( 'Inter"><img src=x>' ),
			'url'        => array( 'url(https://evil.test/font)' ),
			'newline'    => array( "Inter\n" ),
		);
	}

	public function test_a_mono_stack_is_checked_too(): void {
		$this->expectException( InvalidArgumentException::class );

		new Theme( mono_font: 'monospace;color:red' );
	}

	public function test_an_ordinary_font_stack_is_accepted(): void {
		$theme = new Theme( font: "'Noto Sans JP', 'ヒラギノ角ゴ', -apple-system, sans-serif" );

		$this->assertStringContainsString( 'Noto Sans JP', $theme->font );
	}

	#[DataProvider( 'bad_line_heights' )]
	public function test_a_line_height_is_a_number( string $line_height ): void {
		$this->expectException( InvalidArgumentException::class );

		new Theme( line_height: $line_height );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_line_heights(): array {
		return array(
			'declaration' => array( '1.6;color:red' ),
			'attribute'   => array( '1.6" onmouseover="x' ),
			'word'        => array( 'tall' ),
		);
	}

	public function test_a_line_height_may_carry_a_unit(): void {
		$this->assertSame( '22px', ( new Theme( line_height: '22px' ) )->line_height );
	}

	public function test_a_dimension_cannot_be_negative(): void {
		$this->expectException( InvalidArgumentException::class );

		new Theme( width: -600 );
	}

	public function test_a_logo_url_cannot_leave_its_attribute(): void {
		$this->expectException( InvalidArgumentException::class );

		new Branding( 'Acme', '#000000', logo_url: 'https://x.test/logo.png" onerror="alert(1)' );
	}

	public function test_an_accent_with_a_trailing_newline_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		new Branding( 'Acme', "#000000\n" );
	}

	/* ─── Links ─────────────────────────────────────────────────────── */

	/**
	 * `?` starts a mailto link's headers: an address taken from settings
	 * must not be able to add a Bcc or write the reader's reply for them.
	 */
	public function test_a_contact_address_cannot_add_mailto_headers(): void {
		$line = new ContactLine( 'Questions?', email: 'help@acme.test?bcc=attacker@evil.test&body=hi' );

		$this->assertSame( '', $line->html( new Context(), $this->style() ) );
		$this->assertSame( '', $line->text( new Context() ) );
	}

	public function test_a_bad_contact_address_still_leaves_the_help_page(): void {
		$line = new ContactLine( 'Questions?', email: 'not an address', url: 'https://acme.test/help' );
		$html = $line->html( new Context(), $this->style() );

		$this->assertStringNotContainsString( 'mailto:', $html );
		$this->assertStringContainsString( 'https://acme.test/help', $html );
	}

	public function test_a_link_with_an_array_for_a_label_is_not_an_error(): void {
		$context = new Context( array( 'links' => array( array( 'label' => array( 'x' ), 'url' => 'https://example.test/' ) ) ) );

		$this->assertStringContainsString( 'https://example.test/', ( new LinkList() )->html( $context, $this->style() ) );
	}

	/* ─── Files ─────────────────────────────────────────────────────── */

	#[DataProvider( 'bad_extensions' )]
	public function test_resolve_refuses_an_extension_that_is_a_path( string $extension ): void {
		$dir = sys_get_temp_dir() . '/et-sec-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir . '/receipt.', 0777, true );
		file_put_contents( $dir . '/secret.txt', 'secret' );

		try {
			$this->assertNull( Template::resolve( $dir, 'receipt', 'en', 'en', $extension ) );
		} finally {
			unlink( $dir . '/secret.txt' );
			rmdir( $dir . '/receipt.' );
			rmdir( $dir );
		}
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_extensions(): array {
		return array(
			'parent'  => array( '/../secret.txt' ),
			'slash'   => array( 'html/x' ),
			'newline' => array( "html\n" ),
			'empty'   => array( '' ),
		);
	}

	/* ─── Coercion ──────────────────────────────────────────────────── */

	public function test_context_text_coerces_without_warnings(): void {
		$this->assertSame( 'a', Context::text( 'a' ) );
		$this->assertSame( '2', Context::text( 2 ) );
		$this->assertSame( '', Context::text( null ) );
		$this->assertSame( '', Context::text( array( 'x' ) ) );
		$this->assertSame( '', Context::text( new \stdClass() ) );
	}
}
