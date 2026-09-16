<?php
/**
 * The built-in components.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\EmailTemplates\Branding;
use SugarCommerce\EmailTemplates\Component;
use SugarCommerce\EmailTemplates\Components\Button;
use SugarCommerce\EmailTemplates\Components\CodeList;
use SugarCommerce\EmailTemplates\Components\Details;
use SugarCommerce\EmailTemplates\Components\Divider;
use SugarCommerce\EmailTemplates\Components\LinkList;
use SugarCommerce\EmailTemplates\Components\OrderItems;
use SugarCommerce\EmailTemplates\Context;
use SugarCommerce\EmailTemplates\Palette;
use SugarCommerce\EmailTemplates\Style;
use SugarCommerce\EmailTemplates\Theme;

#[CoversClass( OrderItems::class )]
#[CoversClass( LinkList::class )]
#[CoversClass( CodeList::class )]
#[CoversClass( Button::class )]
#[CoversClass( Details::class )]
#[CoversClass( Divider::class )]
#[CoversClass( Style::class )]
final class ComponentsTest extends TestCase {

	private function style( bool $inverted = false ): Style {
		$branding = new Branding( 'Acme', '#06d6a0' );

		return new Style(
			$branding,
			$inverted ? Palette::inverted( $branding ) : Palette::light( $branding ),
			new Theme()
		);
	}

	private function context( array $data ): Context {
		return new Context( $data );
	}

	/* ─── OrderItems ────────────────────────────────────────────────── */

	public function test_order_items_renders_a_row_per_item(): void {
		$html = ( new OrderItems() )->html(
			$this->context( array( 'items' => array(
				array( 'title' => 'Widget', 'total' => '£10' ),
				array( 'title' => 'Gadget', 'total' => '£20' ),
			) ) ),
			$this->style()
		);

		$this->assertSame( 2, substr_count( $html, '<tr>' ) );
		$this->assertStringContainsString( 'Widget', $html );
		$this->assertStringContainsString( 'Gadget', $html );
	}

	public function test_order_items_adds_a_total_row_when_a_total_exists(): void {
		$context = $this->context( array(
			'items' => array( array( 'title' => 'Widget', 'total' => '£10' ) ),
			'order' => array( 'total' => '£10' ),
		) );

		$this->assertStringContainsString( 'Total', ( new OrderItems() )->html( $context, $this->style() ) );
		$this->assertStringContainsString( 'Total: £10', ( new OrderItems() )->text( $context ) );
	}

	public function test_order_items_omits_the_total_row_without_one(): void {
		$context = $this->context( array( 'items' => array( array( 'title' => 'Widget', 'total' => '£10' ) ) ) );

		$this->assertStringNotContainsString( 'Total', ( new OrderItems() )->html( $context, $this->style() ) );
	}

	public function test_order_items_shows_quantity_only_above_one(): void {
		$one  = $this->context( array( 'items' => array( array( 'title' => 'A', 'quantity' => '1', 'total' => '£1' ) ) ) );
		$many = $this->context( array( 'items' => array( array( 'title' => 'A', 'quantity' => '3', 'total' => '£3' ) ) ) );

		$this->assertStringNotContainsString( '&times;', ( new OrderItems() )->html( $one, $this->style() ) );
		$this->assertStringContainsString( '&times;3', ( new OrderItems() )->html( $many, $this->style() ) );
	}

	public function test_order_items_escapes_titles(): void {
		$html = ( new OrderItems() )->html(
			$this->context( array( 'items' => array( array( 'title' => '<script>x</script>', 'total' => '£1' ) ) ) ),
			$this->style()
		);

		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_order_items_renders_nothing_without_items(): void {
		$this->assertSame( '', ( new OrderItems() )->html( $this->context( array() ), $this->style() ) );
		$this->assertSame( '', ( new OrderItems() )->text( $this->context( array() ) ) );
	}

	public function test_order_items_skips_malformed_entries(): void {
		$html = ( new OrderItems() )->html(
			$this->context( array( 'items' => array( 'not an array', array( 'title' => 'Real', 'total' => '£1' ) ) ) ),
			$this->style()
		);

		$this->assertStringContainsString( 'Real', $html );
		$this->assertSame( 1, substr_count( $html, '<tr>' ) );
	}

	/* ─── LinkList ──────────────────────────────────────────────────── */

	public function test_link_list_renders_anchors(): void {
		$html = ( new LinkList() )->html(
			$this->context( array( 'links' => array( array( 'label' => 'Download', 'url' => 'https://x.com/d', 'note' => '4 MB' ) ) ) ),
			$this->style()
		);

		$this->assertStringContainsString( 'href="https://x.com/d"', $html );
		$this->assertStringContainsString( 'Download', $html );
		$this->assertStringContainsString( '4 MB', $html );
	}

	/**
	 * A dead link is worse than a missing one.
	 */
	#[DataProvider( 'unsafe_link_urls' )]
	public function test_link_list_drops_unsafe_urls( string $url ): void {
		$context = $this->context( array( 'links' => array( array( 'label' => 'Bad', 'url' => $url ) ) ) );

		$this->assertSame( '', ( new LinkList() )->html( $context, $this->style() ) );
		$this->assertSame( '', ( new LinkList() )->text( $context ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function unsafe_link_urls(): array {
		return array(
			'javascript' => array( 'javascript:alert(1)' ),
			'data'       => array( 'data:text/html,<script>x</script>' ),
			'relative'   => array( '/local' ),
			'empty'      => array( '' ),
		);
	}

	public function test_link_list_keeps_good_links_beside_bad(): void {
		$html = ( new LinkList() )->html(
			$this->context( array( 'links' => array(
				array( 'label' => 'Bad', 'url' => 'javascript:alert(1)' ),
				array( 'label' => 'Good', 'url' => 'https://x.com' ),
			) ) ),
			$this->style()
		);

		$this->assertStringContainsString( 'Good', $html );
		$this->assertStringNotContainsString( 'Bad', $html );
	}

	public function test_link_list_text_includes_the_url(): void {
		$text = ( new LinkList() )->text(
			$this->context( array( 'links' => array( array( 'label' => 'Download', 'url' => 'https://x.com/d' ) ) ) )
		);

		$this->assertStringContainsString( 'https://x.com/d', $text );
	}

	/* ─── CodeList ──────────────────────────────────────────────────── */

	public function test_code_list_accepts_plain_strings(): void {
		$context = $this->context( array( 'codes' => array( 'AAA-BBB', 'CCC-DDD' ) ) );

		$html = ( new CodeList() )->html( $context, $this->style() );

		$this->assertStringContainsString( 'AAA-BBB', $html );
		$this->assertStringContainsString( 'CCC-DDD', $html );
		$this->assertSame( "AAA-BBB\nCCC-DDD", ( new CodeList() )->text( $context ) );
	}

	public function test_code_list_accepts_labelled_entries(): void {
		$context = $this->context( array( 'codes' => array( array( 'label' => 'Licence', 'code' => 'AAA-BBB' ) ) ) );

		$this->assertStringContainsString( 'Licence', ( new CodeList() )->html( $context, $this->style() ) );
		$this->assertSame( 'Licence: AAA-BBB', ( new CodeList() )->text( $context ) );
	}

	public function test_code_list_ignores_entries_with_no_code(): void {
		$context = $this->context( array( 'codes' => array( array( 'label' => 'Empty' ) ) ) );

		$this->assertSame( '', ( new CodeList() )->html( $context, $this->style() ) );
	}

	public function test_code_list_escapes_codes(): void {
		$html = ( new CodeList() )->html( $this->context( array( 'codes' => array( '<b>x</b>' ) ) ), $this->style() );

		$this->assertStringNotContainsString( '<b>x</b>', $html );
	}

	/* ─── Button ────────────────────────────────────────────────────── */

	public function test_button_renders_with_a_url(): void {
		$context = $this->context( array( 'button' => array( 'label' => 'View order', 'url' => 'https://x.com/o' ) ) );

		$html = ( new Button() )->html( $context, $this->style() );

		$this->assertStringContainsString( 'href="https://x.com/o"', $html );
		$this->assertStringContainsString( 'View order', $html );
		$this->assertStringContainsString( 'https://x.com/o', ( new Button() )->text( $context ) );
	}

	public function test_button_defaults_its_label(): void {
		$html = ( new Button() )->html(
			$this->context( array( 'button' => array( 'url' => 'https://x.com' ) ) ),
			$this->style()
		);

		$this->assertStringContainsString( 'Continue', $html );
	}

	public function test_button_renders_nothing_without_a_safe_url(): void {
		foreach ( array( '', 'javascript:alert(1)' ) as $url ) {
			$context = $this->context( array( 'button' => array( 'label' => 'Go', 'url' => $url ) ) );

			$this->assertSame( '', ( new Button() )->html( $context, $this->style() ) );
			$this->assertSame( '', ( new Button() )->text( $context ) );
		}
	}

	/* ─── Details ───────────────────────────────────────────────────── */

	public function test_details_accepts_a_map(): void {
		$context = $this->context( array( 'details' => array( 'Order' => '1234', 'Date' => 'today' ) ) );

		$this->assertSame( "Order: 1234\nDate: today", ( new Details() )->text( $context ) );
	}

	public function test_details_accepts_a_list_of_pairs(): void {
		$context = $this->context( array( 'details' => array( array( 'Order', '1234' ), array( 'Order', '5678' ) ) ) );

		$this->assertSame( "Order: 1234\nOrder: 5678", ( new Details() )->text( $context ) );
	}

	public function test_details_never_renders_the_word_array(): void {
		$html = ( new Details() )->html(
			$this->context( array( 'details' => array( array( array(), array() ) ) ) ),
			$this->style()
		);

		$this->assertStringNotContainsString( 'Array', $html );
	}

	public function test_details_escapes_values(): void {
		$html = ( new Details() )->html(
			$this->context( array( 'details' => array( 'Note' => '<script>x</script>' ) ) ),
			$this->style()
		);

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/* ─── Divider ───────────────────────────────────────────────────── */

	public function test_divider_renders_in_both_modes(): void {
		$this->assertStringContainsString( '<table', ( new Divider() )->html( $this->context( array() ), $this->style() ) );
		$this->assertStringContainsString( '---', ( new Divider() )->text( $this->context( array() ) ) );
	}

	/* ─── Palette adaptation ────────────────────────────────────────── */

	/**
	 * The bug that started this: components hardcoded light-mode greys,
	 * so every rule and panel showed as a white line on the dark variant.
	 */
	#[DataProvider( 'every_component' )]
	public function test_no_component_hardcodes_a_colour( Component $component, array $data ): void {
		$light = $component->html( $this->context( $data ), $this->style() );
		$dark  = $component->html( $this->context( $data ), $this->style( inverted: true ) );

		if ( '' === trim( $light ) ) {
			$this->markTestSkipped( 'Component renders nothing for this data.' );
		}

		$lightPalette = Palette::light( new Branding( 'Acme', '#06d6a0' ) );

		foreach ( array( $lightPalette->border, $lightPalette->muted, $lightPalette->text, $lightPalette->surface ) as $colour ) {
			$this->assertStringNotContainsString(
				$colour,
				$dark,
				'A light-mode colour survived into the inverted rendering.'
			);
		}

		$this->assertNotSame( $light, $dark, 'The two variants should differ.' );
	}

	/**
	 * @return array<string, array{0: Component, 1: array<string, mixed>}>
	 */
	public static function every_component(): array {
		return array(
			'order_items' => array( new OrderItems(), array(
				'items' => array( array( 'title' => 'A', 'meta' => 'm', 'total' => '£1' ) ),
				'order' => array( 'total' => '£1' ),
			) ),
			'links'       => array( new LinkList(), array( 'links' => array( array( 'label' => 'L', 'url' => 'https://x.com', 'note' => 'n' ) ) ) ),
			'codes'       => array( new CodeList(), array( 'codes' => array( array( 'label' => 'K', 'code' => 'AAA' ) ) ) ),
			'details'     => array( new Details(), array( 'details' => array( 'A' => 'B' ) ) ),
			'divider'     => array( new Divider(), array() ),
		);
	}
}
