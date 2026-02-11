<?php

namespace Gpp\GeonameBundle\Tests\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gpp\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {}
