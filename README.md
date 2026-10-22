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
composer require sugarcommerce/email-templates
```

## Usage

```php
use SugarCommerce\EmailTemplates\{Branding, Renderer, Template};

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

Delivering it with [`sugarcommerce/mailer`](https://github.com/sugarcommerce/mailer) is three lines:

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
use SugarCommerce\EmailTemplates\{Palette, Theme};

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

Amounts are pre-formatted strings, not minor units, so the package needs no opinion about currency — format with [`sugarcommerce/currency`](https://github.com/sugarcommerce/currency) first.

`{{details}}` accepts a map or a list of pairs; the list form is what a loop produces and the only one that can repeat a label.

Three more are built from values the application already holds at send time, and registered under whatever tag a body uses:

| Class | Renders |
|---|---|
| `Paragraph( $text, aside: false )` | The sender's own words, line breaks kept. `aside: true` is small and muted, for the line under a button. Nothing at all when the text is empty, which `<p>{{note}}</p>` cannot do |
| `Notice( $title, $message )` | An amber banner — "**Test mode.** Nothing was charged." The one fixed colour in the package: a warning is amber in every theme |
| `ContactLine( $lead, $email, $url, $label, $or )` | "Questions? Acme can help — help@acme.test or their help page." Every word passed in, so a translation has no English left in it. Nothing when there is nowhere to send the reader |

```php
$renderer
    ->register( 'note', new Paragraph( $seller_note ) )
    ->register( 'support', new ContactLine( 'Questions? Acme can help', 'help@acme.test', 'https://acme.test/help', 'their help page' ) );
```

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
- Substitution is one pass: a value that contains `{{tag}}` text is printed as that text, never expanded.
- The subject and preheader always come back on one line. Line breaks and control characters in the data become spaces, so a customer name cannot write a `Bcc:` header into the message.
- A derived text part prints values exactly as given — a customer called `<Bob>` is not stripped as a tag, and markup in the data cannot add a link to it.
- `javascript:`, `data:` and `vbscript:` URLs are dropped from links and buttons, not rendered inert. A contact address that would add headers to its `mailto:` link (`?bcc=`) is not linked.
- Every design value lands in a `style` attribute unescaped, so each is checked where it is made, and a bad one throws: the accent must be a six-digit hex colour; palette colours must be hex, `rgb()`/`hsl()` or a keyword; font stacks may hold family names, single quotes, commas and hyphens only; a line height is a number with an optional unit; no dimension is negative.
- A logo URL must be absolute https, with no whitespace, quotes or angle brackets.
- `Template::resolve()` takes a slug and extension that are file names, never paths, and a locale that is a locale.
- Component output is trusted by design, which is why the contract puts escaping on the component.
- The template body itself is trusted markup. Let operators edit it only if they are trusted with the HTML of every email you send.

### Templates in files

An email body is markup with a few tags in it. Kept in a PHP file it is a
string literal nobody wants to edit and no editor highlights; kept in a file it
is a file, and a designer can open it.

```
templates/emails/order.receipt.html
```

```
---
subject: Your order from {{business.name}}
preheader: Thanks for your purchase.
layout: ruled
---
<p>Thanks for your purchase.</p>
{{order_items}}
{{button}}
```

```php
$template  = Template::from_file( 'templates/emails/order.receipt.html' );
$templates = Template::from_directory( 'templates/emails' );   // keyed by slug
```

Front matter is optional, and it is deliberately not YAML — the whole grammar
is `key: value` on one line, which needs no dependency and cannot surprise
anyone. A real YAML parser would turn `subject: No` into a boolean.

Translations are the same files in a folder named for the locale, so a
translator works on whole emails rather than sentences cut out of them:

```
templates/emails/order.receipt.html      the source language
templates/emails/de/order.receipt.html   German
```

```php
Template::for_locale( 'templates/emails', 'de' );                    // only what German has
Template::resolve( 'templates/emails', 'order.receipt', 'de-AT' );   // de-AT, then de, then English

$renderer->render( $template, $data, lang: 'de' );                   // <html lang="de">
$renderer->render( $template, $data, lang: 'ar', rtl: true );        // <html lang="ar" dir="rtl">
```

`for_locale()` has no fallback on purpose: an application that lets an operator
edit the English needs to know a translation is missing before it chooses
between the edit and the English that ships. A locale tag that is not one —
`../`, a quote — finds nothing rather than a path. `render()` sends anything
that is not a language tag as `en`; whether a language reads right to left is
the caller's to say, since an application that translates already knows.

The file is read, never included. A template is content, not code; `include`
would make every template an arbitrary-code hole the moment one became
editable by an operator.

### Tags of your own

Built-in components cover the common blocks. For the ones no library could
ship — a licence key, a seat count, a renewal date in your own wording —
register a closure:

```php
$renderer->tag(
    'licence',
    fn( Context $c, Style $s ) => '<code style="color:' . $s->tokens()['{{muted}}'] . '">'
        . Html::escape( (string) $c->get( 'licence.key' ) ) . '</code>',
    fn( Context $c ) => (string) $c->get( 'licence.key' ),
);
```

The HTML closure owns its own escaping, exactly as a component class does —
whatever it returns is trusted and will not be escaped again. It receives the
resolved `Style`, so a custom tag can track the layout rather than hardcoding a
grey that turns into a white line on the inverted variant.

Both forms are required. A tag with no text form silently contributes nothing
to the plain-text body, which scores worse with spam filters and reads as a
hole in a text client.

Use `register()` with a `Component` class when the block is big enough to
deserve one, or is shared between applications.

## Testing

```bash
composer install
composer test
```

184 tests, 378 assertions — escaping in each context, templates per language and their fallback, the document's `lang` and `dir`, unresolved-tag handling, every layout rendering a complete document with no surviving token, each component in both modes, and an assertion that no component or layout hardcodes a colour.

## Samples

```bash
php samples.php
```

Writes ten realistic emails plus every layout to `~/Desktop/sugarcommerce-email-samples`, with an index page and the plain-text version of each.

## License

GPL-2.0-or-later
