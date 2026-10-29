<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    App\Providers\StagingIsolationProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    App\Providers\IdentityServiceProvider::class,
];
