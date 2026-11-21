<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use Illuminate\Contracts\Support\Htmlable;
use MailFrame\BlockType;

/** A paragraph. */
final class Line implements Block
{
    public BlockType $type {
        get => BlockType::line;
    }

    public function __construct(public readonly string|Htmlable $text) {}
}
