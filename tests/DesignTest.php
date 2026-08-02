<?php
/**
 * Branding, palette, theme and layout.
 *
 * @package   Sugar\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\EmailTemplates\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sugar\EmailTemplates\Branding;
use Sugar\EmailTemplates\Layout;
use Sugar\EmailTemplates\Palette;
use Sugar\EmailTemplates\Renderer;
use Sugar\EmailTemplates\Template;
use Sugar\EmailTemplates\Theme;

#[CoversClass( Branding::class )]
#[CoversClass( Palette::class )]
#[CoversClass( Theme::class )]
#[CoversClass( Layout::class )]
final class DesignTest extends TestCase {

	/* ─── Branding ──────────────────────────────────────────────────── */

	/**
	 * The accent lands inside a `style` attribute, so anything but a hex
	 * colour is a stylesheet injection point an operator could reach
	 * through a settings form.
	 */
	#[DataProvider( 'bad_accents' )]
	public function test_a_malformed_accent_is_rejected( string $accent ): void {
		$this->expectException( InvalidArgumentException::class );

		new Branding( 'Acme', $accent );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_accents(): array {
		return array(
			'no hash'    => array( '06d6a0' ),
			'short form' => array( '#fff' ),
			'named'      => array( 'red' ),
			'injection'  => array( '#fff;} body { display:none' ),
			'quote'      => array( '#fff" onload="alert(1)' ),
			'empty'      => array( '' ),
		);
	}

	public function test_a_valid_accent_is_accepted(): void {
		$this->assertSame( '#06d6a0', ( new Branding( 'Acme', '#06d6a0' ) )->accent );
		$this->assertSame( '#ABCDEF', ( new Branding( 'Acme', '#ABCDEF' ) )->accent );
	}

	/**
	 * A flat channel average puts white text on yellow; the BT.601 luma
	 * weights green heavily because the eye does.
	 */
	#[DataProvider( 'accent_contrasts' )]
	public function test_accent_foreground_picks_a_readable_colour( string $accent, string $expected ): void {
		$this->assertSame( $expected, ( new Branding( 'Acme', $accent ) )->accent_foreground() );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function accent_contrasts(): array {
		return array(
			'black'  => array( '#000000', '#ffffff' ),
			'white'  => array( '#ffffff', '#111111' ),
			'yellow' => array( '#ffff00', '#111111' ),
			'navy'   => array( '#001f3f', '#ffffff' ),
			'mint'   => array( '#06d6a0', '#ffffff' ),
		);
	}

	#[DataProvider( 'bad_logo_urls' )]
	public function test_a_logo_url_must_be_https( string $url ): void {
		$this->expectException( InvalidArgumentException::class );

		new Branding( 'Acme', '#000000', logo_url: $url );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function bad_logo_urls(): array {
		return array(
			'http'       => array( 'http://x.com/logo.png' ),
			'javascript' => array( 'javascript:alert(1)' ),
			'data'       => array( 'data:image/png;base64,AAA' ),
			'relative'   => array( '/logo.png' ),
		);
	}

	public function test_an_https_logo_is_accepted(): void {
		$this->assertSame( 'https://x.com/logo.png', ( new Branding( 'Acme', '#000000', logo_url: 'https://x.com/logo.png' ) )->logo_url );
	}

	/* ─── Palette ───────────────────────────────────────────────────── */

	public function test_the_two_palettes_differ_on_every_surface(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );
		$light    = Palette::light( $branding );
		$dark     = Palette::inverted( $branding );

		foreach ( array( 'text', 'muted', 'border', 'surface', 'card', 'background' ) as $property ) {
			$this->assertNotSame( $light->$property, $dark->$property, $property . ' should differ between variants.' );
		}

		$this->assertFalse( $light->inverted );
		$this->assertTrue( $dark->inverted );
	}

	public function test_both_palettes_carry_the_branding_accent(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );

		$this->assertSame( '#06d6a0', Palette::light( $branding )->accent );
		$this->assertSame( '#06d6a0', Palette::inverted( $branding )->accent );
	}

	/* ─── Theme ─────────────────────────────────────────────────────── */

	public function test_a_theme_has_workable_defaults(): void {
		$theme = new Theme();

		$this->assertSame( 600, $theme->width );
		$this->assertStringContainsString( 'sans-serif', $theme->font, 'The stack must end in a system fallback.' );
		$this->assertStringContainsString( 'monospace', $theme->mono_font );
	}

	public function test_every_design_value_can_be_replaced(): void {
		$theme = new Theme(
			font: "'Inter', sans-serif",
			width: 720,
			font_size: 17,
			radius: 20,
			button_radius: 999,
			padding: 48,
		);

		$this->assertSame( 720, $theme->width );
		$this->assertSame( 17, $theme->font_size );
		$this->assertSame( 999, $theme->button_radius );
		$this->assertSame( 48, $theme->padding );
	}

	public function test_a_theme_falls_back_to_the_stock_palettes(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );
		$theme    = new Theme();

		$this->assertSame( Palette::light( $branding )->text, $theme->palette( $branding, false )->text );
		$this->assertSame( Palette::inverted( $branding )->text, $theme->palette( $branding, true )->text );
	}

	public function test_a_custom_palette_overrides_the_default(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );
		$custom   = Palette::light( $branding )->with( text: '#ff0000', muted: '#00ff00', border: '#0000ff' );
		$theme    = new Theme( light: $custom );

		$this->assertSame( '#ff0000', $theme->palette( $branding, false )->text );
		$this->assertSame( Palette::inverted( $branding )->text, $theme->palette( $branding, true )->text, 'The dark side is untouched.' );
	}

	public function test_palettes_can_be_swapped_on_a_copy(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );
		$custom   = Palette::light( $branding )->with( text: '#ff0000', muted: '#00ff00', border: '#0000ff' );
		$theme    = ( new Theme( width: 700 ) )->with_palettes( light: $custom );

		$this->assertSame( '#ff0000', $theme->palette( $branding, false )->text );
		$this->assertSame( 700, $theme->width, 'Other settings survive the copy.' );
	}

	/**
	 * The whole point of the exercise — a fully custom design with no
	 * package default surviving anywhere in the output.
	 */
	public function test_a_custom_theme_reaches_the_rendered_email(): void {
		$branding = new Branding( 'Acme', '#ff6600' );
		$theme    = new Theme(
			font: "'Custom Face', serif",
			width: 720,
			font_size: 19,
			radius: 24,
			light: Palette::light( $branding )->with( text: '#123456', border: '#abcdef', card: '#0f0f0f' ),
		);

		$html = ( new Renderer( $branding, $theme ) )->render( new Template( 't', 'S', '<p>x</p>' ) )->html;

		foreach ( array( "'Custom Face', serif", '720', '19px', '24px', '#123456', '#abcdef', '#0f0f0f' ) as $expected ) {
			$this->assertStringContainsString( $expected, $html );
		}

		$this->assertStringNotContainsString( '#37352f', $html, 'A stock palette value leaked into a custom theme.' );
	}

	/* ─── Layout ────────────────────────────────────────────────────── */

	public function test_inverted_variants_are_identified(): void {
		$this->assertFalse( Layout::Card->is_inverted() );
		$this->assertTrue( Layout::CardInverted->is_inverted() );
		$this->assertTrue( Layout::RuledInverted->is_inverted() );
	}

	public function test_a_layout_toggles_both_ways(): void {
		$this->assertSame( Layout::CardInverted, Layout::Card->inverted() );
		$this->assertSame( Layout::Card, Layout::CardInverted->inverted() );
		$this->assertSame( Layout::PlainInverted, Layout::Plain->inverted() );
	}

	public function test_every_layout_has_an_inverted_counterpart(): void {
		foreach ( Layout::cases() as $layout ) {
			$this->assertNotSame( $layout, $layout->inverted() );
			$this->assertSame( $layout, $layout->inverted()->inverted(), 'Toggling twice returns the original.' );
		}
	}

	public function test_variants_share_a_base_design(): void {
		$this->assertSame( Layout::Card, Layout::CardInverted->base() );
		$this->assertSame( Layout::Card, Layout::Card->base() );
	}

	public function test_a_variant_pair_shares_one_layout_file(): void {
		$this->assertSame( Layout::Card->html(), Layout::CardInverted->html() );
	}

	public function test_every_layout_file_exists_and_has_a_content_slot(): void {
		foreach ( Layout::cases() as $layout ) {
			$this->assertStringContainsString( '{{content}}', $layout->html(), $layout->value . ' has no content slot.' );
		}
	}

	/**
	 * A literal colour in a layout is a value the operator cannot change.
	 */
	public function test_no_layout_hardcodes_a_colour_or_a_dimension(): void {
		foreach ( Layout::cases() as $layout ) {
			$html = $layout->html();

			$this->assertSame( 0, preg_match( '/#[0-9a-fA-F]{6}/', $html ), $layout->value . ' hardcodes a colour.' );
			$this->assertSame( 0, preg_match( '/font-size:\s*\d+px/', $html ), $layout->value . ' hardcodes a font size.' );
			$this->assertSame( 0, preg_match( '/font-family:\s*[a-zA-Z-]/', $html ), $layout->value . ' hardcodes a font.' );
		}
	}

	public function test_each_variant_selects_the_matching_palette(): void {
		$branding = new Branding( 'Acme', '#06d6a0' );

		$this->assertFalse( Layout::Card->palette( $branding )->inverted );
		$this->assertTrue( Layout::CardInverted->palette( $branding )->inverted );
	}

	public function test_labels_and_descriptions_are_populated(): void {
		foreach ( Layout::cases() as $layout ) {
			$this->assertNotSame( '', $layout->label() );
			$this->assertNotSame( '', $layout->description() );
		}

		$this->assertSame( 'Card', Layout::Card->label() );
		$this->assertSame( 'Card (inverted)', Layout::CardInverted->label() );
	}

	public function test_options_can_hide_the_inverted_variants(): void {
		$this->assertCount( 6, Layout::options() );
		$this->assertCount( 3, Layout::options( include_inverted: false ) );
		$this->assertArrayNotHasKey( 'card-inverted', Layout::options( include_inverted: false ) );
	}
}
