<?php

namespace Gpp\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoHierarchy
{
    #[ORM\Id]
    #[ORM\Column(name: 'parentid', type: 'integer')]
    protected ?int $parentId = null;

    #[ORM\Id]
    #[ORM\Column(name: 'childid', type: 'integer')]
    protected ?int $childId = null;

    #[ORM\Column(length: 20, nullable: true)]
    protected ?string $type = null;

    public function getParentId(): ?int { return $this->parentId; }
    public function setParentId(int $parentId): self { $this->parentId = $parentId; return $this; }
    public function getChildId(): ?int { return $this->childId; }
    public function setChildId(int $childId): self { $this->childId = $childId; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }
}
