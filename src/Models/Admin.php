<?php

namespace App\Models;

class Admin
{
    private string $userId;
    private ?string $username;
    private ?string $firstName;
    private string $rank;
    private string $addedAt;

    private const RANKS = ['owner', 'admin', 'moderator'];

    public function __construct(
        string $userId,
        ?string $username = null,
        ?string $firstName = null,
        string $rank = 'moderator',
        ?string $addedAt = null
    ) {
        $this->userId = $userId;
        $this->username = $username;
        $this->firstName = $firstName;
        $this->rank = in_array($rank, self::RANKS) ? $rank : 'moderator';
        $this->addedAt = $addedAt ?? date('Y-m-d H:i:s');
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

    public function getRank(): string
    {
        return $this->rank;
    }

    public function setRank(string $rank): void
    {
        if (in_array($rank, self::RANKS)) {
            $this->rank = $rank;
        }
    }

    public function getAddedAt(): string
    {
        return $this->addedAt;
    }

    public function getRankLevel(): int
    {
        return match($this->rank) {
            'owner' => 3,
            'admin' => 2,
            'moderator' => 1,
            default => 0
        };
    }

    public function hasPermission(string $requiredRank): bool
    {
        $requiredLevel = match($requiredRank) {
            'owner' => 3,
            'admin' => 2,
            'moderator' => 1,
            default => 0
        };

        return $this->getRankLevel() >= $requiredLevel;
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

    public function getRankIcon(): string
    {
        return match($this->rank) {
            'owner' => '👑',
            'admin' => '🛡️',
            'moderator' => '🔧',
            default => '❓'
        };
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['user_id'],
            $data['username'] ?? null,
            $data['first_name'] ?? null,
            $data['rank'] ?? 'moderator',
            $data['added_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'first_name' => $this->firstName,
            'rank' => $this->rank,
            'added_at' => $this->addedAt
        ];
    }

    public static function getValidRanks(): array
    {
        return self::RANKS;
    }

    public static function isValidRank(string $rank): bool
    {
        return in_array($rank, self::RANKS);
    }
}
