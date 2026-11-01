<?php

declare(strict_types=1);

namespace MailFrame\Listeners;

use Illuminate\Mail\Events\MessageSending;
use MailFrame\Tone;

/** A listener, so no frame mail can forget its plain-text part. */
final class AddPlainTextPart
{
    private const string FACT_SEPARATOR = 'mail-frame::email.fact-separator';

    public function handle(MessageSending $event): void
    {
        // Only a frame mail carries a Tone.
        if (! ($event->data['tone'] ?? null) instanceof Tone || $event->message->getTextBody() !== null) {
            return;
        }

        $text = $this->convert((string)$event->message->getHtmlBody());

        if (trim($text) !== '') {
            $event->message->text($text);
        }
    }

    /**
     * ponytail: regexes fit this frame's markup, an Htmlable's own is best effort. A hidden tag that nests its own name or omits
     * its closer ends in the wrong place, and huge runs of unclosed tags scan slowly or fall back to the raw HTML.
     * Dom\HTMLDocument if that stops being enough.
     */
    public function convert(string $html): string
    {
        // Possessive loops: a long body must not hit pcre.backtrack_limit.
        $text = preg_replace(
            [
                '#<!--(?:-?>|(?:[^-]++|-(?!->))*+(*COMMIT)-->)|<\?[^>]*+>|<(head|style|script)\b[^>]*+>(?:[^<]++|<(?!/\1>))*+</\1>#i',
                '#<(\w+)\b(?:[^\s"\'<>]++|[\s"\'](?!style\s*=))*+[\s"\']style\s*=\s*(?:"[^"]*?|\'[^\']*?|)display:\s*none[^>]*+>(?:[^<]++|<(?!/\1>))*+</\1>#i',
                '#<p class="mail-url"[^>]*+>(?:[^<]++|<(?!/p>))*+</p>#',
                '#<(?![a-z/!?])#i', // strip_tags() would eat the mail after a bare "<"
            ],
            ['', '', '', '&lt;'],
            $html,
        ) ?? $html;

        // A text part goes out unread, so a language with no separator gets the plain one rather than the key.
        // scope="row" is the frame's own fact row: a caller's table keeps the gap between its rows.
        $separator = __(self::FACT_SEPARATOR);

        $text = preg_replace(
            ['#\s*<li\b[^>]*+>#i', '#<br\b[^>]*+>\R?#i', '#</th>\s*+<td\b[^>]*+>#i', '#</td>\s*+</tr>\s*+(?=<tr\b[^>]*+>\s*+<th\s++scope="row")#i', '#</(p|div|tr|h[1-6]|ul|ol|table|blockquote|center|dd|dt|section)>#i', '#</t[dh]>#i'],
            ["\n- ", "\n", is_string($separator) && $separator !== self::FACT_SEPARATOR ? $separator : ': ', "\n", "\n\n", "\n"],
            $text,
        ) ?? $text;

        // Escaped, so "label <url>" survives strip_tags() and decodes with the rest. (*COMMIT): no closer left, stop scanning.
        $text = preg_replace_callback(
            '#<a\b(?:[^\s"\'<>]++|[\s"\'](?!href\s*=))*+[\s"\']href\s*=\s*(?|"([^"]*+)"|\'([^\']*+)\'|([^\s>]++))[^<>]*+>((?:[^<]++|<(?!/a>))*+)(*COMMIT)</a>#i',
            function (array $match): string {
                $url = $this->decode($match[1]);
                $label = trim($this->decode(strip_tags($match[2])));

                return e($label === '' || $label === $url ? $url : "{$label} <{$url}>");
            },
            $text,
        ) ?? $text;

        $text = $this->decode(strip_tags($text));

        // Bytes, not /u: one invalid sequence must not cost the whole part.
        $text = preg_replace(['/\r\n?/', "/\xC2\xA0/", '/[ \t]+/', '/^ | $/m', "/\n{3,}/"], ["\n", ' ', ' ', '', "\n\n"], $text) ?? $text;

        // An isolate that fills its lines isolates nothing, and copying a line would pick up the mark with a code or a link.
        $text = preg_replace("/^\xE2\x81\xA8((?:(?!\xE2\x81[\xA8\xA9]).)*+)\xE2\x81\xA9$/ms", '$1', $text) ?? $text;

        return trim($text) . "\n";
    }

    private function decode(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
