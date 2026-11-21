<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use MailFrame\BlockType;
use MailFrame\Tone;

/** A button, and its URL below the card. A null tone follows the mail's. */
final class Button implements Block
{
    public BlockType $type {
        get => BlockType::action;
    }

    public function __construct(public readonly ?string $text, public readonly string $url, public readonly ?Tone $tone) {}
}
