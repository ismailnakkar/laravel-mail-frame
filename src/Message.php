<?php

declare(strict_types=1);

namespace MailFrame;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Action;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Arr;

/** Blocks print in call order, which intro and outro lines cannot do. A string is text; owned markup is an Htmlable. */
class Message extends MailMessage
{
    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    /** @param  Tone  $neutral  the default for tone() and every panel */
    public function __construct(private readonly Tone $neutral)
    {
        // A view, never the markdown pipeline: Markdown::render() re-serialises through DOMDocument.
        $this->view('mail-frame::email', ['tone' => $neutral]);
    }

    /**
     * Adds to the frame's data, where MailMessage would wipe it. text() lands here too.
     *
     * @param  array<string, string|null>|string  $view
     * @param  array<string, mixed>  $data
     */
    public function view($view, array $data = []): static
    {
        return parent::view($view, $data + $this->viewData);
    }

    public function tone(Tone $tone): static
    {
        $this->viewData['tone'] = $tone;

        return $this;
    }

    /** The line a client shows after the subject; unset, it scrapes the body. */
    public function preheader(string $text): static
    {
        $this->viewData['preheader'] = $text;

        return $this;
    }

    public function heading(string $text): static
    {
        $this->viewData['heading'] = $text;

        return $this;
    }

    /**
     * line(), lines() and lineIf() all land here.
     *
     * @param  string|array<string>|Htmlable|Action  $line
     */
    public function with($line): static
    {
        if ($line instanceof Action) {
            return $this->action($line->text, $line->url);
        }

        $this->blocks[] = ['type' => BlockType::line, 'text' => $this->formatLine($line)];

        return $this;
    }

    /** @param  list<string|Htmlable|null>  $items */
    public function bullets(array $items): static
    {
        $items = array_values(array_filter($items, self::prints(...)));

        if ($items !== []) {
            $this->blocks[] = ['type' => BlockType::bullets, 'items' => $items];
        }

        return $this;
    }

    /**
     * label => value rows, or a list of label/value pairs where two rows may share a label. A value that prints nothing
     * drops its row.
     *
     * @param  array<array-key, mixed>  $rows
     */
    public function facts(array $rows): static
    {
        if (! array_is_list($rows)) {
            $rows = Arr::map($rows, static fn (mixed $value, int|string $label): array => is_array($value) ? $value : ['label' => $label, 'value' => $value]);
        }

        $rows = array_values(array_filter($rows, static fn (array $row): bool => self::prints($row['value'])));

        if ($rows !== []) {
            $this->blocks[] = ['type' => BlockType::facts, 'rows' => $rows];
        }

        return $this;
    }

    /** A quoted block. A string keeps its lines. */
    public function panel(string|Htmlable|null $text, ?Tone $tone = null): static
    {
        if (self::prints($text)) {
            $this->blocks[] = ['type' => BlockType::panel, 'text' => $text, 'tone' => $tone ?? $this->neutral];
        }

        return $this;
    }

    /** Follows the mail's tone unless $tone is given. Also sets actionText and actionUrl, where apps read a mail's link. */
    public function action($text, $url, ?Tone $tone = null): static
    {
        $this->blocks[] = ['type' => BlockType::action, 'text' => $text, 'url' => $url, 'tone' => $tone];

        return parent::action($text, $url);
    }

    /** Signs with the site name unless $signature is given. */
    public function salutation($salutation, ?string $signature = null): static
    {
        $this->viewData['signature'] = $signature;

        return parent::salutation($salutation);
    }

    public function footnote(string|Htmlable $text): static
    {
        $this->viewData['footnote'] = $text;

        return $this;
    }

    /** Small print under the card, then the site name linked to $url. */
    public function footer(string|Htmlable $text, ?string $url = null): static
    {
        $this->viewData['footer'] = ['text' => $text, 'url' => $url ?? config('app.url')];

        return $this;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [...parent::data(), 'blocks' => $this->blocks];
    }

    /**
     * First-strong isolates, for a value inside right-to-left copy. Marks, not <bdi>: a subject and the text part carry no
     * markup. Per line: nl2br's <br> ends an isolate. Never an href.
     */
    public static function isolate(string $text): string
    {
        if ($text === '' || ! locale_is_right_to_left(app()->getLocale())) {
            return $text;
        }

        return "\u{2068}" . preg_replace('/\r\n?|\n/', "\u{2069}\n\u{2068}", $text) . "\u{2069}";
    }

    private static function prints(mixed $value): bool
    {
        return trim(e($value)) !== '';
    }
}
