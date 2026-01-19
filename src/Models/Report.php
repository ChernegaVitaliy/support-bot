<?php

namespace App\Models;

class Report
{
    private int $id;
    private string $userId;
    private string $reporterNick;
    private string $reportedNick;
    private string $reason;
    private ?string $proof;
    private string $proofType;
    private ?string $fileId;
    private string $status;
    private string $createdAt;
    private ?string $adminNotes;
    private ?string $processedBy;
    private ?string $processedAt;

    private const STATUSES = ['pending', 'accepted', 'rejected'];
    private const PROOF_TYPES = ['text', 'photo', 'video', 'document'];

    public function __construct(
        ?int $id = null,
        string $userId = '',
        string $reporterNick = '',
        string $reportedNick = '',
        string $reason = '',
        ?string $proof = null,
        string $proofType = 'text',
        ?string $fileId = null,
        string $status = 'pending',
        ?string $createdAt = null,
        ?string $adminNotes = null,
        ?string $processedBy = null,
        ?string $processedAt = null
    ) {
        $this->id = $id ?? 0;
        $this->userId = $userId;
        $this->reporterNick = $reporterNick;
        $this->reportedNick = $reportedNick;
        $this->reason = $reason;
        $this->proof = $proof;
        $this->proofType = in_array($proofType, self::PROOF_TYPES) ? $proofType : 'text';
        $this->fileId = $fileId;
        $this->status = in_array($status, self::STATUSES) ? $status : 'pending';
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->adminNotes = $adminNotes;
        $this->processedBy = $processedBy;
        $this->processedAt = $processedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getReporterNick(): string
    {
        return $this->reporterNick;
    }

    public function setReporterNick(string $reporterNick): void
    {
        $this->reporterNick = $reporterNick;
    }

    public function getReportedNick(): string
    {
        return $this->reportedNick;
    }

    public function setReportedNick(string $reportedNick): void
    {
        $this->reportedNick = $reportedNick;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getProof(): ?string
    {
        return $this->proof;
    }

    public function setProof(?string $proof): void
    {
        $this->proof = $proof;
    }

    public function getProofType(): string
    {
        return $this->proofType;
    }

    public function setProofType(string $proofType): void
    {
        if (in_array($proofType, self::PROOF_TYPES)) {
            $this->proofType = $proofType;
        }
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function setFileId(?string $fileId): void
    {
        $this->fileId = $fileId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (in_array($status, self::STATUSES)) {
            $this->status = $status;
        }
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): void
    {
        $this->adminNotes = $adminNotes;
    }

    public function getProcessedBy(): ?string
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?string $processedBy): void
    {
        $this->processedBy = $processedBy;
    }

    public function getProcessedAt(): ?string
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?string $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isProcessed(): bool
    {
        return !$this->isPending();
    }

    public function getStatusIcon(): string
    {
        return match($this->status) {
            'pending' => '⏳',
            'accepted' => '✅',
            'rejected' => '❌',
            default => '❓'
        };
    }

    public function getStatusText(): string
    {
        return match($this->status) {
            'pending' => 'Очікує',
            'accepted' => 'Прийнято',
            'rejected' => 'Відхилено',
            default => 'Невідомо'
        };
    }

    public function getProofTypeIcon(): string
    {
        return match($this->proofType) {
            'text' => '📝',
            'photo' => '📷',
            'video' => '🎥',
            'document' => '📎',
            default => '📄'
        };
    }

    public function hasMedia(): bool
    {
        return in_array($this->proofType, ['photo', 'video', 'document']) && !empty($this->fileId);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['user_id'] ?? '',
            $data['reporter_nick'] ?? '',
            $data['reported_nick'] ?? '',
            $data['reason'] ?? '',
            $data['proof'] ?? null,
            $data['proof_type'] ?? 'text',
            $data['file_id'] ?? null,
            $data['status'] ?? 'pending',
            $data['created_at'] ?? null,
            $data['admin_notes'] ?? null,
            $data['processed_by'] ?? null,
            $data['processed_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'reporter_nick' => $this->reporterNick,
            'reported_nick' => $this->reportedNick,
            'reason' => $this->reason,
            'proof' => $this->proof,
            'proof_type' => $this->proofType,
            'file_id' => $this->fileId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'admin_notes' => $this->adminNotes,
            'processed_by' => $this->processedBy,
            'processed_at' => $this->processedAt
        ];
    }

    public static function getValidStatuses(): array
    {
        return self::STATUSES;
    }

    public static function getValidProofTypes(): array
    {
        return self::PROOF_TYPES;
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    public static function isValidProofType(string $proofType): bool
    {
        return in_array($proofType, self::PROOF_TYPES);
    }
}
