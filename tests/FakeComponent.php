<?php
/**
 * A component stub for the registration tests.
 *
 * @package   SugarCommerce\EmailTemplates
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\EmailTemplates\Tests;

use SugarCommerce\EmailTemplates\Component;
use SugarCommerce\EmailTemplates\Context;
use SugarCommerce\EmailTemplates\Style;

/**
 * Class FakeComponent
 *
 * @since 1.0.0
 */
final readonly class FakeComponent implements Component {

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function html( Context $context, Style $style ): string {
		return '<b>LOUD</b>';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function text( Context $context ): string {
		return 'LOUD';
	}
}
