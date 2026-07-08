<?php

namespace App\Models;

class News
{
    private int $id;
    private string $title;
    private string $body;
    private string $authorId;
    private string $createdAt;
    private ?string $authorUsername;
    private ?string $authorFirstName;

    public function __construct(
        ?int $id = null,
        string $title = '',
        string $body = '',
        ?string $authorId = null,
        ?string $createdAt = null,
        ?string $authorUsername = null,
        ?string $authorFirstName = null
    ) {
        $this->id = $id ?? 0;
        $this->title = $title;
        $this->body = $body;
        $this->authorId = $authorId ?? '';
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->authorUsername = $authorUsername;
        $this->authorFirstName = $authorFirstName;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getAuthorUsername(): ?string
    {
        return $this->authorUsername;
    }

    public function getAuthorFirstName(): ?string
    {
        return $this->authorFirstName;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['title'] ?? '',
            $data['body'] ?? '',
            $data['author_id'] ?? null,
            $data['created_at'] ?? null,
            $data['author_username'] ?? null,
            $data['author_first_name'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'author_id' => $this->authorId,
            'created_at' => $this->createdAt,
            'author_username' => $this->authorUsername,
            'author_first_name' => $this->authorFirstName,
        ];
    }
}
