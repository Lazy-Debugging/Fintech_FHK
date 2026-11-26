<?php

return [
    'host'             => env('MQTT_HOST', 'broker.emqx.io'),
    'port'             => (int) env('MQTT_PORT', 1883),
    'client_id'        => env('MQTT_CLIENT_ID', 'fhk_laravel_backend'),
    'username'         => env('MQTT_AUTH_USERNAME', null),
    'password'         => env('MQTT_AUTH_PASSWORD', null),
    'clean_session'    => true,
    'default_kiosk_id' => env('MQTT_DEFAULT_KIOSK_ID', 'FHK-JAKARTA-01'),
    'qos'              => 1,
    'keep_alive'       => 60,
    'timeout'          => 10,
];

