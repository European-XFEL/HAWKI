<?php

return [
    'base_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/'),
    'token_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/') . '/oauth/token',
    'api_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/') . '/api',
    'client' => env('XFEL_ACCESS_CLIENT'),
    'secret' => env('XFEL_ACCESS_SECRET'),
    'dachs_requirement_id' => env('XFEL_ACCESS_DACHS_REQUIREMENT_ID'),
    'base_resource_name' => env('XFEL_ACCESS_BASE_RESOURCE_NAME'),
    'training_resource_name' => env('XFEL_ACCESS_TRAINING_RESOURCE_NAME'),
 ];