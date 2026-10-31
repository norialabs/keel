<?php

declare(strict_types=1);

namespace Noria\Keel\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Noria\Keel\Http\TrustedProxies;

class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $this->proxies = TrustedProxies::from(Config::get('keel.http.trusted_proxies'));
    }
}
