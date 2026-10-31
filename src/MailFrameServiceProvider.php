<?php

declare(strict_types=1);

namespace MailFrame;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MailFrame\Listeners\AddPlainTextPart;

/** @internal */
class MailFrameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__ . '/../config/mail-frame.php', 'mail-frame');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mail-frame');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'mail-frame');

        $this->publishes([__DIR__ . '/../config/mail-frame.php' => config_path('mail-frame.php')], 'mail-frame-config');

        Event::listen(MessageSending::class, AddPlainTextPart::class);
    }
}
