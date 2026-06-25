<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Nexo API",
    version: "1.0.0",
    description: "Fiscal centralizing API for integration between sales systems, government and accounting platforms."
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Server"
)]
#[OA\Server(
    url: "https://nexo.test",
    description: "Herd Local Server (HTTPS)"
)]
#[OA\Server(
    url: "https://backendnexo.shop",
    description: "Mounted Server"
)]
class OpenApiSpec
{
}