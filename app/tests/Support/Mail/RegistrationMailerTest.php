<?php

declare(strict_types=1);

namespace Multilotka\Tests\Support\Mail;

use Multilotka\Support\Mail\RegistrationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;

final class RegistrationMailerTest extends TestCase
{
    public function testSendConfirmationBuildsProperEmail(): void
    {
        $collector = new class implements MailerInterface {
            public ?Email $lastMessage = null;

            public function send(RawMessage $message, Envelope $envelope = null): void
            {
                if (!$message instanceof Email) {
                    return;
                }

                $this->lastMessage = $message;
            }
        };

        $mailer = new RegistrationMailer(
            $collector,
            'no-reply@example.com',
            'https://app.test'
        );

        $mailer->sendConfirmation('user@example.com', 'token-123', 'Jan Kowalski');

        $email = $collector->lastMessage;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('user@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('no-reply@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Potwierdź swoją rejestrację w Multilotka', $email->getSubject());

        $expectedUrl = 'https://app.test/register/confirm?token=token-123';
        self::assertStringContainsString($expectedUrl, $email->getHtmlBody());
        self::assertStringContainsString($expectedUrl, $email->getTextBody());
        self::assertStringContainsString('Jan Kowalski', $email->getTextBody());
    }
}
