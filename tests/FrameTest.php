<?php

declare(strict_types=1);

namespace MailFrame\Tests;

use Illuminate\Notifications\Action;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\WorkerStopReason;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use MailFrame\MailFrameServiceProvider;
use MailFrame\Tests\Fixtures\MailTone;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

final class FrameTest extends TestCase
{
    public function test_blocks_render_in_the_order_they_were_added(): void
    {
        config(['mail-frame.logo' => 'img/logo-email.png']);

        $html = $this->html($this->message()
            ->subject('SUBJECT-SENTINEL')
            ->preheader('PREHEADER-SENTINEL')
            ->heading('HEADING-SENTINEL')
            ->greeting('GREETING-SENTINEL')
            ->line('FIRST-LINE')
            ->bullets(['BULLET-ONE', 'BULLET-TWO'])
            ->facts(['FACT-LABEL' => 'FACT-VALUE', 'EMPTY-LABEL' => null])
            ->panel('PANEL-SENTINEL', MailTone::action)
            ->action('ACTION-LABEL', 'https://example.test/go')
            ->line('LAST-LINE')
            ->salutation('SALUTATION-SENTINEL')
            ->footnote('FOOTNOTE-SENTINEL')
            ->footer('FOOTER-SENTINEL', 'https://example.test/home'));

        $offsets = [];

        foreach ([
            'PREHEADER-SENTINEL',
            'img/logo-email.png',
            'HEADING-SENTINEL',
            'GREETING-SENTINEL',
            'FIRST-LINE',
            'BULLET-ONE',
            'BULLET-TWO',
            'FACT-LABEL',
            'FACT-VALUE',
            'PANEL-SENTINEL',
            'ACTION-LABEL',
            'LAST-LINE',
            'SALUTATION-SENTINEL',
            'FOOTNOTE-SENTINEL',
            'FOOTER-SENTINEL',
            'https://example.test/home',
        ] as $needle) {
            $offset = strpos($html, $needle);

            $this->assertNotFalse($offset, "The rendered mail is missing '{$needle}'.");

            $offsets[$needle] = $offset;
        }

        $sorted = $offsets;
        asort($sorted);

        $this->assertSame(array_keys($offsets), array_keys($sorted), 'Blocks must render in the order they were added.');
        $this->assertStringNotContainsString('EMPTY-LABEL', $html);
        $this->assertMatchesRegularExpression('~>FACT-LABEL</th><td[^>]*>FACT-VALUE</td>~', $html);
    }

    public function test_the_tone_line_is_a_cell_border_and_outlook_keeps_the_buttons_padding(): void
    {
        foreach (MailTone::cases() as $tone) {
            $html = $this->html($this->message()->tone($tone)->heading('Heading')->action('Go', 'https://example.test'));

            $this->assertStringContainsString('<td class="mail-pad mail-head" style="border-top:3px solid ' . $tone->accent() . '; border-radius:13px 13px 0 0; padding:30px 32px 0;', $html, $tone->name);
            $this->assertStringNotContainsString('height="3"', $html, $tone->name);
            $this->assertStringContainsString('<td align="center" bgcolor="' . $tone->accent() . '" style="mso-padding-alt:14px 22px;', $html, $tone->name);
            $this->assertStringContainsString('display:inline-block; padding:14px 22px; mso-padding-alt:0;', $html, $tone->name);
            $this->assertSame(1, substr_count($html, 'border-top:3px solid'), $tone->name);
        }

        $html = $this->html($this->message()->tone(MailTone::security)->line('Words'));

        $this->assertSame(1, substr_count($html, 'border-top:3px solid'));
        $this->assertStringContainsString('<td class="mail-pad mail-body" style="border-top:3px solid #b91c33; border-radius:13px 13px 0 0; padding:30px 32px 30px;', $html);
    }

    public function test_the_shell_holds_up_where_clients_strip_or_ignore_css(): void
    {
        $html = $this->html($this->message()->preheader('Peek')->line('Words'));

        $this->assertStringNotContainsString('table { border-collapse: collapse; }', $html);
        $this->assertStringContainsString('@media only screen and (max-width: 480px) {', $html);
        $this->assertStringContainsString('<meta name="color-scheme" content="light only" />', $html);
        $this->assertStringContainsString('style="width:100%; max-width:600px;"', $html);
        $this->assertStringContainsString('<!--[if mso]><table role="presentation" width="600"', $html);
        $this->assertStringContainsString('<!--[if mso]></td></tr></table><![endif]-->', $html);
        $this->assertStringContainsString('display:none; mso-hide:all;', $html);
        $this->assertStringContainsString("font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;", $html);
    }

