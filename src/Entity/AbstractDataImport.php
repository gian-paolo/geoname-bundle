<?php

namespace Gpp\GeonameBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractDataImport
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 100)]
    protected ?string $type = null;

    #[ORM\Column(length: 20)]
    protected ?string $status = null;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_MUTABLE)]
    protected ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: 'ended_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(name: 'records_processed', type: Types::INTEGER)]
    protected int $recordsProcessed = 0;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    protected ?string $errorMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $details = null;

    public function __construct()
    {
        $this->startedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function getEndedAt(): ?\DateTimeInterface { return $this->endedAt; }
    public function setEndedAt(?\DateTimeInterface $endedAt): self { $this->endedAt = $endedAt; return $this; }
    public function getRecordsProcessed(): int { return $this->recordsProcessed; }
    public function setRecordsProcessed(int $recordsProcessed): self { $this->recordsProcessed = $recordsProcessed; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): self { $this->errorMessage = $errorMessage; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): self { $this->details = $details; return $this; }
}
