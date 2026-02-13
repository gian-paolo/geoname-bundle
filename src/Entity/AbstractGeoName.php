<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoName
{
    #[ORM\Id]
    #[ORM\Column(name: 'geonameid', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(length: 200)]
    protected ?string $name = null;

    #[ORM\Column(name: 'ascii_name', length: 200)]
    protected ?string $asciiname = null;

    #[ORM\Column(name: 'alternate_names', type: Types::TEXT, length: 10000, nullable: true)]
    protected ?string $alternatenames = null;

    #[ORM\Column(type: Types::FLOAT)]
    protected ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT)]
    protected ?float $longitude = null;

    #[ORM\Column(name: 'feature_class', length: 1, nullable: true, options: ['fixed' => true])]
    protected ?string $featureClass = null;

    #[ORM\Column(name: 'feature_code', length: 10, nullable: true)]
    protected ?string $featureCode = null;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true, options: ['fixed' => true])]
    protected ?string $countryCode = null;

    #[ORM\Column(name: 'admin1_code', length: 20, nullable: true)]
    protected ?string $admin1Code = null;

    #[ORM\Column(name: 'admin2_code', length: 80, nullable: true)]
    protected ?string $admin2Code = null;

    #[ORM\Column(name: 'admin3_code', length: 20, nullable: true)]
    protected ?string $admin3Code = null;

    #[ORM\Column(name: 'admin4_code', length: 20, nullable: true)]
    protected ?string $admin4Code = null;

    #[ORM\Column(name: 'admin5_code', length: 20, nullable: true)]
    protected ?string $admin5Code = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    protected ?string $population = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $elevation = null;

    #[ORM\Column(length: 40, nullable: true)]
    protected ?string $timezone = null;

    #[ORM\Column(name: 'modification_date', type: Types::DATE_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $modificationDate = null;

    #[ORM\Column(name: 'is_deleted')]
    protected bool $isDeleted = false;

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
