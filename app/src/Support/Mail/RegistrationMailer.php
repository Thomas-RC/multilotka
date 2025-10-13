<?php

declare(strict_types=1);

namespace Multilotka\Support\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class RegistrationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
        private readonly string $appUrl,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendConfirmation(string $recipient, string $token, string $fullName): void
    {
        $confirmationUrl = rtrim($this->appUrl, '/') . '/register/confirm?token=' . urlencode($token);

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipient)
            ->subject('Potwierdź swoją rejestrację w Multilotka')
            ->html($this->renderHtmlBody($fullName, $confirmationUrl))
            ->text($this->renderTextBody($fullName, $confirmationUrl));

        $this->mailer->send($email);
    }

    private function renderHtmlBody(string $fullName, string $url): string
    {
        return <<<HTML
<p>Cześć {$fullName},</p>
<p>Dziękujemy za założenie konta w Multilotka. Aby dokończyć rejestrację, potwierdź adres e-mail, klikając poniższy przycisk:</p>
<p>
    <a href="{$url}" style="display:inline-block;padding:12px 18px;background-color:#a3e635;color:#111827;text-decoration:none;border-radius:8px;font-weight:600;">
        Potwierdź rejestrację
    </a>
</p>
<p>Jeżeli przycisk nie działa, skopiuj do przeglądarki ten adres:</p>
<p><a href="{$url}">{$url}</a></p>
<p>Do zobaczenia w panelu Multilotka!</p>
HTML;
    }

    private function renderTextBody(string $fullName, string $url): string
    {
        return <<<TEXT
Cześć {$fullName},

Dziękujemy za założenie konta w Multilotka. Skorzystaj z linku poniżej, aby potwierdzić adres e-mail:
{$url}

Do zobaczenia w panelu Multilotka!
TEXT;
    }
}
