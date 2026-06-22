<?php

return [
    App\Providers\AppServiceProvider::class,
    // TelescopeServiceProvider is registered conditionally (local only) in
    // AppServiceProvider::register(). Registering it here would fatal under a
    // production `composer install --no-dev` build, where laravel/telescope
    // (a dev dependency) is absent.
];