    public function test_a_plain_mail_message_keeps_laravels_own_template(): void
    {
        $this->assertNull(new MailMessage()->view);
        $this->assertStringContainsString('Illuminate/Notifications/resources/views', view('notifications::email')->getPath());
    }

    public static function escapedStrings(): iterable
    {
        yield 'preheader' => [static fn ($mail) => $mail->preheader('<i>x</i>')];
        yield 'heading' => [static fn ($mail) => $mail->heading('<i>x</i>')];
        yield 'greeting' => [static fn ($mail) => $mail->greeting('<i>x</i>')];
        yield 'line' => [static fn ($mail) => $mail->line('<i>x</i>')];
        yield 'bullet' => [static fn ($mail) => $mail->bullets(['<i>x</i>'])];
        yield 'fact label' => [static fn ($mail) => $mail->facts(['<i>x</i>' => 'value'])];
        yield 'fact value' => [static fn ($mail) => $mail->facts(['label' => '<i>x</i>'])];
        yield 'panel' => [static fn ($mail) => $mail->panel('<i>x</i>')];
        yield 'action label' => [static fn ($mail) => $mail->action('<i>x</i>', 'https://example.test')];
        yield 'salutation' => [static fn ($mail) => $mail->salutation('<i>x</i>')];
        yield 'footnote' => [static fn ($mail) => $mail->footnote('<i>x</i>')];
        yield 'footer' => [static fn ($mail) => $mail->footer('<i>x</i>', 'https://example.test')];
        yield 'subject' => [static fn ($mail) => $mail->subject('<i>x</i>')];
    }

    #[DataProvider('escapedStrings')]
    public function test_a_string_is_text(callable $add): void
    {
        $html = $this->html($add($this->message()));

        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('&lt;i&gt;', $html);
    }

    public static function ownedMarkup(): iterable
    {
        $markup = new HtmlString('<b>x</b>');

        yield 'line' => [static fn ($mail) => $mail->line($markup)];
        yield 'bullet' => [static fn ($mail) => $mail->bullets([$markup])];
        yield 'panel' => [static fn ($mail) => $mail->panel($markup)];
        yield 'footnote' => [static fn ($mail) => $mail->footnote($markup)];
        yield 'footer' => [static fn ($mail) => $mail->footer($markup, 'https://example.test')];
    }

    #[DataProvider('ownedMarkup')]
    public function test_a_caller_can_hand_over_its_own_markup(callable $add): void
    {
        $this->assertStringContainsString('<b>x</b>', $this->html($add($this->message())));
    }

    public function test_a_url_is_escaped_wherever_it_prints(): void
    {
        $url = 'https://example.test/?a=1&b="><i>x</i>';
        $html = $this->html($this->message()->action('Go', $url)->footer('Small', $url));

        $this->assertStringNotContainsString('<i>', $html);
        $this->assertSame(4, substr_count($html, e($url)), 'Button href, bare URL href, bare URL text, footer href.');
    }

    public function test_facts_take_a_list_where_two_rows_share_a_label(): void
    {
        $html = $this->html($this->message()->facts([['label' => 'Link', 'value' => 'one'], ['label' => 'Link', 'value' => 'two']]));

        $this->assertSame(2, substr_count($html, '>Link</th>'));
    }

    public function test_an_empty_value_drops_its_row_but_a_zero_stays(): void
    {
        $message = $this->message()->facts(['A' => null, 'B' => '', 'C' => '0', 'D' => 0]);

        $this->assertSame(['C', 'D'], array_column($message->blocks[0]['rows'], 'label'));
        $this->assertSame([['label' => 'Z', 'value' => 0]], $this->message()->facts([['label' => 'Y', 'value' => null], ['label' => 'Z', 'value' => 0]])->blocks[0]['rows']);
    }

    public function test_a_block_with_nothing_to_print_adds_nothing(): void
    {
        $message = $this->message()
            ->facts([])->facts(['A' => null])->facts(['A' => '  '])
            ->bullets([])->bullets(['', null, new HtmlString('')])
            ->panel('')->panel(null)->panel("\n ")->panel(new HtmlString(''));

        $this->assertSame([], $message->blocks);
        $this->assertSame(['x'], $this->message()->bullets(['', 'x'])->blocks[0]['items']);
    }

