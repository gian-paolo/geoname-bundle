<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoAdmin2
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    protected ?string $code = null; // e.g., IT.09.TO

    #[ORM\Column(length: 200)]
    protected ?string $name = null;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $asciiname = null;

    #[ORM\Column(name: 'geonameid', type: 'integer', nullable: true)]
    protected ?int $geonameId = null;

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getGeonameId(): ?int { return $this->geonameId; }
    public function setGeonameId(?int $geonameId): self { $this->geonameId = $geonameId; return $this; }
}
