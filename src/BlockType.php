<?php

declare(strict_types=1);

namespace MailFrame;

/** What a body block in Message::$blocks prints. The block holds the value, so a test can read it as a string. */
enum BlockType: string
{
    case line = 'line';
    case bullets = 'bullets';
    case facts = 'facts';
    case panel = 'panel';
    case action = 'action';
}
