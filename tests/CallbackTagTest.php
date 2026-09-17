<?php
/**
 * Tags defined inline, as closures.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\EmailTemplates\{Branding, Context, Html, Renderer, Style, Template};

final class CallbackTagTest extends TestCase {

	private function renderer(): Renderer {
		return new Renderer( new Branding( name: 'Studio Nine', accent: '#6d28d9' ) );
	}

	private function template( string $html ): Template {
		return new Template( slug: 'test', subject: 'Test', html: $html );
	}

	public function test_an_inline_tag_renders_in_both_forms(): void {
		$renderer = $this->renderer()->tag(
			'licence',
			fn( Context $c ) => '<code>' . Html::escape( (string) $c->get( 'key' ) ) . '</code>',
			fn( Context $c ) => (string) $c->get( 'key' ),
		);

		$rendered = $renderer->render( $this->template( '{{licence}}' ), array( 'key' => 'ABC-123' ) );

		$this->assertStringContainsString( '<code>ABC-123</code>', $rendered->html );
		$this->assertStringContainsString( 'ABC-123', $rendered->text );
	}

	public function test_an_inline_tag_owns_its_escaping(): void {
		$renderer = $this->renderer()->tag(
			'licence',
			fn( Context $c ) => '<code>' . Html::escape( (string) $c->get( 'key' ) ) . '</code>',
			fn( Context $c ) => (string) $c->get( 'key' ),
		);

		$rendered = $renderer->render( $this->template( '{{licence}}' ), array( 'key' => '<script>x</script>' ) );

		$this->assertStringNotContainsString( '<script>', $rendered->html );
		$this->assertStringContainsString( '&lt;script&gt;', $rendered->html );
	}

	public function test_an_inline_tag_receives_the_resolved_style(): void {
		// So a custom tag can track the layout rather than hardcoding a grey
		// that turns into a white line on the inverted variant.
		$renderer = $this->renderer()->tag(
			'swatch',
			fn( Context $c, Style $s ) => '<span>' . $s->branding->accent . '</span>',
			fn( Context $c ) => '',
		);

		$this->assertStringContainsString( '#6d28d9', $renderer->render( $this->template( '{{swatch}}' ) )->html );
	}

	public function test_an_inline_tag_can_render_nothing(): void {
		$renderer = $this->renderer()->tag(
			'maybe',
			fn( Context $c ) => $c->has( 'thing' ) ? '<p>yes</p>' : '',
			fn( Context $c ) => $c->has( 'thing' ) ? 'yes' : '',
		);

		$this->assertStringNotContainsString( 'yes', $renderer->render( $this->template( '{{maybe}}' ) )->html );
	}

	public function test_an_inline_tag_replaces_a_built_in(): void {
		$renderer = $this->renderer()->tag(
			'button',
			fn( Context $c ) => '<p>replaced</p>',
			fn( Context $c ) => 'replaced',
		);

		$rendered = $renderer->render( $this->template( '{{button}}' ), array(
			'button' => array( 'label' => 'Download', 'url' => 'https://example.com' ),
		) );

		$this->assertStringContainsString( 'replaced', $rendered->html );
		$this->assertStringNotContainsString( 'Download', $rendered->html );
	}

	public function test_an_inline_tag_is_listed(): void {
		$this->assertContains( 'licence', $this->renderer()->tag(
			'licence',
			fn( Context $c ) => '',
			fn( Context $c ) => '',
		)->components() );
	}

	public function test_a_tag_name_the_pattern_cannot_match_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->renderer()->tag( 'not a tag', fn( Context $c ) => '', fn( Context $c ) => '' );
	}

	public function test_a_resolved_inline_tag_is_not_reported_unresolved(): void {
		$rendered = $this->renderer()
			->tag( 'licence', fn( Context $c ) => 'x', fn( Context $c ) => 'x' )
			->render( $this->template( '{{licence}}' ) );

		$this->assertTrue( $rendered->is_complete() );
	}
}
