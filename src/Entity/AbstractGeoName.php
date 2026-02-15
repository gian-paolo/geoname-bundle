<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\Index(columns: ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code'], name: 'idx_admin_hierarchy')]
abstract class AbstractGeoName
{
    #[ORM\Id]
    #[ORM\Column(name: 'geonameid', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(length: 200)]
    protected ?string $name = null;

    #[ORM\Column(name: 'ascii_name', length: 200, options: ['charset' => 'ascii'])]
    protected ?string $asciiName = null;

    #[ORM\Column(name: 'alternate_names', type: Types::TEXT, length: 10000, nullable: true)]
    protected ?string $alternatenames = null;

    #[ORM\Column(type: Types::FLOAT)]
    protected ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT)]
    protected ?float $longitude = null;

    #[ORM\Column(name: 'feature_class', length: 1, nullable: true, options: ['fixed' => true, 'charset' => 'ascii'])]
    protected ?string $featureClass = null;

    #[ORM\Column(name: 'feature_code', length: 10, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $featureCode = null;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true, options: ['fixed' => true, 'charset' => 'ascii'])]
    protected ?string $countryCode = null;

    #[ORM\Column(name: 'admin1_code', length: 20, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $admin1Code = null;

    #[ORM\Column(name: 'admin2_code', length: 80, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $admin2Code = null;

    #[ORM\Column(name: 'admin3_code', length: 20, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $admin3Code = null;

    #[ORM\Column(name: 'admin4_code', length: 20, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $admin4Code = null;

    #[ORM\Column(name: 'admin5_code', length: 20, nullable: true, options: ['charset' => 'ascii'])]
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

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }
    public function getAsciiName(): ?string { return $this->asciiName; }
    public function setAsciiName(?string $asciiName): self { $this->asciiName = $asciiName; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }
    public function getFeatureClass(): ?string { return $this->featureClass; }
    public function setFeatureClass(?string $featureClass): self { $this->featureClass = $featureClass; return $this; }
    public function getFeatureCode(): ?string { return $this->featureCode; }
    public function setFeatureCode(?string $featureCode): self { $this->featureCode = $featureCode; return $this; }
    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): self { $this->countryCode = $countryCode; return $this; }
    public function getAdmin1Code(): ?string { return $this->admin1Code; }
    public function setAdmin1Code(?string $admin1Code): self { $this->admin1Code = $admin1Code; return $this; }
    public function getAdmin2Code(): ?string { return $this->admin2Code; }
    public function setAdmin2Code(?string $admin2Code): self { $this->admin2Code = $admin2Code; return $this; }
    public function getAdmin3Code(): ?string { return $this->admin3Code; }
    public function setAdmin3Code(?string $admin3Code): self { $this->admin3Code = $admin3Code; return $this; }
    public function getAdmin4Code(): ?string { return $this->admin4Code; }
    public function setAdmin4Code(?string $admin4Code): self { $this->admin4Code = $admin4Code; return $this; }
    public function getAdmin5Code(): ?string { return $this->admin5Code; }
    public function setAdmin5Code(?string $admin5Code): self { $this->admin5Code = $admin5Code; return $this; }
}