    public function test_a_fact_value_prints_as_blade_prints_it(): void
    {
        $html = $this->html($this->message()->facts(['Why' => WorkerStopReason::Interrupted, 'Note' => new HtmlString('<b>x</b>')]));

        $this->assertStringContainsString('>interrupted</td>', $html);
        $this->assertStringContainsString('<b>x</b>', $html);
        $this->assertSame([], $this->message()->facts(['A' => false, 'B' => str(''), 'C' => new HtmlString('')])->blocks);
    }

    public function test_each_fact_row_picks_its_own_shape(): void
    {
        $message = $this->message()->facts(['A' => null, 'B' => '1', ['label' => 'C', 'value' => '2']]);

        $this->assertSame(['B', 'C'], array_column($message->blocks[0]['rows'], 'label'));
    }

    public function test_a_list_of_bare_values_fails_loudly(): void
    {
        $this->expectException(TypeError::class);

        $this->message()->facts(['Paid', 'Shipped']);
    }

    public function test_an_htmlable_fact_or_panel_prints_as_given(): void
    {
        $markup = new HtmlString("<ul>\n<li>x</li>\n</ul>");

        $this->assertSame(2, substr_count($this->html($this->message()->facts(['A' => $markup])->panel($markup)), $markup->toHtml()));
    }

    public function test_a_panel_keeps_its_lines(): void
    {
        $this->assertStringContainsString("one &lt;b&gt;<br />\ntwo", $this->html($this->message()->panel("one <b>\ntwo")));
        $this->assertStringContainsString('>Trailing</td>', $this->html($this->message()->panel("Trailing\n")));
    }

    public function test_a_text_view_keeps_the_frame(): void
    {
        $html = $this->html($this->message()->heading('HEAD')->line('BODY')->text('some.text.view'));

        $this->assertStringContainsString('HEAD', $html);
        $this->assertStringContainsString('BODY', $html);
    }

    public function test_a_screen_reader_gets_a_heading_and_a_labelled_table(): void
    {
        $html = $this->html($this->message()->heading('Title')->facts(['Label' => 'Value']));

        $this->assertMatchesRegularExpression('~<h1 [^>]*>Title</h1>~', $html);
        $this->assertMatchesRegularExpression('~<table width="100%"[^>]*>\s*<tr><th scope="row"~', $html);
    }

