<?php

return [
    'base_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/'),
    'token_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/') . '/oauth/token',
    'api_url' => trim(env('XFEL_ACCESS_BASE_URL'), '/') . '/api',
    'client' => env('XFEL_ACCESS_CLIENT'),
    'secret' => env('XFEL_ACCESS_SECRET'),
    'access_right_id' => env('XFEL_ACCESS_RIGHT_ID'),
    'access_group_name' => env('XFEL_ACCESS_GROUP_NAME'),
    'staff_group_name' => env('XFEL_STAFF_GROUP_NAME'),
 ];