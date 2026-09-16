<?php
/**
 * Render one sample of every template, in every layout, to disk.
 *
 * Not part of the package — a development tool for eyeballing output.
 * Run: php samples.php
 *
 * @package SugarCommerce\EmailTemplates
 */

declare( strict_types=1 );

require __DIR__ . '/vendor/autoload.php';

use SugarCommerce\EmailTemplates\Branding;
use SugarCommerce\EmailTemplates\Layout;
use SugarCommerce\EmailTemplates\Renderer;
use SugarCommerce\EmailTemplates\Theme;
use SugarCommerce\EmailTemplates\Template;

$out = getenv( 'HOME' ) . '/Desktop/sugarcommerce-email-samples';
@mkdir( $out, 0755, true );

$branding = new Branding(
	name: 'Sugarcart',
	accent: '#06d6a0',
	footer: 'You are receiving this because you have an account with Sugarcart.',
	address: '12 Example Street, Dublin 2, Ireland',
);

/* ─── The catalogue ─────────────────────────────────────────────────── */

$templates = array(

	'order-receipt' => array(
		'layout'   => Layout::Card,
		'title'    => 'Order receipt',
		'why'      => 'The workhorse. Items, totals, downloads, a button.',
		'template' => new Template(
			slug: 'order.receipt',
			subject: 'Your receipt from {{business.name}}',
			preheader: 'Order {{order.number}} — {{order.total}}',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Thanks for your order. Everything is ready to download below.</p>'
				. '{{order_items}}'
				. '{{divider}}'
				. '<p style="font-weight:600;margin:0 0 4px;">Your downloads</p>'
				. '{{links}}'
				. '{{button}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">Download links stay valid for 30 days. '
				. 'You can always get them again from your account.</p>',
		),
		'data'     => array(
			'business' => array( 'name' => 'Sugarcart' ),
			'customer' => array( 'first_name' => 'Dave' ),
			'order'    => array( 'number' => 'SC-2026-0042', 'total' => '€64.99' ),
			'items'    => array(
				array( 'title' => 'Waveform Pro', 'meta' => 'Annual licence', 'total' => '€49.99' ),
				array( 'title' => 'Extra seat', 'quantity' => '3', 'total' => '€15.00' ),
			),
			'details'  => array(),
			'links'    => array(
				array( 'label' => 'WaveformPro-2.4.dmg', 'url' => 'https://example.com/d/1', 'note' => '84 MB' ),
				array( 'label' => 'Manual.pdf', 'url' => 'https://example.com/d/2', 'note' => '2 MB' ),
			),
			'button'   => array( 'label' => 'View your order', 'url' => 'https://example.com/account/orders/42' ),
		),
	),

	'licence-delivery' => array(
		'layout'   => Layout::Card,
		'title'    => 'Licence delivery',
		'why'      => 'Codes a customer must copy exactly. Monospaced panels.',
		'template' => new Template(
			slug: 'licence.delivery',
			subject: 'Your {{product.name}} licence key',
			preheader: 'Activate on up to {{licence.seats}} machines',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Here is your licence key for <strong>{{product.name}}</strong>. '
				. 'It activates on up to {{licence.seats}} machines.</p>'
				. '{{codes}}'
				. '{{button}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">Keep this email. '
				. 'If you lose the key you can always find it in your account.</p>',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'product'  => array( 'name' => 'Waveform Pro' ),
			'licence'  => array( 'seats' => '3' ),
			'codes'    => array(
				array( 'label' => 'Licence key', 'code' => 'WZPRV-R06ZZ-4F2CR-T73HR' ),
			),
			'button'   => array( 'label' => 'Manage activations', 'url' => 'https://example.com/account/licences' ),
		),
	),

	'magic-link' => array(
		'layout'   => Layout::Plain,
		'title'    => 'Sign-in link',
		'why'      => 'Plain layout — a security email should not look like marketing.',
		'template' => new Template(
			slug: 'account.magic_link',
			subject: 'Your sign-in link',
			preheader: 'Expires in {{link.minutes}} minutes',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Click below to sign in. The link expires in {{link.minutes}} minutes '
				. 'and can only be used once.</p>'
				. '{{button}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">If you did not ask for this, '
				. 'you can ignore it — nobody can sign in without the link.</p>',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'link'     => array( 'minutes' => '15' ),
			'button'   => array( 'label' => 'Sign in to Sugarcart', 'url' => 'https://example.com/auth/abc123' ),
		),
	),

	'abandoned-cart' => array(
		'layout'   => Layout::Card,
		'title'    => 'Abandoned cart',
		'why'      => 'Recovery. Shows what they left and one link back to it.',
		'template' => new Template(
			slug: 'cart.abandoned',
			subject: 'You left something behind',
			preheader: 'Your cart is still saved',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>You started checking out but did not finish. '
				. 'Your cart is still here whenever you want it.</p>'
				. '{{order_items}}'
				. '{{button}}',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'order'    => array( 'total' => '€49.99' ),
			'items'    => array(
				array( 'title' => 'Waveform Pro', 'meta' => 'Annual licence', 'total' => '€49.99' ),
			),
			'button'   => array( 'label' => 'Finish checking out', 'url' => 'https://example.com/checkout/resume/xyz' ),
		),
	),

	'subscription-renewed' => array(
		'layout'   => Layout::Ruled,
		'title'    => 'Subscription renewed',
		'why'      => 'Monochrome — a billing document, not a promotion.',
		'template' => new Template(
			slug: 'subscription.renewed',
			subject: 'Your subscription renewed — {{order.total}}',
			preheader: 'Next payment {{subscription.next_date}}',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Your subscription renewed today. Here are the details.</p>'
				. '{{details}}'
				. '{{divider}}'
				. '{{order_items}}'
				. '{{button}}',
		),
		'data'     => array(
			'customer'     => array( 'first_name' => 'Dave' ),
			'order'        => array( 'total' => '€49.99' ),
			'subscription' => array( 'next_date' => '1 September 2026' ),
			'details'      => array(
				'Invoice'      => 'INV-2026-0311',
				'Billed on'    => '1 August 2026',
				'Next payment' => '1 September 2026',
				'Card'         => 'Visa ending 4242',
			),
			'items'        => array(
				array( 'title' => 'Waveform Pro', 'meta' => 'Monthly, 1 seat', 'total' => '€49.99' ),
			),
			'button'       => array( 'label' => 'Download invoice', 'url' => 'https://example.com/invoices/311' ),
		),
	),

	'payment-failed' => array(
		'layout'   => Layout::Card,
		'title'    => 'Payment failed',
		'why'      => 'Urgency without alarm. One clear action.',
		'template' => new Template(
			slug: 'subscription.payment_failed',
			subject: 'We could not take your payment',
			preheader: 'We will try again on {{retry.date}}',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Your payment for <strong>{{product.name}}</strong> did not go through. '
				. 'We will try again on {{retry.date}}.</p>'
				. '{{details}}'
				. '{{button}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">Your access stays active until then.</p>',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'product'  => array( 'name' => 'Waveform Pro' ),
			'retry'    => array( 'date' => '4 August 2026' ),
			'details'  => array(
				'Amount' => '€49.99',
				'Card'   => 'Visa ending 4242',
				'Reason' => 'Insufficient funds',
			),
			'button'   => array( 'label' => 'Update payment method', 'url' => 'https://example.com/account/billing' ),
		),
	),

	'refund' => array(
		'layout'   => Layout::Ruled,
		'title'    => 'Refund issued',
		'why'      => 'Money moving back. Sober and precise.',
		'template' => new Template(
			slug: 'order.refunded',
			subject: 'Your refund of {{refund.amount}}',
			preheader: 'Back on your card in 5–10 days',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>We have refunded {{refund.amount}} to your original payment method. '
				. 'It usually appears within 5–10 working days.</p>'
				. '{{details}}'
				. '{{divider}}'
				. '{{order_items}}',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'refund'   => array( 'amount' => '€49.99' ),
			'order'    => array( 'total' => '€49.99' ),
			'details'  => array(
				'Order'    => 'SC-2026-0042',
				'Refunded' => '1 August 2026',
				'To'       => 'Visa ending 4242',
			),
			'items'    => array(
				array( 'title' => 'Waveform Pro', 'meta' => 'Annual licence — refunded', 'total' => '€49.99' ),
			),
		),
	),

	'team-invite' => array(
		'layout'   => Layout::Card,
		'title'    => 'Team invitation',
		'why'      => 'One action, no clutter.',
		'template' => new Template(
			slug: 'team.invite',
			subject: '{{inviter.name}} invited you to {{business.name}}',
			preheader: 'Accept within {{invite.days}} days',
			html: '<p>Hi,</p>'
				. '<p><strong>{{inviter.name}}</strong> has invited you to join '
				. '<strong>{{business.name}}</strong> as {{invite.role}}.</p>'
				. '{{button}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">This invitation expires in {{invite.days}} days.</p>',
		),
		'data'     => array(
			'business' => array( 'name' => 'Sugarcart' ),
			'inviter'  => array( 'name' => 'Dave Sherlock' ),
			'invite'   => array( 'role' => 'an admin', 'days' => '7' ),
			'button'   => array( 'label' => 'Accept invitation', 'url' => 'https://example.com/team/accept/abc' ),
		),
	),

	'shipped' => array(
		'layout'   => Layout::Card,
		'title'    => 'Order shipped',
		'why'      => 'Physical goods. Tracking as a detail row plus a link.',
		'template' => new Template(
			slug: 'order.shipped',
			subject: 'Your order is on its way',
			preheader: 'Tracking {{shipment.tracking}}',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Your order has shipped.</p>'
				. '{{details}}'
				. '{{button}}'
				. '{{divider}}'
				. '{{order_items}}',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'order'    => array( 'total' => '€38.00' ),
			'shipment' => array( 'tracking' => 'AB123456789IE' ),
			'details'  => array(
				'Carrier'  => 'An Post',
				'Tracking' => 'AB123456789IE',
				'Shipping' => '12 Example Street, Dublin 2',
			),
			'items'    => array(
				array( 'title' => 'Tour T-shirt', 'meta' => 'Large / Black', 'total' => '€25.00' ),
				array( 'title' => 'Enamel pin', 'quantity' => '2', 'total' => '€13.00' ),
			),
			'button'   => array( 'label' => 'Track your parcel', 'url' => 'https://example.com/track/AB123456789IE' ),
		),
	),

	'recovery-codes' => array(
		'layout'   => Layout::CardInverted,
		'title'    => 'Two-factor recovery codes',
		'why'      => 'Dark layout. Several codes in one block.',
		'template' => new Template(
			slug: 'account.recovery_codes',
			subject: 'Your recovery codes',
			preheader: 'Store these somewhere safe',
			html: '<p>Hi {{customer.first_name}},</p>'
				. '<p>Two-factor authentication is now on. '
				. 'Keep these recovery codes somewhere safe — each works once.</p>'
				. '{{codes}}'
				. '<p style="color:{{muted}};font-size:{{small_size}}px;">Anyone with these codes can sign in as you. '
				. 'Do not forward this email.</p>',
		),
		'data'     => array(
			'customer' => array( 'first_name' => 'Dave' ),
			'codes'    => array(
				'4F2CR-T73HR',
				'WZPRV-R06ZZ',
				'B7H4W-AS835',
				'Z0ZGE-BVQE5',
			),
		),
	),
);

