<?php

declare(strict_types=1);

namespace MailFrame\Tests;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use MailFrame\Listeners\AddPlainTextPart;
use MailFrame\Message;
use MailFrame\Tests\Fixtures\MailTone;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;

final class PlainTextTest extends TestCase
{
    private function convert(string $html): string
    {
        return new AddPlainTextPart()->convert($html);
    }

    private function textPart(Email $message): ?string
    {
        new AddPlainTextPart()->handle(new MessageSending($message, ['tone' => MailTone::routine]));

        return $message->getTextBody();
    }

    public function test_a_full_mail_reads_exactly_like_this_as_text(): void
    {
        $html = (string)$this->message()
            ->preheader('Pre')
            ->heading('Head')
            ->greeting('Hello')
            ->line('First')
            ->bullets(['one', 'two'])
            ->facts(['Links' => "https://a.test\nhttps://b.test", 'Amount' => '$1.00'])
            ->panel('Quoted')
            ->action('Terms & Go', 'https://example.test/go')
            ->salutation('Thanks')
            ->footnote('Note')
            ->footer('Small', 'https://example.test/home')
            ->render();

        $this->assertSame(<<<'TEXT'
            Acme

            Head

            Hello

            First

            - one
            - two

            Links
            https://a.test
            https://b.test

            Amount
            $1.00

            Quoted

            Terms & Go <https://example.test/go>

            Thanks
            Acme

            Note

            Small
            Acme <https://example.test/home>

            TEXT, $this->convert($html));
    }

    public static function trickyHtml(): iterable
    {
        yield 'a self-labelled link encoded differently' => ['<p><a href="https://h.test/?a=1&amp;b=2">https://h.test/?a=1&b=2</a></p>', "https://h.test/?a=1&b=2\n"];
        yield 'entities decode once' => ['<p>it&#039;s &amp;copy; &amp;lt;b&amp;gt; ?ref=abc&amp;copy=1</p>', "it's &copy; &lt;b&gt; ?ref=abc&copy=1\n"];
        yield 'invalid UTF-8' => ["<style>x{}</style><p>bad \xff  byte <a href=\"https://h.test/x\">Go</a></p>", "bad \xff byte Go <https://h.test/x>\n"];
        yield 'HTML the frame never prints' => [
            '<head><title>t</title></head><style>.x{}</style><script>y()</script><h1>a   b&nbsp;c</h1><div>d</div><p>e</p>'
            . "<table><tr><td>f</td></tr><tr><td>g</td></tr></table><ul><li>h</li></ul><p><a href=\"https://h.test/t\">\n  <b>A &amp; B</b>\n</a></p><p><a href=\"https://h.test/i\"><img src=\"i.png\" /></a></p>",
            "a b c\n\nd\n\ne\n\nf\n\ng\n\n- h\n\nA & B <https://h.test/t>\n\nhttps://h.test/i\n",
        ];
        yield 'loose HTML' => [
            "<DIV STYLE='display: none'>x\ny</DIV><STYLE>p{}</STYLE><P>a</P><A HREF='https://h.test/b'>b</A><ol><li>c</li></ol><p>d\t\te\rf</p><table><tr><td>g</td><td>h</td></tr></table>",
            "a\n\nb <https://h.test/b>\n- c\n\nd e\nf\n\ng\nh\n",
        ];
        yield 'a bare <' => ['<p>price<5 today</p><p>next</p>', "price<5 today\n\nnext\n"];
        yield 'an attribute after href' => ['<a href="https://h.test/real" data-href="https://h.test/wrong">Go</a>', "Go <https://h.test/real>\n"];
        yield 'an attribute before href' => ['<a data-href="https://h.test/wrong" href="https://h.test/real">Go</a>', "Go <https://h.test/real>\n"];
        yield 'an unquoted href' => ['<a href=https://h.test/real>Go</a>', "Go <https://h.test/real>\n"];
        yield 'spaces around an href' => ['<a href = "https://h.test/real">Go</a>', "Go <https://h.test/real>\n"];
        yield 'a raw < in an href' => ['<p><a href="https://h.test/?q=a<b">z</a></p><p>after</p>', "z <https://h.test/?q=a<b>\n\nafter\n"];
        yield 'a link in a comment' => ['<!-- <a href="https://h.test/old"> --><p>Kept</p><a href="https://h.test/go">Go</a>', "Kept\n\nGo <https://h.test/go>\n"];
        yield 'a hidden span' => ['<span style="display:none">hide</span><p>Kept</p>', "Kept\n"];
        yield 'an unquoted hidden style' => ['<div style = display:none>hide</div><p>Kept</p>', "Kept\n"];
        yield 'a quote before display:none' => ['<div style="font-family:\'Segoe UI\'; display:none">hide</div><p>Kept</p>', "Kept\n"];
        yield 'display:none in visible text' => ['<div>display:none</div><p>Kept</p>', "display:none\n\nKept\n"];
        yield 'a hidden element after a value that mentions it' => ['<div style="color:red" title="display:none">Seen</div>', "Seen\n"];
        yield 'attributes with no space between them' => ['<div class="x"style="display:none">hide</div><a class="x"href="https://h.test/y">Go</a>', "Go <https://h.test/y>\n"];
        yield 'a comment opened in a script' => ['<script>var s="<!--";</script><p>Kept</p><!-- end --><p>Too</p>', "Kept\n\nToo\n"];
        yield 'an empty comment' => ['<p>One</p><!--><p>Two</p><!---><p>Three</p><!-- c --><p>Four</p>', "One\n\nTwo\n\nThree\n\nFour\n"];
        yield 'a processing instruction' => ['<?xml:namespace prefix = o ns = "urn:office" /><p>word</p><p>rest</p>', "word\n\nrest\n"];
        yield 'a table with no closing row' => ['<table><tr><td>Plan</td><td>Pro</table><div>Footer</div>', "Plan\nPro\n\nFooter\n"];
        yield 'a break in a link label' => ['<a href="https://h.test/u">First<br>second</a>', "First\nsecond <https://h.test/u>\n"];
        yield 'attributes on a br' => ['one<br class="x">two', "one\ntwo\n"];
        yield 'block tags' => ['<blockquote>a</blockquote><center>b</center><dl><dt>c</dt><dd>d</dd><dd>e</dd></dl><section>f</section><section>g</section>', "a\n\nb\n\nc\n\nd\n\ne\n\nf\n\ng\n"];
    }

