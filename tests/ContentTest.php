<?php

declare(strict_types=1);

namespace MailFrame\Tests;

use MailContent\Audience;
use MailContent\Blocks\Block;
use MailContent\Blocks\Bullets;
use MailContent\Blocks\Button;
use MailContent\Blocks\Code;
use MailContent\Blocks\Fact;
use MailContent\Blocks\Facts;
use MailContent\Blocks\Line;
use MailContent\Blocks\Link;
use MailContent\Blocks\LinkedLine;
use MailContent\Blocks\Links;
use MailContent\Blocks\Panel;
use MailContent\Content;
use MailFrame\Blocks\Line as FrameLine;
use MailFrame\Blocks\Panel as FramePanel;
use MailFrame\BlockType;
use MailFrame\Tests\Fixtures\Alert;
use MailFrame\Tests\Fixtures\MailTone;

final class ContentTest extends TestCase
{
    public function test_blocks_keep_their_order(): void
    {
        $message = $this->message()->content($this->content([
            new Line('First'),
            new Bullets(['One', 'Two']),
            new Facts([new Fact('Address', '0xabc'), new Fact('Address', '0xdef')]),
            new Panel("Sent it\ntwice"),
            new Links([new Link('Held', 'https://example.test/held')]),
            new Button('Review', 'https://example.test/review'),
            new Line('Last'),
        ]));

        $this->assertSame(
            [BlockType::line, BlockType::bullets, BlockType::facts, BlockType::panel, BlockType::bullets, BlockType::action, BlockType::line],
            array_column($message->blocks, 'type'),
        );
        $this->assertSame([['Address', '0xabc'], ['Address', '0xdef']], array_map(static fn ($row): array => [$row->label, $row->value], $message->blocks[2]->rows));
        $this->assertSame("Sent it\ntwice", $message->blocks[3]->text);
        $this->assertSame(['Review', 'https://example.test/review'], [$message->actionText, $message->actionUrl]);
        $this->assertSame('Subject', $message->subject);
        $this->assertSame('Heading', $message->viewData['heading']);
    }

    public function test_a_links_text_and_href_are_escaped(): void
    {
        $message = $this->message()->content($this->content([
            new Links([new Link('<b>Held</b> & more', 'https://example.test/held?a=1&b="2"')]),
        ]));

        $this->assertSame(
            '<a href="https://example.test/held?a=1&amp;b=&quot;2&quot;">&lt;b&gt;Held&lt;/b&gt; &amp; more</a>',
            (string)$message->blocks[0]->items[0],
        );
    }

    public function test_a_linked_line_is_one_paragraph_with_its_link_inside_and_only_the_link_is_markup(): void
    {
        $message = $this->message()->content($this->content([
            new LinkedLine('If <b>not</b>, :link & now.', new Link('contact "us"', 'https://example.test/contact?a=1&b=2')),
        ]));

        $this->assertSame([BlockType::line], array_column($message->blocks, 'type'));
        $this->assertStringContainsString(
            '<p style="margin:0 0 16px;">If &lt;b&gt;not&lt;/b&gt;, <a href="https://example.test/contact?a=1&amp;b=2">contact &quot;us&quot;</a> &amp; now.</p>',
            $this->html($message),
        );
        $this->assertStringContainsString('If <b>not</b>, contact "us" <https://example.test/contact?a=1&b=2> & now.', (string)$this->send($message)->getTextBody());
    }

    public function test_a_code_is_a_panel_in_the_neutral_tone(): void
    {
        $message = $this->message()->tone(MailTone::security)->content($this->content([new Code('123456')]));

        $this->assertEquals([new FramePanel('123456', MailTone::routine)], $message->blocks);
    }

    public function test_the_tone_is_left_alone(): void
    {
        $message = $this->message()->tone(MailTone::action)->content($this->content([], error: true));

        $this->assertSame(MailTone::action, $message->viewData['tone']);
    }

    public function test_the_greeting_and_preheader_are_set_only_when_given(): void
    {
        $bare = $this->message()->greeting('Hello Pat,')->content($this->content([]));

        $this->assertSame('Hello Pat,', $bare->greeting);
        $this->assertArrayNotHasKey('preheader', $bare->viewData);

        $given = $this->message()->content($this->content([], greeting: 'Hi Sam,', preheader: 'Preview'));

        $this->assertSame('Hi Sam,', $given->greeting);
        $this->assertSame('Preview', $given->viewData['preheader']);
    }

    public function test_a_block_it_has_no_layout_for_prints_its_lines(): void
    {
        $message = $this->message()->content($this->content([new class implements Block
        {
            public function lines(): array
            {
                return ['First', 'A <b>bold</b> claim'];
            }
        }]));

        $this->assertEquals([new FrameLine('First'), new FrameLine('A <b>bold</b> claim')], $message->blocks);
        $this->assertStringContainsString('A &lt;b&gt;bold&lt;/b&gt; claim', $this->html($message));
    }

    /** @param  list<Block>  $blocks */
    private function content(array $blocks, ?string $greeting = null, ?string $preheader = null, bool $error = false): Content
    {
        return new Content(Alert::failed, Audience::member, 'Subject', 'Heading', $blocks, $greeting, $preheader, $error);
    }
}
