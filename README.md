# Email Templates

Declarative transactional email: developer-owned layouts, operator-editable bodies, and typed components. Tags are escaped for the context they land in, and an unresolved tag never reaches a customer. Zero dependencies.

## Why

Two failure modes account for most broken transactional email.

**Hand-built HTML at the call site.** Someone concatenates a table into a string to get a receipt out, and six months later there are nine of them, each with slightly different markup, none tested, and the customer's name is interpolated unescaped.

**A template engine borrowed from web pages.** Those escape everything, which is right for HTML and wrong for a subject line and a plain-text part — so the customer receives `Tom &amp; Jerry`.

Here the split is explicit: **layouts** are yours, **bodies** belong to the operator, and escaping follows the context.

## Features

- 🎨 **Nothing hardcoded** — every colour, font, width, radius and spacing comes from a `Theme` or a `Palette`. The layout files contain no literal values.
- 🌓 **Inverted variants** — three designs, each light or dark, sharing one layout file.
- 🔒 **Context-correct escaping** — escaped in HTML, raw in text and subject.
- 🚫 **No leaked tags** — an unresolved tag is removed and reported, never shown.
- 🧱 **Six components** — order table, links, codes, button, details, divider. Add your own.
- 📝 **A text part for free** — derived by re-rendering the body in text mode, so components contribute proper prose rather than mangled table cells.
- 🖋️ **Operators get the tokens too** — `style="color:{{muted}}"` in a body follows whichever layout is selected.

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarmill/email-templates
```

## Usage

```php
use SugarMill\EmailTemplates\{Branding, Renderer, Template};

$renderer = new Renderer(
    new Branding(
        name: 'Acme',
        accent: '#06d6a0',
        footer: 'You are receiving this because you have an account.',
        address: '12 Example Street, Dublin 2',
    )
);

$template = new Template(
    slug: 'order.receipt',
    subject: 'Your receipt from {{business.name}}',
    preheader: 'Order {{order.number}} — {{order.total}}',
    html: '<p>Hi {{customer.first_name}},</p>{{order_items}}{{divider}}{{links}}{{button}}',
);

$email = $renderer->render( $template, [
    'business' => [ 'name' => 'Acme' ],
    'customer' => [ 'first_name' => 'Dave' ],
    'order'    => [ 'number' => 'SC-42', 'total' => '€64.99' ],
    'items'    => [ [ 'title' => 'Waveform Pro', 'meta' => 'Annual', 'total' => '€49.99' ] ],
    'links'    => [ [ 'label' => 'Download', 'url' => 'https://…', 'note' => '84 MB' ] ],
    'button'   => [ 'label' => 'View your order', 'url' => 'https://…' ],
] );

$email->subject;   // 'Your receipt from Acme'
$email->html;      // complete document, layout included
$email->text;      // plain-text alternative
```

Delivering it with [`sugarmill/mailer`](https://github.com/sugarmill/mailer) is three lines:

```php
$mailer->send(
    Message::to( $customer )->subject( $email->subject )->html( $email->html )->text( $email->text )
);
```

## Tags

**Scalar tags** resolve a dotted path against your data — `{{customer.name}}`, `{{order.total}}`, `{{items.0.title}}`.

**Component tags** render a block — `{{order_items}}`, `{{links}}`, `{{codes}}`, `{{button}}`, `{{details}}`, `{{divider}}`.

**Design tokens** expose the current theme — `{{accent}}`, `{{text}}`, `{{muted}}`, `{{border}}`, `{{surface}}`, `{{card}}`, `{{background}}`, `{{font}}`, `{{font_size}}`, `{{small_size}}`, `{{radius}}`. Give these to operators so a body reads `<p style="color:{{muted}}">` and tracks the layout instead of hardcoding a grey that becomes a white line on the inverted variant.

Double braces are deliberate: email bodies are full of inline CSS, and single braces would collide with every `{ … }` rule in the message.

### Unresolved tags

A tag with no value is **removed**, not printed. `Hi {{customer.name}}` becomes `Hi`, never `Hi {{customer.name}}`.

Which ones went missing is reported rather than swallowed:

```php
$email->is_complete();   // false
$email->unresolved;      // ['customer.first_name']
```

Worth asserting across your whole catalogue in a test — a template referencing `first_name` when the data says `name` produces a subtly broken email and nothing else will tell you. In development, make it loud:

```php
new Renderer( $branding, $theme, strict: true );   // throws instead
```

Leave strict off in production. A missing middle name should not stop a receipt.

## Layouts

| Layout | |
|---|---|
| `card` / `card-inverted` | Bordered container on a tinted page. The default. |
| `plain` / `plain-inverted` | No container. Reads as personal rather than automated — good for security mail. |
| `ruled` / `ruled-inverted` | Hairline rules, no border. For receipts and invoices. |

```php
$template->with_layout( Layout::Ruled );
Layout::Card->inverted();                    // Layout::CardInverted
Layout::options();                           // value => label, for a dropdown
Layout::options( include_inverted: false );  // just the three designs
```

Each pair shares one layout file; the variant only selects a palette.

Layouts are table-based with inline styles because Outlook on Windows renders through Word — no flexbox, no grid, and most of `<style>` discarded.

## Theming

Every value is yours to set:

```php
use SugarMill\EmailTemplates\{Palette, Theme};

