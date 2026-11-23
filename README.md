# laravel-mail-frame

One hand-tuned HTML frame for your notification mail: table layout, inline styles, Outlook and Gmail fixes, right-to-left
support, and a plain-text part on every message.

## Install

Needs PHP 8.4+, `ext-intl` and Laravel 13.

```bash
composer require ismailnakkar/laravel-mail-frame
```

A listener gives every `Message` a plain-text part, unless it has its own text view. Your other mail is left alone.

## Usage

Give your mail its tones, as an enum of hex colours.

```php
use MailFrame\Tone;

enum MailTone implements Tone
{
    case routine;
    case security;

    public function accent(): string // the line across the card, and the button
    {
        return match ($this) {
            self::routine => '#2563eb',
            self::security => '#b91c1c',
        };
    }

    public function tint(): string // a panel's background
    {
        return match ($this) {
            self::routine => '#f5f6f8',
            self::security => '#fdeaec',
        };
    }

    public function border(): string // a panel's border
    {
        return match ($this) {
            self::routine => '#e5e7eb',
            self::security => '#e3939f',
        };
    }
}
```

Build a `MailFrame\Message` with your neutral tone, the one a mail uses until `tone()` changes it, and return it from
`toMail()`:

```php
use MailFrame\Message;

public function toMail(object $notifiable): Message
{
    return new Message(MailTone::routine)
        ->subject('Your withdrawal is on its way')
        ->preheader('$40.00 to your PayPal account.')
        ->heading('Withdrawal sent')
        ->greeting('Hello Pat,')
        ->line('We have sent your money.')
        ->facts(['Amount' => '$40.00', 'Method' => 'PayPal'])
        ->action('View withdrawals', route('withdrawals'))
        ->salutation('Thanks')
        ->footer('You received this because of activity on your account.');
}
```

`line()`, `bullets()`, `facts()`, `panel()` and `action()` print in the order you call them; everything else has a fixed
place.

| Method                                    | Does                                                                                        |
|-------------------------------------------|---------------------------------------------------------------------------------------------|
| `tone($tone)`                             | Colours the line across the card and the buttons.                                           |
| `preheader($text)`                        | Sets the line a client shows after the subject. Unset, it scrapes the body.                 |
| `heading($text)`                          | Prints the title of the card.                                                               |
| `greeting($text)`                         | Prints the opening line. Unset, nothing.                                                    |
| `line($text)`                             | Prints a paragraph. `lines()`, `lineIf()` and `linesIf()` work too.                         |
| `bullets($items)`                         | Prints a list.                                                                              |
| `facts($rows)`                            | Prints ruled label and value rows.                                                          |
| `panel($text, $tone = null)`              | Prints a quoted block that keeps its lines, in your neutral tone unless the call names one. |
| `action($text, $url, $tone = null)`       | Prints a button in the mail's tone unless the call names one, and its URL below the card.   |
| `salutation($text, $signature = null)`    | Signs off, then signs with `$signature` or `config('app.name')`. Unset, nothing.            |
| `footnote($text)`                         | Prints a note about this mail, below the card.                                              |
| `footer($text, $url = null)`              | Prints small print every mail shares, then the site name linked to `$url` or `app.url`.     |
| `content($content)`                       | Lays out a package's mail (see Package mail).                                               |

`facts()` takes a map, or a list of `['label' => …, 'value' => …]` rows when two share a label. A value prints as Blade
would print it and keeps its lines. `facts()`, `bullets()` and `panel()` drop whatever prints nothing, so a conditional
value needs no branch.

The rest of `MailMessage` works: `subject()`, `from()`, `replyTo()`, `attach()` and so on. `text()` adds your own text
view and keeps the frame. What picks Laravel's template or colour does nothing (`level()`, `error()`, `success()`,
`theme()`, `template()`), so use `tone()`. `markdown()`, or `view()` with another view, leaves the frame behind, blocks
included.

Most sites want one subclass that sets the footer and the tone, so a notification only says what is new:

```php
class SiteMail extends Message
{
    public function __construct()
    {
        parent::__construct(MailTone::routine);

        $this->footer('You received this because you have an account.');
    }
}
```

## Package mail

