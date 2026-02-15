<?php

namespace Pallari\GeonameBundle\Tests\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {}
