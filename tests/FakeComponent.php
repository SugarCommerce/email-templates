<?php
/**
 * A component stub for the registration tests.
 *
 * @package   Sugar\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\EmailTemplates\Tests;

use Sugar\EmailTemplates\Component;
use Sugar\EmailTemplates\Context;
use Sugar\EmailTemplates\Style;

/**
 * Class FakeComponent
 *
 * @since 1.0.0
 */
final readonly class FakeComponent implements Component {

	/**
	 * {@inheritDoc}
	 */
	public function html( Context $context, Style $style ): string {
		return '<b>LOUD</b>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function text( Context $context ): string {
		return 'LOUD';
	}
}