A package that sends mail through [laravel-mail-content](https://github.com/ismailnakkar/laravel-mail-content) hands
your template a `MailContent\Content`. `content()` lays it out, so one template serves every package:

```php
use Illuminate\Notifications\Messages\MailMessage;
use MailContent\Content;
use MailContent\Template;

final class SiteTemplate implements Template
{
    public function build(Content $content, object $notifiable): MailMessage
    {
        return new SiteMail()
            ->tone($content->error ? MailTone::security : MailTone::routine)
            ->content($content);
    }
}
```

Name it in each package's `notifications.template` config. `content()` sets the subject and heading, the preheader and
greeting when the content has them, then prints each block in order: a `Line` with `line()`, `Bullets` with
`bullets()`, `Facts` with `facts()`, a `Panel` and a `Code` with `panel()`, `Links` as `bullets()` of links, and a
`Button` with `action()`. A block type newer than this release prints its `lines()`, each with `line()`. It leaves the
tone alone, so set it as above. To word your own greeting, call `greeting()` after `content()`.

## Escaping

A string is text. To print your own markup, pass an `Htmlable` to `line()`, `bullets()`, `facts()`, `panel()`,
`footnote()` or `footer()`. It prints as given, so escape anything in it that you did not write:

```php
use Illuminate\Support\HtmlString;

->panel(new HtmlString('Read <a href="' . e($url) . '">the full report</a>.'))
```

## Right to left

The frame follows the language the mail is rendered in: Laravel sets it from the notification's `locale()`, or from
`preferredLocale()` on a notifiable that implements `HasLocalePreference`. An Arabic, Hebrew or Persian mail gets
`dir="rtl"`, and fact values, panels and a button's URL keep their own order.

An `Htmlable` fact or panel is marked as a whole, so keep its markup inline, with `<span>`, `<strong>` and `<br>`. A `<div>`
puts each mark on a line of its own, which adds a blank line above and below it.

Use `Message::isolate()` for a value you put inside a right-to-left sentence, such as a name in a greeting. Call it in
`toMail()`, where the language is set. It returns the text unchanged in a left-to-right language.

```php
->greeting(__('Hello :name,', ['name' => Message::isolate($notifiable->name)]))
```

## Configuration

```bash
php artisan vendor:publish --tag=mail-frame-config
```

Set only the keys you change.

| Key              | Default   | Meaning                                                                     |
|------------------|-----------|-----------------------------------------------------------------------------|
| `logo`           | `null`    | A public PNG path, passed to `asset()`. `null` prints `config('app.name')`. |
| `logo_width`     | `120`     | In pixels.                                                                  |
| `colors.page`    | `#f5f6f8` | Behind the card.                                                            |
| `colors.rule`    | `#e5e7eb` | The card's border and the rules between facts.                              |
| `colors.heading` | `#111827` | The site name, the heading, fact values and panel text.                     |
| `colors.text`    | `#374151` | The body.                                                                   |
| `colors.muted`   | `#69717f` | Fact labels and everything below the card.                                  |
| `colors.link`    | `#2563eb` | Links in the body.                                                          |

The logo, or the site name without one, is centred above the card in every language.

Use a PNG on an opaque background for the logo: Outlook 2016 and older cannot show SVG, and a transparent PNG inverts in
dark mode.

To change the markup itself, copy the view to `resources/views/vendor/mail-frame/email.blade.php`. You then own it: a
package update no longer reaches it.

## Translations

The line below the card that gives a button's URL, and what joins a fact's label to its value in the plain-text part,
ship in English, French, Spanish and Arabic. To change them or add a language, set `trouble-clicking` and
`fact-separator` in `lang/vendor/mail-frame/{locale}/email.php`. Keep the `:action` placeholder.

A language with no file reads `app.fallback_locale`, and a region code such as `ar_SA` does not read `ar`. If the fallback
has no file either, the button's line prints its key, so add one; a fact falls back to `: `.

## API

- `MailFrame\Message`: the builder above, a `MailMessage`. `Message::isolate(string $text): string` marks a value for
  right-to-left copy with `Message::FIRST_STRONG_ISOLATE` and `Message::POP_DIRECTIONAL_ISOLATE`, the marks the view
  uses too, and `$message->blocks` lists the body blocks in call order.
- `Message::content(MailContent\Content $content): static` lays out a package's mail (see Package mail).
- `MailFrame\Tone`: the interface your tones implement: `accent()`, `tint()` and `border()`, each a hex colour.
- `MailFrame\Blocks\Block`: the interface of a body block, one readonly class per type (see Testing). Its `type` is a
  `MailFrame\BlockType` case: `line`, `bullets`, `facts`, `panel` or `action`.
- The view `mail-frame::email`, the config file `mail-frame` (publish tag `mail-frame-config`) and the translation file
  `mail-frame::email`.

Anything else, such as the listener that makes the text part, is internal and may change in any release.

## Testing

`Mail::fake()` records nothing for a notification, and neither it nor `Notification::fake()` makes the text part. To read
it, use the array mailer:

```php
config(['mail.default' => 'array']);

$user->notify(new WithdrawalSent);

$text = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getTextBody();
```

For `$mail = (new WithdrawalSent)->toMail($user)`, `$mail->viewData` holds the `tone` and, once set, the `heading`,
`preheader` and `footnote`, and `$mail->blocks` the body blocks: one readonly object each, from `MailFrame\Blocks`.

| Class     | `type`               | Other properties                                            |
|-----------|----------------------|-------------------------------------------------------------|
| `Line`    | `BlockType::line`    | `text`                                                      |
| `Bullets` | `BlockType::bullets` | `items`                                                     |
| `Facts`   | `BlockType::facts`   | `rows`, a list of `Fact`, each with a `label` and a `value` |
| `Panel`   | `BlockType::panel`   | `text`, `tone`                                              |
| `Button`  | `BlockType::action`  | `text`, `url`, `tone` (`null` follows the mail's)           |

```php
$this->assertSame([BlockType::line, BlockType::facts, BlockType::action], array_column($mail->blocks, 'type'));
$this->assertSame(['type' => BlockType::line, 'text' => 'We have sent your money.'], get_object_vars($mail->blocks[0]));
$this->assertSame('$40.00', array_column($mail->blocks[1]->rows, 'value', 'label')['Amount']);
```

## Upgrading

### From 0.6

0.7 requires `ismailnakkar/laravel-mail-content` `^1.0`, which Composer installs with it. Nothing else changes: add
`content()` to your template when a package you use sends its mail through laravel-mail-content.

### From 0.5

`Message::$blocks` holds readonly objects from `MailFrame\Blocks` (see Testing), not arrays. Read `$block->text`, not
`$block['text']`, and the same for `items`, `rows`, `url` and `tone`; a fact row is a `Fact`, so `$row->label` and
`$row->value`. `type` is still the `BlockType` case, as a property, so `array_column($mail->blocks, 'type')`,
`array_column($facts->rows, 'value', 'label')` and a collection's `where()` and `pluck()` keep working. A closure typed
`fn (array $block)` throws a `TypeError` now: type it `MailFrame\Blocks\Block`. A test that compared blocks or fact rows
with arrays keeps its arrays and compares `array_map(get_object_vars(...), $mail->blocks)`, or the same over `->rows`:
`type` comes first, as its key did. In that list a facts block's `rows` stay `Fact` objects, so compare them apart, over
`->rows`. A fact whose label was a numeric map key, such as `'2024'`, keeps it as a string, not the int PHP made of it.
A fact's label is a string or an `Htmlable`, and a button's label a string or `null` and its URL a string: anything else
throws a `TypeError` where it used to print. If you copied the view to `resources/views/vendor/mail-frame`, make the
same changes there.

### From 0.4

A block's `type` in `Message::$blocks` is the `MailFrame\BlockType` case, not its string: compare it with
`BlockType::action`, not `'action'` or `BlockType::action->value`. Update any test of yours that reads it.

### From 0.3

The plain-text part prints a fact as `Amount: $1.00` on one line, not the label and value on two. The separator ships in
English, French, Spanish and Arabic, and another language gets `: ` unless you add `fact-separator` to its file (see
Translations). Update any test of yours that pins the old text.

### From 0.2

The URL under a button moved below the card, into a line that says what to do with it, as Laravel's own mail does. The
line ships in English, French, Spanish and Arabic: if you send another language, add its file (see Translations). Update
any test of yours that pins the old markup.

### From 0.1

The `mail-frame-views` publish tag is gone. A file at `resources/views/vendor/mail-frame/email.blade.php` still overrides
the frame, so copy it there by hand if you need your own.

The logo is centred, and the plain-text part prints a button's URL once. Update any test of yours that pins either.

## Licence

MIT.
