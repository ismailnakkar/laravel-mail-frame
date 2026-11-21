<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use MailFrame\BlockType;

/** Ruled label and value rows. */
final class Facts implements Block
{
    public BlockType $type {
        get => BlockType::facts;
    }

    /** @param  list<Fact>  $rows */
    public function __construct(public readonly array $rows) {}
}
