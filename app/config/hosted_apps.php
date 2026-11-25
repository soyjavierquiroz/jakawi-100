<?php
return [
    'secret' => env('HOSTED_APP_SECRET'),
    // Explicit registry: never accept an arbitrary URL or redirect from a product.
    'apps' => ['gluteos' => ['entry' => 'https://jakawi.store/gluteos/_entry', 'health' => 'https://jakawi.store/gluteos/_health']],
];