new Renderer( $branding, new Theme(
    font: "'Inter', -apple-system, sans-serif",
    mono_font: "'JetBrains Mono', monospace",
    width: 640,
    font_size: 16,
    line_height: '1.7',
    padding: 40,
    radius: 12,
    button_radius: 999,        // pill buttons
    small_size: 13,
    light: Palette::light( $branding )->with( text: '#1a1a1a', border: '#ddd' ),
    inverted: Palette::inverted( $branding )->with( card: '#000000' ),
) );
```

`Palette::with()` takes only what you want to change. The stock palettes are a warm off-black on off-white rather than `#000` on `#fff`, which is harsher on screen than it looks and is the quickest way to make an email feel like a system notice.

Note that inverted is dark for **every** reader, not a response to their system theme. `prefers-color-scheme` support across mail clients is too patchy to rely on, and a half-applied dark mode is worse than none.

## Components

A component turns structured data into a block. That is the one thing an operator cannot reasonably hand-write, because it means looping — and giving operators a loop syntax means maintaining a template language, letting them break the table scaffolding, and opening an injection surface.

| Tag | Reads | Renders |
|---|---|---|
| `{{order_items}}` | `items[]`, `order.total` | Line-item table with an optional total row |
| `{{links}}` | `links[]` | Labelled links, unsafe URLs dropped |
| `{{codes}}` | `codes[]` | Monospaced panels for keys and codes |
| `{{button}}` | `button.label`, `button.url` | One call to action |
| `{{details}}` | `details` | Label/value summary |
| `{{divider}}` | — | Hairline rule |

Amounts are pre-formatted strings, not minor units, so the package needs no opinion about currency — format with [`sugarmill/currency`](https://github.com/sugarmill/currency) first.

`{{details}}` accepts a map or a list of pairs; the list form is what a loop produces and the only one that can repeat a label.

### Writing one

```php
final readonly class Testimonial implements Component {

    public function html( Context $context, Style $style ): string {
        $quote = $context->get( 'testimonial.quote' );

        if ( null === $quote ) {
            return '';
        }

        return '<blockquote style="border-left:3px solid ' . $style->palette->border . ';'
            . 'padding-left:14px;color:' . $style->palette->muted . ';'
            . 'font-size:' . $style->theme->font_size . 'px;">'
            . Html::escape( $quote )
            . '</blockquote>';
    }

    public function text( Context $context ): string {
        return (string) $context->get( 'testimonial.quote' );
    }
}

$renderer->register( 'testimonial', new Testimonial() );
```

Two rules. **Take every colour and dimension from `$style`** — a hardcoded grey renders as a white line across the inverted layout. **Escape your own interpolations**; the renderer trusts what a component returns and will not escape it again.

Always implement `text()`. A message with no text alternative scores worse with spam filters and is unreadable in a text client.

## Security

Everything a template renders arrived from somewhere else — a customer's name from a checkout form, a product title from an admin screen. See [SECURITY.md](SECURITY.md). In short:

- Scalar tags are HTML-escaped in the body and left raw in the subject and text part.
- `javascript:`, `data:` and `vbscript:` URLs are dropped from links and buttons, not rendered inert.
- The accent must be a six-digit hex colour — it lands in a `style` attribute an operator can reach through a settings form.
- A logo URL must be absolute https.
- Component output is trusted by design, which is why the contract puts escaping on the component.

## Testing

```bash
composer install
composer test
```

122 tests, 291 assertions — escaping in each context, unresolved-tag handling, every layout rendering a complete document with no surviving token, each component in both modes, and an assertion that no component or layout hardcodes a colour.

## Samples

```bash
php samples.php
```

Writes ten realistic emails plus every layout to `~/Desktop/sugarmill-email-samples`, with an index page and the plain-text version of each.

## License

GPL-2.0-or-later
