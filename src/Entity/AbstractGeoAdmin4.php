<?php

namespace Pallari\GeonameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGeoAdmin4 extends AbstractGeoAdmin1
{
    // Inherits code, name, asciiname, geonameid from AbstractGeoAdmin1
}
