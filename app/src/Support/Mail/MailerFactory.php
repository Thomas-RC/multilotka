<?php

declare(strict_types=1);

namespace Multilotka\Support\Mail;

use Multilotka\Core\Environment;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

final class MailerFactory
{
    public static function create(): MailerInterface
    {
        Environment::bootstrap();

        $dsn = (string) Environment::get('MAILER_DSN', 'smtp://mailpit:1025');
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }
}
