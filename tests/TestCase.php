<?php

declare(strict_types=1);

namespace MailFrame\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifications;
use MailFrame\MailFrameServiceProvider;
use MailFrame\Message;
use MailFrame\Tests\Fixtures\MailTone;
use Orchestra\Testbench\TestCase as Testbench;
use Symfony\Component\Mime\Email;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [MailFrameServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Acme');
        $app['config']->set('mail.default', 'array');
    }

    protected function message(): Message
    {
        return new Message(MailTone::routine);
    }

    protected function html(Message $message): string
    {
        return (string)$message->render();
    }

    // The array mailer, not Mail::fake(): a fake never fires MessageSending, where the text part is made.
    protected function send(Message $message, ?string $locale = null): Email
    {
        Notifications::route('mail', 'pat@example.test')->notify(new class($message) extends Notification
        {
            public function __construct(private Message $message) {}

            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): Message
            {
                return $this->message;
            }
        }->locale($locale));

        return Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    }
}
