<?php

namespace App\Filesystem;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use App\Services\Netdisk123Service;

class Netdisk123ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Storage::extend('netdisk123', function ($app, $config) {
            $service = new Netdisk123Service();
            return new Netdisk123FilesystemAdapter($service);
        });
    }
}
