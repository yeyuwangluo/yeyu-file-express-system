<?php

namespace App\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;

class ConfigurePhpErrorReporting
{
    public function bootstrap(Application $app): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }
}
