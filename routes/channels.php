<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('agent.{organizationId}', fn() => true);
