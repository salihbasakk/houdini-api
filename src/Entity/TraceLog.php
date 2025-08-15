<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: 'App\Repository\TraceLogRepository')]
#[ORM\Table(name: 'trace_logs')]
class TraceLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $projectId = null;

    #[ORM\Column(type: Types::JSON)]
    private ?array $telemetryData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function setProjectId(string $projectId): static
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function getTelemetryData(): ?array
    {
        return $this->telemetryData;
    }

    public function setTelemetryData(?array $telemetryData): static
    {
        $this->telemetryData = $telemetryData;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
