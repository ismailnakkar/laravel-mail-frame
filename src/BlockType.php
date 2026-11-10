<?php

declare(strict_types=1);

namespace MailFrame;

/** What a body block in Message::$blocks prints: the block's `type` is the case. */
enum BlockType: string
{
    case line = 'line';
    case bullets = 'bullets';
    case facts = 'facts';
    case panel = 'panel';
    case action = 'action';
}
