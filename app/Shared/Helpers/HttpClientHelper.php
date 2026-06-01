<?php

namespace App\Shared\Helpers;

use GuzzleHttp\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpClientHelper
{
    public static function guzzle(): Client
    {
        return new Client([
            'verify' => storage_path('certs/cacert.pem'),
        ]);
    }

    public static function http(): PendingRequest
    {
        return Http::withOptions([
            'verify' => storage_path('certs/cacert.pem'),
        ]);
    }
}