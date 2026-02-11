<?php

namespace Gpp\GeonameBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class GppGeonameBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
