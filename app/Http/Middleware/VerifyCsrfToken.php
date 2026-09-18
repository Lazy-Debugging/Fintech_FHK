<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'app/fhk/callback',
        'app/fhk/callback/*',
        'callback',
        'callback/*',
        'api/aiyo/callback',
        'api/aiyo/callback/*',
        'api/kiosk/*',
        'api/iot/*',
        'kiosk/order',
        'app/fhk/kiosk/order',
        'fhk/kiosk/order',
        'app/fhk/api/kiosk/*',
        'fhk/api/kiosk/*',
        'respon.php',
        'app/fhk/respon.php',
        'fhk/respon.php',
    ];
}