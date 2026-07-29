<?php
/**
 * Application Configuration
 */
return [
    'name'        => 'NexaOps — App & AI Command Center',
    'version'     => '1.0.0',
    'base_url'    => getenv('BASE_URL') ?: 'http://localhost',
    'api_key'     => getenv('APP_API_KEY') ?: 'nexaops_dev_key_change_me',
    'timezone'    => 'Africa/Nairobi',
    'debug'       => true,
    'log_retention_days' => 90,
    'rate_limit'  => [
        'max_requests' => 120,
        'window_seconds' => 60,
    ],
];