/* ─── Render ────────────────────────────────────────────────────────── */

$renderer = new Renderer( $branding, new Theme() );
$cards    = '';
$written  = 0;

foreach ( $templates as $key => $spec ) {
	$rendered = $renderer->render( $spec['template']->with_layout( $spec['layout'] ), $spec['data'] );

	file_put_contents( $out . '/' . $key . '.html', $rendered->html );
	file_put_contents( $out . '/' . $key . '.txt', $rendered->text );
	++$written;

	$status = $rendered->is_complete()
		? '<span style="color:#059669;">all tags resolved</span>'
		: '<span style="color:#dc2626;">unresolved: ' . implode( ', ', $rendered->unresolved ) . '</span>';

	$cards .= '<article>'
		. '<h2>' . htmlspecialchars( $spec['title'] ) . '</h2>'
		. '<p class="why">' . htmlspecialchars( $spec['why'] ) . '</p>'
		. '<p class="meta">' . $spec['layout']->label() . ' layout &middot; ' . $status
		. ' &middot; <a href="' . $key . '.html">open</a> &middot; <a href="' . $key . '.txt">text version</a></p>'
		. '<iframe src="' . $key . '.html" loading="lazy"></iframe>'
		. '</article>';
}

/* One template across all four layouts, for comparison. */

