<?php

declare(strict_types=1);

namespace Multilotka\Support;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

final class UserRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws DBALException
     */
    public function create(User $user): User
    {
        $payload = $user->toDatabasePayload();
        $this->connection->insert('users', $payload);

        $id = (int) $this->connection->lastInsertId();

        return $user->withId($id);
    }

    /**
     * @throws DBALException
     */
    public function findByEmail(string $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => $email],
        );

        return $row !== false ? User::fromArray($row) : null;
    }

    /**
     * @throws DBALException
     */
    public function findByConfirmationToken(string $token): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE confirmation_token = :token LIMIT 1',
            ['token' => $token],
        );

        return $row !== false ? User::fromArray($row) : null;
    }

    /**
     * @throws DBALException
     */
    public function findById(int $id): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $id],
        );

        return $row !== false ? User::fromArray($row) : null;
    }

    /**
     * @throws DBALException
     */
    public function confirm(User $user, DateTimeImmutable $confirmedAt): User
    {
        $confirmedUser = $user->confirm($confirmedAt);

        $this->connection->update(
            'users',
            [
                'confirmed_at' => $confirmedUser->confirmedAt()?->format('Y-m-d H:i:s'),
                'confirmation_token' => $confirmedUser->confirmationToken(),
                'updated_at' => $confirmedUser->updatedAt()->format('Y-m-d H:i:s'),
            ],
            ['id' => $confirmedUser->id()],
        );

        return $confirmedUser;
    }

    /**
     * @throws DBALException
     */
    public function recordLogin(User $user, DateTimeImmutable $loginAt): User
    {
        $updatedUser = $user->withLastLogin($loginAt);

        $this->connection->update(
            'users',
            [
                'last_login_at' => $updatedUser->lastLoginAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $updatedUser->updatedAt()->format('Y-m-d H:i:s'),
            ],
            ['id' => $updatedUser->id()],
        );

        return $updatedUser;
    }
}
