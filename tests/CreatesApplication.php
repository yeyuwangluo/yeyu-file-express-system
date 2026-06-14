<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        putenv('APP_ROUTES_CACHE='.dirname(__DIR__).'/storage/framework/testing/routes-v7.php');
        $_ENV['APP_ROUTES_CACHE'] = dirname(__DIR__).'/storage/framework/testing/routes-v7.php';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
