<?php
/**
 * Templates loaded from files.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\EmailTemplates\{Layout, Template};

final class TemplateFileTest extends TestCase {

	private const FIXTURES = __DIR__ . '/fixtures';

	public function test_front_matter_becomes_metadata(): void {
		$template = Template::from_file( self::FIXTURES . '/order.receipt.html' );

		$this->assertSame( 'Your order from {{business.name}}', $template->subject );
		$this->assertSame( 'Thanks for your purchase.', $template->preheader );
		$this->assertSame( Layout::Ruled, $template->layout );
	}

	public function test_the_body_is_what_follows_the_front_matter(): void {
		$template = Template::from_file( self::FIXTURES . '/order.receipt.html' );

		$this->assertStringStartsWith( '<p>Thanks for your purchase.</p>', $template->html );
		$this->assertStringNotContainsString( '---', $template->html );
		$this->assertStringNotContainsString( 'subject:', $template->html );
	}

	public function test_the_slug_defaults_to_the_filename(): void {
		$this->assertSame( 'order.receipt', Template::from_file( self::FIXTURES . '/order.receipt.html' )->slug );
	}

	public function test_the_slug_can_be_given(): void {
		$this->assertSame( 'custom', Template::from_file( self::FIXTURES . '/order.receipt.html', 'custom' )->slug );
	}

	public function test_front_matter_is_optional(): void {
		$template = Template::from_file( self::FIXTURES . '/bare.html' );

		$this->assertSame( '<p>No front matter here.</p>', $template->html );
		$this->assertSame( '', $template->subject );
		$this->assertSame( Layout::Card, $template->layout );
	}

	public function test_an_unknown_layout_falls_back_rather_than_throwing(): void {
		$path = tempnam( sys_get_temp_dir(), 'tpl' ) . '.html';
		file_put_contents( $path, "---\nlayout: nonsense\n---\n<p>x</p>" );

		$this->assertSame( Layout::Card, Template::from_file( $path )->layout );

		unlink( $path );
	}

	public function test_a_value_containing_a_colon_survives(): void {
		$path = tempnam( sys_get_temp_dir(), 'tpl' ) . '.html';
		file_put_contents( $path, "---\nsubject: Order: confirmed\n---\n<p>x</p>" );

		$this->assertSame( 'Order: confirmed', Template::from_file( $path )->subject );

		unlink( $path );
	}

	public function test_a_missing_file_throws(): void {
		$this->expectException( \RuntimeException::class );

		Template::from_file( self::FIXTURES . '/nope.html' );
	}

	public function test_the_file_is_not_executed(): void {
		// A template is content, not code. include() would make every
		// template an arbitrary-code hole the moment one became editable.
		$path = tempnam( sys_get_temp_dir(), 'tpl' ) . '.html';
		file_put_contents( $path, "<p><?php echo 'executed'; ?></p>" );

		$this->assertStringContainsString( '<?php', Template::from_file( $path )->html );

		unlink( $path );
	}

	public function test_a_directory_loads_keyed_by_slug(): void {
		$templates = Template::from_directory( self::FIXTURES );

		$this->assertArrayHasKey( 'order.receipt', $templates );
		$this->assertArrayHasKey( 'bare', $templates );
		$this->assertContainsOnlyInstancesOf( Template::class, $templates );
	}

	public function test_a_missing_directory_throws(): void {
		$this->expectException( \RuntimeException::class );

		Template::from_directory( self::FIXTURES . '/nope' );
	}

	public function test_a_file_template_still_reports_its_tags(): void {
		$tags = Template::from_file( self::FIXTURES . '/order.receipt.html' )->tags();

		$this->assertContains( 'order_items', $tags );
		$this->assertContains( 'button', $tags );
		$this->assertContains( 'business.name', $tags );
	}
}
