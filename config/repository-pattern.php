<?php

return [
    'paths' => [
        'repositories' => app_path('Repositories'),
        'interfaces' => app_path('Repositories/Contracts'),
        'services' => app_path('Services'),
        'dtos' => app_path('DTOs'),
        'stubs' => resource_path('stubs/vendor/repository-pattern'),
    ],

    'namespaces' => [
        'repositories' => 'App\\Repositories',
        'interfaces' => 'App\\Repositories\\Contracts',
        'services' => 'App\\Services',
        'dtos' => 'App\\DTOs',
    ],

    'auto_bind' => true,
    'generate_service' => false,
    'generate_model_if_missing' => true,
];
