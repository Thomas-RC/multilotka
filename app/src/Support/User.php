<?php

declare(strict_types=1);

namespace Multilotka\Support;

use DateTimeImmutable;

final class User
{
    public function __construct(
        private ?int $id,
        private string $firstName,
        private string $lastName,
        private string $email,
        private string $passwordHash,
        private ?string $confirmationToken,
        private ?DateTimeImmutable $confirmedAt,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $lastLoginAt,
    ) {
    }

    public static function register(
        string $firstName,
        string $lastName,
        string $email,
        string $passwordHash,
        string $confirmationToken,
        DateTimeImmutable $now,
    ): self {
        return new self(
            null,
            $firstName,
            $lastName,
            $email,
            $passwordHash,
            $confirmationToken,
            null,
            $now,
            $now,
            null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int) $data['id'] : null,
            (string) $data['first_name'],
            (string) $data['last_name'],
            (string) $data['email'],
            (string) $data['password_hash'],
            $data['confirmation_token'] !== null ? (string) $data['confirmation_token'] : null,
            isset($data['confirmed_at']) && $data['confirmed_at'] !== null
                ? new DateTimeImmutable((string) $data['confirmed_at'])
                : null,
            new DateTimeImmutable((string) $data['created_at']),
            new DateTimeImmutable((string) $data['updated_at']),
            isset($data['last_login_at']) && $data['last_login_at'] !== null
                ? new DateTimeImmutable((string) $data['last_login_at'])
                : null,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function confirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }

    public function passwordMatches(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function confirm(DateTimeImmutable $confirmedAt): self
    {
        $clone = clone $this;
        $clone->confirmedAt = $confirmedAt;
        $clone->confirmationToken = null;
        $clone->updatedAt = $confirmedAt;

        return $clone;
    }

    public function withLastLogin(DateTimeImmutable $lastLoginAt): self
    {
        $clone = clone $this;
        $clone->lastLoginAt = $lastLoginAt;
        $clone->updatedAt = $lastLoginAt;

        return $clone;
    }

    public function withUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $clone = clone $this;
        $clone->updatedAt = $updatedAt;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'confirmation_token' => $this->confirmationToken,
            'confirmed_at' => $this->confirmedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
        ];
    }
}
