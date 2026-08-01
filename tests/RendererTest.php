<?php
/**
 * The rendering engine.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SugarMill\EmailTemplates\Branding;
use SugarMill\EmailTemplates\Context;
use SugarMill\EmailTemplates\Html;
use SugarMill\EmailTemplates\Layout;
use SugarMill\EmailTemplates\Rendered;
use SugarMill\EmailTemplates\Renderer;
use SugarMill\EmailTemplates\Template;
use SugarMill\EmailTemplates\Theme;

#[CoversClass( Renderer::class )]
#[CoversClass( Template::class )]
#[CoversClass( Context::class )]
#[CoversClass( Html::class )]
#[CoversClass( Rendered::class )]
final class RendererTest extends TestCase {

	private function renderer( bool $strict = false ): Renderer {
		return new Renderer( $this->branding(), new Theme(), $strict );
	}

	private function branding(): Branding {
		return new Branding( name: 'Acme', accent: '#06d6a0', footer: 'Small print.', address: '1 Example St' );
	}

	private function template( string $html, string $subject = 'Hello', string $text = '' ): Template {
		return new Template( 'test', $subject, $html, $text );
	}

	/* ─── Substitution ──────────────────────────────────────────────── */

	public function test_a_scalar_tag_is_replaced(): void {
		$out = $this->renderer()->render( $this->template( '<p>Hi {{customer.name}}</p>' ), array( 'customer' => array( 'name' => 'Dave' ) ) );

		$this->assertStringContainsString( 'Hi Dave', $out->html );
	}

	public function test_tags_tolerate_inner_whitespace(): void {
		$out = $this->renderer()->render( $this->template( '<p>{{  customer.name  }}</p>' ), array( 'customer' => array( 'name' => 'Dave' ) ) );

		$this->assertStringContainsString( 'Dave', $out->html );
	}

	public function test_a_deep_path_resolves(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>{{a.b.c.d}}</p>' ),
			array( 'a' => array( 'b' => array( 'c' => array( 'd' => 'deep' ) ) ) )
		);

		$this->assertStringContainsString( 'deep', $out->html );
	}

	public function test_the_subject_is_substituted(): void {
		$out = $this->renderer()->render( $this->template( '<p>x</p>', 'Receipt for {{customer.name}}' ), array( 'customer' => array( 'name' => 'Dave' ) ) );

		$this->assertSame( 'Receipt for Dave', $out->subject );
	}

	public function test_numeric_and_boolean_values_are_coerced(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>{{n}} {{f}} {{b}}</p>' ),
			array( 'n' => 42, 'f' => 1.5, 'b' => true )
		);

		$this->assertStringContainsString( '42 1.5 yes', $out->html );
	}

	/* ─── Unresolved tags ───────────────────────────────────────────── */

	/**
	 * A customer seeing "Hi {{customer.name}}" is worse than seeing "Hi".
	 */
	public function test_an_unresolved_tag_is_removed_not_left_in_place(): void {
		$out = $this->renderer()->render( $this->template( '<p>Hi {{customer.name}}</p>' ) );

		$this->assertStringNotContainsString( '{{', $out->html );
		$this->assertStringNotContainsString( 'customer.name', $out->html );
	}

	public function test_unresolved_tags_are_reported(): void {
		$out = $this->renderer()->render( $this->template( '<p>{{a}} {{b}}</p>' ), array( 'a' => 'set' ) );

		$this->assertSame( array( 'b' ), $out->unresolved );
		$this->assertFalse( $out->is_complete() );
	}

	public function test_a_clean_render_reports_complete(): void {
		$out = $this->renderer()->render( $this->template( '<p>{{a}}</p>' ), array( 'a' => 'set' ) );

		$this->assertTrue( $out->is_complete() );
		$this->assertSame( array(), $out->unresolved );
	}

	public function test_unresolved_tags_are_deduplicated(): void {
		$out = $this->renderer()->render( $this->template( '<p>{{a}} {{a}} {{a}}</p>' ) );

		$this->assertSame( array( 'a' ), $out->unresolved );
	}

	public function test_strict_mode_throws_on_an_unresolved_tag(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'customer.name' );

		$this->renderer( strict: true )->render( $this->template( '<p>{{customer.name}}</p>' ) );
	}

	public function test_strict_mode_passes_a_complete_render(): void {
		$out = $this->renderer( strict: true )->render( $this->template( '<p>{{a}}</p>' ), array( 'a' => 'x' ) );

		$this->assertTrue( $out->is_complete() );
	}

	/* ─── Escaping ──────────────────────────────────────────────────── */

	/**
	 * A customer's name arrives from a checkout form.
	 */
	public function test_scalar_tags_are_escaped_in_html(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>{{name}}</p>' ),
			array( 'name' => '<script>alert(1)</script>' )
		);

		$this->assertStringNotContainsString( '<script>', $out->html );
		$this->assertStringContainsString( '&lt;script&gt;', $out->html );
	}

	public function test_attribute_breaking_characters_are_escaped(): void {
		$out = $this->renderer()->render(
			$this->template( '<p title="{{name}}">x</p>' ),
			array( 'name' => '" onmouseover="alert(1)' )
		);

		$this->assertStringNotContainsString( 'onmouseover="alert', $out->html );
		$this->assertStringContainsString( '&quot;', $out->html );
	}

	/**
	 * Escaping the text part would show the customer literal entities.
	 */
	public function test_the_text_part_is_not_escaped(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>{{name}}</p>', 'Hi', '{{name}}' ),
			array( 'name' => 'Tom & Jerry' )
		);

		$this->assertStringContainsString( 'Tom & Jerry', $out->text );
		$this->assertStringNotContainsString( '&amp;', $out->text );
	}

	public function test_the_subject_is_not_escaped(): void {
		$out = $this->renderer()->render( $this->template( '<p>x</p>', '{{name}}' ), array( 'name' => 'Tom & Jerry' ) );

		$this->assertSame( 'Tom & Jerry', $out->subject );
	}

	/* ─── Components ────────────────────────────────────────────────── */

	public function test_the_default_components_are_registered(): void {
		$names = $this->renderer()->components();

		foreach ( array( 'order_items', 'links', 'codes', 'button', 'details', 'divider' ) as $expected ) {
			$this->assertContains( $expected, $names );
		}
	}

	public function test_a_component_renders_in_place(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>a</p>{{details}}<p>b</p>' ),
			array( 'details' => array( 'Order' => '1234' ) )
		);

		$this->assertStringContainsString( 'Order', $out->html );
		$this->assertStringContainsString( '1234', $out->html );
	}

	public function test_a_component_with_no_data_renders_nothing(): void {
		$out = $this->renderer()->render( $this->template( '<p>a</p>{{order_items}}<p>b</p>' ) );

		$this->assertStringNotContainsString( 'Total', $out->html );
		$this->assertTrue( $out->is_complete(), 'A component tag is never "unresolved".' );
	}

	public function test_a_custom_component_can_be_registered(): void {
		$renderer = $this->renderer()->register( 'shout', new FakeComponent() );

		$out = $renderer->render( $this->template( '<p>{{shout}}</p>' ) );

		$this->assertStringContainsString( '<b>LOUD</b>', $out->html );
		$this->assertStringContainsString( 'LOUD', $out->text );
	}

	public function test_a_component_name_must_match_the_tag_pattern(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->renderer()->register( 'bad name', new FakeComponent() );
	}

	/* ─── Design tokens ─────────────────────────────────────────────── */

	/**
	 * So an operator can write style="color:{{muted}}" instead of
	 * hardcoding a grey that becomes a white line on the dark variant.
	 */
	public function test_design_tokens_resolve_inside_a_body(): void {
		$out = $this->renderer()->render( $this->template( '<p style="color:{{muted}}">x</p>' ) );

		$this->assertStringContainsString( 'color:#787774', $out->html );
		$this->assertTrue( $out->is_complete() );
	}

	public function test_design_tokens_follow_the_inverted_variant(): void {
		$template = new Template( 't', 'S', '<p style="color:{{muted}}">x</p>', '', Layout::CardInverted );
		$out      = $this->renderer()->render( $template );

		$this->assertStringContainsString( 'color:#9b9b9b', $out->html );
		$this->assertStringNotContainsString( '#787774', $out->html );
	}

	/**
	 * The accent appears on links and buttons only. The layouts carry no
	 * coloured banner — that is the difference between looking designed
	 * and looking like a 2015 notification.
	 */
	public function test_the_accent_reaches_components_not_the_chrome(): void {
		$plain = $this->renderer()->render( $this->template( '<p>x</p>' ) );

		$this->assertStringNotContainsString( '#06d6a0', $plain->html );

		$withButton = $this->renderer()->render(
			$this->template( '{{button}}' ),
			array( 'button' => array( 'label' => 'Go', 'url' => 'https://x.com' ) )
		);

		$this->assertStringContainsString( '#06d6a0', $withButton->html );
	}

	/* ─── Layout ────────────────────────────────────────────────────── */

	public function test_the_body_is_wrapped_in_a_full_document(): void {
		$out = $this->renderer()->render( $this->template( '<p>body text</p>' ) );

		$this->assertStringStartsWith( '<!doctype html>', $out->html );
		$this->assertStringContainsString( '<p>body text</p>', $out->html );
		$this->assertStringContainsString( '</html>', $out->html );
	}

	public function test_the_layout_carries_branding(): void {
		$out = $this->renderer()->render( $this->template( '<p>x</p>' ) );

		$this->assertStringContainsString( 'Acme', $out->html );
		$this->assertStringContainsString( 'Small print.', $out->html );
		$this->assertStringContainsString( '1 Example St', $out->html );
	}

	public function test_no_layout_token_survives_rendering(): void {
		$out = $this->renderer()->render( $this->template( '<p>x</p>' ) );

		$this->assertSame( 0, preg_match( '/\{\{[a-z_]+\}\}/', $out->html ), 'An unreplaced token reached the output.' );
	}

	#[DataProvider( 'every_layout' )]
	public function test_every_layout_renders_a_complete_document( Layout $layout ): void {
		$out = $this->renderer()->render( new Template( 't', 'S', '<p>x</p>', '', $layout ) );

		$this->assertStringStartsWith( '<!doctype html>', $out->html );
		$this->assertStringContainsString( '</html>', $out->html );
		$this->assertSame( 0, preg_match( '/\{\{/', $out->html ) );
	}

	/**
	 * @return array<string, array{0: Layout}>
	 */
	public static function every_layout(): array {
		$cases = array();

		foreach ( Layout::cases() as $layout ) {
			$cases[ $layout->value ] = array( $layout );
		}

		return $cases;
	}

	public function test_the_preheader_is_included(): void {
		$template = new Template( 't', 'S', '<p>x</p>', '', Layout::Card, 'Order {{n}} confirmed' );
		$out      = $this->renderer()->render( $template, array( 'n' => '42' ) );

		$this->assertStringContainsString( 'Order 42 confirmed', $out->html );
	}

	/* ─── Text derivation ───────────────────────────────────────────── */

	public function test_an_explicit_text_body_wins(): void {
		$out = $this->renderer()->render( $this->template( '<p>HTML</p>', 'S', 'PLAIN' ) );

		$this->assertStringContainsString( 'PLAIN', $out->text );
		$this->assertStringNotContainsString( 'HTML', $out->text );
	}

	public function test_text_is_derived_when_absent(): void {
		$out = $this->renderer()->render( $this->template( '<p>Hello there</p>' ) );

		$this->assertStringContainsString( 'Hello there', $out->text );
		$this->assertStringNotContainsString( '<p>', $out->text );
	}

	/**
	 * Converting the finished HTML would give "Order1234"; rendering the
	 * body again in text mode lets each component speak for itself.
	 */
	public function test_derived_text_uses_component_text_output(): void {
		$out = $this->renderer()->render(
			$this->template( '<p>Hi</p>{{details}}' ),
			array( 'details' => array( 'Order' => '1234' ) )
		);

		$this->assertStringContainsString( 'Order: 1234', $out->text );
	}

	public function test_adjacent_components_do_not_run_together_in_text(): void {
		$out = $this->renderer()->render(
			$this->template( '{{details}}{{button}}' ),
			array(
				'details' => array( 'Order' => '1234' ),
				'button'  => array( 'label' => 'View', 'url' => 'https://x.com' ),
			)
		);

		$this->assertStringNotContainsString( '1234View', $out->text );
	}

	public function test_the_text_part_carries_the_footer(): void {
		$out = $this->renderer()->render( $this->template( '<p>x</p>' ) );

		$this->assertStringContainsString( 'Small print.', $out->text );
		$this->assertStringContainsString( '1 Example St', $out->text );
	}

	/* ─── Template ──────────────────────────────────────────────────── */

	public function test_a_template_lists_its_tags(): void {
		$template = new Template( 't', '{{a}}', '<p>{{b}} {{order_items}}</p>', '{{c}}', Layout::Card, '{{d}}' );

		$this->assertSame( array( 'a', 'b', 'order_items', 'c', 'd' ), $template->tags() );
	}

	public function test_tags_are_listed_once_each(): void {
		$template = new Template( 't', '{{a}}', '<p>{{a}} {{a}}</p>' );

		$this->assertSame( array( 'a' ), $template->tags() );
	}

	public function test_a_template_can_switch_layout(): void {
		$template = new Template( 't', 'S', '<p>x</p>' );
		$dark     = $template->with_layout( Layout::RuledInverted );

		$this->assertSame( Layout::Card, $template->layout );
		$this->assertSame( Layout::RuledInverted, $dark->layout );
		$this->assertSame( $template->html, $dark->html );
	}

	/* ─── Context ───────────────────────────────────────────────────── */

	public function test_context_resolves_and_reports_absence(): void {
		$context = new Context( array( 'a' => array( 'b' => 'c' ), 'n' => 1 ) );

		$this->assertSame( 'c', $context->get( 'a.b' ) );
		$this->assertSame( '1', $context->get( 'n' ) );
		$this->assertNull( $context->get( 'a.missing' ) );
		$this->assertNull( $context->get( 'nothing.at.all' ) );
		$this->assertTrue( $context->has( 'a.b' ) );
		$this->assertFalse( $context->has( 'a.z' ) );
	}

	public function test_context_returns_arrays_for_components(): void {
		$context = new Context( array( 'items' => array( 1, 2 ) ) );

		$this->assertCount( 2, $context->list( 'items' ) );
		$this->assertSame( array(), $context->list( 'missing' ) );
		$this->assertSame( array(), $context->list( 'items.0' ), 'A scalar is not a list.' );
	}

	public function test_context_get_returns_null_for_an_array(): void {
		$context = new Context( array( 'a' => array( 'b' => 'c' ) ) );

		$this->assertNull( $context->get( 'a' ) );
	}

	public function test_context_can_be_extended(): void {
		$context = ( new Context( array( 'a' => 1 ) ) )->with( array( 'b' => 2 ) );

		$this->assertSame( '1', $context->get( 'a' ) );
		$this->assertSame( '2', $context->get( 'b' ) );
	}

	/* ─── Html ──────────────────────────────────────────────────────── */

	#[DataProvider( 'unsafe_urls' )]
	public function test_unsafe_urls_are_rejected( string $url ): void {
		$this->assertSame( '', Html::url( $url ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function unsafe_urls(): array {
		return array(
			'javascript' => array( 'javascript:alert(1)' ),
			'mixed case' => array( 'JaVaScRiPt:alert(1)' ),
			'data'       => array( 'data:text/html,<script>alert(1)</script>' ),
			'vbscript'   => array( 'vbscript:msgbox(1)' ),
			'file'       => array( 'file:///etc/passwd' ),
			'relative'   => array( '/account' ),
			'empty'      => array( '' ),
			'newline'    => array( "https://x.com/\r\nSet-Cookie: a=b" ),
		);
	}

	public function test_safe_urls_survive(): void {
		$this->assertNotSame( '', Html::url( 'https://example.com/a?b=c' ) );
		$this->assertNotSame( '', Html::url( 'http://example.com' ) );
		$this->assertNotSame( '', Html::url( 'mailto:a@b.com' ) );
	}

	public function test_to_text_keeps_link_targets(): void {
		$this->assertSame( 'Download (https://x.com/d)', Html::to_text( '<a href="https://x.com/d">Download</a>' ) );
	}

	public function test_to_text_separates_table_cells(): void {
		$text = Html::to_text( '<table><tr><td>Order</td><td>1234</td></tr></table>' );

		$this->assertStringContainsString( 'Order: 1234', $text );
	}
}
