<?php

declare(strict_types=1);

namespace Multilotka\Tests\Support;

use DateTimeImmutable;
use Multilotka\Support\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterInitializesUnconfirmedUser(): void
    {
        $now = new DateTimeImmutable('2024-09-05 12:00:00');
        $user = User::register(
            'Jan',
            'Kowalski',
            'jan.kowalski@example.com',
            password_hash('SecretPass123', PASSWORD_DEFAULT),
            'token123',
            $now,
        );

        self::assertNull($user->id());
        self::assertFalse($user->isConfirmed());
        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
        self::assertSame('jan.kowalski@example.com', $user->email());
        self::assertSame('token123', $user->confirmationToken());
        self::assertSame($now, $user->createdAt());
        self::assertSame($now, $user->updatedAt());
    }

    public function testConfirmationClearsTokenAndSetsTimestamp(): void
    {
        $now = new DateTimeImmutable('2024-09-05 12:00:00');
        $user = User::register(
            'Jan',
            'Kowalski',
            'jan.kowalski@example.com',
            password_hash('SecretPass123', PASSWORD_DEFAULT),
            'token123',
            $now,
        )->withId(10);

        $confirmedAt = new DateTimeImmutable('2024-09-06 10:15:00');
        $confirmed = $user->confirm($confirmedAt);

        self::assertTrue($confirmed->isConfirmed());
        self::assertNull($confirmed->confirmationToken());
        self::assertEquals($confirmedAt, $confirmed->confirmedAt());
        self::assertEquals($confirmedAt, $confirmed->updatedAt());
        self::assertSame(10, $confirmed->id());
    }

    public function testToDatabasePayloadMatchesColumns(): void
    {
        $now = new DateTimeImmutable('2024-09-05 12:00:00');
        $user = User::register(
            'Jan',
            'Kowalski',
            'jan.kowalski@example.com',
            password_hash('SecretPass123', PASSWORD_DEFAULT),
            'token123',
            $now,
        );

        $payload = $user->toDatabasePayload();

        self::assertArrayHasKey('first_name', $payload);
        self::assertArrayHasKey('last_name', $payload);
        self::assertArrayHasKey('email', $payload);
        self::assertArrayHasKey('password_hash', $payload);
        self::assertArrayHasKey('confirmation_token', $payload);
        self::assertArrayHasKey('confirmed_at', $payload);
        self::assertArrayHasKey('created_at', $payload);
        self::assertArrayHasKey('updated_at', $payload);
        self::assertArrayHasKey('last_login_at', $payload);
        self::assertSame('Jan', $payload['first_name']);
        self::assertSame('Kowalski', $payload['last_name']);
        self::assertSame('jan.kowalski@example.com', $payload['email']);
        self::assertNull($payload['confirmed_at']);
        self::assertNull($payload['last_login_at']);
    }
}
