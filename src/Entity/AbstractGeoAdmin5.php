<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoAdmin5
{
    #[ORM\Id]
    #[ORM\Column(name: 'country_code', length: 2, options: ['fixed' => true, 'charset' => 'ascii'])]
    protected ?string $countryCode = null;

    #[ORM\Id]
    #[ORM\Column(name: 'admin1_code', length: 20, options: ['charset' => 'ascii'])]
    protected ?string $admin1Code = null;

    #[ORM\Id]
    #[ORM\Column(name: 'admin2_code', length: 80, options: ['charset' => 'ascii'])]
    protected ?string $admin2Code = null;

    #[ORM\Id]
    #[ORM\Column(name: 'admin3_code', length: 20, options: ['charset' => 'ascii'])]
    protected ?string $admin3Code = null;

    #[ORM\Id]
    #[ORM\Column(name: 'admin4_code', length: 20, options: ['charset' => 'ascii'])]
    protected ?string $admin4Code = null;

    #[ORM\Id]
    #[ORM\Column(name: 'admin5_code', length: 20, options: ['charset' => 'ascii'])]
    protected ?string $admin5Code = null;

    #[ORM\Column(length: 200)]
    protected ?string $name = null;

    #[ORM\Column(name: 'ascii_name', length: 200, nullable: true, options: ['charset' => 'ascii'])]
    protected ?string $asciiName = null;

    #[ORM\Column(name: 'geonameid', type: 'integer', nullable: true)]
    protected ?int $geonameId = null;

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(string $countryCode): self { $this->countryCode = $countryCode; return $this; }
    public function getAdmin1Code(): ?string { return $this->admin1Code; }
    public function setAdmin1Code(string $admin1Code): self { $this->admin1Code = $admin1Code; return $this; }
    public function getAdmin2Code(): ?string { return $this->admin2Code; }
    public function setAdmin2Code(string $admin2Code): self { $this->admin2Code = $admin2Code; return $this; }
    public function getAdmin3Code(): ?string { return $this->admin3Code; }
    public function setAdmin3Code(string $admin3Code): self { $this->admin3Code = $admin3Code; return $this; }
    public function getAdmin4Code(): ?string { return $this->admin4Code; }
    public function setAdmin4Code(string $admin4Code): self { $this->admin4Code = $admin4Code; return $this; }
    public function getAdmin5Code(): ?string { return $this->admin5Code; }
    public function setAdmin5Code(string $admin5Code): self { $this->admin5Code = $admin5Code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getAsciiName(): ?string { return $this->asciiName; }
    public function setAsciiName(?string $asciiName): self { $this->asciiName = $asciiName; return $this; }
    public function getGeonameId(): ?int { return $this->geonameId; }
    public function setGeonameId(?int $geonameId): self { $this->geonameId = $geonameId; return $this; }
}
