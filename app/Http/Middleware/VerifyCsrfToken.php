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
    ];
}