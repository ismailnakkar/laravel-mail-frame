<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use Illuminate\Contracts\Support\Htmlable;
use MailFrame\BlockType;
use MailFrame\Tone;

/** A quoted block. A string keeps its lines. */
final class Panel implements Block
{
    public BlockType $type {
        get => BlockType::panel;
    }

    public function __construct(public readonly string|Htmlable $text, public readonly Tone $tone) {}
}