    public function test_every_way_in_to_a_line_prints(): void
    {
        $html = $this->html($this->message()->with('ONE')->line(['TWO', 'THREE'])->lineIf(true, 'FOUR')->with(new Action('Go', 'https://example.test/go')));

        foreach (['>ONE</p>', '>TWO THREE</p>', '>FOUR</p>', 'href="https://example.test/go"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_only_the_last_fact_closes_the_table_with_a_rule(): void
    {
        $html = $this->html($this->message()->facts(['A' => '1', 'B' => '2']));

        // The label's cell and the value's.
        $this->assertSame(2, substr_count($html, 'border-bottom:1px solid'));
    }

    public function test_a_button_follows_the_mails_tone_unless_it_names_one(): void
    {
        $this->assertStringContainsString('bgcolor="#7d5200" style="mso-padding-alt:14px 22px; border-radius:10px; background:#7d5200;"', $this->html($this->message()->tone(MailTone::action)->action('Go', 'https://example.test')));
        $this->assertStringContainsString('bgcolor="#b91c33"', $this->html($this->message()->tone(MailTone::action)->action('Go', 'https://example.test', MailTone::security)));
    }

    public function test_a_button_follows_a_tone_set_after_it(): void
    {
        $this->assertStringContainsString('bgcolor="#7d5200"', $this->html($this->message()->action('Go', 'https://example.test')->tone(MailTone::action)));
    }

    public function test_a_button_still_sets_the_mails_action(): void
    {
        $message = $this->message()->action('Go', 'https://example.test');

        $this->assertSame('Go', $message->actionText);
        $this->assertSame('https://example.test', $message->actionUrl);
    }

    public function test_the_url_under_a_button_is_a_link(): void
    {
        $this->assertStringContainsString('style="color:#69717f;">https://example.test/go</a>', $this->html($this->message()->action('Go', 'https://example.test/go')));
    }

    public function test_a_panel_is_neutral_whatever_the_mails_tone_unless_it_names_one(): void
    {
        $this->assertStringContainsString('background:#f2f6fd; border:1px solid #e2e6f7;', $this->html($this->message()->tone(MailTone::security)->panel('Quoted')));
        $this->assertStringContainsString('background:#fdf3e0; border:1px solid #d4a954;', $this->html($this->message()->panel('Quoted', MailTone::action)));
    }

    public function test_a_salutation_signs_with_the_site_name(): void
    {
        $this->assertStringContainsString('Regards<br />Acme</p>', $this->html($this->message()->salutation('Regards')));
        $this->assertStringNotContainsString('<br />Acme</p>', $this->html($this->message()));

        $this->assertStringContainsString('Regards<br />Acme Team</p>', $this->html($this->message()->salutation('Regards', 'Acme Team')));
    }

    public function test_the_footer_links_the_site_name(): void
    {
        $this->assertStringContainsString('<a href="https://example.test/fr" style="color:#69717f;">Acme</a>', $this->html($this->message()->footer('Small print', 'https://example.test/fr')));

        config(['app.url' => 'https://acme.test']);

        $this->assertStringContainsString('<a href="https://acme.test" style="color:#69717f;">Acme</a>', $this->html($this->message()->footer('Small print')));
    }

    public function test_what_is_unset_prints_nothing(): void
    {
        $html = $this->html($this->message()->footer('Small', 'https://example.test'));

        $this->assertStringContainsString('<title>Acme</title>', $html);
        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringNotContainsString('<p style="margin:0 0 16px;"></p>', $html);
        $this->assertStringNotContainsString('<div style="margin:0 0 8px;"></div>', $html);
        $this->assertStringContainsString('Small<br />', $html);
    }

    public function test_a_mail_with_no_footnote_or_footer_prints_no_row_for_them(): void
    {
        $this->assertStringNotContainsString('padding:22px 8px 0;', $this->html($this->message()));
        $this->assertStringContainsString('padding:22px 8px 0;', $this->html($this->message()->footnote('Note')));
    }

    public function test_a_mail_with_no_logo_prints_the_site_name(): void
    {
        $html = $this->html($this->message());

        $this->assertStringNotContainsString('<img', $html);
        $this->assertMatchesRegularExpression('~font-weight:700; color:#111827;">\s*Acme\s*</td>~', $html);
    }

    public function test_a_logo_is_sized_by_the_config(): void
    {
        config(['mail-frame.logo' => 'img/logo.png', 'mail-frame.logo_width' => 112]);

        $this->assertStringContainsString('<img src="' . asset('img/logo.png') . '" alt="Acme" width="112" style="display:block; width:112px; height:auto; margin:0 auto; border:0;" />', $this->html($this->message()));
    }

    public function test_every_colour_comes_from_the_config(): void
    {
        config(['mail-frame.colors' => ['page' => '#a00001', 'rule' => '#a00002', 'heading' => '#a00003', 'text' => '#a00004', 'muted' => '#a00005', 'link' => '#a00006']]);

        $html = $this->html($this->message()->heading('H')->preheader('P')->facts(['A' => '1'])->panel('Q')->action('Go', 'https://example.test')->footnote('N')->footer('F', 'https://example.test'));

        $this->assertStringContainsString('background: #a00001;', $html);
        $this->assertStringContainsString('border:1px solid #a00002;', $html);
        $this->assertStringContainsString('color:#a00003;', $html);
        $this->assertStringContainsString('color:#a00004;', $html);
        $this->assertStringContainsString('color:#a00005;', $html);
        $this->assertStringContainsString('a { color: #a00006; }', $html);

        preg_match_all('/#[0-9a-f]{6}\b/i', $html, $found);

        $this->assertSame([], array_values(array_diff($found[0], [...config('mail-frame.colors'), MailTone::routine->accent(), MailTone::routine->tint(), MailTone::routine->border(), '#ffffff'])));
    }

    public function test_an_app_sets_only_the_keys_it_changes(): void
    {
        config(['mail-frame' => ['logo' => 'img/logo.png', 'colors' => ['link' => '#a00006']]]);

        (new MailFrameServiceProvider($this->app))->register();

        $this->assertSame('img/logo.png', config('mail-frame.logo'));
        $this->assertSame('#a00006', config('mail-frame.colors.link'));
        $this->assertSame('#111827', config('mail-frame.colors.heading'));
    }

    public function test_only_the_config_can_be_published(): void
    {
        $this->assertContains(config_path('mail-frame.php'), ServiceProvider::pathsToPublish(MailFrameServiceProvider::class, 'mail-frame-config'));
        $this->assertSame([], ServiceProvider::pathsToPublish(MailFrameServiceProvider::class, 'mail-frame-views'));
    }
}
