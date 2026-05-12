<?php

return [
    'allow_origin'      => ['*'], // 也可以限制为 ['http://localhost:1818']
    'allow_methods'     => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allow_headers'     => ['*'],
    'expose_headers'    => [],
    'max_age'           => 3600,
    'allow_credentials' => false,
];

