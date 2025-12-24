<?php

namespace Daugt\Commerce;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{

    protected $vite = [
        'input' => [
          'resources/js/addon.js',
          'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register() {
        parent::register();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/statamic/daugt-commerce.php',
            'statamic.daugt-commerce'
        );
    }

    public function boot() {
        parent::boot();
    }

    public function bootAddon()
    {
        parent::bootAddon();
        $this->publishes([
            __DIR__ . '/../config/statamic/daugt-commerce.php' => config_path('statamic/daugt-commerce.php'),
        ], 'daugt-commerce-config');
    }
}
