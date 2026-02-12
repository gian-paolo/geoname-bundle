<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoCountry
{
    #[ORM\Id]
    #[ORM\Column(length: 2)]
    protected ?string $code = null; // ISO 3166-1 alpha-2 (e.g., IT, FR)

    #[ORM\Column(length: 100)]
    protected ?string $name = null;

    #[ORM\Column(name: 'is_enabled')]
    protected bool $isEnabled = true;

    #[ORM\Column(name: 'last_imported_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $lastImportedAt = null;

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = strtoupper($code); return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $isEnabled): self { $this->isEnabled = $isEnabled; return $this; }

    public function getLastImportedAt(): ?\DateTimeInterface { return $this->lastImportedAt; }
    public function setLastImportedAt(?\DateTimeInterface $lastImportedAt): self { $this->lastImportedAt = $lastImportedAt; return $this; }
}
