<?php
require 'vendor/autoload.php';
$openapi = OpenApi\Generator::scan(['app/modules/OpenApi']);
echo $openapi->toJson();
