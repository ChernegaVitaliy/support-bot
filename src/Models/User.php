<?php

namespace App\Models;

class User
{
    private string $userId;
    private ?string $username;
    private ?string $firstName;
    private string $language;
    private string $createdAt;

    public function __construct(
        string $userId,
        ?string $username = null,
        ?string $firstName = null,
        string $language = 'uk',
        ?string $createdAt = null
    ) {
        $this->userId = $userId;
        $this->username = $username;
        $this->firstName = $firstName;
        $this->language = $language;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getDisplayName(): string
    {
        if ($this->firstName) {
            return $this->firstName;
        }
        if ($this->username) {
            return "@{$this->username}";
        }
        return $this->userId;
    }

    public function getMention(): string
    {
        if ($this->username) {
            return "@{$this->username}";
        }
        return $this->firstName ?? $this->userId;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['user_id'],
            $data['username'] ?? null,
            $data['first_name'] ?? null,
            $data['language'] ?? 'uk',
            $data['created_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'first_name' => $this->firstName,
            'language' => $this->language,
            'created_at' => $this->createdAt
        ];
    }
}
