<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoAdmin2
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
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getGeonameId(): ?int { return $this->geonameId; }
    public function setGeonameId(?int $geonameId): self { $this->geonameId = $geonameId; return $this; }
}
