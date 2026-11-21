<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use MailFrame\BlockType;

/** A body block in Message::$blocks. Each class is one type, and `type` names it. */
interface Block
{
    public BlockType $type { get; }
}
