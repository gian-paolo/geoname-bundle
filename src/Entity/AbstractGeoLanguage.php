<?php

namespace Gpp\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoLanguage
{
    #[ORM\Id]
    #[ORM\Column(length: 7)]
    protected ?string $code = null; // ISO 639-1 or 639-3 (e.g., 'it', 'en', 'fr')

    #[ORM\Column(length: 100)]
    protected ?string $name = null;

    #[ORM\Column(name: 'is_enabled')]
    protected bool $isEnabled = true;

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = strtolower($code); return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $isEnabled): self { $this->isEnabled = $isEnabled; return $this; }
}
