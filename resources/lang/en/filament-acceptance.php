<?php

declare(strict_types=1);

return [

    'plugin' => [
        'label' => 'Acceptance',
        'navigation_group' => 'Victorio',
    ],

    'resource' => [
        'label' => 'Example',
        'plural_label' => 'Examples',
    ],

    'fields' => [
        'name' => 'Name',
        'expires_at' => 'Expires at',
    ],

    'actions' => [
        'revoke' => [
            'label' => 'Revoke',
            'success' => 'The link has been revoked.',
        ],
    ],

    'errors' => [
        'expired' => 'This link has expired.',
        'revoked' => 'This link is no longer available.',
        'throttled' => 'Too many requests. Please try again later.',
    ],

];
