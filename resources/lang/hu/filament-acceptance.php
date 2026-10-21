<?php

declare(strict_types=1);

return [

    'plugin' => [
        'label' => 'Acceptance',
        'navigation_group' => 'Victorio',
    ],

    'resource' => [
        'label' => 'Példa',
        'plural_label' => 'Példák',
    ],

    'fields' => [
        'name' => 'Megnevezés',
        'expires_at' => 'Lejárat',
    ],

    'actions' => [
        'revoke' => [
            'label' => 'Visszavonás',
            'success' => 'A hivatkozást visszavontuk.',
        ],
    ],

    'errors' => [
        'expired' => 'Ez a hivatkozás lejárt.',
        'revoked' => 'Ez a hivatkozás már nem elérhető.',
        'throttled' => 'Túl sok kérés. Kérjük, próbálja újra később.',
    ],

];
