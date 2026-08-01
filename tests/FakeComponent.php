<?php
/**
 * A component stub for the registration tests.
 *
 * @package   SugarMill\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\EmailTemplates\Tests;

use SugarMill\EmailTemplates\Component;
use SugarMill\EmailTemplates\Context;
use SugarMill\EmailTemplates\Style;

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