$comparison = '';

foreach ( Layout::cases() as $layout ) {
	$spec = $templates['order-receipt'];
	$rendered = $renderer->render( $spec['template']->with_layout( $layout ), $spec['data'] );
	$file     = 'layout-' . $layout->value . '.html';

	file_put_contents( $out . '/' . $file, $rendered->html );
	++$written;

	$comparison .= '<article>'
		. '<h2>' . $layout->label() . '</h2>'
		. '<p class="why">' . htmlspecialchars( $layout->description() ) . '</p>'
		. '<p class="meta"><a href="' . $file . '">open</a></p>'
		. '<iframe src="' . $file . '" loading="lazy"></iframe>'
		. '</article>';
}

$index = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>sugarcommerce/email-templates — samples</title>
<style>
  :root { color-scheme: light dark; }
  body { margin:0; padding:32px; font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
         background:#fafafa; color:#18181b; }
  @media (prefers-color-scheme: dark) { body { background:#09090b; color:#e4e4e7; } }
  h1 { font-size:24px; margin:0 0 4px; letter-spacing:-0.02em; }
  .lede { color:#71717a; margin:0 0 28px; max-width:65ch; }
  h2 { font-size:16px; margin:0 0 2px; letter-spacing:-0.01em; }
  .why { color:#71717a; font-size:13px; margin:0 0 6px; }
  .meta { color:#a1a1aa; font-size:12px; margin:0 0 10px; }
  .meta a { color:inherit; }
  section { margin-bottom:44px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:26px; }
  article { min-width:0; }
  iframe { width:100%; height:520px; border:1px solid #e4e4e7; border-radius:10px; background:#fff; }
  @media (prefers-color-scheme: dark) { iframe { border-color:#27272a; } }
</style>
</head>
<body>
<h1>sugarcommerce/email-templates</h1>
<p class="lede">Every template below is rendered by the package from a subject line, a body
containing tags, and a data array — nothing is hand-written HTML. Each has a plain-text
version generated from the same source.</p>

<section>
<h1 style="font-size:18px;">Templates</h1>
<div class="grid">$cards</div>
</section>

<section>
<h1 style="font-size:18px;">The same receipt in all four layouts</h1>
<p class="lede">The layout is developer-owned chrome. The operator edits the body inside it.</p>
<div class="grid">$comparison</div>
</section>
</body>
</html>
HTML;

file_put_contents( $out . '/index.html', $index );

echo "wrote " . ( $written * 1 ) . " emails + index to:\n  " . $out . "\n";
