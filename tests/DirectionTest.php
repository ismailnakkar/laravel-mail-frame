<?php

declare(strict_types=1);

namespace MailFrame\Tests;

use Illuminate\Support\HtmlString;
use MailFrame\Message;
use PHPUnit\Framework\Attributes\DataProvider;

final class DirectionTest extends TestCase
{
    public function test_a_left_to_right_mail_carries_no_marks(): void
    {
        $html = $this->html($this->message()->facts(['Amount' => '$1.00'])->action('Go', 'https://example.test'));

        $this->assertStringContainsString('<html xmlns="http://www.w3.org/1999/xhtml" lang="en" dir="ltr">', $html);
        $this->assertStringNotContainsString("\u{2068}", $html);
        $this->assertSame('$1.00', Message::isolate('$1.00'));
    }

    public static function rightToLeftLocales(): iterable
    {
        yield ['ar'];
        yield ['ar_SA'];
        yield ['fa'];
        yield ['he'];
        yield ['ur'];
        yield ['arz'];
        yield ['iw'];
    }

    #[DataProvider('rightToLeftLocales')]
    public function test_the_direction_is_set_wherever_a_client_keeps_it(string $locale): void
    {
        app()->setLocale($locale);

        $html = $this->html($this->message()->facts(['Amount' => '$1.00']));

        $this->assertStringContainsString('lang="' . str_replace('_', '-', $locale) . '" dir="rtl">', $html);
        $this->assertStringContainsString('<body dir="rtl"', $html);
        $this->assertStringContainsString('<table role="presentation" lang="' . str_replace('_', '-', $locale) . '" width="100%"', $html);
        $this->assertStringContainsString('border="0" dir="rtl" style="background:', $html);
    }

    public function test_start_and_end_swap_for_outlook(): void
    {
        $english = $this->html($this->message()->facts(['Amount' => '$1.00'])->bullets(['One']));

        app()->setLocale('ar');

        $arabic = $this->html($this->message()->facts(['Amount' => '$1.00'])->bullets(['One']));

        $this->assertStringContainsString('<td align="center" style="padding:0 4px 18px;', $english);
        $this->assertMatchesRegularExpression('~<th scope="row" align="left"[^>]* text-align:left;~', $english);
        $this->assertMatchesRegularExpression('~<td align="right" style="padding:9px 0;[^>]* text-align:right;~', $english);
        $this->assertStringContainsString('margin-left:20px;', $english);

        $this->assertStringContainsString('<td align="center" style="padding:0 4px 18px;', $arabic);
        $this->assertMatchesRegularExpression('~<th scope="row" align="right"[^>]* text-align:right;~', $arabic);
        $this->assertMatchesRegularExpression('~<td align="left" style="padding:9px 0;[^>]* text-align:left;~', $arabic);
        $this->assertStringContainsString('margin-right:20px;', $arabic);
    }

    public function test_a_value_is_isolated_line_by_line(): void
    {
        app()->setLocale('ar');

        $this->assertSame("\u{2068}$1.00\u{2069}", Message::isolate('$1.00'));
        $this->assertSame("\u{2068}one\u{2069}\n\u{2068}two\u{2069}", Message::isolate("one\ntwo"));
        $this->assertSame("\u{2068}one\u{2069}\n\u{2068}two\u{2069}\n\u{2068}three\u{2069}", Message::isolate("one\r\ntwo\rthree"));
        $this->assertSame('', Message::isolate(''));
    }

    public function test_isolating_leaves_a_multibyte_arabic_value_whole(): void
    {
        app()->setLocale('ar');

        $this->assertSame("\u{2068}مرحبا\u{2069}", Message::isolate('مرحبا'));
    }

    public function test_a_fact_value_is_isolated_when_printed_and_stored_as_given(): void
    {
        app()->setLocale('ar');

        $message = $this->message()->facts(['Links' => "https://a.test\nhttps://b.test", 'Number' => 42]);

        $this->assertSame("https://a.test\nhttps://b.test", $message->blocks[0]['rows'][0]['value']);
        $this->assertStringContainsString("\u{2068}https://a.test\u{2069}<br />\n\u{2068}https://b.test\u{2069}", $this->html($message));
        $this->assertStringContainsString("\u{2068}42\u{2069}", $this->html($message));
    }

    public function test_a_panel_is_isolated_line_by_line_and_owned_markup_as_a_whole(): void
    {
        app()->setLocale('ar');

        $html = $this->html($this->message()->panel("one\ntwo\n")->facts(['A' => new HtmlString("<ul>\n<li>x</li>\n</ul>")]));

        $this->assertStringContainsString("\u{2068}one\u{2069}<br />\n\u{2068}two\u{2069}</td>", $html);
        $this->assertStringContainsString("\u{2068}<ul>\n<li>x</li>\n</ul>\u{2069}", $html);
    }

    public function test_a_button_url_is_isolated_outside_its_link(): void
    {
        app()->setLocale('ar');

        $html = $this->html($this->message()->action('Go', 'https://example.test/go'));

        $this->assertStringContainsString("\u{2068}<a href=\"https://example.test/go\"", $html);
        $this->assertStringContainsString("</a>\u{2069}", $html);
        $this->assertStringNotContainsString("https://example.test/go\u{2069}\"", $html);
    }

    /** Arabic letters join, so spacing them is not a matter of taste. */
    public function test_a_heading_is_letter_spaced_only_left_to_right(): void
    {
        $this->assertStringContainsString('letter-spacing:', $this->html($this->message()->heading('Head')));

        app()->setLocale('ar');

        $this->assertStringNotContainsString('letter-spacing:', $this->html($this->message()->heading('Head')));
    }

    public function test_a_notification_sent_in_arabic_renders_in_its_language(): void
    {
        $message = $this->send($this->message()->subject('A subject')->line('Words'), 'ar');

        $this->assertSame('en', app()->getLocale());
        $this->assertStringContainsString('dir="rtl"', (string)$message->getHtmlBody());
    }
}
