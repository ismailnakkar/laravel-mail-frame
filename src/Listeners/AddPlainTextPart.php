<?php

declare(strict_types=1);

namespace MailFrame\Listeners;

use Illuminate\Mail\Events\MessageSending;
use MailFrame\Tone;

/**
 * A listener, so no frame mail can forget its plain-text part.
 *
 * @internal
 */
final class AddPlainTextPart
{
    private const string FACT_SEPARATOR_KEY = 'mail-frame::email.fact-separator';

    /** A first-strong isolate around a whole line, balanced isolates inside it allowed. $1 is what it holds. */
    private const string WHOLE_LINE_ISOLATE = "/^\xE2\x81\xA8((?:(?!\xE2\x81[\xA8\xA9]).|\xE2\x81\xA8(?1)\xE2\x81\xA9)*+)\xE2\x81\xA9$/ms";

    /** A text part goes out unread, so a language and fallback with no separator get this rather than the key. */
    private const string PLAIN_FACT_SEPARATOR = ': ';

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
     *
     * Possessive loops throughout: a long body must not hit pcre.backtrack_limit.
     */
    public function convert(string $html): string
    {
        $text = $this->replace($html, [
            // Comments, processing instructions, and the head, style and script elements.
            '#<!--(?:-?>|(?:[^-]++|-(?!->))*+(*COMMIT)-->)|<\?[^>]*+>|<(head|style|script)\b[^>]*+>(?:[^<]++|<(?!/\1>))*+</\1>#i' => '',
            // An element an inline style hides, such as the preheader.
            '#<(\w+)\b(?:[^\s"\'<>]++|[\s"\'](?!style\s*=))*+[\s"\']style\s*=\s*(?:"[^"]*?|\'[^\']*?|)display:\s*none[^>]*+>(?:[^<]++|<(?!/\1>))*+</\1>#i' => '',
            // A button's URL line below the card: the text part prints the URL on the button's own line.
            '#<p class="mail-url"[^>]*+>(?:[^<]++|<(?!/p>))*+</p>#' => '',
            // A bare "<", after which strip_tags() would eat the rest of the mail.
            '#<(?![a-z/!?])#i' => '&lt;',
        ]);

        $text = $this->replace($text, [
            '#\s*<li\b[^>]*+>#i'       => "\n- ",
            '#<br\b[^>]*+>\R?#i'       => "\n",
            '#</th>\s*+<td\b[^>]*+>#i' => $this->factSeparator(),
            // scope="row" is the frame's own fact row: a caller's table keeps the gap between its rows.
            '#</td>\s*+</tr>\s*+(?=<tr\b[^>]*+>\s*+<th\s++scope="row")#i'         => "\n",
            '#</(p|div|tr|h[1-6]|ul|ol|table|blockquote|center|dd|dt|section)>#i' => "\n\n",
            '#</t[dh]>#i'                                                         => "\n",
        ]);

        $text = $this->decode(strip_tags($this->inlineLinks($text)));

        // Bytes, not /u: one invalid sequence must not cost the whole part.
        $text = $this->replace($text, [
            '/\r\n?/'    => "\n",
            "/\xC2\xA0/" => ' ',
            '/[ \t]+/'   => ' ',
            '/^ | $/m'   => '',
            "/\n{3,}/"   => "\n\n",
        ]);

        return trim($this->unwrapWholeLineIsolates($text)) . "\n";
    }

    /**
     * An isolate that fills its line isolates nothing, and copying the line would pick up the mark with a code or a link.
     * Until none is left: a value isolated twice, such as panel(Message::isolate($code)), fills its line twice over.
     */
    private function unwrapWholeLineIsolates(string $text): string
    {
        do {
            $before = $text;
            $text = $this->replace($text, [self::WHOLE_LINE_ISOLATE => '$1']);
        } while ($text !== $before);

        return $text;
    }

    /** A link becomes "label <url>", escaped so the brackets survive strip_tags() and decode with the rest. */
    private function inlineLinks(string $html): string
    {
        // (*COMMIT): with no closer left, stop scanning.
        return preg_replace_callback(
            '#<a\b(?:[^\s"\'<>]++|[\s"\'](?!href\s*=))*+[\s"\']href\s*=\s*(?|"([^"]*+)"|\'([^\']*+)\'|([^\s>]++))[^<>]*+>((?:[^<]++|<(?!/a>))*+)(*COMMIT)</a>#i',
            function (array $match): string {
                $url = $this->decode($match[1]);
                $label = trim($this->decode(strip_tags($match[2])));

                return e($label === '' || $label === $url ? $url : "{$label} <{$url}>");
            },
            $html,
        ) ?? $html;
    }

    private function factSeparator(): string
    {
        $separator = __(self::FACT_SEPARATOR_KEY);

        return is_string($separator) && $separator !== self::FACT_SEPARATOR_KEY ? $separator : self::PLAIN_FACT_SEPARATOR;
    }

    /**
     * Patterns run in order. On a PCRE error the text goes on as it was.
     *
     * @param  array<string, string>  $replacements  pattern => replacement
     */
    private function replace(string $text, array $replacements): string
    {
        return preg_replace(array_keys($replacements), array_values($replacements), $text) ?? $text;
    }

    private function decode(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
