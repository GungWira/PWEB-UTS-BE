<?php

return [
    'secret' => env('JWT_SECRET'),

    'algo' => 'HS256',

    // Masa berlaku token dalam detik.
    'ttl' => (int) env('JWT_TTL', 7200),
];