    #[DataProvider('trickyHtml')]
    public function test_tricky_html_reads_as_text(string $html, string $text): void
    {
        $this->assertSame($text, $this->convert($html));
    }

    public function test_a_long_body_does_not_hit_the_backtrack_limit(): void
    {
        $long = str_repeat('a ', 1_500_000);

        $this->assertSame("hello\n", $this->convert("<style>{$long}</style><div style=\"display:none\">{$long}</div><!-- {$long} --><p>hello</p>"));
        $this->assertStringEndsWith("a <https://h.test/x>\n", $this->convert("<a href=\"https://h.test/x\">{$long}</a>"));
        $this->assertSame("hello\n", $this->convert('<style>x{}</style><img src="data:' . str_repeat('A', 1_100_000) . '"><p>hello</p>'));
        $this->assertSame("a b\n", $this->convert('<style>x{}</style><p>a' . str_repeat(' ', 10_000) . 'b</p>'));
        $this->assertSame("a b\n", $this->convert('<p>a' . str_repeat("\u{A0}", 10_000) . 'b</p>'));

        $tags = str_repeat('<b>x</b>', 20_000);

        $this->assertSame(str_repeat('x', 20_000) . " <https://h.test/x>\n", $this->convert("<style>{$tags}</style><div style=\"display:none\">{$tags}</div><a href=\"https://h.test/x\">{$tags}</a>"));
    }

    public function test_the_listener_gives_an_html_message_a_text_part(): void
    {
        $this->assertSame("Hello there <https://h.test/z>\n", $this->textPart(new Email()->html('<p>Hello <a href="https://h.test/z">there</a></p>')));
    }

    public function test_the_listener_never_overwrites_an_explicit_text_part(): void
    {
        $this->assertSame('hand written', $this->textPart(new Email()->html('<p>derived</p>')->text('hand written')));
    }

    public function test_the_listener_adds_no_blank_text_part_to_an_image_only_message(): void
    {
        $this->assertNull($this->textPart(new Email()->html('<p><img src="a.png"></p>')));
    }

    public function test_mail_outside_the_frame_is_left_alone(): void
    {
        Mail::html('<p>Hello</p>', static fn ($message) => $message->to('pat@example.test'));

        $this->assertNull(Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getTextBody());
    }

    public function test_a_sent_notification_carries_a_plain_text_part_with_its_links(): void
    {
        $text = $this->send($this->message()
            ->subject('A subject')
            ->preheader('PREHEADER-SENTINEL')
            ->heading('HEADING-SENTINEL')
            ->line('BODY-SENTINEL')
            ->bullets(['BULLET-SENTINEL'])
            ->facts(['Amount' => '$1.0000'])
            ->action('ACTION-LABEL', 'https://example.test/go?a=1&b=2'))->getTextBody();

        $this->assertIsString($text, 'The message went out as a single text/html part.');
        $this->assertStringContainsString('HEADING-SENTINEL', $text);
        $this->assertStringContainsString('BODY-SENTINEL', $text);
        $this->assertStringContainsString('- BULLET-SENTINEL', $text);
        $this->assertMatchesRegularExpression('~Amount\s+\$1\.0000~', $text);
        $this->assertStringContainsString('ACTION-LABEL <https://example.test/go?a=1&b=2>', $text);
        $this->assertStringNotContainsString('<a ', $text);
        $this->assertStringNotContainsString('PREHEADER-SENTINEL', $text);
        $this->assertStringNotContainsString('color-scheme', $text);
    }

    public function test_a_button_url_prints_once(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $text = $this->convert($this->html($this->message()->action('Go', 'https://example.test/go')));

            $this->assertSame(1, substr_count($text, 'https://example.test/go'), $locale);
            $this->assertStringContainsString('Go <https://example.test/go>', $text, $locale);
        }
    }

    /** A line needs no isolate of its own, and copying a whole line must not pick one up with a code. */
    public function test_direction_marks_stay_off_the_ends_of_a_line(): void
    {
        app()->setLocale('ar');

        $text = $this->convert($this->html($this->message()
            ->line('Hello ' . Message::isolate('sam') . ',')
            ->line(Message::isolate('sam') . ' said')
            ->facts(['Amount' => '$1.00'])
            ->panel(new HtmlString('123456<br />expires'))));

        $this->assertStringContainsString("Hello \u{2068}sam\u{2069},", $text);
        $this->assertStringContainsString("\u{2068}sam\u{2069} said", $text);
        $this->assertStringContainsString("\n$1.00\n", $text);
        $this->assertStringContainsString("\n123456\nexpires\n", $text);
    }
}
