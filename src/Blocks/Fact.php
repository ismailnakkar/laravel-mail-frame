<?php

declare(strict_types=1);

namespace MailFrame\Blocks;

use Illuminate\Contracts\Support\Htmlable;

/** One row of a Facts block. The value prints as Blade would print it. */
final readonly class Fact
{
    public function __construct(public string|Htmlable $label, public mixed $value) {}
}
