<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoAlternateName
{
    #[ORM\Id]
    #[ORM\Column(name: 'alternatenameid', type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(name: 'geonameid', type: 'integer')]
    protected ?int $geonameId = null;

    #[ORM\Column(length: 7, nullable: true)]
    protected ?string $isoLanguage = null;

    #[ORM\Column(type: 'text', length: 10000)]
    protected ?string $alternateName = null;

    #[ORM\Column]
    protected bool $isPreferredName = false;

    #[ORM\Column]
    protected bool $isShortName = false;

    #[ORM\Column]
    protected bool $isColloquial = false;

    #[ORM\Column]
    protected bool $isHistoric = false;

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }
    public function getGeonameId(): ?int { return $this->geonameId; }
    public function setGeonameId(int $geonameId): self { $this->geonameId = $geonameId; return $this; }
    public function getIsoLanguage(): ?string { return $this->isoLanguage; }
    public function setIsoLanguage(?string $isoLanguage): self { $this->isoLanguage = $isoLanguage; return $this; }
    public function getAlternateName(): ?string { return $this->alternateName; }
    public function setAlternateName(string $alternateName): self { $this->alternateName = $alternateName; return $this; }
    public function isPreferredName(): bool { return $this->isPreferredName; }
    public function setIsPreferredName(bool $isPreferredName): self { $this->isPreferredName = $isPreferredName; return $this; }
    public function isShortName(): bool { return $this->isShortName; }
    public function setIsShortName(bool $isShortName): self { $this->isShortName = $isShortName; return $this; }
    public function isColloquial(): bool { return $this->isColloquial; }
    public function setIsColloquial(bool $isColloquial): self { $this->isColloquial = $isColloquial; return $this; }
    public function isHistoric(): bool { return $this->isHistoric; }
    public function setIsHistoric(bool $isHistoric): self { $this->isHistoric = $isHistoric; return $this; }
}
