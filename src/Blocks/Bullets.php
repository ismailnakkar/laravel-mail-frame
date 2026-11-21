<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use Illuminate\Contracts\Support\Htmlable;
use MailFrame\BlockType;

/** A list. */
final class Bullets implements Block
{
    public BlockType $type {
        get => BlockType::bullets;
    }

    /** @param  list<string|Htmlable>  $items */
    public function __construct(public readonly array $items) {}
}
