<?php

declare(strict_types=1);

namespace MailFrame;

/** An app's enum of mail tones. Hex literals: mail clients cannot read CSS custom properties. */
interface Tone
{
    /** The line across the top of the card, and the button. */
    public function accent(): string;

    /** A panel's background. */
    public function tint(): string;

    /** A panel's border. */
    public function border(): string;
}
