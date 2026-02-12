<?php

namespace Pallari\GeonameBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PallariGeonameBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
