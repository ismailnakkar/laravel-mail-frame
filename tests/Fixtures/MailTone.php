<?php

declare(strict_types=1);

namespace MailFrame\Tests\Fixtures;

use MailFrame\Tone;

enum MailTone implements Tone
{
    case routine;

    case action;

    case security;

    public function accent(): string
    {
        return match ($this) {
            self::routine  => '#1b45f5',
            self::action   => '#7d5200',
            self::security => '#b91c33',
        };
    }

    public function tint(): string
    {
        return match ($this) {
            self::routine  => '#f2f6fd',
            self::action   => '#fdf3e0',
            self::security => '#fdeaec',
        };
    }

    public function border(): string
    {
        return match ($this) {
            self::routine  => '#e2e6f7',
            self::action   => '#d4a954',
            self::security => '#e3939f',
        };
    }
}
